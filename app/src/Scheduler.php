<?php
declare(strict_types=1);

/**
 * The tick: schedule due DAGs, reconcile, cascade, dispatch, retry, close.
 * Designed to be idempotent and safe to run every minute from cron.
 */
final class Scheduler
{
    private const TERMINAL = ['success', 'failed', 'upstream_failed', 'skipped', 'timed_out'];
    private const BLOCKING = ['failed', 'timed_out', 'upstream_failed'];

    public static function tick(): void
    {
        if (!Lock::acquire()) {
            self::log('tick skipped — lock busy');
            return;
        }
        try {
            $defs = DagLoader::loadAll();
            $dags = DagLoader::sync($defs);
            self::scheduleDue($dags);

            foreach (Db::all("SELECT * FROM pn_dag_run WHERE state = 'running'") as $run) {
                self::processRun($run);
            }
            Alerter::flush();
        } finally {
            Lock::release();
        }
    }

    private static function scheduleDue(array $dags): void
    {
        $now = new DateTimeImmutable('now');

        foreach ($dags as $dag) {
            if ((int) $dag['is_active'] !== 1) {
                continue;
            }

            if ($dag['schedule_type'] === 'interval') {
                $minutes = max(1, (int) $dag['schedule']);
                if (empty($dag['next_run_at'])) {
                    Db::run('UPDATE pn_dag SET next_run_at = ? WHERE id = ?',
                        [$now->modify("+{$minutes} minutes")->format('Y-m-d H:i:s'), $dag['id']]);
                    continue;
                }
                if (new DateTimeImmutable((string) $dag['next_run_at']) > $now) {
                    continue;
                }
            } else {
                if (!Cron::matches((string) $dag['schedule'], $now)) {
                    continue;
                }
                if (!empty($dag['last_run_at'])
                    && substr((string) $dag['last_run_at'], 0, 16) === $now->format('Y-m-d H:i')) {
                    continue; // already created this minute
                }
            }

            $runId = $now->format('Y-m-d\TH:i');
            self::createRun((int) $dag['id'], $runId, $now);

            Db::run('UPDATE pn_dag SET last_run_at = ? WHERE id = ?',
                [$now->format('Y-m-d H:i:s'), $dag['id']]);

            if ($dag['schedule_type'] === 'interval') {
                $next = $now->modify('+' . max(1, (int) $dag['schedule']) . ' minutes');
                Db::run('UPDATE pn_dag SET next_run_at = ? WHERE id = ?',
                    [$next->format('Y-m-d H:i:s'), $dag['id']]);
            }
        }
    }

