<?php /** @var string $head @var string $html @var string $slug */ ?>
<header class="page-head">
  <div>
    <h1><?= e($head) ?></h1>
    <div class="chips">
      <a class="chip <?= $slug === '' ? 'on' : '' ?>" href="<?= url('/docs') ?>">Deploy to cPanel</a>
      <a class="chip <?= $slug === 'dags' ? 'on' : '' ?>" href="<?= url('/docs/dags') ?>">Writing DAGs</a>
      <a class="chip <?= $slug === 'docker' ? 'on' : '' ?>" href="<?= url('/docs/docker') ?>">Running with Docker</a>
    </div>
  </div>
</header>

<article class="card doc">
  <?= $html ?>
</article>
