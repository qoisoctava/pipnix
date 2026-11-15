<?php
/**
 * pipnix bootstrap — config, timezone and class loading.
 * Every entry point (scheduler, runner, dagcheck, UI) requires this file.
 */
declare(strict_types=1);

define('PN_BASE', dirname(__DIR__));
define('PN_APP', __DIR__);
define('PN_SRC', __DIR__ . '/src');

$cfgFile = PN_APP . '/config.php';
$GLOBALS['pn_config'] = require (is_file($cfgFile) ? $cfgFile : PN_APP . '/config.example.php');

date_default_timezone_set((string) ($GLOBALS['pn_config']['timezone'] ?? 'UTC'));

foreach (['Env', 'Db', 'Lock', 'Cron', 'Logger', 'Alerter',
          'DagLoader', 'Scheduler', 'Runner'] as $class) {
    $file = PN_SRC . '/' . $class . '.php';
    if (!is_file($file)) {
        fwrite(STDERR, "pipnix: missing class file {$file}\n");
        exit(70);
    }
    require_once $file;
}

foreach (['logs', 'logs/runs', 'storage', 'dags', 'jobs'] as $dir) {
    $path = PN_BASE . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}
