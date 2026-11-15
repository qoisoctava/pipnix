<?php /** @var string $title @var ?array $flash @var string $content */ ?>
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
<?php if (!empty($flash)): ?>
  <div class="flash floating <?= e($flash['kind']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>
<?= $content ?>
</body>
</html>
