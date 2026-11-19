<?php /** @var string $head @var string $html @var string $slug */ ?>
<header class="page-head">
  <div>
    <h1><?= e($head) ?></h1>
    <div class="chips">
      <a class="chip <?= $slug === 'guide' ? 'on' : '' ?>" href="<?= url('/docs') ?>">Getting started</a>
      <a class="chip <?= $slug === 'cpanel' ? 'on' : '' ?>" href="<?= url('/docs/cpanel') ?>">cPanel</a>
      <a class="chip <?= $slug === 'dags' ? 'on' : '' ?>" href="<?= url('/docs/dags') ?>">DAGs</a>
      <a class="chip <?= $slug === 'docker' ? 'on' : '' ?>" href="<?= url('/docs/docker') ?>">Running with Docker</a>
      <a class="chip <?= $slug === 'updates' ? 'on' : '' ?>" href="<?= url('/docs/updates') ?>">Updating</a>
    </div>
  </div>
</header>

<article class="card doc">
  <?= $html ?>
</article>
