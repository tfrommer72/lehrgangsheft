<?php /** @var string $aktiv @var string $csrf @var string $admin */ ?>
<header class="kopf">
  <div class="bb"><span>LV Baden · Lehrgangsheft</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <nav>
    <a href="/admin" <?= $aktiv === 'lehrgaenge' ? 'aria-current="page"' : '' ?>>Lehrgänge</a>
    <a href="/admin/admins" <?= $aktiv === 'admins' ? 'aria-current="page"' : '' ?>>Admins</a>
    <span class="trenner"></span>
    <a href="/" target="_blank" rel="noopener">Heft ansehen</a>
    <a href="/heft.pdf" target="_blank" rel="noopener">Heft-PDF</a>
    <form method="post" action="/admin/logout"><input type="hidden" name="csrf" value="<?= e($csrf) ?>"><button class="link">Abmelden (<?= e($admin) ?>)</button></form>
  </nav>
</header>
