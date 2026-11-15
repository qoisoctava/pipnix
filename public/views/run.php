<?php /** @var array $run @var array $tasks */ ?>
<header class="page-head">
  <div>
    <p class="muted small">
      <a class="link" href="<?= url('/runs') ?>">← Runs</a> ·
      <a class="link" href="<?= url('/dags/' . rawurlencode($run['dag_name'])) ?>"><?= e($run['dag_name']) ?></a>
    </p>
    <h1>Run #<?= (int) $run['id'] ?>
      <span class="badge <?= state_class($run['state']) ?>"><?= e(state_label($run['state'])) ?></span>
    </h1>
    <p class="muted small mono"><?= e($run['run_id']) ?></p>
  </div>
</header>

<section class="card kv-row">
  <div><span class="label">Started</span><span class="mono"><?= e($run['started_at'] ?? '—') ?></span></div>
  <div><span class="label">Finished</span><span class="mono"><?= e($run['finished_at'] ?? '—') ?></span></div>
  <div><span class="label">Duration</span><span class="mono"><?= e(human_duration($run['started_at'], $run['finished_at'])) ?></span></div>
  <div><span class="label">Tasks</span><span class="mono"><?= count($tasks) ?></span></div>
</section>

<section class="card">
  <div class="card-head"><h3>Tasks in this run</h3></div>
  <table class="table">
    <thead><tr><th>Task</th><th>State</th><th>Attempt</th><th>Started</th><th>Duration</th><th>Exit</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr class="<?= $t['state'] === 'failed' ? 'row-fail' : '' ?>">
        <td class="mono"><?= e($t['task_name']) ?></td>
        <td><span class="badge <?= state_class($t['state']) ?>"><?= e(state_label($t['state'])) ?></span></td>
        <td class="mono small"><?= (int) $t['attempt'] ?></td>
        <td class="mono small"><?= e($t['started_at'] ?? '—') ?></td>
        <td class="mono small"><?= e(human_duration($t['started_at'], $t['finished_at'])) ?></td>
        <td class="mono small"><?= $t['exit_code'] !== null ? (int) $t['exit_code'] : '—' ?></td>
        <td class="right">
          <?php if (!empty($t['log_path'])): ?>
            <a class="link small" href="<?= url('/logs/' . (int) $t['id']) ?>">Logs</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php if (!empty($t['error'])): ?>
        <tr class="row-fail">
          <td colspan="7" class="mono small err"><?= e(mb_substr((string) $t['error'], 0, 400)) ?></td>
        </tr>
      <?php endif; ?>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
