<?php
/** @var array $venvs @var ?array $selected @var array $packages */
$statusClass = static fn (string $s): string => match ($s) {
    'healthy' => 'ok', 'outdated' => 'warn', 'error', 'missing' => 'bad', default => 'idle',
};
?>
<header class="page-head">
  <h1>Python Environments</h1>
  <?php if ($selected): ?>
    <a class="btn" href="<?= url('/python') ?>">All environments</a>
  <?php endif; ?>
</header>

<?php if (!$venvs): ?>
  <div class="card empty">
    <p>No environments registered yet. Add one with:</p>
    <pre class="mono small">php app/install.php --venv=reporting:/home/USER/venvs/reporting</pre>
  </div>
<?php elseif (!$selected): ?>
  <div class="stack">
    <?php foreach ($venvs as $v): ?>
      <section class="card">
        <div class="row-between">
          <div class="dag-title">
            <a class="link mono" href="<?= url('/python/' . rawurlencode($v['name'])) ?>"><?= e($v['name']) ?></a>
            <?php if ((int) $v['is_default'] === 1): ?><span class="badge primary">default</span><?php endif; ?>
            <span class="badge <?= $statusClass($v['status']) ?>"><?= e(state_label($v['status'])) ?></span>
          </div>
          <span class="muted small"><?= (int) $v['packages'] ?> packages · <?= (int) $v['outdated'] ?> outdated</span>
        </div>
        <div class="cols-3">
          <div><span class="label">Directory</span><span class="mono small"><?= e($v['path']) ?></span></div>
          <div><span class="label">Python</span><span class="mono small"><?= e($v['python_version'] ?? '—') ?> · pip <?= e($v['pip_version'] ?? '—') ?></span></div>
          <div><span class="label">Last check</span><span class="mono small"><?= e($v['last_checked_at'] ?? 'never') ?></span></div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="card kv-row">
    <div><span class="label">Directory</span><span class="mono small"><?= e($selected['path']) ?></span></div>
    <div><span class="label">Python</span><span class="mono small"><?= e($selected['python_version'] ?? '—') ?></span></div>
    <div><span class="label">pip</span><span class="mono small"><?= e($selected['pip_version'] ?? '—') ?></span></div>
    <div><span class="label">Status</span><span class="badge <?= $statusClass($selected['status']) ?>"><?= e(state_label($selected['status'])) ?></span></div>
  </section>

  <section class="card">
    <div class="card-head"><h3>Installed packages</h3><span class="muted small"><?= count($packages) ?> packages</span></div>
    <?php if (!$packages): ?>
      <p class="muted">No inventory yet. Run <code class="mono">php app/ops.php venv:check --name=<?= e($selected['name']) ?></code>.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Package</th><th>Version</th><th>Latest</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
          <tr>
            <td class="mono small"><?= e($p['name']) ?></td>
            <td class="mono small"><?= e($p['version']) ?></td>
            <td class="mono small"><?= e($p['latest_version'] ?? '') ?></td>
            <td><?php if ($p['latest_version']): ?><span class="badge warn">update available</span><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<p class="muted small">Package management (install / upgrade / requirements.txt) arrives with the action screens; for now use <code class="mono">php app/ops.php venv:install</code>.</p>
