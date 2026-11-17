#!/usr/bin/env php
<?php
/**
 * pipnix installer — creates the schema and the first admin user.
 *
 *   php app/install.php --admin=you:secret [--venv=reporting:/home/USER/venvs/reporting]
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (!Env::isCli()) {
    http_response_code(403);
    exit('pipnix: CLI only');
}

$admin = null;
$venv  = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--admin=')) {
        $admin = substr($arg, 8);
    }
    if (str_starts_with($arg, '--venv=')) {
        $venv = substr($arg, 7);
    }
}

$schema = (string) file_get_contents(PN_BASE . '/schema.sql');
$schema = (string) preg_replace('/^\s*--.*$/m', '', $schema);   // strip line comments
$statements = array_filter(array_map('trim', explode(';', $schema)));
foreach ($statements as $sql) {
    if ($sql === '') {
        continue;
    }
    Db::pdo()->exec($sql);
}
echo "schema: ok\n";

if ($venv !== null) {
    [$name, $path] = array_pad(explode(':', $venv, 2), 2, '');
    if ($name !== '' && $path !== '') {
        $path = rtrim($path, '/');
        if (!is_file($path . '/bin/python3') && !is_file($path . '/bin/python')) {
            fwrite(STDERR, "venv: no interpreter at {$path}/bin/python3\n"
                . "  Create it first, e.g.:\n"
                . "    python3.12 -m venv {$path}\n"
                . "  (/home/USER in the docs is a placeholder — your home is "
                . (getenv('HOME') ?: 'unknown') . ")\n");
            exit(1);
        }
        Db::run(
            'INSERT INTO pn_venv (name, path, is_default, status, created_at, updated_at)
             VALUES (?,?,1,"healthy",?,?)
             ON DUPLICATE KEY UPDATE path=VALUES(path), updated_at=VALUES(updated_at)',
            [$name, $path, Env::now(), Env::now()]
        );
        echo "venv: {$name} → {$path} (default)\n";
    }
}

if ($admin !== null) {
    [$user, $pass] = array_pad(explode(':', $admin, 2), 2, '');
    if ($user !== '' && $pass !== '') {
        Db::run(
            'INSERT INTO pn_user (username, password_hash, role, is_active, created_at)
             VALUES (?, ?, "admin", 1, ?)
             ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash)',
            [$user, password_hash($pass, PASSWORD_DEFAULT), Env::now()]
        );
        echo "admin: {$user}\n";
    }
}

echo "done — next: put a DAG in dags/ and run `php app/dagcheck.php --file=dags/daily_report.py`\n";
