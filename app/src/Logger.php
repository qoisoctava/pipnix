<?php
declare(strict_types=1);

/**
 * Per-task-attempt log files: logs/runs/<dag>__<run>__<task>__a<n>.log
 */
final class Logger
{
    public static function pathFor(string $dag, string $runId, string $task, int $attempt): string
    {
        $slug = static fn (string $s): string =>
            trim(preg_replace('/[^A-Za-z0-9_.\-]+/', '-', $s) ?? 'x', '-');
        $dir = Env::logs() . '/runs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir . '/' . $slug($dag) . '__' . $slug($runId) . '__'
            . $slug($task) . '__a' . $attempt . '.log';
    }

    public static function line(string $path, string $line): void
    {
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($path, "[{$ts}] {$line}\n", FILE_APPEND);
    }

    public static function tail(string $path, int $bytes = 4096): string
    {
        if (!is_file($path)) {
            return '';
        }
        $size = (int) @filesize($path);
        $fh = @fopen($path, 'rb');
        if (!$fh) {
            return '';
        }
        if ($size > $bytes) {
            fseek($fh, $size - $bytes);
        }
        $data = stream_get_contents($fh) ?: '';
        fclose($fh);
        return $data;
    }
}
