<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Passwort vergessen · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/admin.css')) ?>">
</head>
<body class="login">
<form method="post" action="/admin/passwort-vergessen" class="karte">
  <div class="bb"><span>LV Baden</span><img src="/assets/wortmarke.svg" alt="DLRG"></div>
  <h1>Passwort vergessen</h1>
  <p class="text">Gib die E-Mail-Adresse deines Zugangs ein. Du bekommst einen Link, mit dem du ein neues Passwort festlegst.</p>
  <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
  <label for="email">E-Mail-Adresse</label>
  <input id="email" name="email" type="email" autocomplete="username" required autofocus>
  <button type="submit">Link anfordern</button>
  <a class="klein" href="/admin/login">Zurück zur Anmeldung</a>
</form>
</body>
</html>
