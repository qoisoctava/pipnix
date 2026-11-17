<?php /** @var string $state @var array $rows */
$states = ['queued', 'running', 'retrying', 'success', 'failed', 'timed_out', 'upstream_failed', 'skipped'];
?>
<header class="page-head">
  <div>
    <h1>Logs</h1>
    <p class="muted small">The 100 newest task runs — open one for its per-attempt log.
      Full history lives on <a class="link" href="<?= url('/runs') ?>">Runs</a>.</p>
  </div>
</header>

<div class="card">
  <div class="card-head">
    <h3>Task runs</h3>
    <div class="chips">
      <a class="chip <?= $state === '' ? 'on' : '' ?>" href="<?= url('/logs') ?>">all</a>
      <?php foreach ($states as $s): ?>
        <a class="chip <?= $state === $s ? 'on' : '' ?>" href="<?= url('/logs?state=' . $s) ?>"><?= e(state_label($s)) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php if (!$rows): ?>
    <p class="muted">No task runs yet<?= $state !== '' ? ' in state "' . e(state_label($state)) . '"' : '' ?>.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>Run</th><th>Dag</th><th>Task</th><th>Attempt</th><th>State</th><th>Started</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="mono small"><a class="link" href="<?= url('/runs/' . (int) $r['dag_run_id']) ?>">#<?= (int) $r['dag_run_id'] ?></a></td>
          <td class="mono small"><?= e((string) $r['dag_name']) ?></td>
          <td class="mono"><?= e((string) $r['task_name']) ?></td>
          <td class="mono small"><?= (int) $r['attempt'] ?></td>
          <td><span class="badge <?= state_class((string) $r['state']) ?>"><?= e(state_label((string) $r['state'])) ?></span></td>
          <td class="mono small"><?= e((string) ($r['started_at'] ?? '—')) ?></td>
          <td class="right nowrap">
            <?php if ($r['has_log']): ?>
              <a class="link small" href="<?= url('/logs/' . (int) $r['id']) ?>">Log →</a>
            <?php else: ?>
              <span class="muted small">no log</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
