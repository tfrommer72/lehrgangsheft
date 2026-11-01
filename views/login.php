<?php /** @var ?string $fehler */ ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Anmeldung · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css">
</head>
<body class="login">
<form method="post" action="/admin/login" class="karte">
  <div class="bb"><span>LV Baden</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <h1>Lehrgangsheft verwalten</h1>
  <?php if ($fehler): ?><p class="fehler"><?= e($fehler) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <label for="name">Benutzername</label>
  <input id="name" name="name" autocomplete="username" required autofocus>
  <label for="passwort">Passwort</label>
  <input id="passwort" name="passwort" type="password" autocomplete="current-password" required>
  <button type="submit">Anmelden</button>
</form>
</body>
</html>
