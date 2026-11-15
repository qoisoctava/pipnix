<?php /** @var array $tr @var array $lines */ ?>
<header class="page-head">
  <div>
    <p class="muted small">
      <a class="link" href="<?= url('/runs/' . (int) $tr['dag_run_id']) ?>">← Run #<?= (int) $tr['dag_run_id'] ?></a> ·
      <?= e($tr['dag_name']) ?>
    </p>
    <h1 class="mono"><?= e($tr['task_name']) ?>
      <span class="badge <?= state_class($tr['state']) ?>"><?= e(state_label($tr['state'])) ?></span>
    </h1>
    <p class="muted small mono">
      <?= e($tr['run_id']) ?> · attempt <?= (int) $tr['attempt'] ?>
      <?php if (!empty($tr['log_path'])): ?> · <?= e(basename((string) $tr['log_path'])) ?><?php endif; ?>
    </p>
  </div>
</header>

<section class="card log-card">
  <div class="card-head">
    <h3>Log</h3>
    <span class="muted small"><?= count($lines) ?> lines shown<?= count($lines) >= 500 ? ' (tail)' : '' ?></span>
  </div>
  <?php if (!$lines): ?>
    <p class="muted">No log yet — the task may still be queued.</p>
  <?php else: ?>
    <pre class="log">
<?php foreach ($lines as $line):
    $cls = '';
    if (str_contains($line, '[ERROR]') || str_contains($line, 'Traceback')) {
        $cls = 'err';
    } elseif (str_contains($line, '[WARN]')) {
        $cls = 'warn';
    }
    echo '<span class="' . $cls . '">' . e($line) . "</span>\n";
endforeach; ?>
    </pre>
  <?php endif; ?>
</section>
