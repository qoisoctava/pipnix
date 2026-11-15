#!/usr/bin/env php
<?php
/**
 * pipnix ops — operator CLI (works before the web UI exists).
 *
 *   php app/ops.php dags
 *   php app/ops.php runs [--dag=NAME] [--n=20]
 *   php app/ops.php tasks --run=<dag_run_id>
 *   php app/ops.php trigger --dag=NAME [--run-id=manual__...]
 *   php app/ops.php pause|resume --dag=NAME
 *   php app/ops.php clear --run=<task_run_id>
 *   php app/ops.php logs --run=<task_run_id> [--tail=100]
 *   php app/ops.php tick
 *   php app/ops.php venv:check [--name=NAME]
 *   php app/ops.php venv:list [--name=NAME]
 *   php app/ops.php venv:install --name=NAME pkg[==ver] ...
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!Env::isCli()) {
    http_response_code(403);
    exit('pipnix: CLI only');
}

$argvCopy = array_slice($argv, 1);
$cmd  = $argvCopy[0] ?? 'help';
$opts = [];
$pos  = [];
foreach (array_slice($argvCopy, 1) as $arg) {
    if (preg_match('/^--([a-zA-Z0-9:_-]+)(?:=(.*))?$/', $arg, $m)) {
        $opts[$m[1]] = $m[2] ?? true;
    } else {
        $pos[] = $arg;
    }
}

try {
    exit(dispatch($cmd, $opts, $pos));
} catch (Throwable $e) {
    fwrite(STDERR, "pipnix: " . $e->getMessage() . "\n");
    exit(1);
}

// ---------------------------------------------------------------------------

function dispatch(string $cmd, array $opts, array $pos): int
{
    switch ($cmd) {
        case 'help':
            echo helpText();
            return 0;
        case 'dags':
            return cmdDags();
        case 'runs':
            return cmdRuns($opts);
        case 'tasks':
            return cmdTasks($opts);
        case 'trigger':
            return cmdTrigger($opts);
        case 'pause':
            return cmdPause($opts, false);
        case 'resume':
            return cmdPause($opts, true);
        case 'clear':
            return cmdClear($opts);
        case 'logs':
            return cmdLogs($opts);
        case 'tick':
            Scheduler::tick();
            echo "tick done\n";
            return 0;
        case 'venv:check':
            return cmdVenvCheck($opts);
        case 'venv:list':
            return cmdVenvList($opts);
        case 'venv:install':
            return cmdVenvInstall($opts, $pos);
        default:
            fwrite(STDERR, "unknown command '{$cmd}'\n\n" . helpText());
            return 64;
    }
}

function helpText(): string
{
    return <<<TXT
pipnix ops

  dags                              list DAGs
  runs [--dag=NAME] [--n=20]        recent dag runs
  tasks --run=<dag_run_id>          tasks of one run
  trigger --dag=NAME [--run-id=..]  create a manual run now
  pause  --dag=NAME                 disable scheduling
  resume --dag=NAME                 enable scheduling
  clear --run=<task_run_id>         reset a task (+ downstream) for a rerun
  logs --run=<task_run_id> [--tail=100]
  tick                              run one scheduler tick
  venv:check [--name=NAME]          refresh interpreter + package inventory
  venv:list [--name=NAME]           installed packages from the DB
  venv:install --name=NAME pkg[==ver] ...
TXT;
}

// ------------------------------------------------------------------- dags

function cmdDags(): int
{
    $rows = Db::all(
        'SELECT d.*,
                (SELECT COUNT(*) FROM pn_task t WHERE t.dag_id = d.id) AS tasks,
                (SELECT r.state FROM pn_dag_run r WHERE r.dag_id = d.id
                  ORDER BY r.id DESC LIMIT 1) AS last_state
           FROM pn_dag d ORDER BY d.name'
    );
    if ($rows === []) {
        echo "no dags loaded yet (put a module in dags/ and run a tick)\n";
        return 0;
    }
    table(['NAME', 'SCHEDULE', 'ACTIVE', 'TASKS', 'LAST', 'NEXT RUN'], array_map(
        static fn (array $r): array => [
            $r['name'],
            $r['schedule_type'] === 'interval' ? 'every ' . $r['schedule'] . 'm' : $r['schedule'],
            (int) $r['is_active'] === 1 ? 'yes' : 'paused',
            (string) $r['tasks'],
            (string) ($r['last_state'] ?? '-'),
            (string) ($r['next_run_at'] ?? '-'),
        ],
        $rows
    ));
    return 0;
}

function cmdRuns(array $opts): int
{
    $n = (int) ($opts['n'] ?? 20);
    $params = [];
    $where = '';
    if (!empty($opts['dag'])) {
        $where = 'WHERE d.name = ?';
        $params[] = (string) $opts['dag'];
    }
    $rows = Db::all(
        "SELECT r.*, d.name AS dag_name,
                (SELECT COUNT(*) FROM pn_task_run tr WHERE tr.dag_run_id = r.id) AS tasks,
                (SELECT COUNT(*) FROM pn_task_run tr WHERE tr.dag_run_id = r.id
                  AND tr.state IN ('success','failed','skipped','upstream_failed','timed_out')) AS done
           FROM pn_dag_run r JOIN pn_dag d ON d.id = r.dag_id
           {$where}
          ORDER BY r.id DESC LIMIT {$n}",
        $params
    );
    if ($rows === []) {
        echo "no runs\n";
        return 0;
    }
    table(['ID', 'DAG', 'RUN ID', 'STATE', 'TASKS', 'STARTED', 'FINISHED'], array_map(
        static fn (array $r): array => [
            (string) $r['id'], (string) $r['dag_name'], (string) $r['run_id'],
            (string) $r['state'], $r['done'] . '/' . $r['tasks'],
            (string) ($r['started_at'] ?? '-'), (string) ($r['finished_at'] ?? '-'),
        ],
        $rows
    ));
    return 0;
}

function cmdTasks(array $opts): int
{
    $runId = (int) ($opts['run'] ?? 0);
    if ($runId <= 0) {
        fwrite(STDERR, "usage: tasks --run=<dag_run_id>\n");
        return 64;
    }
    $rows = Db::all(
        'SELECT tr.*, t.name AS task_name, t.depends_on
           FROM pn_task_run tr JOIN pn_task t ON t.id = tr.task_id
          WHERE tr.dag_run_id = ? ORDER BY tr.id',
        [$runId]
    );
    if ($rows === []) {
        echo "no task runs for dag_run {$runId}\n";
        return 0;
    }
    table(['ID', 'TASK', 'STATE', 'ATT', 'EXIT', 'STARTED', 'FINISHED', 'ERROR'], array_map(
        static fn (array $r): array => [
            (string) $r['id'], (string) $r['task_name'], (string) $r['state'],
            (string) $r['attempt'], (string) ($r['exit_code'] ?? '-'),
            (string) ($r['started_at'] ?? '-'), (string) ($r['finished_at'] ?? '-'),
            trim((string) ($r['error'] ?? '')),
        ],
        $rows
    ));
    return 0;
}

// ---------------------------------------------------------------- actions

function cmdTrigger(array $opts): int
{
    $name = (string) ($opts['dag'] ?? '');
    $dag = Db::one('SELECT * FROM pn_dag WHERE name = ?', [$name]);
    if (!$dag) {
        fwrite(STDERR, "unknown dag '{$name}'\n");
        return 1;
    }
    $runId = (string) ($opts['run-id'] ?? ('manual__' . date('Y-m-d\TH:i:s')));
    $id = Scheduler::createRun((int) $dag['id'], $runId);
    Db::run('UPDATE pn_dag SET last_run_at = ? WHERE id = ?', [Env::now(), $dag['id']]);
    $n = (int) (Db::one('SELECT COUNT(*) AS n FROM pn_task_run WHERE dag_run_id = ?', [$id])['n'] ?? 0);
    echo "created dag_run #{$id} ({$runId}) with {$n} tasks\n";
    echo "run `php app/ops.php tick` (or wait for cron) to dispatch\n";
    return 0;
}

function cmdPause(array $opts, bool $resume): int
{
    $name = (string) ($opts['dag'] ?? '');
    $dag = Db::one('SELECT * FROM pn_dag WHERE name = ?', [$name]);
    if (!$dag) {
        fwrite(STDERR, "unknown dag '{$name}'\n");
        return 1;
    }
    Db::run('UPDATE pn_dag SET is_active = ?, updated_at = ? WHERE id = ?',
        [$resume ? 1 : 0, Env::now(), $dag['id']]);
    echo ($resume ? 'resumed' : 'paused') . " {$name}\n";
    return 0;
}

function cmdClear(array $opts): int
{
    $id = (int) ($opts['run'] ?? 0);
    if ($id <= 0) {
        fwrite(STDERR, "usage: clear --run=<task_run_id>\n");
        return 64;
    }
    $tr = Db::one(
        'SELECT tr.*, t.name AS task_name, t.dag_id
           FROM pn_task_run tr JOIN pn_task t ON t.id = tr.task_id
          WHERE tr.id = ?',
        [$id]
    );
    if (!$tr) {
        fwrite(STDERR, "unknown task_run {$id}\n");
        return 1;
    }
    $dagRunId = (int) $tr['dag_run_id'];
    $dagId    = (int) $tr['dag_id'];

    // dependency graph of this dag
    $tasks = Db::all('SELECT id, name, depends_on FROM pn_task WHERE dag_id = ?', [$dagId]);
    $deps = [];
    $idByName = [];
    foreach ($tasks as $t) {
        $deps[$t['name']] = $t['depends_on'] === '' ? [] : explode(',', (string) $t['depends_on']);
        $idByName[$t['name']] = (int) $t['id'];
    }

    // collect the task + all downstream tasks (reverse edges)
    $reset = [$tr['task_name'] => true];
    $queue = [$tr['task_name']];
    while ($queue) {
        $n = array_pop($queue);
        foreach ($deps as $task => $ds) {
            if (in_array($n, $ds, true) && !isset($reset[$task])) {
                $reset[$task] = true;
                $queue[] = $task;
            }
        }
    }

    $count = 0;
    foreach (array_keys($reset) as $taskName) {
        $taskId = $idByName[$taskName] ?? null;
        if ($taskId === null) {
            continue;
        }
        $count += Db::run(
            'UPDATE pn_task_run
                SET state = ?, attempt = 1, started_at = NULL, finished_at = NULL,
                    next_attempt_at = NULL, pid = NULL, exit_code = NULL,
                    error = NULL, alerted = 0, updated_at = ?
              WHERE dag_run_id = ? AND task_id = ?',
            ['queued', Env::now(), $dagRunId, $taskId]
        )->rowCount();
    }
    Db::run("UPDATE pn_dag_run SET state = 'running', finished_at = NULL WHERE id = ?", [$dagRunId]);

    echo "cleared {$count} task run(s) in dag_run #{$dagRunId} (task {$tr['task_name']} + downstream)\n";
    echo "run `php app/ops.php tick` to dispatch\n";
    return 0;
}

function cmdLogs(array $opts): int
{
    $id = (int) ($opts['run'] ?? 0);
    $tail = max(1, (int) ($opts['tail'] ?? 100));
    if ($id <= 0) {
        fwrite(STDERR, "usage: logs --run=<task_run_id> [--tail=100]\n");
        return 64;
    }
    $tr = Db::one('SELECT id, log_path FROM pn_task_run WHERE id = ?', [$id]);
    if (!$tr || empty($tr['log_path']) || !is_file((string) $tr['log_path'])) {
        fwrite(STDERR, "no log for task_run {$id}\n");
        return 1;
    }
    $lines = file((string) $tr['log_path'], FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lines, -$tail) as $line) {
        echo $line, "\n";
    }
    return 0;
}

// ----------------------------------------------------------------- venvs

function cmdVenvCheck(array $opts): int
{
    $name = $opts['name'] ?? null;
    $venvs = $name
        ? Db::all('SELECT * FROM pn_venv WHERE name = ?', [(string) $name])
        : Db::all('SELECT * FROM pn_venv ORDER BY is_default DESC, name');
    if ($venvs === []) {
        echo "no venvs registered\n";
        return 0;
    }
    foreach ($venvs as $venv) {
        $path = rtrim((string) $venv['path'], '/');
        $py = is_file($path . '/bin/python') ? $path . '/bin/python' : $path . '/bin/python3';
        if (!is_file($py)) {
            Db::run('UPDATE pn_venv SET status = ?, last_checked_at = ?, updated_at = ? WHERE id = ?',
                ['missing', Env::now(), Env::now(), $venv['id']]);
            echo "{$venv['name']}: MISSING ({$py})\n";
            continue;
        }
        [$rcV, $outV, $errV] = runCapture([$py, '--version']);
        $pythonVersion = null;
        if (preg_match('/Python\s+([0-9][0-9A-Za-z.\-]*)/', $outV . ' ' . $errV, $m)) {
            $pythonVersion = $m[1];
        }

        [$rcP, $outP] = runCapture([$py, '-m', 'pip', '--version']);
        $pipVersion = null;
        if ($rcP === 0 && preg_match('/pip\s+([0-9][0-9A-Za-z.\-]*)/', $outP, $m)) {
            $pipVersion = $m[1];
        }

        [$rcL, $listJson, $errL] = runCapture([$py, '-m', 'pip', 'list', '--format=json']);
        $pkgs = json_decode($listJson, true);
        if (!is_array($pkgs)) {
            Db::run('UPDATE pn_venv SET status = ?, last_checked_at = ?, updated_at = ? WHERE id = ?',
                ['error', Env::now(), Env::now(), $venv['id']]);
            echo "{$venv['name']}: pip list failed — {$errL}\n";
            continue;
        }

        [$rcO, $outdatedJson] = runCapture([$py, '-m', 'pip', 'list', '--outdated',
            '--format=json', '--disable-pip-version-check', '--retries', '0', '--timeout', '5']);
        $latest = [];
        foreach (json_decode($outdatedJson, true) ?: [] as $o) {
            $latest[strtolower((string) $o['name'])] = (string) $o['latest_version'];
        }

        Db::tx(static function () use ($venv, $pkgs, $latest): void {
            Db::run('DELETE FROM pn_venv_package WHERE venv_id = ?', [$venv['id']]);
            foreach ($pkgs as $pkg) {
                $pname = strtolower((string) $pkg['name']);
                Db::run(
                    'INSERT INTO pn_venv_package (venv_id, name, version, latest_version, updated_at)
                     VALUES (?,?,?,?,?)',
                    [$venv['id'], $pname, (string) $pkg['version'], $latest[$pname] ?? null, Env::now()]
                );
            }
        });

        $status = $latest === [] ? 'healthy' : 'outdated';
        Db::run(
            'UPDATE pn_venv SET python_version = ?, pip_version = ?, status = ?,
                last_checked_at = ?, updated_at = ? WHERE id = ?',
            [$pythonVersion, $pipVersion, $status, Env::now(), Env::now(), $venv['id']]
        );
        echo "{$venv['name']}: {$pythonVersion}; " . count($pkgs) . ' packages, '
            . count($latest) . " outdated\n";
    }
    return 0;
}

function cmdVenvList(array $opts): int
{
    $name = (string) ($opts['name'] ?? '');
    $venv = Db::one('SELECT * FROM pn_venv WHERE name = ?', [$name]);
    if (!$venv) {
        fwrite(STDERR, "unknown venv '{$name}'\n");
        return 1;
    }
    $rows = Db::all('SELECT * FROM pn_venv_package WHERE venv_id = ? ORDER BY name', [$venv['id']]);
    if ($rows === []) {
        echo "no package inventory yet — run: php app/ops.php venv:check --name={$name}\n";
        return 0;
    }
    table(['PACKAGE', 'VERSION', 'LATEST'], array_map(
        static fn (array $r): array => [
            (string) $r['name'], (string) $r['version'], (string) ($r['latest_version'] ?? ''),
        ],
        $rows
    ));
    return 0;
}

function cmdVenvInstall(array $opts, array $pos): int
{
    $name = (string) ($opts['name'] ?? '');
    $venv = Db::one('SELECT * FROM pn_venv WHERE name = ?', [$name]);
    if (!$venv) {
        fwrite(STDERR, "unknown venv '{$name}'\n");
        return 1;
    }
    if ($pos === []) {
        fwrite(STDERR, "usage: venv:install --name=NAME pkg[==ver] ...\n");
        return 64;
    }
    $path = rtrim((string) $venv['path'], '/');
    $py = is_file($path . '/bin/python') ? $path . '/bin/python' : $path . '/bin/python3';
    $cmd = array_merge([$py, '-m', 'pip', 'install'], $pos);
    echo '$ ' . implode(' ', array_map('escapeshellarg', $cmd)) . "\n";
    $rc = procRunPassthrough($cmd);
    echo "pip exited with {$rc}\n";
    if ($rc === 0) {
        $opts2 = ['name' => $name];
        cmdVenvCheck($opts2);
    }
    return $rc === 0 ? 0 : 1;
}

// ---------------------------------------------------------------- helpers

function table(array $headers, array $rows): void
{
    $widths = array_map('strlen', $headers);
    foreach ($rows as $row) {
        foreach ($row as $i => $cell) {
            $widths[$i] = max($widths[$i] ?? 0, strlen((string) $cell));
        }
    }
    $fmt = static function (array $cells) use ($widths): string {
        $parts = [];
        foreach ($cells as $i => $cell) {
            $parts[] = str_pad((string) $cell, $widths[$i]);
        }
        return rtrim(implode('  ', $parts));
    };
    echo $fmt($headers), "\n";
    echo str_repeat('-', min(120, array_sum($widths) + 2 * (count($widths) - 1))), "\n";
    foreach ($rows as $row) {
        echo $fmt($row), "\n";
    }
}

/** @param string[] $cmd @return array{0:int,1:string,2:string} */
function runCapture(array $cmd): array
{
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $desc, $pipes, PN_BASE);
    if (!is_resource($proc)) {
        return [127, '', 'cannot start command'];
    }
    $out = (string) stream_get_contents($pipes[1]);
    $err = (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    return [$rc, $out, $err];
}

/** @param string[] $cmd */
function procRunPassthrough(array $cmd): int
{
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $desc, $pipes, PN_BASE);
    if (!is_resource($proc)) {
        return 127;
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    while (true) {
        $o = (string) stream_get_contents($pipes[1]);
        $e = (string) stream_get_contents($pipes[2]);
        if ($o !== '') {
            fwrite(STDOUT, $o);
        }
        if ($e !== '') {
            fwrite(STDERR, $e);
        }
        $st = proc_get_status($proc);
        if (!$st['running']) {
            break;
        }
        usleep(50000);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    return proc_close($proc);
}
