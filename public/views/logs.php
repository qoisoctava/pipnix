<?php
/**
 * @var array $tr @var array $lines
 * @var ?int $attempt @var ?string $logPath
 */
$shownAttempt = $attempt ?? (int) $tr['attempt'];
$file = basename((string) ($logPath ?? $tr['log_path'] ?? ''));
$download = url('/logs/' . (int) $tr['id'] . '/download')
    . (isset($attempt) ? '?attempt=' . (int) $attempt : '');
?>
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
      <?= e($tr['run_id']) ?> · attempt <?= (int) $shownAttempt ?>
      <?php if ($file !== ''): ?> · <?= e($file) ?><?php endif; ?>
    </p>
  </div>
  <div class="toolbar">
    <a class="btn" href="<?= url('/task/' . (int) $tr['id']) ?>">Task detail</a>
    <?php if ($file !== ''): ?>
      <a class="btn" href="<?= e($download) ?>">⤓ Download</a>
    <?php endif; ?>
  </div>
</header>

<section class="card log-card">
  <div class="card-head">
    <h3>Log</h3>
    <span class="muted small"><?= count($lines) ?> lines shown<?= count($lines) >= 500 ? ' (tail)' : '' ?></span>
  </div>
  <div class="log-tools">
    <input id="log-filter" type="search" placeholder="Filter lines…" autocomplete="off">
    <label class="check"><input id="log-live" type="checkbox"> live (5s)</label>
    <span class="muted small" id="log-count"></span>
  </div>
  <?php if (!$lines): ?>
    <p class="muted">No log yet — the task may still be queued.</p>
  <?php else: ?>
    <pre class="log" id="log-body"><?php foreach ($lines as $line):
        $cls = '';
        if (str_contains($line, '[ERROR]') || str_contains($line, 'Traceback')) {
            $cls = 'err';
        } elseif (str_contains($line, '[WARN]')) {
            $cls = 'warn';
        }
        echo '<span class="log-line ' . $cls . '">' . e($line) . "</span>\n";
    endforeach; ?></pre>
  <?php endif; ?>
</section>
<script>
(() => {
  const input = document.getElementById('log-filter');
  const body = document.getElementById('log-body');
  const count = document.getElementById('log-count');
  if (!input || !body) return;
  const lines = Array.from(body.querySelectorAll('.log-line'));

  const apply = () => {
    const q = input.value.trim().toLowerCase();
    let shown = 0;
    for (const line of lines) {
      const hit = q === '' || line.textContent.toLowerCase().includes(q);
      line.style.display = hit ? '' : 'none';
      line.classList.toggle('hit', q !== '' && hit);
      if (hit) shown++;
    }
    if (count) count.textContent = q === '' ? '' : (shown + ' / ' + lines.length + ' lines');
  };
  input.addEventListener('input', apply);
  apply();

  const live = document.getElementById('log-live');
  if (live) {
    live.addEventListener('change', () => {
      if (live.checked) {
        setTimeout(() => location.reload(), 5000);
      }
    });
  }
})();
</script>
