# Deploying pipnix to cPanel

pipnix targets exactly one cheap shared-hosting box: PHP 8.1+, one MySQL
database, one Python venv, and one cron line. Budget ~20 minutes.

```
/home/USER/
├── pipnix/                  ← the app (NEVER web-accessible)
│   ├── app/  lib/  dags/  jobs/  logs/  storage/
├── public_html/
│   └── pipnix/              ← only the contents of pipnix/public/ go here
└── virtualenv/pipnix/3.11/  ← venv created by cPanel “Setup Python App”
```

`public/index.php` auto-detects the app directory: from
`public_html/pipnix/` it walks up to `~/pipnix/app`. If you move things
around, set the `PIPNIX_APP` environment variable instead.

---

## Step 1 — Upload the release

1. Build the release zip locally: `make dist` → `dist/pipnix-v<version>.zip`
   (only tracked files, no `app/config.php`).
2. cPanel → **File Manager** → upload the zip to your home directory (`/home/USER/`).
3. Extract it. You get `/home/USER/pipnix/`.
4. Select everything **inside** `/home/USER/pipnix/public/` and move/copy it
   into `/home/USER/public_html/pipnix/` (create that folder). Nothing else
   from the repo ever goes into `public_html`.

## Step 2 — PHP version

cPanel → **MultiPHP Manager**: set the domain to **PHP 8.1+** (8.2/8.3 fine).
Note the CLI binary path shown by cPanel (usually `/usr/local/bin/php`,
sometimes `/opt/cpanel/ea-php82/root/usr/bin/php`) — you need it for the cron
line in Step 6.

## Step 3 — MySQL database

cPanel → **MySQL® Databases**:

1. Create database: `cpaneluser_pipnix`.
2. Create user (`cpaneluser_pipnix`) with a strong password.
3. **Add User To Database** → grant **ALL PRIVILEGES**.

## Step 4 — Configure + install

1. File Manager: in `/home/USER/pipnix/app/`, copy `config.example.php` to
   `config.php` and edit it:

   ```php
   'db' => [
       'host' => 'localhost',          // cPanel MySQL is always localhost
       'port' => 3306,
       'name' => 'cpaneluser_pipnix',
       'user' => 'cpaneluser_pipnix',
       'pass' => 'your-db-password',
   ],
   'timezone' => 'Asia/Jakarta',       // dates of cron/interval runs
   'python_bin' => '/usr/bin/python3', // used to LOAD dags before a venv exists
   ```

2. cPanel → **Terminal** (or SSH):

   ```bash
   cd ~/pipnix
   /usr/local/bin/php app/install.php --admin=you:REALLY-strong-password
   ```

   This creates the 8 `pn_*` tables and your admin account. (Optional flag:
   `--venv=reporting:/home/USER/virtualenv/pipnix/3.11` registers a venv in
   the same step — you can also do it from the web console later.)

## Step 5 — Python environment

cPanel → **Setup Python App**:

1. Create app: Python 3.10/3.11, name `pipnix`, home directory `pipnix`
   (the venv lands in `~/virtualenv/pipnix/<ver>/`).
2. Copy the **"Enter to the virtual environment"** command shown by cPanel —
   you only need the path: `~/virtualenv/pipnix/3.11`.
3. Log into the console (`https://yourdomain.com/pipnix/`) → **Python** →
   *Register environment*: name `main` (or anything), path from step 2,
   tick *make default*.

`lib/dag_loader.py` and `lib/runner.py` are stdlib-only — there is nothing to
`pip install` for pipnix itself. Install only your own job dependencies from
the Python page (or `php app/ops.php venv:install ...`).

## Step 6 — The cron line

cPanel → **Cron Jobs** → add (common settings: "Every minute"):

```
* * * * *  /usr/local/bin/php /home/USER/pipnix/app/scheduler.php >> /home/USER/pipnix/logs/cron.log 2>&1
```

Optional second line — weekly retention cleanup (Sun 04:07):

```
7 4 * * 0  /usr/local/bin/php /home/USER/pipnix/app/ops.php prune --days=30 >> /home/USER/pipnix/logs/cron.log 2>&1
```

That first line *is* the scheduler daemon. Nothing else runs continuously.

## Step 7 — Verify

1. Open `https://yourdomain.com/pipnix/` and log in with the admin account.
2. **Home** should show a green health row and non-empty *Runner Slots*.
3. Manual check via Terminal: `ls -la ~/pipnix/storage/heartbeat` — its
   timestamp must be ≤ 1–2 minutes old (proof the cron fires).
4. Upload a test DAG (**Dags → Import** or the editor), **Trigger**, and watch
   the run go `queued → running → success`; open its task log.

---

## Troubleshooting

| Symptom | Likely cause / fix |
| --- | --- |
| Blank page / 500 on first load | PHP < 8.1 on the domain (Step 2); check cPanel error log |
| `cannot locate app/bootstrap.php` | The console prints every path it checked. In the default layout (`~/pipnix/` + `public_html/pipnix/`) detection is automatic; if you renamed or moved the folder, add one line to the webroot's `.htaccess`: `SetEnv PIPNIX_APP /home/USER/pipnix/app` |
| Heartbeat stale, runs never start | Cron line wrong PHP path, or cron disabled by hosting — test the command manually in Terminal |
| `unknown venv` when triggering a DAG | Register the venv (Python page) and run **Check now**; the DAG's `venv` name must match |
| `runner did not report` on every task | `proc_open`/`nohup` blocked by the host — ask support to allow it, or run tasks via `php app/ops.php tick` |
| Login works but every form says 419 | Page was open across a session change — reload, then retry |
| Assets (CSS/logo) 404 | `.htaccess` in `public_html/pipnix/` missing — re-copy it from `public/` |
| Email alerts never arrive | `alerts.enabled` false, or the task/DAG has no `email_on_failure`; cPanel needs the From address to be a mailbox on your domain |

## Security checklist

- [ ] `app/config.php` exists **only** under `~/pipnix/app/` (never in `public_html`).
- [ ] Admin password is long and unique (it executes DAG code — treat it like shell access).
- [ ] Only `public/` contents live in `public_html`; `.htaccess` is present.
- [ ] HTTPS enforced (cPanel → Domains → *Force HTTPS Redirect*).
- [ ] `PIPNIX_UI_DEV` is **not** set anywhere on the server.
- [ ] Weekly `prune` cron added so `logs/runs/` and run history stay bounded.
