<?php /** @var string $token @var bool $gueltig @var string $name @var ?string $fehler */ ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Passwort festlegen · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/admin.css')) ?>">
</head>
<body class="login">
<?php if (!$gueltig): ?>
<div class="karte">
  <div class="bb"><span>LV Baden</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <h1>Link nicht mehr gültig</h1>
  <p class="text">Dieser Link ist abgelaufen oder wurde schon benutzt. Fordere einfach einen neuen an.</p>
  <a class="knopf" href="/admin/passwort-vergessen">Neuen Link anfordern</a>
</div>
<?php else: ?>
<form method="post" action="/admin/passwort-setzen" class="karte">
  <div class="bb"><span>LV Baden</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <h1>Passwort festlegen</h1>
  <p class="text">Hallo <?= e($name) ?>, lege dein Passwort fest – mindestens <?= Benutzer::MIN_LAENGE ?> Zeichen.</p>
  <?php if ($fehler): ?><p class="fehler" role="alert"><?= e($fehler) ?></p><?php endif; ?>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label for="pw1">Neues Passwort</label>
  <input id="pw1" name="pw1" type="password" autocomplete="new-password" minlength="<?= Benutzer::MIN_LAENGE ?>" required autofocus>
  <label for="pw2">Passwort wiederholen</label>
  <input id="pw2" name="pw2" type="password" autocomplete="new-password" minlength="<?= Benutzer::MIN_LAENGE ?>" required>
  <button type="submit">Passwort speichern</button>
</form>
<?php endif; ?>
</body>
</html>
