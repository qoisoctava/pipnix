<?php /** @var array $values */ ?>
<header class="page-head">
  <div>
    <h1>Settings</h1>
    <p class="muted small">Runtime behaviour. Changes apply on the next scheduler tick.</p>
  </div>
  <div class="toolbar">
    <a class="btn" href="<?= url('/admin') ?>">← Admin</a>
    <a class="btn" href="<?= url('/admin/audit') ?>">Audit log</a>
  </div>
</header>

<section class="card">
  <div class="card-head"><h3>Scheduler</h3><span class="muted small">read-only — lives in app/config.php</span></div>
  <div class="kv-row">
    <div><span class="label">Cron tick</span><span class="mono">60s (cron / supercronic)</span></div>
    <div><span class="label">Timezone</span><span class="mono"><?= e((string) Env::cfg('timezone', 'UTC')) ?></span></div>
    <div><span class="label">Lock timeout</span><span class="mono"><?= (int) Env::cfg('lock.timeout', 50) ?>s</span></div>
    <div><span class="label">Launches / tick</span><span class="mono"><?= (int) Env::cfg('tick.max_launches_per_tick', 4) ?></span></div>
    <div><span class="label">Stale runner grace</span><span class="mono"><?= (int) Env::cfg('tick.stale_runner_grace', 15) ?>s</span></div>
    <div><span class="label">Version</span><span class="mono"><?= e(PN_VERSION) ?></span></div>
  </div>
</section>

<form method="post" action="<?= url('/admin/settings') ?>" class="stack">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <section class="card">
    <div class="card-head">
      <h3>Global task defaults</h3>
      <span class="muted small">used when a task omits the key</span>
    </div>
    <div class="form-grid">
      <div>
        <label for="s-retries">Retries</label>
        <input id="s-retries" name="task_retries" type="number" min="0" max="10"
               value="<?= (int) $values['task.retries'] ?>">
      </div>
      <div>
        <label for="s-delay">Retry delay (s)</label>
        <input id="s-delay" name="task_retry_delay" type="number" min="0" max="86400"
               value="<?= (int) $values['task.retry_delay'] ?>">
      </div>
      <div>
        <label for="s-timeout">Timeout (s, 0 = none)</label>
        <input id="s-timeout" name="task_timeout" type="number" min="0" max="86400"
               value="<?= (int) $values['task.timeout'] ?>">
      </div>
      <div>
        <label>Email on failure</label>
        <label class="check"><input type="checkbox" name="task_email" value="1"
          <?= $values['task.email'] ? 'checked' : '' ?>> default for new tasks</label>
      </div>
    </div>
    <p class="hint">Saving invalidates the DAG cache so existing modules pick the new defaults up on the next tick.</p>
  </section>

  <section class="card">
    <div class="card-head">
      <h3>Alerting</h3>
      <span class="muted small">PHP mail() — no SMTP editor in this release</span>
    </div>
    <div class="form-grid">
      <div>
        <label>Alerts</label>
        <label class="check"><input type="checkbox" name="alerts_enabled" value="1"
          <?= $values['alerts.enabled'] ? 'checked' : '' ?>> send email on failure</label>
      </div>
      <div>
        <label for="s-from">From</label>
        <input id="s-from" name="alerts_from" type="email" class="mono"
               value="<?= e((string) $values['alerts.from']) ?>" required>
      </div>
      <div>
        <label for="s-to">Recipients</label>
        <input id="s-to" name="alerts_to" class="mono" placeholder="ops@example.com, oncall@example.com"
               value="<?= e((string) $values['alerts.to']) ?>">
        <p class="hint">comma-separated; a DAG-level <span class="mono">email</span> overrides it</p>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h3>Retention</h3><span class="muted small">finished runs and their logs</span></div>
    <div class="form-grid">
      <div>
        <label for="s-retention">Keep for (days)</label>
        <input id="s-retention" name="retention_days" type="number" min="1" max="3650"
               value="<?= (int) $values['retention.days'] ?>">
        <p class="hint">used by the weekly prune cron when no <span class="mono">--days</span> is given</p>
      </div>
    </div>
  </section>

  <div class="toolbar">
    <button class="btn btn-primary" type="submit">Save changes</button>
  </div>
</form>

<section class="card" style="border-color:#7F1D1D">
  <div class="card-head"><h3>Danger zone</h3><span class="muted small">irreversible</span></div>
  <p class="muted">Delete finished runs and their per-attempt logs older than the retention window. Audit rows are kept.</p>
  <form method="post" action="<?= url('/admin/settings/purge') ?>" class="form-grid"
        onsubmit="return confirm('Purge finished runs and logs older than <?= (int) $values['retention.days'] ?> days?')">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div>
      <label for="p-days">Older than (days)</label>
      <input id="p-days" name="days" type="number" min="1" max="3650" value="<?= (int) $values['retention.days'] ?>">
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-danger" type="submit">Purge now</button>
    </div>
  </form>
</section>
