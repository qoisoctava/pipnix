<?php
/** @var array $venvs @var ?array $selected @var array $packages */
$statusClass = static fn (string $s): string => match ($s) {
    'healthy' => 'ok', 'outdated' => 'warn', 'error', 'missing' => 'bad', default => 'idle',
};
?>
<header class="page-head">
  <h1>Python Environments</h1>
  <div class="toolbar">
    <?php if ($selected): ?>
      <a class="btn" href="<?= url('/python') ?>">All environments</a>
    <?php endif; ?>
    <?php if (can('venv') && is_file(venv_log_path($selected['name'] ?? ''))): ?>
      <a class="btn" href="<?= url('/python/' . rawurlencode($selected['name']) . '/log') ?>">pip log</a>
    <?php endif; ?>
  </div>
</header>

<?php if (!$venvs): ?>
  <div class="card empty">
    <p>No environments registered yet. Add one with:</p>
    <pre class="mono small">php app/install.php --venv=reporting:/home/USER/venvs/reporting</pre>
  </div>
<?php elseif (!$selected): ?>
  <?php if (can('venv')): ?>
  <section class="card">
    <div class="card-head"><h3>Register environment</h3>
      <span class="muted small">e.g. cPanel → Setup Python App</span></div>
    <form method="post" action="<?= url('/python/new') ?>" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div>
        <label for="v-name">Name</label>
        <input id="v-name" name="name" placeholder="reporting" pattern="[a-z0-9][a-z0-9_-]{1,31}" required>
      </div>
      <div>
        <label for="v-path">Path</label>
        <input id="v-path" name="path" class="mono" placeholder="/home/USER/venvs/reporting" required>
      </div>
      <div>
        <label>Default</label>
        <label class="check"><input type="checkbox" name="default" value="1"> make default</label>
      </div>
      <div>
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit">Register</button>
      </div>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h3>Create environment</h3>
      <span class="muted small">runs <span class="mono">python3 -m venv</span> on the server</span></div>
    <form method="post" action="<?= url('/python/create') ?>" class="form-grid">
      <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
      <div>
        <label for="c-name">Name</label>
        <input id="c-name" name="name" placeholder="reporting" pattern="[a-z0-9][a-z0-9_-]{1,31}" required>
      </div>
      <div>
        <label for="c-path">Directory</label>
        <input id="c-path" name="path" class="mono" placeholder="/home/USER/venvs/reporting" required>
      </div>
      <div>
        <label for="c-python">Interpreter</label>
        <input id="c-python" name="python" class="mono" value="<?= e((string) Env::cfg('python_bin', 'python3')) ?>">
        <p class="hint">binary used to create the venv</p>
      </div>
      <div>
        <label>&nbsp;</label>
        <button class="btn btn-primary" type="submit">Create</button>
      </div>
    </form>
  </section>
  <?php endif; ?>
  <div class="stack">
    <?php foreach ($venvs as $v): ?>
      <section class="card">
        <div class="row-between">
          <div class="dag-title">
            <a class="link mono" href="<?= url('/python/' . rawurlencode($v['name'])) ?>"><?= e($v['name']) ?></a>
            <?php if ((int) $v['is_default'] === 1): ?><span class="badge primary">default</span><?php endif; ?>
            <span class="badge <?= $statusClass($v['status']) ?>"><?= e(state_label($v['status'])) ?></span>
          </div>
          <div class="toolbar">
            <span class="muted small"><?= (int) $v['packages'] ?> packages · <?= (int) $v['outdated'] ?> outdated</span>
            <?php if (can('venv')): ?>
              <form class="inline" method="post" action="<?= url('/python/' . rawurlencode($v['name']) . '/delete') ?>"
                    onsubmit="return confirm('Unregister <?= e($v['name']) ?>? Files on disk are kept.')">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <button class="btn btn-sm" type="submit" title="Unregister (refused while DAGs use it)">⌫</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
        <div class="cols-3">
          <div><span class="label">Directory</span><span class="mono small"><?= e($v['path']) ?></span></div>
          <div><span class="label">Python</span><span class="mono small"><?= e($v['python_version'] ?? '—') ?> · pip <?= e($v['pip_version'] ?? '—') ?></span></div>
          <div><span class="label">Last check</span><span class="mono small"><?= e($v['last_checked_at'] ?? 'never') ?></span></div>
        </div>
      </section>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <section class="card">
    <div class="kv-row">
      <div><span class="label">Directory</span><span class="mono small"><?= e($selected['path']) ?></span></div>
      <div><span class="label">Python</span><span class="mono small"><?= e($selected['python_version'] ?? '—') ?></span></div>
      <div><span class="label">pip</span><span class="mono small"><?= e($selected['pip_version'] ?? '—') ?></span></div>
      <div><span class="label">Status</span><span class="badge <?= $statusClass($selected['status']) ?>"><?= e(state_label($selected['status'])) ?></span></div>
      <div><span class="label">Last check</span><span class="mono small"><?= e($selected['last_checked_at'] ?? 'never') ?></span></div>
    </div>
    <?php if (can('venv')): ?>
      <div class="toolbar" style="margin-top:14px">
        <form class="inline" method="post" action="<?= url('/python/' . rawurlencode($selected['name']) . '/check') ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn" type="submit">↻ Check now</button>
        </form>
        <form class="inline" method="post" action="<?= url('/python/' . rawurlencode($selected['name']) . '/install') ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <input name="spec" class="mono" placeholder="package==1.2.3" size="22" required>
          <button class="btn btn-primary" type="submit">Install</button>
        </form>
        <form class="inline" method="post" action="<?= url('/python/' . rawurlencode($selected['name']) . '/upgrade') ?>"
              onsubmit="return confirm('Upgrade every outdated package in <?= e($selected['name']) ?>?')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn" type="submit">↥ Upgrade all</button>
        </form>
        <form class="inline" method="post" enctype="multipart/form-data"
              action="<?= url('/python/' . rawurlencode($selected['name']) . '/requirements') ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <label class="check"><input type="file" name="requirements" accept=".txt" required> requirements.txt</label>
          <button class="btn" type="submit">Install file</button>
        </form>
        <?php if (is_file(venv_log_path($selected['name']))): ?>
          <a class="btn" href="<?= url('/python/' . rawurlencode($selected['name']) . '/log') ?>">pip log</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-head"><h3>Installed packages</h3><span class="muted small"><?= count($packages) ?> packages</span></div>
    <?php if (!$packages): ?>
      <p class="muted">No inventory yet. Run <code class="mono">php app/ops.php venv:check --name=<?= e($selected['name']) ?></code>.</p>
    <?php else: ?>
      <table class="table">
        <thead><tr><th>Package</th><th>Version</th><th>Latest</th><th></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($packages as $p): ?>
          <tr>
            <td class="mono small"><?= e($p['name']) ?></td>
            <td class="mono small"><?= e($p['version']) ?></td>
            <td class="mono small"><?= e($p['latest_version'] ?? '') ?></td>
            <td><?php if ($p['latest_version']): ?><span class="badge warn">update available</span><?php endif; ?></td>
            <td class="right">
              <?php if (can('venv') && $p['name'] !== 'pip'): ?>
                <form class="inline" method="post"
                      action="<?= url('/python/' . rawurlencode($selected['name']) . '/uninstall') ?>"
                      onsubmit="return confirm('Uninstall <?= e($p['name']) ?>?')">
                  <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                  <input type="hidden" name="pkg" value="<?= e($p['name']) ?>">
                  <button class="btn btn-sm" type="submit">Uninstall</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endif; ?>

<p class="muted small">Installs, upgrades and requirements files stream into the pip log (refreshed automatically). The same actions exist on the CLI: <code class="mono">php app/ops.php venv:install|upgrade|requirements</code>.</p>