    public static function createRun(int $dagId, string $runId, ?DateTimeImmutable $now = null): int
    {
        $now = $now ?? new DateTimeImmutable('now');
        $existing = Db::one('SELECT id FROM pn_dag_run WHERE dag_id = ? AND run_id = ?',
            [$dagId, $runId]);
        if ($existing) {
            return (int) $existing['id'];
        }
        return Db::tx(static function () use ($dagId, $runId, $now): int {
            $id = Db::insert(
                'INSERT INTO pn_dag_run (dag_id, run_id, state, started_at) VALUES (?,?,?,?)',
                [$dagId, $runId, 'running', $now->format('Y-m-d H:i:s')]
            );
            foreach (Db::all('SELECT id FROM pn_task WHERE dag_id = ?', [$dagId]) as $t) {
                Db::insert(
                    'INSERT INTO pn_task_run (dag_run_id, task_id, attempt, state,
                        created_at, updated_at) VALUES (?,?,?,?,?,?)',
                    [$id, $t['id'], 1, 'queued', Env::now(), Env::now()]
                );
            }
            return $id;
        });
    }

    // ------------------------------------------------------------- per run

    private static function processRun(array $run): void
    {
        $dag = Db::one('SELECT * FROM pn_dag WHERE id = ?', [$run['dag_id']]);
        if (!$dag) {
            return;
        }

        self::reconcile($run);
        self::cascade($run);
        self::dispatch($dag, $run);
        self::retry($run);
        self::closeIfDone($dag, $run);
    }

    /** @return array<int,array> task runs joined with task definitions */
    private static function rows(int $dagRunId): array
    {
        return Db::all(
            'SELECT tr.*, t.name AS task_name, t.depends_on, t.retries, t.retry_delay,
                    t.timeout, t.email_on_failure, t.runner, t.func, t.command, t.venv_id
               FROM pn_task_run tr
               JOIN pn_task t ON t.id = tr.task_id
              WHERE tr.dag_run_id = ?',
            [$dagRunId]
        );
    }

    private static function reconcile(array $run): void
    {
        $grace = (int) Env::cfg('tick.stale_runner_grace', 15);
        foreach (self::rows((int) $run['id']) as $tr) {
            if ($tr['state'] !== 'running') {
                continue;
            }
            $started = $tr['started_at'] ? strtotime((string) $tr['started_at']) : null;
            $timeout = (int) $tr['timeout'];

            if ($timeout > 0 && $started !== null && ($started + $timeout) <= time()) {
                self::kill((int) ($tr['pid'] ?? 0));
                self::mark($tr, 'timed_out', ['error' => 'timeout exceeded']);
                continue;
            }
            if (!empty($tr['pid']) && $started !== null
                && (time() - $started) > $grace && !self::alive((int) $tr['pid'])) {
                self::mark($tr, 'failed', ['error' => 'runner did not report']);
            }
        }
    }

    private static function cascade(array $run): void
    {
        $rows = self::rows((int) $run['id']);
        $byName = [];
        foreach ($rows as $r) {
            $byName[$r['task_name']] = $r;
        }
        foreach ($rows as $tr) {
            if ($tr['state'] !== 'queued') {
                continue;
            }
            $deps = $tr['depends_on'] === '' ? [] : explode(',', (string) $tr['depends_on']);
            $blocked = false;
            $skipped = false;
            foreach ($deps as $d) {
                $state = $byName[$d]['state'] ?? 'queued';
                if (in_array($state, self::BLOCKING, true)) {
                    $blocked = true;
                } elseif ($state === 'skipped') {
                    $skipped = true;
                }
            }
            if ($blocked) {
                self::mark($tr, 'upstream_failed');
            } elseif ($skipped) {
                self::mark($tr, 'skipped');
            }
        }
    }

    private static function dispatch(array $dag, array $run): void
    {
        $rows = self::rows((int) $run['id']);
        $byName = [];
        $running = 0;
        foreach ($rows as $r) {
            $byName[$r['task_name']] = $r;
            if ($r['state'] === 'running') {
                $running++;
            }
        }

        $capacity = max(0, (int) $dag['max_concurrency'] - $running);
        $perTick = (int) Env::cfg('tick.max_launches_per_tick', 4);
        $capacity = min($capacity, $perTick);
        if ($capacity <= 0) {
            return;
        }

        $now = time();
        foreach ($rows as $tr) {
            if ($capacity <= 0) {
                break;
            }
            if ($tr['state'] !== 'queued') {
                continue;
            }
            if (!empty($tr['next_attempt_at'])
                && strtotime((string) $tr['next_attempt_at']) > $now) {
                continue;
            }
            $deps = $tr['depends_on'] === '' ? [] : explode(',', (string) $tr['depends_on']);
            foreach ($deps as $d) {
                if (($byName[$d]['state'] ?? 'queued') !== 'success') {
                    continue 2;
                }
            }

            $pid = self::spawn((int) $tr['id']);
            if ($pid > 0) {
                Db::run('UPDATE pn_task_run SET state = ?, started_at = ?, pid = ?, updated_at = ?
                          WHERE id = ?',
                    ['running', Env::now(), $pid, Env::now(), $tr['id']]);
                $capacity--;
            } else {
                self::log('spawn failed for task_run ' . $tr['id']);
            }
        }
    }

    private static function retry(array $run): void
    {
        foreach (self::rows((int) $run['id']) as $tr) {
            if (!in_array($tr['state'], ['failed', 'timed_out'], true)) {
                continue;
            }
            if ((int) $tr['attempt'] > (int) $tr['retries']) {
                continue;
            }
            $delay = (int) $tr['retry_delay'] * max(1, (int) $tr['attempt']);
            $next = date('Y-m-d H:i:s', time() + max(0, $delay));
            Db::run('UPDATE pn_task_run
                        SET state = ?, attempt = attempt + 1, next_attempt_at = ?,
                            finished_at = NULL, pid = NULL, exit_code = NULL, updated_at = ?
                      WHERE id = ?',
                ['queued', $next, Env::now(), $tr['id']]);
            self::log("retry queued: {$tr['task_name']} attempt " . ((int) $tr['attempt'] + 1));
        }
    }

    private static function closeIfDone(array $dag, array $run): void
    {
        $rows = self::rows((int) $run['id']);
        if ($rows === []) {
            return;
        }
        $failed = false;
        foreach ($rows as $tr) {
            if (!in_array($tr['state'], self::TERMINAL, true)) {
                return; // still pending
            }
            if (in_array($tr['state'], self::BLOCKING, true)) {
                $failed = true;
            }
            if (in_array($tr['state'], ['failed', 'timed_out'], true)) {
                Alerter::maybeNotify($tr, $tr, $dag);
            }
        }
        Db::run('UPDATE pn_dag_run SET state = ?, finished_at = ? WHERE id = ?',
            [$failed ? 'failed' : 'success', Env::now(), $run['id']]);
        self::log("run closed: {$dag['name']} → " . ($failed ? 'failed' : 'success'));
    }

    // ------------------------------------------------------------- helpers

    private static function mark(array $tr, string $state, array $extra = []): void
    {
        $set = 'state = ?, finished_at = ?, updated_at = ?';
        $params = [$state, Env::now(), Env::now()];
        if (isset($extra['error'])) {
            $set .= ', error = ?';
            $params[] = (string) $extra['error'];
        }
        $params[] = $tr['id'];
        Db::run('UPDATE pn_task_run SET ' . $set . ' WHERE id = ?', $params);
    }

    private static function spawn(int $taskRunId): int
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PN_APP . '/runner.php')
            . ' --run=' . $taskRunId;
        $log = escapeshellarg(Env::logs() . '/cron.log');
        $full = 'nohup ' . $cmd . ' >> ' . $log . ' 2>&1 & echo $!';
        $out = @shell_exec($full);
        return (int) trim((string) $out);
    }

    private static function alive(int $pid): bool
    {
        if ($pid <= 0) {
            return false;
        }
        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }
        @exec('kill -0 ' . $pid . ' 2>/dev/null', $o, $rc);
        return $rc === 0;
    }

    private static function kill(int $pid): void
    {
        if ($pid <= 0) {
            return;
        }
        if (function_exists('posix_kill')) {
            @posix_kill($pid, 9);
            return;
        }
        @exec('kill -9 ' . $pid . ' 2>/dev/null');
    }

    private static function log(string $line): void
    {
        @file_put_contents(Env::logs() . '/cron.log',
            '[' . Env::now() . '] ' . $line . "\n", FILE_APPEND);
    }
}
