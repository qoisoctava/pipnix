<?php
declare(strict_types=1);

/**
 * Task-level write operations shared by the CLI (`ops.php`) and the web UI.
 */
final class TaskOps
{
    /**
     * Clear one task attempt and every downstream task of the same run so the
     * scheduler re-dispatches them on the next tick.
     *
     * @return array{count:int,dag_run_id:int,task:string,dag:string}
     */
    public static function clear(int $taskRunId): array
    {
        $tr = Db::one(
            'SELECT tr.*, t.name AS task_name, t.dag_id
               FROM pn_task_run tr JOIN pn_task t ON t.id = tr.task_id
              WHERE tr.id = ?',
            [$taskRunId]
        );
        if (!$tr) {
            throw new InvalidArgumentException("unknown task_run {$taskRunId}");
        }
        $dagRunId = (int) $tr['dag_run_id'];
        $dagId    = (int) $tr['dag_id'];

        $tasks = Db::all('SELECT id, name, depends_on FROM pn_task WHERE dag_id = ?', [$dagId]);
        $deps    = [];
        $idByName = [];
        foreach ($tasks as $t) {
            $deps[$t['name']]    = $t['depends_on'] === '' ? [] : explode(',', (string) $t['depends_on']);
            $idByName[$t['name']] = (int) $t['id'];
        }

        $reset = [$tr['task_name'] => true];
        $queue = [$tr['task_name']];
        while ($queue) {
            $current = array_pop($queue);
            foreach ($deps as $task => $ds) {
                if (in_array($current, $ds, true) && !isset($reset[$task])) {
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

        $dagName = Db::one('SELECT d.name FROM pn_dag d JOIN pn_dag_run r ON r.dag_id = d.id WHERE r.id = ?',
            [$dagRunId]);

        return [
            'count'      => $count,
            'dag_run_id' => $dagRunId,
            'task'       => (string) $tr['task_name'],
            'dag'        => (string) ($dagName['name'] ?? ''),
        ];
    }

    /**
     * Mark one attempt as success and requeue downstream tasks that were
     * skipped because of it (upstream_failed / skipped).
     *
     * @return array{count:int,dag_run_id:int,task:string}
     */
    public static function markSuccess(int $taskRunId): array
    {
        $tr = Db::one(
            'SELECT tr.*, t.name AS task_name, t.dag_id
               FROM pn_task_run tr JOIN pn_task t ON t.id = tr.task_id
              WHERE tr.id = ?',
            [$taskRunId]
        );
        if (!$tr) {
            throw new InvalidArgumentException("unknown task_run {$taskRunId}");
        }
        $dagRunId = (int) $tr['dag_run_id'];
        $dagId    = (int) $tr['dag_id'];

        Db::run(
            "UPDATE pn_task_run SET state = 'success', finished_at = ?, updated_at = ?
              WHERE id = ?",
            [Env::now(), Env::now(), $taskRunId]
        );

        // requeue downstream tasks that only failed/skipped because of this one
        $tasks = Db::all('SELECT id, name, depends_on FROM pn_task WHERE dag_id = ?', [$dagId]);
        $downstream = [];
        foreach ($tasks as $t) {
            $ds = $t['depends_on'] === '' ? [] : explode(',', (string) $t['depends_on']);
            if (in_array($tr['task_name'], $ds, true)) {
                $downstream[(int) $t['id']] = true;
            }
        }
        $count = 0;
        foreach (array_keys($downstream) as $taskId) {
            $count += Db::run(
                "UPDATE pn_task_run
                    SET state = 'queued', attempt = 1, started_at = NULL, finished_at = NULL,
                        next_attempt_at = NULL, pid = NULL, exit_code = NULL, error = NULL,
                        alerted = 0, updated_at = ?
                  WHERE dag_run_id = ? AND task_id = ? AND state IN ('upstream_failed','skipped')",
                [Env::now(), $dagRunId, $taskId]
            )->rowCount();
        }
        Db::run("UPDATE pn_dag_run SET state = 'running', finished_at = NULL WHERE id = ?", [$dagRunId]);

        return ['count' => $count + 1, 'dag_run_id' => $dagRunId, 'task' => (string) $tr['task_name']];
    }
}
