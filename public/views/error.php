<?php /** @var int $code @var string $title @var string $message */ ?>
<div class="error-page">
  <img class="error-mark" src="<?= url('/assets/logo-white.png') ?>" alt="">
  <div class="error-code"><?= e((string) $code) ?></div>
  <h1><?= e($title) ?></h1>
  <p><?= e($message) ?></p>
  <a class="btn btn-primary" href="<?= url('/') ?>">Back to dashboard</a>
</div>
