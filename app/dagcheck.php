#!/usr/bin/env php
<?php
/**
 * Validate one DAG file without touching the DB schedule:
 *   php app/dagcheck.php --file=dags/daily_report.py
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$file = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $file = substr($arg, 7);
    }
}
if ($file === null) {
    fwrite(STDERR, "usage: dagcheck.php --file=<path>\n");
    exit(64);
}
if (!str_starts_with($file, '/')) {
    $file = PN_BASE . '/' . ltrim($file, '/');
}

$res = DagLoader::loadFile($file);
if (!empty($res['ok'])) {
    echo json_encode($res['dag'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    if (!empty($res['warnings'])) {
        fwrite(STDERR, "warnings:\n - " . implode("\n - ", $res['warnings']) . "\n");
    }
    exit(0);
}
fwrite(STDERR, 'invalid: ' . ($res['error'] ?? 'unknown error') . "\n");
if (!empty($res['traceback'])) {
    fwrite(STDERR, $res['traceback'] . "\n");
}
exit(1);
