<?php
declare(strict_types=1);

/**
 * Config access + filesystem helpers.
 */
final class Env
{
    /** @return mixed */
    public static function cfg(?string $key = null, $default = null)
    {
        $cfg = $GLOBALS['pn_config'] ?? [];
        if ($key === null) {
            return $cfg;
        }
        $cur = $cfg;
        foreach (explode('.', $key) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return $default;
            }
            $cur = $cur[$part];
        }
        return $cur;
    }

    public static function path(string $sub = ''): string
    {
        return PN_BASE . ($sub === '' ? '' : '/' . ltrim($sub, '/'));
    }

    public static function logs(): string
    {
        return self::path('logs');
    }

    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli';
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Resolve the interpreter + venv path for a task/dag.
     *
     * @return array{bin:string, venv:?string, name:?string}
     */
    public static function python(?int $venvId = null, ?string $venvName = null): array
    {
        $row = null;
        if ($venvId !== null) {
            $row = Db::one('SELECT * FROM pn_venv WHERE id = ?', [$venvId]);
        }
        if (!$row && $venvName !== null && $venvName !== '') {
            $row = Db::one('SELECT * FROM pn_venv WHERE name = ?', [$venvName]);
        }
        if (!$row) {
            $row = Db::one('SELECT * FROM pn_venv WHERE is_default = 1 ORDER BY id LIMIT 1');
        }
        if ($row) {
            $bin = rtrim((string) $row['path'], '/') . '/bin/python';
            if (!is_file($bin)) {
                $bin = rtrim((string) $row['path'], '/') . '/bin/python3';
            }
            return ['bin' => $bin, 'venv' => (string) $row['path'], 'name' => (string) $row['name']];
        }

        $fallbackVenv = self::cfg('default_venv');
        if (is_string($fallbackVenv) && $fallbackVenv !== '') {
            $bin = rtrim($fallbackVenv, '/') . '/bin/python';
            return ['bin' => $bin, 'venv' => $fallbackVenv, 'name' => null];
        }

        return [
            'bin'  => (string) (self::cfg('python_bin') ?: 'python3'),
            'venv' => null,
            'name' => null,
        ];
    }

    /**
     * Environment injected into python subprocesses (zero-install shim).
     *
     * @return array<string,string>
     */
    public static function pyEnv(?array $py = null): array
    {
        // PN_BASE itself must be on the path for package-style funcs like
        // "jobs.reporting.extract" (jobs/ is a package → the parent dir is
        // what Python needs); lib/, dags/, jobs/ cover top-level modules.
        $paths = [
            PN_BASE,
            PN_BASE . '/lib',
            PN_BASE . '/dags',
            PN_BASE . '/jobs',
        ];
        if (!empty($py['venv'])) {
            $paths[] = rtrim((string) $py['venv'], '/') . '/lib';
        }
        $env = [
            'PATH'              => (isset($py['venv']) && $py['venv']
                                        ? rtrim((string) $py['venv'], '/') . '/bin:'
                                        : '') . (getenv('PATH') ?: '/usr/bin:/bin'),
            'PYTHONPATH'        => implode(':', $paths),
            'PYTHONNOUSERSITE'  => '1',
            'PYTHONDONTWRITEBYTECODE' => '1',
            'HOME'              => getenv('HOME') ?: PN_BASE,
            'LANG'              => getenv('LANG') ?: 'C.UTF-8',
        ];
        if (!empty($py['venv'])) {
            $env['VIRTUAL_ENV'] = (string) $py['venv'];
        }
        return $env;
    }
}
