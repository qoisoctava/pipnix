<?php /**
 * Self-service password change — also the forced step after signing in with a
 * weak/temporary password.
 * @var array $user
 */
$forced = (int) ($user['must_change_password'] ?? 0) === 1;
?>
<header class="page-head">
  <div>
    <h1><?= $forced ? 'Set a new password' : 'Change password' ?></h1>
    <p class="muted small">
      <?= $forced
          ? 'This account still uses a temporary password. Choose a new one before continuing.'
          : 'Signed in as ' . e((string) $user['username']) . '.' ?>
    </p>
  </div>
</header>

<?php if ($forced): ?>
  <div class="card" style="border-color:#7F1D1D;margin-bottom:16px">
    <p class="muted" style="margin:0">
      A password equal to the username or a common default (like <span class="mono">admin</span>)
      is refused here — pick something unique, at least 8 characters.
    </p>
  </div>
<?php endif; ?>

<section class="card" style="max-width:520px">
  <form method="post" action="<?= url('/account/password') ?>" class="pwd-form">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

    <div>
      <label for="current_password">Current password</label>
      <input id="current_password" name="current_password" type="password"
             autocomplete="current-password" required autofocus>
    </div>

    <div>
      <label for="new_password">New password</label>
      <input id="new_password" name="new_password" type="password"
             autocomplete="new-password" minlength="8" required>
    </div>

    <div>
      <label for="confirm_password">Repeat new password</label>
      <input id="confirm_password" name="confirm_password" type="password"
             autocomplete="new-password" minlength="8" required>
    </div>

    <div class="row-between">
      <span class="muted small">Minimum 8 characters, must differ from the username.</span>
      <button class="btn btn-primary" type="submit">Save password</button>
    </div>
  </form>
</section>

<section class="card" style="max-width:520px;margin-top:16px">
  <div class="card-head"><h3>Profile</h3><span class="chip"><?= e((string) ($user['role'] ?? '')) ?></span></div>
  <div class="kv-row">
    <div><span class="label">User</span><span class="mono"><?= e((string) ($user['username'] ?? '')) ?></span></div>
    <div><span class="label">Email</span><span class="mono"><?= e((string) ($user['email'] ?? '—')) ?></span></div>
    <div><span class="label">Last login</span><span class="mono"><?= e((string) ($user['last_login_at'] ?? 'never')) ?></span></div>
    <div><span class="label">Member since</span><span class="mono"><?= e((string) ($user['created_at'] ?? '—')) ?></span></div>
  </div>
  <form method="post" action="<?= url('/logout') ?>" style="margin-top:14px">
    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
    <button class="btn" type="submit">⏻ Sign out</button>
  </form>
</section>
