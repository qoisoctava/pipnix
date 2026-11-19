<?php
/**
 * @var array $dag @var array $tasks @var array $runs @var array $grid @var ?string $venvName
 * @var string $tab @var ?array $latest @var array $latestTasks @var array $graph @var ?array $source
 */
$lastState = $runs[0]['state'] ?? null;
$gridRuns = array_reverse($runs); // oldest → newest, left → right
$shortRun = static function (string $id): string {
    if (preg_match('/(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})/', $id, $m)) {
        return substr($m[1], 5) . ' ' . $m[2];
    }
    return substr($id, 0, 10);
};
$tabs = ['grid' => 'Grid', 'graph' => 'Graph', 'gantt' => 'Gantt', 'code' => 'Code'];
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
    <div class="toolbar" style="margin-top:10px">
      <?php if (can('trigger')): ?>
        <form class="inline" method="post" action="<?= url('/dags/' . rawurlencode($dag['name']) . '/trigger') ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn btn-primary" type="submit">▶ Trigger</button>
        </form>
        <form class="inline" method="post" action="<?= url('/dags/' . rawurlencode($dag['name']) . ((int) $dag['is_active'] === 1 ? '/pause' : '/resume')) ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn" type="submit"><?= (int) $dag['is_active'] === 1 ? '⏸ Pause' : '⏵ Resume' ?></button>
        </form>
      <?php endif; ?>
      <form class="inline" method="post" action="<?= url('/dags/' . rawurlencode($dag['name']) . '/star') ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn <?= (int) ($dag['is_favorite'] ?? 0) === 1 ? 'star-on' : '' ?>" type="submit">
          <?= (int) ($dag['is_favorite'] ?? 0) === 1 ? '★ Favorited' : '☆ Favorite' ?>
        </button>
      </form>
      <?php if (can('edit')): ?>
        <a class="btn" href="<?= url('/dags/' . rawurlencode($dag['name']) . '/edit') ?>">✎ Edit</a>
        <form class="inline" method="post" action="<?= url('/dags/' . rawurlencode($dag['name']) . '/delete') ?>"
              onsubmit="return confirm('Delete <?= e($dag['name']) ?> and its run history?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn btn-danger" type="submit">⌫ Delete</button>
        </form>
      <?php endif; ?>
      <a class="btn" href="<?= url('/dags/import') ?>">Import</a>
    </div>
  </div>
</header>

<div class="chips" style="margin-bottom:14px">
  <?php foreach ($tabs as $key => $label): ?>
    <a class="chip <?= $tab === $key ? 'on' : '' ?>"
       href="<?= url('/dags/' . rawurlencode($dag['name']) . '?tab=' . $key) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
  <?php if ($latest && $tab !== 'code'): ?>
    <span class="muted small">latest run <?= e($latest['run_id']) ?></span>
  <?php endif; ?>
</div>

<?php if ($tab === 'grid'): ?>
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
              <a href="<?= url('/runs/' . (int) $r['id']) ?>"><?= e($shortRun((string) $r['run_id'])) ?></a>
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

<?php elseif ($tab === 'graph'): ?>
<?php
$pos = [];
$colW = 220;
$rowH = 96;
$nodeW = 150;
$nodeH = 54;
foreach ($graph['levels'] ?? [] as $col => $nodes) {
    foreach ($nodes as $row => $node) {
        $pos[$node['name']] = [30 + $col * $colW, 30 + $row * $rowH];
    }
}
$svgW = max(360, 40 + max(1, $graph['cols'] ?? 1) * $colW);
$svgH = max(160, 40 + max(1, $graph['rows'] ?? 1) * $rowH);
?>
<section class="card">
  <div class="card-head">
    <h3>Dependency graph</h3>
    <span class="muted small">latest run states · <?= count($tasks) ?> tasks</span>
  </div>
  <?php if (!$latest): ?>
    <p class="muted">No run yet — trigger the Dag to see states on the graph.</p>
  <?php endif; ?>
  <div class="graph-wrap">
    <svg class="dag-graph" viewBox="0 0 <?= $svgW ?> <?= $svgH ?>" width="<?= $svgW ?>" height="<?= $svgH ?>" role="img"
         aria-label="Dependency graph for <?= e($dag['name']) ?>">
      <?php foreach ($graph['nodes'] as $name => $node): ?>
        <?php foreach ($node['deps'] as $dep): ?>
          <?php if (!isset($pos[$dep], $pos[$name])) continue; ?>
          <?php [$x1, $y1] = $pos[$dep]; [$x2, $y2] = $pos[$name]; ?>
          <path class="gedge"
                d="M <?= $x1 + $nodeW ?> <?= $y1 + $nodeH / 2 ?>
                   C <?= $x1 + $nodeW + 40 ?> <?= $y1 + $nodeH / 2 ?>,
                     <?= $x2 - 40 ?> <?= $y2 + $nodeH / 2 ?>,
                     <?= $x2 ?> <?= $y2 + $nodeH / 2 ?>" />
        <?php endforeach; ?>
      <?php endforeach; ?>
      <?php foreach ($pos as $name => [$x, $y]): $node = $graph['nodes'][$name]; ?>
        <g class="gnode <?= state_class((string) $node['state']) ?>">
          <rect x="<?= $x ?>" y="<?= $y ?>" width="<?= $nodeW ?>" height="<?= $nodeH ?>" rx="10" />
          <text x="<?= $x + 14 ?>" y="<?= $y + 22 ?>"><?= e($name) ?></text>
          <text class="gstate" x="<?= $x + 14 ?>" y="<?= $y + 39 ?>">
            <?= e(state_label((string) $node['state'])) ?> · <?= e((string) $node['duration']) ?>
          </text>
        </g>
      <?php endforeach; ?>
    </svg>
  </div>
