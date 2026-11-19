<?php
/**
 * Task instance detail.
 * @var array $tr @var array $attempts @var string $resolved @var array $tail
 */
$state = (string) $tr['state'];
?>
<header class="page-head">
  <div>
    <p class="muted small">
      <a class="link" href="<?= url('/runs/' . (int) $tr['dag_run_id']) ?>">← Run #<?= (int) $tr['dag_run_id'] ?></a> ·
      <a class="link" href="<?= url('/dags/' . rawurlencode((string) $tr['dag_name'])) ?>"><?= e((string) $tr['dag_name']) ?></a>
    </p>
    <h1 class="mono"><?= e((string) $tr['task_name']) ?>
      <span class="badge <?= state_class($state) ?>"><?= e(state_label($state)) ?></span>
    </h1>
    <p class="muted small mono">
      <?= e((string) $tr['run_id']) ?> · attempt <?= (int) $tr['attempt'] ?>/<?= (int) $tr['retries'] + 1 ?>
    </p>
  </div>
  <div class="toolbar">
    <?php if (!empty($tr['log_path'])): ?>
      <a class="btn btn-primary" href="<?= url('/logs/' . (int) $tr['id']) ?>">View logs</a>
    <?php endif; ?>
    <?php if (can('mark') && in_array($state, ['failed', 'timed_out'], true)): ?>
      <form class="inline" method="post" action="<?= url('/task/' . (int) $tr['id'] . '/mark-success') ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn" type="submit">Mark success</button>
      </form>
    <?php endif; ?>
    <?php if (can('clear') && $state !== 'running'): ?>
      <form class="inline" method="post" action="<?= url('/task/' . (int) $tr['id'] . '/clear') ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="btn" type="submit" title="Reset this task and downstream">Clear</button>
      </form>
    <?php endif; ?>
  </div>
</header>

<div class="dash-main">
  <div class="stack">
    <section class="card">
      <div class="card-head"><h3>Resolved command</h3><span class="chip"><?= e((string) $tr['runner']) ?></span></div>
      <pre class="resolved"><?= e($resolved) ?></pre>
      <div class="kv-row" style="margin-top:12px">
        <div><span class="label">Venv</span><span class="mono"><?= e((string) ($tr['venv_name'] ?? '—')) ?></span></div>
        <div><span class="label">Working dir</span><span class="mono"><?= e(PN_BASE) ?></span></div>
        <div><span class="label">Timeout</span><span class="mono"><?= (int) $tr['timeout'] > 0 ? (int) $tr['timeout'] . 's' : 'none' ?></span></div>
        <div><span class="label">Retries</span><span class="mono"><?= (int) $tr['retries'] ?> · <?= (int) $tr['retry_delay'] ?>s</span></div>
      </div>
    </section>

    <?php if (!empty($tr['error'])): ?>
      <section class="card" style="border-color:#7F1D1D">
        <div class="card-head"><h3>Error excerpt</h3></div>
        <pre class="resolved err"><?= e(mb_substr((string) $tr['error'], 0, 4000)) ?></pre>
      </section>
    <?php endif; ?>

    <section class="card">
      <div class="card-head">
        <h3>Attempts</h3><span class="muted small"><?= count($attempts) ?> log<?= count($attempts) === 1 ? '' : 's' ?></span>
      </div>
      <?php if (!$attempts): ?>
        <p class="muted">No log files yet — the task may still be queued.</p>
      <?php else: ?>
        <table class="table">
          <thead><tr><th>Attempt</th><th>Log</th><th>Size</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($attempts as $n => $path): ?>
            <tr>
              <td class="mono">a<?= (int) $n ?></td>
              <td class="mono small"><?= e(basename($path)) ?></td>
              <td class="mono small"><?= number_format((int) @filesize($path)) ?> B</td>
              <td class="right"><a class="link small" href="<?= url('/task/' . (int) $tr['id'] . '/attempt/' . (int) $n) ?>">Open</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

    <?php if ($tail): ?>
      <section class="card log-card">
        <div class="card-head"><h3>Log tail</h3><a class="link small" href="<?= url('/logs/' . (int) $tr['id']) ?>">Full log →</a></div>
        <pre class="log"><?php foreach ($tail as $line): ?><span class="<?= str_contains($line, '[ERROR]') || str_contains($line, 'Traceback') ? 'err' : (str_contains($line, '[WARN]') ? 'warn' : '') ?>"><?= e($line) ?>
</span><?php endforeach; ?></pre>
      </section>
    <?php endif; ?>
  </div>

  <aside class="stack">
    <section class="card">
      <div class="card-head"><h3>Details</h3></div>
      <div class="kv-row vertical">
        <div><span class="label">Dag</span><span class="mono"><?= e((string) $tr['dag_name']) ?></span></div>
        <div><span class="label">Task</span><span class="mono"><?= e((string) $tr['task_name']) ?></span></div>
        <div><span class="label">Run ID</span><span class="mono small"><?= e((string) $tr['run_id']) ?></span></div>
        <div><span class="label">Run state</span><span class="badge <?= state_class((string) $tr['run_state']) ?>"><?= e(state_label((string) $tr['run_state'])) ?></span></div>
        <div><span class="label">Started</span><span class="mono"><?= e((string) ($tr['started_at'] ?? '—')) ?></span></div>
        <div><span class="label">Finished</span><span class="mono"><?= e((string) ($tr['finished_at'] ?? '—')) ?></span></div>
        <div><span class="label">Duration</span><span class="mono"><?= e(human_duration($tr['started_at'] ?? null, $tr['finished_at'] ?? null)) ?></span></div>
        <div><span class="label">Exit code</span><span class="mono"><?= $tr['exit_code'] !== null ? (int) $tr['exit_code'] : '—' ?></span></div>
        <div><span class="label">Depends on</span><span class="mono small"><?= e((string) ($tr['depends_on'] ?? '') !== '' ? (string) $tr['depends_on'] : '—') ?></span></div>
        <div><span class="label">Email on failure</span><span class="mono"><?= (int) ($tr['email_on_failure'] ?? 0) === 1 ? 'yes' : 'no' ?></span></div>
      </div>
    </section>

    <?php if (!empty($tr['result_json'])): ?>
      <section class="card">
        <div class="card-head"><h3>Result</h3></div>
        <pre class="resolved"><?= e(mb_substr((string) $tr['result_json'], 0, 4000)) ?></pre>
      </section>
    <?php endif; ?>
  </aside>
</div>
