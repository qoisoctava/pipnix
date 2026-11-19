<?php
/**
 * pipnix configuration — copy to config.php and fill in.
 * config.php is git-ignored and never web-accessible.
 */
declare(strict_types=1);

return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'pipnix',
        'user' => 'pipnix',
        'pass' => 'change-me',
    ],

    'timezone' => 'UTC',

    // Fallback interpreter used to *load* DAG modules when no venv is
    // registered yet (should be python3, 3.8+).
    'python_bin' => '/usr/bin/python3',

    // Fallback venv (absolute path) used for `python` tasks that do not
    // pick a venv. Optional; a venv registered with is_default=1 wins.
    'default_venv' => null,

    'lock' => [
        'name'    => 'pipnix',
        'timeout' => 50,
    ],

    'tick' => [
        // Hard safety limits
        'max_launches_per_tick' => 4,
        'stale_runner_grace'    => 15, // seconds after start before "runner did not report"
    ],

    'alerts' => [
        'enabled' => false,
        'from'    => 'pipnix@example.com',
        // Simple recipients list; per-DAG overrides live in the DAG file.
        'to'      => ['ops@example.com'],
    ],

    'ui' => [
        'title' => 'pipnix',
    ],

    // Built-in updater — pulls source archives from GitHub. Set a read-only
    // token for a private fork; branch is "main" until releases are tagged.
    'updater' => [
        'enabled'      => true,
        'repo'         => 'qoisoctava/pipnix',
        'channel'      => 'branch',   // or "release"
        'branch'       => 'main',
        'token'        => null,
        'allow_docker' => false,      // Docker should rebuild the image instead
    ],
];
