<?php
/**
 * Global search results (⌘K / Ctrl-K opens the palette in the shell).
 * @var string $q @var string $type
 * @var array $dags @var array $runs @var array $tasks @var int $total
 */
$chips = ['all' => 'All', 'dags' => 'Dags', 'runs' => 'Runs', 'tasks' => 'Tasks'];
?>
<header class="page-head">
  <div>
    <h1>Search</h1>
    <p class="muted small">
      <?php if ($q === ''): ?>
        Type a Dag, run or task name — or press ⌘K / Ctrl-K anywhere.
      <?php else: ?>
        <?= $total ?> result<?= $total === 1 ? '' : 's' ?> for <span class="mono"><?= e($q) ?></span>
      <?php endif; ?>
    </p>
  </div>
</header>

<form class="toolbar" method="get" action="<?= url('/search') ?>" style="margin-bottom:14px">
  <input name="q" value="<?= e($q) ?>" placeholder="Search Dags, runs, tasks…"
         autofocus class="search-input">
  <button class="btn btn-primary" type="submit">Search</button>
  <span class="muted small">Enter to search · Esc closes the palette</span>
</form>

<div class="chips" style="margin-bottom:16px">
  <?php foreach ($chips as $key => $label): ?>
    <a class="chip <?= $type === $key ? 'on' : '' ?>"
       href="<?= url('/search?type=' . $key . '&q=' . rawurlencode($q)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($q !== '' && $total === 0): ?>
  <div class="card empty"><p>No matches. Try a shorter fragment.</p></div>
<?php endif; ?>

<?php if ($dags): ?>
<section class="card">
  <div class="card-head"><h3>Dags</h3><span class="muted small"><?= count($dags) ?></span></div>
  <table class="table">
    <tbody>
    <?php foreach ($dags as $d): ?>
      <tr>
        <td class="mono"><a class="link" href="<?= url('/dags/' . rawurlencode($d['name'])) ?>"><?= e($d['name']) ?></a></td>
        <td><?php if ((int) $d['is_favorite'] === 1): ?><span class="badge warn">★ favorite</span><?php endif; ?></td>
        <td><span class="badge <?= (int) $d['is_active'] === 1 ? 'ok' : 'idle' ?>">
          <?= (int) $d['is_active'] === 1 ? 'active' : 'paused' ?></span></td>
        <td class="right"><a class="link small" href="<?= url('/dags/' . rawurlencode($d['name'])) ?>">Open →</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php if ($runs): ?>
<section class="card">
  <div class="card-head"><h3>Runs</h3><span class="muted small"><?= count($runs) ?></span></div>
  <table class="table">
    <tbody>
    <?php foreach ($runs as $r): ?>
      <tr>
        <td class="mono small"><a class="link" href="<?= url('/runs/' . (int) $r['id']) ?>">
          #<?= (int) $r['id'] ?> · <?= e($r['run_id']) ?></a></td>
        <td class="mono"><?= e($r['dag_name']) ?></td>
        <td><span class="badge <?= state_class($r['state']) ?>"><?= e(state_label($r['state'])) ?></span></td>
        <td class="mono small"><?= e((string) ($r['started_at'] ?? '—')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>

<?php if ($tasks): ?>
<section class="card">
  <div class="card-head"><h3>Task instances</h3><span class="muted small"><?= count($tasks) ?></span></div>
  <table class="table">
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td class="mono"><a class="link" href="<?= url('/task/' . (int) $t['id']) ?>"><?= e($t['task_name']) ?></a></td>
        <td class="mono small"><?= e($t['dag_name']) ?></td>
        <td><span class="badge <?= state_class($t['state']) ?>"><?= e(state_label($t['state'])) ?></span></td>
        <td class="mono small"><?= e((string) ($t['started_at'] ?? '—')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