</section>

<?php elseif ($tab === 'gantt'): ?>
<?php
$runStart = $latest && $latest['started_at'] ? strtotime((string) $latest['started_at']) : null;
$rows = [];
$maxEnd = $runStart;
foreach ($latestTasks as $tr) {
    $start = $tr['started_at'] ? strtotime((string) $tr['started_at']) : null;
    $end = $tr['finished_at'] ? strtotime((string) $tr['finished_at']) : ($start ? $start + 1 : null);
    if ($start !== null) {
        $maxEnd = max((int) $maxEnd, (int) $end);
    }
    $rows[] = [$tr, $start, $end];
}
$span = ($runStart !== null && $maxEnd !== null) ? max(30, $maxEnd - $runStart) : 0;
?>
<section class="card">
  <div class="card-head">
    <h3>Timeline</h3>
    <span class="muted small"><?= $latest ? ('run ' . e((string) $latest['run_id']) . ' · ' . $span . 's window') : 'no run yet' ?></span>
  </div>
  <?php if (!$latest || $runStart === null): ?>
    <p class="muted">Trigger the Dag once to populate the Gantt view.</p>
  <?php else: ?>
    <div class="gantt">
      <?php foreach ($rows as [$tr, $start, $end]): ?>
        <?php
        $offset = $start !== null ? max(0, $start - $runStart) : 0;
        $width = ($start !== null && $end !== null) ? max(1, $end - $start) : 0;
        $left = $span > 0 ? ($offset / $span) * 100 : 0;
        $w = $span > 0 ? max(1.5, ($width / $span) * 100) : 0;
        ?>
        <div class="gantt-row">
          <span class="gantt-name mono"><?= e((string) $tr['name']) ?></span>
          <div class="gantt-track">
            <?php if ($start === null): ?>
              <span class="gantt-bar none" style="left:0;width:100%">not run</span>
            <?php else: ?>
              <span class="gantt-bar <?= state_class((string) $tr['state']) ?>"
                    style="left:<?= round($left, 2) ?>%;width:<?= round($w, 2) ?>%"
                    title="<?= e(state_label((string) $tr['state'])) ?> · <?= $width ?>s">
                <?= round($width) ?>s
              </span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php else: /* code */ ?>
<section class="card">
  <div class="card-head">
    <h3>Source</h3>
    <span class="muted small mono">
      <?= $source ? e($source['name']) . ' · ' . number_format($source['size']) . ' bytes' : 'source not found' ?>
    </span>
  </div>
  <?php if (!$source): ?>
    <p class="muted">The module is not inside <code class="mono">dags/</code> (legacy loader?) — nothing to show.</p>
  <?php else: ?>
    <div class="toolbar" style="margin-bottom:12px">
      <a class="btn" href="<?= url('/dags/' . rawurlencode($dag['name']) . '/source') ?>">⤓ Download</a>
      <button class="btn" type="button" id="copy-code">Copy</button>
      <span class="muted small mono"><?= e($source['path']) ?></span>
    </div>
    <div class="code-view">
      <table>
        <tbody>
        <?php foreach ($source['lines'] as $i => $line): ?>
          <tr><td class="ln"><?= $i + 1 ?></td><td class="cl"><?= e($line) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<script>
(() => {
  const btn = document.getElementById('copy-code');
  if (!btn) return;
  btn.addEventListener('click', async () => {
    const text = Array.from(document.querySelectorAll('.code-view .cl'))
      .map(td => td.textContent).join('\n');
    try {
      await navigator.clipboard.writeText(text);
      btn.textContent = 'Copied';
      setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
    } catch (e) {
      btn.textContent = 'Copy failed';
    }
  });
})();
</script>
<?php endif; ?>
