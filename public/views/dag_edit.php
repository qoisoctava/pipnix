<?php
/**
 * DAG editor (new + edit).
 * @var ?array $dag @var array $tasks @var array $venvs @var ?string $venvName
 */
$venvName = $venvName ?? null;
$isEdit = $dag !== null;
$schedule = $isEdit
    ? ($dag['schedule_type'] === 'interval' ? 'interval:' . (int) $dag['schedule'] : (string) $dag['schedule'])
    : '0 3 * * *';
?>
<header class="page-head">
  <div>
    <p class="muted small">
      <a class="link" href="<?= $isEdit ? url('/dags/' . rawurlencode($dag['name'])) : url('/dags') ?>">
        ← <?= $isEdit ? e($dag['name']) : 'Dags' ?>
      </a>
    </p>
    <h1><?= $isEdit ? 'Edit Dag' : 'New Dag' ?></h1>
    <p class="muted small">Saving writes <span class="mono">dags/&lt;name&gt;.py</span>, then re-validates it with the loader before committing.</p>
  </div>
</header>

<form class="stack" method="post"
      action="<?= url($isEdit ? '/dags/' . rawurlencode($dag['name']) . '/edit' : '/dags/new') ?>">
  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

  <section class="card">
    <div class="card-head"><h3>Dag settings</h3></div>
    <div class="form-grid">
      <div>
        <label for="f-name">Name</label>
        <input id="f-name" name="name" value="<?= e($isEdit ? $dag['name'] : '') ?>"
               <?= $isEdit ? 'readonly' : 'autofocus' ?> pattern="[a-z0-9][a-z0-9_.-]{1,63}"
               placeholder="daily_report">
      </div>
      <div>
        <label for="f-schedule">Schedule</label>
        <input id="f-schedule" name="schedule" value="<?= e($schedule) ?>"
               placeholder="0 3 * * *  or  interval:60">
        <p class="hint">cron (5 fields) or <span class="mono">interval:</span>minutes</p>
        <div class="schedule-preview" id="schedule-preview" aria-live="polite">
          <span class="muted small" id="schedule-human">checking…</span>
          <ol id="schedule-next"></ol>
        </div>
      </div>
      <div>
        <label for="f-venv">Venv (python tasks)</label>
        <select id="f-venv" name="venv">
          <option value="">— none —</option>
          <?php foreach ($venvs as $v): ?>
            <option value="<?= e($v['name']) ?>" <?= $venvName === $v['name'] ? 'selected' : '' ?>>
              <?= e($v['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="f-conc">Max concurrency</label>
        <input id="f-conc" name="max_concurrency" type="number" min="1" max="16"
               value="<?= e((string) ($isEdit ? $dag['max_concurrency'] : 1)) ?>">
      </div>
    </div>
  </section>

  <section class="card">
    <div class="card-head">
      <h3>Tasks</h3>
      <span class="muted small push">func <em>or</em> command — never both</span>
    </div>
    <div class="task-grid" id="task-grid">
      <div class="tg-head">
        <span>Task</span><span>Depends on</span><span>Func</span>
        <span>Command</span><span>Retries</span><span>Delay</span><span>Timeout</span><span>Mail</span><span></span>
      </div>
      <?php
      $rows = $isEdit ? $tasks : [['name' => '', 'depends_on' => '', 'func' => '', 'command' => '',
                                    'retries' => 0, 'retry_delay' => 300, 'timeout' => 0, 'email_on_failure' => 0]];
      foreach ($rows as $t): ?>
        <div class="tg-row">
          <input name="tn[]" value="<?= e((string) $t['name']) ?>" placeholder="extract">
          <input name="td[]" value="<?= e(is_array($t['depends_on'] ?? '') ? implode(',', $t['depends_on'] ?? []) : (string) ($t['depends_on'] ?? '')) ?>" placeholder="—">
          <input name="tf[]" value="<?= e((string) ($t['func'] ?? '')) ?>" placeholder="jobs.reporting.extract" class="mono">
          <input name="tc[]" value="<?= e((string) ($t['command'] ?? '')) ?>" placeholder="python -m jobs.x --date {ds}" class="mono">
          <input name="tr[]" type="number" min="0" max="10" value="<?= (int) ($t['retries'] ?? 0) ?>">
          <input name="tdl[]" type="number" min="0" max="86400" value="<?= (int) ($t['retry_delay'] ?? 300) ?>">
          <input name="tt[]" type="number" min="0" max="86400" value="<?= (int) ($t['timeout'] ?? 0) ?>">
          <label class="check">
            <input type="hidden" name="tem[]" value="0">
            <input type="checkbox" name="tem[]" value="1" <?= !empty($t['email_on_failure']) ? 'checked' : '' ?>>
          </label>
          <button class="btn btn-sm" type="button" onclick="this.closest('.tg-row').remove()" title="remove">✕</button>
        </div>
      <?php endforeach; ?>
    </div>
    <p><button class="btn" type="button" id="add-task">+ Add task</button></p>
  </section>

  <div class="toolbar">
    <button class="btn btn-primary" type="submit">Save &amp; validate</button>
    <a class="btn" href="<?= url($isEdit ? '/dags/' . rawurlencode($dag['name']) : '/dags') ?>">Cancel</a>
  </div>
</form>

<script>
document.getElementById('add-task').addEventListener('click', () => {
  const grid = document.getElementById('task-grid');
  const row = grid.querySelector('.tg-row:last-of-type').cloneNode(true);
  row.querySelectorAll('input[type=text],input:not([type])').forEach(i => i.value = '');
  row.querySelectorAll('input[type=number]').forEach(i => i.value = i.defaultValue);
  row.querySelector('input[type=checkbox]').checked = false;
  grid.appendChild(row);
});

(() => {
  const input = document.getElementById('f-schedule');
  const human = document.getElementById('schedule-human');
  const next = document.getElementById('schedule-next');
  const endpoint = <?= json_encode(url('/dags/schedule-preview')) ?>;
  let timer = null;

  const render = async () => {
    const value = input.value.trim();
    if (value === '') {
      human.textContent = 'Enter a schedule to see the next runs.';
      next.innerHTML = '';
      return;
    }
    try {
      const res = await fetch(endpoint + '?schedule=' + encodeURIComponent(value),
        {headers: {'Accept': 'application/json'}});
      const data = await res.json();
      if (!data.ok) {
        human.textContent = data.error || 'invalid schedule';
        human.classList.add('err');
        next.innerHTML = '';
        return;
      }
      human.classList.remove('err');
      human.textContent = data.human;
      next.innerHTML = (data.next || []).map(t => '<li class="mono">' + t + '</li>').join('');
    } catch (e) {
      human.textContent = 'preview unavailable';
    }
  };

  input.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(render, 350);
  });
  render();
})();
</script>
