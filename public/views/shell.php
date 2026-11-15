<?php
/** @var array $user @var string $title @var string $content @var string $nav */
$nav = $nav ?? '';
$flash = take_flash();
$items = [
    ['home',   '/',        '⌂', 'Home'],
    ['dags',   '/dags',    '⬡', 'Dags'],
    ['runs',   '/runs',    '▶', 'Runs'],
    ['logs',   '/logs',    '☰', 'Logs'],
    ['python', '/python',  '>_', 'Python'],
    ['admin',  '/admin',   '⚙', 'Admin'],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · pipnix</title>
<link rel="icon" href="<?= url('/assets/app-icon-32.png') ?>">
<link rel="stylesheet" href="<?= url('/assets/app.css') ?>">
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <a class="logo" href="<?= url('/') ?>" title="pipnix">
      <img src="<?= url('/assets/logo-white.png') ?>" alt="pipnix">
    </a>
    <nav class="nav">
      <?php foreach ($items as [$key, $href, $glyph, $label]): ?>
        <a class="nav-item <?= $nav === $key ? 'active' : '' ?>" href="<?= url($href) ?>">
          <span class="glyph"><?= e($glyph) ?></span>
          <span class="label"><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-bottom">
      <a class="nav-item" href="https://github.com" target="_blank" rel="noreferrer">
        <span class="glyph">❑</span><span class="label">Docs</span>
      </a>
      <form method="post" action="<?= url('/logout') ?>">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <button class="nav-item as-button" type="submit">
          <span class="glyph">◯</span>
          <span class="label"><?= e($user['username'] ?? 'User') ?></span>
        </button>
      </form>
    </div>
  </aside>

  <main class="content">
    <?php if ($flash): ?>
      <div class="flash <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>
    <?= $content ?>
  </main>
</div>
</body>
</html>
