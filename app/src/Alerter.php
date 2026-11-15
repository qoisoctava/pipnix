<?php
declare(strict_types=1);

/**
 * Minimal alerter: one email per failed task attempt (when enabled).
 * Kept deliberately small — pipnix v1 sends mail via PHP mail().
 */
final class Alerter
{
    public static function maybeNotify(array $taskRun, array $task, array $dag): void
    {
        if ((int) ($taskRun['alerted'] ?? 0) === 1) {
            return;
        }
        $enabled = (bool) Env::cfg('alerts.enabled', false);
        if (!$enabled || (int) ($task['email_on_failure'] ?? 0) !== 1) {
            return;
        }

        $to = (string) ($dag['email'] ?? '');
        if ($to === '') {
            $to = implode(',', (array) Env::cfg('alerts.to', []));
        }
        if ($to === '') {
            return;
        }

        $from    = (string) Env::cfg('alerts.from', 'pipnix@localhost');
        $subject = sprintf('[pipnix] %s / %s failed', $dag['name'], $task['name']);
        $body    = "Dag:    {$dag['name']}\n"
                 . "Task:   {$task['name']}\n"
                 . "Run:    {$taskRun['run_id']}\n"
                 . "Attempt: {$taskRun['attempt']}\n"
                 . "State:  {$taskRun['state']}\n"
                 . "Error:  " . substr((string) ($taskRun['error'] ?? ''), 0, 1000) . "\n";

        @mail($to, $subject, $body, 'From: ' . $from);

        Db::run('UPDATE pn_task_run SET alerted = 1 WHERE id = ?', [$taskRun['id']]);
    }

    /** Hook for future batching (digest emails). */
    public static function flush(): void
    {
        // no-op in v1
    }
}
