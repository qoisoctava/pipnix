<?php /** @var array $rows */ ?>
<header class="page-head">
  <div>
    <p class="muted small"><a class="link" href="<?= url('/admin') ?>">← Admin</a></p>
    <h1>Audit log</h1>
    <p class="muted small">Every state-changing action, newest first.</p>
  </div>
</header>

<div class="card">
  <?php if (!$rows): ?>
    <p class="muted">Nothing recorded yet.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Time</th><th>Actor</th><th>Action</th><th>Object</th><th>IP</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono small"><?= e((string) $r['created_at']) ?></td>
          <td class="mono small"><?= e((string) $r['actor']) ?></td>
          <td><span class="badge <?= str_starts_with((string) $r['action'], 'dag.') && str_contains((string) $r['action'], 'fail') ? 'bad' : 'primary' ?>">
              <?= e((string) $r['action']) ?></span></td>
          <td class="mono small"><?= e((string) $r['object']) ?></td>
          <td class="muted small mono"><?= e((string) ($r['ip'] ?? '—')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
