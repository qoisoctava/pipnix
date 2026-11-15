<?php
/** Login — split brand panel + form.
 * @var ?string $username
 */
$u = $username ?? '';
?>
<div class="login">
  <section class="brand-panel">
    <img class="brand-watermark" src="<?= url('/assets/logo-white.png') ?>" alt="">
    <div class="brand-lockup">
      <img src="<?= url('/assets/logo-white.png') ?>" alt="pipnix logo" height="64">
      <span class="brand-name">pipnix</span>
    </div>
    <div class="brand-eyebrow"><span>WORKFLOW SCHEDULER</span><i></i></div>
    <div class="brand-bottom">
      <h2>Less Complexity.<br>More Efficiency.</h2>
      <ul>
        <li>✓&nbsp; Lightweight by Design.</li>
        <li>✓&nbsp; Engineering Simplicity into Every Pipeline.</li>
      </ul>
      <p>pipnix — a lightweight workflow scheduler that runs on any PHP&nbsp;+&nbsp;MySQL host.</p>
    </div>
  </section>

  <section class="login-panel">
    <div class="login-logo">
      <img src="<?= url('/assets/app-icon-180.png') ?>" alt="" width="34" height="34">
      <span>pipnix</span>
    </div>
    <form class="login-form" method="post" action="<?= url('/login') ?>">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <h1>Welcome back</h1>
      <p class="muted">Sign in to your workflow scheduler</p>

      <label for="username">Username</label>
      <input id="username" name="username" value="<?= e($u) ?>" autocomplete="username" autofocus required>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" autocomplete="current-password" required>

      <div class="login-row">
        <label class="check"><input type="checkbox" name="remember" value="1"> Remember me</label>
        <span class="muted small">Ask an admin to reset it</span>
      </div>

      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
      <p class="muted small center">Unauthorized access is monitored · pipnix</p>
    </form>
  </section>
</div>
