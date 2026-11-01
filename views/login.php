<?php /** @var ?string $fehler @var ?string $hinweis */ ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Anmeldung · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/admin.css')) ?>">
</head>
<body class="login">
<form method="post" action="/admin/login" class="karte">
  <div class="bb"><span>LV Baden</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <h1>Lehrgangsheft verwalten</h1>
  <?php if ($hinweis): ?><p class="ok" role="status"><?= e($hinweis) ?></p><?php endif; ?>
  <?php if ($fehler): ?><p class="fehler" role="alert"><?= e($fehler) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <label for="email">E-Mail-Adresse</label>
  <input id="email" name="email" type="text" inputmode="email" autocomplete="username" required autofocus>
  <label for="passwort">Passwort</label>
  <input id="passwort" name="passwort" type="password" autocomplete="current-password" required>
  <button type="submit">Anmelden</button>
  <a class="klein" href="/admin/passwort-vergessen">Passwort vergessen?</a>
</form>
</body>
</html>
