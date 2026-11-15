<?php
/**
 * pipnix web console — front controller.
 *
 * Dev:   php -S 127.0.0.1:8099 public/index.php
 * Prod:  copy public/* to public_html/pipnix/ (or point the docroot at public/)
 */
declare(strict_types=1);

// Serve static files when using PHP's built-in server.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($rawPath === '/lib.php' || str_starts_with($rawPath, '/views')) {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/lib.php';

// ------------------------------------------------------------------ routing
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$base = rtrim((string) Env::cfg('ui.base_path', ''), '/');
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');

$segments = array_values(array_filter(explode('/', $path), 'strlen'));
$page     = $segments[0] ?? '';

// ------------------------------------------------------------------ auth
if ($page === 'login') {
    if ($method === 'POST') {
        csrf_check();
        handle_login();
        return;
    }
    if (current_user()) {
        redirect('/');
    }
    render('login', ['title' => 'Sign in'], 'bare');
    return;
}

if ($method === 'POST' && $page === 'logout') {
    require_login();
    csrf_check();
    $_SESSION = [];
    session_destroy();
    redirect('/login');
    return;
}

require_login();

// ------------------------------------------------------------------ pages
switch ($page) {
    case '':
        dashboard_page();
        return;
    case 'dags':
        if (!isset($segments[1])) {
            dags_page();
            return;
        }
        dag_page((string) $segments[1]);
        return;
    case 'runs':
        if (!isset($segments[1])) {
            runs_page();
            return;
        }
        run_page((int) $segments[1]);
        return;
    case 'logs':
        logs_page((int) ($segments[1] ?? ($_GET['task'] ?? 0)));
        return;
    case 'python':
        python_page($segments[1] ?? null);
        return;
    case 'admin':
        require_role('admin');
        admin_page();
        return;
    default:
        http_response_code(404);
        render('error', ['code' => 404, 'title' => 'Page not found',
            'message' => 'That page does not exist in the console.'], 'bare');
}

// ================================================================== auth
function handle_login(): void
{
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // simple throttling per session
    $fails = (int) ($_SESSION['login_fails'] ?? 0);
    if ($fails >= 5 && (time() - (int) ($_SESSION['login_blocked_at'] ?? 0)) < 30) {
        flash('Too many attempts — try again in 30 seconds.', 'bad');
        render('login', ['title' => 'Sign in'], 'bare');
        return;
    }

    $user = Db::one('SELECT * FROM pn_user WHERE username = ? AND is_active = 1', [$username]);
    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        $_SESSION['login_fails'] = $fails + 1;
        $_SESSION['login_blocked_at'] = time();
        usleep(400000);
        flash('Invalid username or password.', 'bad');
        render('login', ['title' => 'Sign in', 'username' => $username], 'bare');
        return;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int) $user['id'],
        'username' => (string) $user['username'],
        'role'     => (string) $user['role'],
    ];
    unset($_SESSION['login_fails']);
    Db::run('UPDATE pn_user SET last_login_at = ? WHERE id = ?', [Env::now(), $user['id']]);
    redirect('/');
}

