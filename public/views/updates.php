<?php
/**
 * Admin → Software updates.
 * @var array $state @var ?array $check @var bool $running @var ?array $backup
 * @var array $log @var ?string $reason @var array $config
 */
$short = static fn (?string $sha): string => $sha ? substr($sha, 0, 7) : 'unknown';
$remote = is_array($check['remote'] ?? null) ? $check['remote'] : null;
$available = !empty($check['update']);
$currentVersion = (string) ($check['current']['version'] ?? $state['version'] ?? PN_VERSION);
$currentCommit = $check['current']['commit'] ?? $state['commit'] ?? Updater::localCommit();
$blocked = $reason !== null;
?>
<?php if ($running): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
<header class="page-head">
  <div>
    <h1>Software updates</h1>
    <p class="muted small">
      Source archives from <span class="mono"><?= e((string) $config['repo']) ?></span>
      · channel <span class="mono"><?= e((string) $config['channel']) ?></span>
      <?php if ($config['channel'] === 'branch'): ?> (<span class="mono"><?= e((string) $config['branch']) ?></span>)<?php endif; ?>
    </p>
  </div>
  <div class="toolbar">
    <a class="btn" href="<?= url('/admin') ?>">← Admin</a>
    <a class="btn" href="<?= url('/admin/settings') ?>">Settings</a>
  </div>
</header>

<?php if ($running): ?>
  <div class="card" style="border-color:#4A3A12;margin-bottom:16px">
    <div class="card-head"><h3>Update in progress…</h3><span class="muted small">refreshing every 5s</span></div>
    <p class="muted">Files are being replaced and migrations run. Keep this tab open; the log below is live.</p>
  </div>
<?php endif; ?>

<?php if ($blocked): ?>
  <div class="card" style="border-color:#4A3A12;margin-bottom:16px">
    <div class="card-head"><h3>Self-update unavailable</h3></div>
    <p class="muted" style="margin:0"><?= e($reason) ?></p>
  </div>
<?php endif; ?>

