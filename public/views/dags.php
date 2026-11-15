<?php
/** @var array $dags @var string $search */
?>
<header class="page-head">
  <h1>Dags</h1>
  <form class="search" method="get" action="<?= url('/dags') ?>">
    <span class="search-glyph">⌕</span>
    <input name="q" value="<?= e($search) ?>" placeholder="Search Dags">
  </form>
</header>

<?php if (!$dags): ?>
  <div class="card empty">
    <p>No Dags yet. Put a module in <code>dags/</code> and run a tick.</p>
  </div>
<?php else: ?>
  <div class="stack">
    <?php foreach ($dags as $dag): ?>
      <section class="card dag-card">
        <div class="row-between">
          <div class="dag-title">
            <a class="link" href="<?= url('/dags/' . rawurlencode($dag['name'])) ?>"><?= e($dag['name']) ?></a>
            <span class="muted small"><?= (int) $dag['tasks'] ?> tasks</span>
            <?php if ((int) $dag['is_active'] === 0): ?><span class="badge idle">paused</span><?php endif; ?>
          </div>
          <div class="dag-actions muted">
            <?php if ($dag['last_state']): ?>
              <span class="badge <?= state_class($dag['last_state']) ?>"><?= e(state_label($dag['last_state'])) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="cols-3">
          <div><span class="label">Schedule</span><span class="mono"><?= e(human_schedule($dag)) ?></span></div>
          <div><span class="label">Latest Run</span><span class="mono"><?= e($dag['last_started'] ?? '—') ?></span></div>
          <div><span class="label">Next Run</span><span class="mono"><?= e(next_run_text($dag)) ?></span></div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
