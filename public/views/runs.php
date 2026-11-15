<?php
/** @var array $runs @var array $dags @var string $dagFilter @var string $stateFilter */
?>
<header class="page-head">
  <h1>Runs</h1>
  <form class="filters" method="get" action="<?= url('/runs') ?>">
    <select name="dag">
      <option value="">All Dags</option>
      <?php foreach ($dags as $d): ?>
        <option value="<?= e($d['name']) ?>" <?= $dagFilter === $d['name'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="state">
      <?php foreach (['' => 'All states', 'running' => 'Running', 'success' => 'Success', 'failed' => 'Failed'] as $val => $label): ?>
        <option value="<?= e($val) ?>" <?= $stateFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn" type="submit">Filter</button>
  </form>
</header>

<div class="card">
  <?php if (!$runs): ?>
    <p class="muted">No runs match the filter.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>ID</th><th>Dag</th><th>Run</th><th>State</th><th>Tasks</th><th>Started</th><th>Duration</th></tr></thead>
      <tbody>
      <?php foreach ($runs as $r): ?>
        <tr>
          <td><a class="link" href="<?= url('/runs/' . (int) $r['id']) ?>">#<?= (int) $r['id'] ?></a></td>
          <td><a class="link mono small" href="<?= url('/dags/' . rawurlencode($r['dag_name'])) ?>"><?= e($r['dag_name']) ?></a></td>
          <td class="mono small"><?= e($r['run_id']) ?></td>
          <td><span class="badge <?= state_class($r['state']) ?>"><?= e(state_label($r['state'])) ?></span></td>
          <td class="mono small"><?= (int) $r['done'] ?> / <?= (int) $r['tasks'] ?></td>
          <td class="mono small"><?= e($r['started_at'] ?? '—') ?></td>
          <td class="mono small"><?= e(human_duration($r['started_at'], $r['finished_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
