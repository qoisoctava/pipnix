<?php
/**
 * @var array $dag @var array $tasks @var array $runs @var array $grid @var ?string $venvName
 */
$lastState = $runs[0]['state'] ?? null;
$gridRuns = array_reverse($runs); // oldest → newest, left → right
?>
<header class="page-head">
  <div>
    <p class="muted small"><a class="link" href="<?= url('/dags') ?>">← Dags</a></p>
    <h1 class="mono"><?= e($dag['name']) ?>
      <?php if ((int) $dag['is_active'] === 1): ?>
        <span class="badge ok">active</span>
      <?php else: ?>
        <span class="badge idle">paused</span>
      <?php endif; ?>
      <?php if ($lastState): ?><span class="badge <?= state_class($lastState) ?>"><?= e(state_label($lastState)) ?></span><?php endif; ?>
    </h1>
    <p class="muted small">
      <?= e(human_schedule($dag)) ?> · next run <?= e(next_run_text($dag)) ?>
      <?php if ($venvName): ?> · venv <span class="mono"><?= e($venvName) ?></span><?php endif; ?>
      · loader <span class="mono"><?= e($dag['loader']) ?></span>
    </p>
  </div>
</header>

<section class="card">
  <div class="card-head"><h3>Tasks</h3><span class="muted small"><?= count($tasks) ?> tasks</span></div>
  <table class="table">
    <thead><tr><th>Task</th><th>Runner</th><th>Func / Command</th><th>Depends on</th><th>Retries</th><th>Timeout</th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td class="mono"><?= e($t['name']) ?></td>
        <td><span class="chip"><?= e($t['runner']) ?></span></td>
        <td class="mono small"><?= e($t['func'] ?: $t['command']) ?></td>
        <td class="muted small"><?= e($t['depends_on'] !== '' ? $t['depends_on'] : '—') ?></td>
        <td class="mono small"><?= (int) $t['retries'] ?></td>
        <td class="mono small"><?= (int) $t['timeout'] > 0 ? (int) $t['timeout'] . 's' : 'none' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>

<?php if ($gridRuns): ?>
<section class="card">
  <div class="card-head"><h3>Run history</h3><span class="muted small">last <?= count($gridRuns) ?> runs</span></div>
  <div class="grid-wrap">
    <table class="run-grid">
      <thead>
        <tr>
          <th></th>
          <?php foreach ($gridRuns as $r): ?>
            <th title="<?= e($r['run_id']) ?>">
              <a href="<?= url('/runs/' . (int) $r['id']) ?>"><?= e(substr((string) $r['run_id'], 0, 10)) ?></a>
            </th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr>
          <th class="mono"><?= e($t['name']) ?></th>
          <?php foreach ($gridRuns as $r): ?>
            <?php $st = $grid[(int) $t['id']][(int) $r['id']] ?? null; ?>
            <td>
              <?php if ($st): ?>
                <span class="sq <?= state_class($st) ?>" title="<?= e(state_label($st)) ?>"></span>
              <?php else: ?>
                <span class="sq none"></span>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h3>Recent runs</h3></div>
  <table class="table">
    <thead><tr><th>ID</th><th>Run</th><th>State</th><th>Started</th><th>Finished</th></tr></thead>
    <tbody>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td><a class="link" href="<?= url('/runs/' . (int) $r['id']) ?>">#<?= (int) $r['id'] ?></a></td>
        <td class="mono small"><?= e($r['run_id']) ?></td>
        <td><span class="badge <?= state_class($r['state']) ?>"><?= e(state_label($r['state'])) ?></span></td>
        <td class="mono small"><?= e($r['started_at'] ?? '—') ?></td>
        <td class="mono small"><?= e($r['finished_at'] ?? '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
