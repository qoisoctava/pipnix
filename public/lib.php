<?php
/**
 * pipnix web console — shared helpers.
 */
declare(strict_types=1);

// ---------------------------------------------------------------- app boot
$appCandidates = array_filter([
    getenv('PIPNIX_APP') ?: null,
    dirname(__DIR__) . '/app',                 // repo layout: pipnix/public → pipnix/app
    dirname(__DIR__, 3) . '/pipnix/app',       // cPanel: public_html/pipnix → /home/USER/pipnix/app
]);
$appDir = null;
foreach ($appCandidates as $cand) {
    if (is_file($cand . '/bootstrap.php')) {
        $appDir = $cand;
        break;
    }
}
if ($appDir === null) {
    http_response_code(500);
    exit('pipnix: cannot locate app/bootstrap.php (set the PIPNIX_APP environment variable)');
}
require_once $appDir . '/bootstrap.php';

// ---------------------------------------------------------------- session
session_name('pipnix_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

// Dev-only convenience: `PIPNIX_UI_DEV=1 php -S …` skips the login form.
// Guarded to the built-in server so it can never affect a real deployment.
if (PHP_SAPI === 'cli-server' && getenv('PIPNIX_UI_DEV') === '1'
    && empty($_SESSION['user'])
    && !str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/login')
    && !str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/logout')) {
    $_SESSION['user'] = ['id' => 0, 'username' => 'dev', 'role' => 'admin'];
}

// ---------------------------------------------------------------- helpers
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = '/'): string
{
    $base = rtrim((string) Env::cfg('ui.base_path', ''), '/');
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_csrf'] ?? '');
    if ($sent === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $sent)) {
        http_response_code(419);
        exit('CSRF token mismatch — reload the page.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('/login');
    }
}

function require_role(string $role): void
{
    $user = current_user();
    $order = ['viewer' => 0, 'operator' => 1, 'admin' => 2];
    if (!$user || ($order[$user['role']] ?? -1) < ($order[$role] ?? 99)) {
        http_response_code(403);
        render('error', ['code' => 403, 'title' => 'No access',
            'message' => 'Your role cannot open this page.'], 'bare');
        exit;
    }
}

/** Render a view inside the shell (or standalone for login/error). */
function render(string $view, array $data = [], string $layout = 'shell'): void
{
    $viewFile = __DIR__ . '/views/' . $view . '.php';
    $layoutFile = __DIR__ . '/views/' . $layout . '.php';
    if (!is_file($viewFile)) {
        http_response_code(500);
        exit('view missing: ' . e($view));
    }
    $data['user'] = current_user();
    $data['flash'] = take_flash();
    extract($data, EXTR_SKIP);
    ob_start();
    require $viewFile;
    $content = (string) ob_get_clean();
    require $layoutFile;
}

function flash(string $message, string $kind = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'kind' => $kind];
}

function take_flash(): ?array
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- helpers (web)
/** Minimum role required for a UI action. */
function can(string $action): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }
    $order = ['viewer' => 0, 'operator' => 1, 'admin' => 2];
    $need = [
        'trigger' => 'operator', 'pause' => 'operator', 'clear' => 'operator',
        'mark' => 'operator',
        'edit' => 'admin', 'import' => 'admin', 'delete' => 'admin',
        'venv' => 'admin', 'users' => 'admin', 'audit' => 'admin',
    ];
    return ($order[$user['role']] ?? -1) >= ($order[$need[$action] ?? 'admin'] ?? 2);
}

function client_ip(): ?string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return null;
}

function audit(string $action, string $object): void
{
    $user = current_user();
    Audit::record($user['username'] ?? 'system', $action, $object, client_ip());
}

function slug(string $value): string
{
    return strtolower(trim((string) preg_replace('/[^A-Za-z0-9_.-]+/', '-', $value) ?? ''));
}

/** Run `php app/ops.php ...` detached, output appended to a log file. @return int pid */
function spawn_ops(array $args, string $logFile): int
{
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PN_APP . '/ops.php');
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg((string) $arg);
    }
    @touch($logFile);
    $full = 'nohup ' . $cmd . ' >> ' . escapeshellarg($logFile) . ' 2>&1 & echo $!';
    return (int) trim((string) @shell_exec($full));
}

/** Run `php app/ops.php ...` synchronously with a deadline. @return array{0:int,1:string} */
function run_ops_sync(array $args, int $timeout = 90): array
{
    $cmd = array_merge([PHP_BINARY, PN_APP . '/ops.php'], $args);
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = @proc_open($cmd, $desc, $pipes, PN_BASE);
    if (!is_resource($proc)) {
        return [127, 'cannot start ops'];
    }
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $deadline = microtime(true) + $timeout;
    while (true) {
        $out .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
        $st = proc_get_status($proc);
        if (!$st['running']) {
            break;
        }
        if (microtime(true) > $deadline) {
            @proc_terminate($proc, 9);
            $out .= "\n[pipnix] timed out after {$timeout}s";
            break;
        }
        usleep(50000);
    }
    $out .= (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $rc = proc_close($proc);
    return [$rc, $out];
}

function venv_log_path(string $name): string
{
    return Env::logs() . '/venv-' . slug($name) . '.log';
}

// ---------------------------------------------------------------- helpers (data)
function state_class(string $state): string
{
    return match ($state) {
        'success' => 'ok',
        'failed', 'timed_out' => 'bad',
        'running' => 'run',
        'upstream_failed' => 'warn',
        'skipped' => 'skip',
        default => 'idle',
    };
}

function state_label(string $state): string
{
    return ucfirst(str_replace('_', ' ', $state));
}

function human_duration(?string $started, ?string $finished): string
{
    if (!$started) {
        return '—';
    }
    $from = strtotime($started);
    $to = $finished ? strtotime($finished) : time();
    $sec = max(0, $to - $from);
    if ($sec < 60) {
        return $sec . 's';
    }
    if ($sec < 3600) {
        return floor($sec / 60) . 'm ' . ($sec % 60) . 's';
    }
    return floor($sec / 3600) . 'h ' . floor(($sec % 3600) / 60) . 'm';
}

function human_schedule(array $dag): string
{
    if ($dag['schedule_type'] === 'interval') {
        return 'every ' . (int) $dag['schedule'] . ' min';
    }
    return (string) $dag['schedule'];
}

function next_run_text(array $dag): string
{
    if ((int) $dag['is_active'] !== 1) {
        return 'paused';
    }
    if ($dag['schedule_type'] === 'interval') {
        return $dag['next_run_at'] ? substr((string) $dag['next_run_at'], 0, 16) : '—';
    }
    $next = Cron::nextRun((string) $dag['schedule'], new DateTimeImmutable('now'));
    return $next ? $next->format('Y-m-d H:i') : '—';
}

function tail_lines(string $path, int $limit = 400): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
    return array_slice($lines, -$limit);
}
