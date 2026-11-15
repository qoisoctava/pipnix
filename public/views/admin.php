<?php /** @var array $users @var int $venvCount */ ?>
<header class="page-head">
  <h1>Admin</h1>
</header>

<section class="card">
  <div class="card-head"><h3>Users</h3><span class="muted small"><?= count($users) ?> accounts</span></div>
  <table class="table">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td class="mono"><?= e($u['username']) ?></td>
        <td class="muted small"><?= e($u['email'] ?? '—') ?></td>
        <td><span class="chip"><?= e($u['role']) ?></span></td>
        <td><span class="badge <?= (int) $u['is_active'] === 1 ? 'ok' : 'idle' ?>"><?= (int) $u['is_active'] === 1 ? 'active' : 'disabled' ?></span></td>
        <td class="mono small"><?= e($u['last_login_at'] ?? 'never') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <p class="muted small">Invite/reset flows are part of the next phase.</p>
</section>

<section class="card">
  <div class="card-head"><h3>Settings</h3><span class="muted small">read-only summary</span></div>
  <div class="kv-row">
    <div><span class="label">Timezone</span><span class="mono"><?= e((string) Env::cfg('timezone', 'UTC')) ?></span></div>
    <div><span class="label">Scheduler tick</span><span class="mono">60s (cron)</span></div>
    <div><span class="label">Lock timeout</span><span class="mono"><?= (int) Env::cfg('lock.timeout', 50) ?>s</span></div>
    <div><span class="label">Alerts</span><span class="badge <?= Env::cfg('alerts.enabled') ? 'ok' : 'idle' ?>"><?= Env::cfg('alerts.enabled') ? 'enabled' : 'disabled' ?></span></div>
    <div><span class="label">Environments</span><a class="link mono" href="<?= url('/python') ?>"><?= $venvCount ?> registered</a></div>
  </div>
</section>
