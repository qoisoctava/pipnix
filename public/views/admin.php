<?php /** @var array $users @var int $venvCount */ ?>
<header class="page-head">
  <h1>Admin</h1>
  <div class="toolbar">
    <a class="btn" href="<?= url('/admin/updates') ?>">Updates</a>
    <a class="btn" href="<?= url('/admin/settings') ?>">Settings</a>
    <a class="btn" href="<?= url('/admin/audit') ?>">Audit log</a>
    <a class="btn" href="<?= url('/python') ?>">Environments</a>
  </div>
</header>

<section class="card">
  <div class="card-head"><h3>Users</h3><span class="muted small"><?= count($users) ?> accounts</span></div>
  <table class="table">
    <thead><tr><th>User</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td class="mono"><?= e($u['username']) ?></td>
        <td class="muted small"><?= e($u['email'] ?? '—') ?></td>
        <td><span class="chip"><?= e($u['role']) ?></span></td>
        <td><span class="badge <?= (int) $u['is_active'] === 1 ? 'ok' : 'idle' ?>"><?= (int) $u['is_active'] === 1 ? 'active' : 'disabled' ?></span></td>
        <td class="mono small"><?= e($u['last_login_at'] ?? 'never') ?></td>
        <td class="nowrap">
          <form class="inline" method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/toggle') ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-sm" type="submit"><?= (int) $u['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
          </form>
          <form class="inline" method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/password') ?>">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <input name="password" type="password" placeholder="new password" size="12" minlength="8" required>
            <button class="btn btn-sm" type="submit">Set</button>
          </form>
          <?php if ((int) $u['id'] !== (int) ($user['id'] ?? 0)): ?>
            <form class="inline" method="post" action="<?= url('/admin/users/' . (int) $u['id'] . '/role') ?>">
              <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
              <select name="role" aria-label="Role for <?= e($u['username']) ?>">
                <?php foreach (['viewer', 'operator', 'admin'] as $roleOption): ?>
                  <option value="<?= $roleOption ?>" <?= $u['role'] === $roleOption ? 'selected' : '' ?>><?= $roleOption ?></option>
                <?php endforeach; ?>
              </select>
              <button class="btn btn-sm" type="submit">Role</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <form method="post" action="<?= url('/admin/users/new') ?>" class="form-grid" style="margin-top:16px">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <div>
      <label for="u-name">New user</label>
      <input id="u-name" name="username" pattern="[a-z0-9][a-z0-9_.-]{2,31}" placeholder="operator1" required>
    </div>
    <div>
      <label for="u-mail">Email</label>
      <input id="u-mail" name="email" type="email" placeholder="ops@example.com">
    </div>
    <div>
      <label for="u-pass">Password</label>
      <input id="u-pass" name="password" type="password" minlength="8" required>
    </div>
    <div>
      <label for="u-role">Role</label>
      <select id="u-role" name="role">
        <option value="viewer">viewer</option>
        <option value="operator">operator</option>
        <option value="admin">admin</option>
      </select>
    </div>
    <div>
      <label>&nbsp;</label>
      <button class="btn btn-primary" type="submit">Create user</button>
    </div>
  </form>
</section>

<section class="card">
  <div class="card-head"><h3>Settings</h3><span class="muted small">read-only summary</span></div>
  <div class="kv-row">
    <div><span class="label">Timezone</span><span class="mono"><?= e((string) Env::cfg('timezone', 'UTC')) ?></span></div>
    <div><span class="label">Scheduler tick</span><span class="mono">60s (cron)</span></div>
    <div><span class="label">Lock timeout</span><span class="mono"><?= (int) Env::cfg('lock.timeout', 50) ?>s</span></div>
    <div><span class="label">Alerts</span><span class="badge <?= Env::cfg('alerts.enabled') ? 'ok' : 'idle' ?>"><?= Env::cfg('alerts.enabled') ? 'enabled' : 'disabled' ?></span></div>
    <div><span class="label">Version</span><span class="mono"><?= e(PN_VERSION) ?></span></div>
    <div><span class="label">Environments</span><a class="link mono" href="<?= url('/python') ?>"><?= $venvCount ?> registered</a></div>
  </div>
</section>