<div class="dash-main">
  <div class="stack">
    <section class="card">
      <div class="card-head"><h3>Installed</h3><span class="muted small">this installation</span></div>
      <div class="kv-row">
        <div><span class="label">Version</span><span class="mono"><?= e($currentVersion) ?></span></div>
        <div><span class="label">Commit</span><span class="mono"><?= e($short(is_string($currentCommit) ? $currentCommit : null)) ?></span></div>
        <div><span class="label">Applied</span><span class="mono"><?= e((string) ($state['applied_at'] ?? '—')) ?></span></div>
        <div><span class="label">Channel</span><span class="mono"><?= e((string) ($state['channel'] ?? $config['channel'])) ?></span></div>
      </div>
    </section>

    <section class="card">
      <div class="card-head">
        <h3>Latest on GitHub</h3>
        <span class="muted small">
          <?php if ($check): ?>checked <?= e(date('Y-m-d H:i', (int) ($check['checked_at'] ?? time()))) ?><?= !empty($check['cached']) ? ' (cached)' : '' ?><?php else: ?>not checked yet<?php endif; ?>
        </span>
      </div>
      <?php if (!$check): ?>
        <p class="muted">Run a check to compare this installation with GitHub.</p>
      <?php elseif (empty($check['ok'])): ?>
        <p class="muted"><?= e((string) ($check['error'] ?? 'check failed')) ?></p>
      <?php elseif ($remote): ?>
        <div class="kv-row">
          <div><span class="label">Remote</span><span class="mono"><?= e((string) $remote['version']) ?></span></div>
          <div><span class="label">Commit</span><span class="mono"><?= e($short($remote['commit'] ?? null)) ?></span></div>
          <div><span class="label">Date</span><span class="mono"><?= e((string) ($remote['date'] ?? '—')) ?></span></div>
          <div><span class="label">Status</span>
            <?php if ($available): ?><span class="badge warn">update available</span>
            <?php else: ?><span class="badge ok">up to date</span><?php endif; ?>
          </div>
        </div>
        <?php if (($check['channel'] ?? '') === 'branch' && !empty($check['relation'])
                  && $check['relation'] !== 'identical'): ?>
          <p class="muted small" style="margin-top:10px">
            <?php if ($check['relation'] === 'local-only'): ?>
              This checkout has commits that are not on GitHub — no update is offered (prevents a downgrade).
            <?php elseif ($check['relation'] === 'ahead'): ?>
              Local checkout is ahead of GitHub; nothing to pull.
            <?php elseif ($check['relation'] === 'diverged'): ?>
              Local and GitHub histories have diverged — review the commit graph before updating.
            <?php else: ?>
              Comparison: <?= e((string) $check['relation']) ?>.
            <?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if (!empty($remote['title'])): ?>
          <p class="muted" style="margin-top:12px"><?= e((string) $remote['title']) ?></p>
        <?php endif; ?>
        <?php if (!empty($remote['notes'])): ?>
          <pre class="resolved" style="margin-top:10px;max-height:240px;overflow:auto"><?= e(mb_substr((string) $remote['notes'], 0, 4000)) ?></pre>
        <?php endif; ?>
        <?php if (!empty($remote['url'])): ?>
          <p class="muted small" style="margin-top:8px"><a class="link" href="<?= e((string) $remote['url']) ?>" target="_blank" rel="noopener">Open on GitHub ↗</a></p>
        <?php endif; ?>
      <?php endif; ?>

      <div class="toolbar" style="margin-top:14px">
        <form class="inline" method="post" action="<?= url('/admin/updates/check') ?>">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn" type="submit" <?= $blocked || $running ? 'disabled' : '' ?>>↻ Check now</button>
        </form>
        <?php if ($available && !$blocked && !$running): ?>
          <form class="inline" method="post" action="<?= url('/admin/updates/apply') ?>"
                onsubmit="return confirm('Download and apply <?= e((string) $remote['version']) ?>? Files are backed up first, then migrations run.')">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
            <button class="btn btn-primary" type="submit">⇩ Update now</button>
          </form>
        <?php endif; ?>
      </div>
    </section>

    <section class="card">
      <div class="card-head"><h3>Upload an archive</h3><span class="muted small">works without outbound access</span></div>
      <p class="muted small">A GitHub release zip (or the <span class="mono">make dist</span> zip) is applied the same way: protected paths are skipped, files are backed up, migrations run.</p>
      <form method="post" action="<?= url('/admin/updates/upload') ?>" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <div>
          <label for="u-file">Archive (.zip / .tar.gz)</label>
          <input id="u-file" name="archive" type="file" accept=".zip,.tar,.gz,.tgz" required>
        </div>
        <div>
          <label>&nbsp;</label>
          <button class="btn" type="submit" <?= $running ? 'disabled' : '' ?>>Upload &amp; apply</button>
        </div>
      </form>
    </section>
  </div>

  <aside class="stack">
    <section class="card">
      <div class="card-head"><h3>Rollback</h3><span class="muted small">files only</span></div>
      <?php if ($backup): ?>
        <p class="muted small">
          Newest backup: <span class="mono"><?= e((string) ($backup['dir'] ?? 'backup')) ?></span><br>
          <?= e((string) ($backup['from']['version'] ?? '?')) ?> → <?= e((string) ($backup['to']['version'] ?? '?')) ?>
          · <?= (int) ($backup['copied'] ?? 0) ?> files
        </p>
        <form method="post" action="<?= url('/admin/updates/rollback') ?>"
              onsubmit="return confirm('Restore the files from the newest backup? Database migrations are NOT reverted.')">
          <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
          <button class="btn btn-danger" type="submit" <?= $running ? 'disabled' : '' ?>>⟲ Roll back files</button>
        </form>
      <?php else: ?>
        <p class="muted">No backup yet — one is created before the first update.</p>
      <?php endif; ?>
      <p class="hint">Restores overwritten PHP/assets. Rows, tables and files added by the newer version stay; rerun an update to move forward again.</p>
    </section>

    <section class="card">
      <div class="card-head"><h3>Safety</h3></div>
      <ul class="muted small" style="margin:0;padding-left:18px;line-height:1.8">
        <li>Never touches <span class="mono">app/config.php</span>, <span class="mono">.env</span>, <span class="mono">dags/</span>, <span class="mono">jobs/</span>, logs or storage.</li>
        <li>Each file is replaced through a temp file + rename.</li>
        <li>Migrations use the idempotent <span class="mono">install.php</span>.</li>
        <li>HTTPS only; tokens never reach the browser.</li>
      </ul>
    </section>
  </aside>
</div>

<section class="card log-card">
  <div class="card-head"><h3>Update log</h3><span class="muted small"><?= count($log) ?> lines</span></div>
  <?php if (!$log): ?>
    <p class="muted">No update has run yet.</p>
  <?php else: ?>
    <pre class="log"><?php foreach ($log as $line): ?><span class="<?= str_contains($line, 'failed') ? 'err' : '' ?>"><?= e($line) ?>
</span><?php endforeach; ?></pre>
  <?php endif; ?>
</section>