// ================================================================== dashboard
function dashboard_page(): void
{
    $stats = [
        'failed_dags' => (int) (Db::one(
            'SELECT COUNT(*) AS n FROM pn_dag d WHERE (
                SELECT r.state FROM pn_dag_run r WHERE r.dag_id = d.id
                 ORDER BY r.id DESC LIMIT 1) = "failed"')['n'] ?? 0),
        'running_dags' => (int) (Db::one(
            "SELECT COUNT(DISTINCT dag_id) AS n FROM pn_dag_run WHERE state = 'running'")['n'] ?? 0),
        'active_dags' => (int) (Db::one(
            'SELECT COUNT(*) AS n FROM pn_dag WHERE is_active = 1')['n'] ?? 0),
    ];

    $dagRunMix = [];
    foreach (Db::all("SELECT state, COUNT(*) n FROM pn_dag_run
                       WHERE started_at >= (NOW() - INTERVAL 1 DAY)
                       GROUP BY state") as $row) {
        $dagRunMix[$row['state']] = (int) $row['n'];
    }
    $taskMix = [];
    foreach (Db::all("SELECT tr.state, COUNT(*) n
                        FROM pn_task_run tr
                        JOIN pn_dag_run r ON r.id = tr.dag_run_id
                       WHERE r.started_at >= (NOW() - INTERVAL 1 DAY)
                       GROUP BY tr.state") as $row) {
        $taskMix[$row['state']] = (int) $row['n'];
    }

    $slots = [
        'used' => (int) (Db::one(
            "SELECT COUNT(*) AS n FROM pn_task_run WHERE state = 'running'")['n'] ?? 0),
        'total' => (int) (Db::one(
            'SELECT COALESCE(SUM(max_concurrency), 0) AS n FROM pn_dag WHERE is_active = 1')['n'] ?? 0),
    ];

    $heartbeat = Env::path('storage') . '/heartbeat';
    $lastTick = is_file($heartbeat)
        ? (int) filemtime($heartbeat)
        : (is_file(Env::logs() . '/cron.log') ? (int) filemtime(Env::logs() . '/cron.log') : null);
    $health = [
        'database' => true,
        'scheduler' => $lastTick !== null && (time() - $lastTick) < 180,
        'runner' => function_exists('proc_open'),
    ];

    $failures = Db::all(
        "SELECT tr.id, tr.state, tr.attempt, tr.finished_at,
                t.name AS task_name, d.name AS dag_name, r.run_id
           FROM pn_task_run tr
           JOIN pn_task t     ON t.id = tr.task_id
           JOIN pn_dag_run r  ON r.id = tr.dag_run_id
           JOIN pn_dag d      ON d.id = r.dag_id
          WHERE tr.state IN ('failed','timed_out')
          ORDER BY tr.finished_at DESC, tr.id DESC LIMIT 6"
    );

    $favorites = Db::all(
        'SELECT d.name FROM pn_dag d ORDER BY d.updated_at DESC LIMIT 10'
    );

    render('dashboard', [
        'title' => 'Home',
        'nav' => 'home',
        'stats' => $stats,
        'dagRunMix' => $dagRunMix,
        'taskMix' => $taskMix,
        'slots' => $slots,
        'health' => $health,
        'lastTick' => $lastTick,
        'failures' => $failures,
        'favorites' => $favorites,
    ]);
}

// ================================================================== dags
function dags_page(): void
{
    $search = trim((string) ($_GET['q'] ?? ''));
    $sql = 'SELECT d.*,
                   (SELECT COUNT(*) FROM pn_task t WHERE t.dag_id = d.id) AS tasks,
                   (SELECT r.state FROM pn_dag_run r WHERE r.dag_id = d.id
                     ORDER BY r.id DESC LIMIT 1) AS last_state,
                   (SELECT r.started_at FROM pn_dag_run r WHERE r.dag_id = d.id
                     ORDER BY r.id DESC LIMIT 1) AS last_started
              FROM pn_dag d';
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE d.name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY d.name';

    render('dags', [
        'title' => 'Dags',
        'nav' => 'dags',
        'dags' => Db::all($sql, $params),
        'search' => $search,
    ]);
}

function dag_page(string $name): void
{
    $dag = Db::one('SELECT * FROM pn_dag WHERE name = ?', [$name]);
    if (!$dag) {
        http_response_code(404);
        render('error', ['code' => 404, 'title' => 'Dag not found',
            'message' => 'No Dag named "' . $name . '".'], 'bare');
        return;
    }
    $tasks = Db::all('SELECT * FROM pn_task WHERE dag_id = ? ORDER BY id', [$dag['id']]);
    $runs = Db::all('SELECT * FROM pn_dag_run WHERE dag_id = ? ORDER BY id DESC LIMIT 12',
        [$dag['id']]);

    // grid: task × run
    $grid = [];
    if ($runs) {
        $ids = array_column($runs, 'id');
        $in = implode(',', array_fill(0, count($ids), '?'));
        foreach (Db::all("SELECT task_id, dag_run_id, state FROM pn_task_run
                           WHERE dag_run_id IN ($in)", $ids) as $cell) {
            $grid[(int) $cell['task_id']][(int) $cell['dag_run_id']] = $cell['state'];
        }
    }
    $venv = $dag['default_venv_id']
        ? Db::one('SELECT name FROM pn_venv WHERE id = ?', [$dag['default_venv_id']]) : null;

    render('dag', [
        'title' => $dag['name'],
        'nav' => 'dags',
        'dag' => $dag,
        'tasks' => $tasks,
        'runs' => $runs,
        'grid' => $grid,
        'venvName' => $venv['name'] ?? null,
    ]);
}

// ================================================================== runs
function runs_page(): void
{
    $dagFilter = trim((string) ($_GET['dag'] ?? ''));
    $stateFilter = trim((string) ($_GET['state'] ?? ''));

    $sql = 'SELECT r.*, d.name AS dag_name,
                   (SELECT COUNT(*) FROM pn_task_run tr WHERE tr.dag_run_id = r.id) AS tasks,
                   (SELECT COUNT(*) FROM pn_task_run tr WHERE tr.dag_run_id = r.id
                     AND tr.state IN ("success","failed","skipped","upstream_failed","timed_out")) AS done
              FROM pn_dag_run r JOIN pn_dag d ON d.id = r.dag_id WHERE 1=1';
    $params = [];
    if ($dagFilter !== '') {
        $sql .= ' AND d.name = ?';
        $params[] = $dagFilter;
    }
    if ($stateFilter !== '') {
        $sql .= ' AND r.state = ?';
        $params[] = $stateFilter;
    }
    $sql .= ' ORDER BY r.id DESC LIMIT 50';

    render('runs', [
        'title' => 'Runs',
        'nav' => 'runs',
        'runs' => Db::all($sql, $params),
        'dags' => Db::all('SELECT name FROM pn_dag ORDER BY name'),
        'dagFilter' => $dagFilter,
        'stateFilter' => $stateFilter,
    ]);
}

function run_page(int $id): void
{
    $run = Db::one('SELECT r.*, d.name AS dag_name FROM pn_dag_run r
                      JOIN pn_dag d ON d.id = r.dag_id WHERE r.id = ?', [$id]);
    if (!$run) {
        http_response_code(404);
        render('error', ['code' => 404, 'title' => 'Run not found',
            'message' => 'No run with id ' . $id . '.'], 'bare');
        return;
    }
    $tasks = Db::all(
        'SELECT tr.*, t.name AS task_name, t.depends_on
           FROM pn_task_run tr JOIN pn_task t ON t.id = tr.task_id
          WHERE tr.dag_run_id = ? ORDER BY tr.id',
        [$id]
    );
    render('run', [
        'title' => 'Run #' . $run['id'],
        'nav' => 'runs',
        'run' => $run,
        'tasks' => $tasks,
    ]);
}

// ================================================================== logs
function logs_page(int $taskRunId): void
{
    $tr = Db::one(
        'SELECT tr.*, t.name AS task_name, d.name AS dag_name, r.run_id
           FROM pn_task_run tr
           JOIN pn_task t    ON t.id = tr.task_id
           JOIN pn_dag_run r ON r.id = tr.dag_run_id
           JOIN pn_dag d     ON d.id = r.dag_id
          WHERE tr.id = ?', [$taskRunId]);
    if (!$tr) {
        http_response_code(404);
        render('error', ['code' => 404, 'title' => 'Log not found',
            'message' => 'No task instance with id ' . $taskRunId . '.'], 'bare');
        return;
    }
    $lines = $tr['log_path'] ? tail_lines((string) $tr['log_path'], 500) : [];
    render('logs', [
        'title' => 'Logs',
        'nav' => 'logs',
        'tr' => $tr,
        'lines' => $lines,
    ]);
}

// ================================================================== python
function python_page(?string $name): void
{
    $venvs = Db::all('SELECT v.*,
                        (SELECT COUNT(*) FROM pn_venv_package p WHERE p.venv_id = v.id) AS packages,
                        (SELECT COUNT(*) FROM pn_venv_package p WHERE p.venv_id = v.id
                          AND p.latest_version IS NOT NULL) AS outdated
                        FROM pn_venv v ORDER BY v.is_default DESC, v.name');
    $selected = null;
    $packages = [];
    if ($name !== null) {
        $selected = Db::one('SELECT * FROM pn_venv WHERE name = ?', [$name]);
        if (!$selected) {
            http_response_code(404);
            render('error', ['code' => 404, 'title' => 'Environment not found',
                'message' => 'No environment named "' . $name . '".'], 'bare');
            return;
        }
        $packages = Db::all('SELECT * FROM pn_venv_package WHERE venv_id = ? ORDER BY name',
            [$selected['id']]);
    }
    render('python', [
        'title' => 'Python',
        'nav' => 'python',
        'venvs' => $venvs,
        'selected' => $selected,
        'packages' => $packages,
    ]);
}

// ================================================================== admin
function admin_page(): void
{
    $users = Db::all('SELECT * FROM pn_user ORDER BY id');
    $venvCount = (int) (Db::one('SELECT COUNT(*) AS n FROM pn_venv')['n'] ?? 0);
    render('admin', [
        'title' => 'Admin',
        'nav' => 'admin',
        'users' => $users,
        'venvCount' => $venvCount,
    ]);
}
