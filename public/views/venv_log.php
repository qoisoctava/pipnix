<?php
/** @var string $name @var array $lines */
$log = venv_log_path($name);
$fresh = is_file($log) && (time() - (int) filemtime($log)) < 120;
?>
<?php if ($fresh): ?><meta http-equiv="refresh" content="3"><?php endif; ?>
<header class="page-head">
  <div>
    <p class="muted small"><a class="link" href="<?= url('/python/' . rawurlencode($name)) ?>">← <?= e($name) ?></a></p>
    <h1>pip log
      <?php if ($fresh): ?><span class="badge run">running</span><?php endif; ?>
    </h1>
    <p class="muted small mono"><?= e(basename($log)) ?></p>
  </div>
</header>

<section class="card log-card">
  <?php if (!$lines): ?>
    <p class="muted">Nothing here yet — trigger an install or check first.</p>
  <?php else: ?>
    <pre class="log">
<?php foreach ($lines as $line):
    $cls = '';
    if (str_contains($line, 'ERROR') || str_contains($line, 'error:')) {
        $cls = 'err';
    } elseif (str_contains($line, 'WARNING')) {
        $cls = 'warn';
    }
    echo '<span class="' . $cls . '">' . e($line) . "</span>\n";
endforeach; ?>
    </pre>
  <?php endif; ?>
</section>
