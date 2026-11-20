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

    /**
     * Absolute path to a PHP **CLI** binary, safe to spawn from the web SAPI.
     *
     * PHP_BINARY cannot be trusted under PHP-FPM/CGI: cPanel runs domains on
     * PHP-FPM, where PHP_BINARY is `…/sbin/php-fpm`, and spawning that makes
     * the child hang forever (web actions then die with "timed out after 60s").
     * Resolution order: `php_bin` in config.php → PHP_BINARY when it really is
     * a CLI binary → well-known cPanel paths (newest EA-PHP first) → "php".
     */
    public static function phpCli(): string
    {
        $configured = self::cfg('php_bin');
        if (is_string($configured) && $configured !== '' && self::usableBinary($configured)) {
            return $configured;
        }

        $binary = (string) (defined('PHP_BINARY') ? PHP_BINARY : '');
        if ($binary !== '' && self::usableBinary($binary)) {
            return $binary;
        }

        $candidates = [];
        foreach (['/opt/cpanel/ea-php*/root/usr/bin/php', '/opt/alt/php*/usr/bin/php'] as $pattern) {
            $found = glob($pattern) ?: [];
            rsort($found);
            $candidates = array_merge($candidates, $found);
        }
        $candidates[] = (defined('PHP_BINDIR') ? PHP_BINDIR : '/usr/bin') . '/php';
        $candidates[] = '/usr/local/bin/php';
        $candidates[] = '/usr/bin/php';

        foreach ($candidates as $candidate) {
            if (self::usableBinary($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }

    /** True for an executable that is php or php<N>, never php-fpm/php-cgi. */
    private static function usableBinary(string $path): bool
    {
        if ($path === '' || !is_file($path) || !is_executable($path)) {
            return false;
        }
        return !preg_match('/php-(fpm|cgi)$/i', basename($path));
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
            $base = rtrim((string) $row['path'], '/');
            foreach (['/bin/python3', '/bin/python'] as $suffix) {
                if (is_file($base . $suffix)) {
                    return ['bin' => $base . $suffix, 'venv' => $base,
                            'name' => (string) $row['name'], 'error' => null];
                }
            }
            // Registered but the interpreter is gone (path typo, moved host,
            // deleted venv). Say so instead of failing with a bare exit 127.
            return [
                'bin'   => (string) (self::cfg('python_bin') ?: 'python3'),
                'venv'  => $base,
                'name'  => (string) $row['name'],
                'error' => sprintf("venv '%s' has no python at %s/bin/python3",
                                   $row['name'], $base),
            ];
        }

        $fallbackVenv = self::cfg('default_venv');
        if (is_string($fallbackVenv) && $fallbackVenv !== '') {
            $base = rtrim($fallbackVenv, '/');
            if (is_file($base . '/bin/python') || is_file($base . '/bin/python3')) {
                return ['bin' => $base . '/bin/python', 'venv' => $base,
                        'name' => null, 'error' => null];
            }
            return [
                'bin'   => (string) (self::cfg('python_bin') ?: 'python3'),
                'venv'  => $base,
                'name'  => null,
                'error' => sprintf("default_venv '%s' has no python at %s/bin/python3",
                                   $base, $base),
            ];
        }

        return [
            'bin'   => (string) (self::cfg('python_bin') ?: 'python3'),
            'venv'  => null,
            'name'  => null,
            'error' => null,
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
