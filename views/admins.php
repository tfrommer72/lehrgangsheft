<?php /** @var array $liste @var ?string $hinweis @var ?string $fehler @var string $csrf @var string $admin @var ?int $ich */ ?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<title>Admins · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/admin.css')) ?>">
</head>
<body>
<?php $aktiv = 'admins'; require APP_ROOT . '/views/_kopf.php'; ?>
<main>
  <?php if ($hinweis): ?><p class="hinweis" role="status"><?= e($hinweis) ?></p><?php endif; ?>
  <?php if ($fehler): ?><p class="hinweis fehlerbox" role="alert"><?= e($fehler) ?></p><?php endif; ?>

  <section class="block">
    <h2>Admin hinzufügen</h2>
    <p class="grau">Die Person bekommt eine E-Mail mit einem Link und legt ihr Passwort selbst fest. Der Link gilt 48 Stunden.</p>
    <form method="post" action="/admin/admins/anlegen" class="zeile">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div><label for="name">Name</label><input id="name" name="name" required maxlength="80" autocomplete="off"></div>
      <div><label for="email">E-Mail-Adresse</label><input id="email" name="email" type="email" required autocomplete="off"></div>
      <button type="submit" class="primaer">Einladen</button>
    </form>
  </section>

  <div class="tabelle">
  <table>
    <thead><tr><th>Name</th><th>E-Mail</th><th>Status</th><th>Zuletzt angemeldet</th><th></th></tr></thead>
    <tbody>
    <?php if (!$liste): ?><tr><td colspan="5" class="grau">Noch keine Admins angelegt. Du bist mit dem Notfallzugang aus config.php angemeldet – lege dich am besten zuerst selbst mit deiner E-Mail-Adresse an.</td></tr><?php endif; ?>
    <?php foreach ($liste as $b): $id = (int)$b['id']; ?>
      <tr>
        <td><b><?= e($b['name']) ?></b><?= $id === $ich ? ' <span class="marke">du</span>' : '' ?></td>
        <td><?= e($b['email']) ?></td>
        <td><small><?= (int)$b['hat_passwort'] ? 'aktiv' : 'Einladung offen' ?></small></td>
        <td class="zahlen"><small><?= $b['letzter_login'] ? e(date('d.m.Y H:i', strtotime($b['letzter_login']))) : '–' ?></small></td>
        <td class="aktionen">
          <form method="post" action="/admin/admins/<?= $id ?>/einladen"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit"><?= (int)$b['hat_passwort'] ? 'Link zum Passwort-Ändern senden' : 'Einladung erneut senden' ?></button></form>
          <?php if ($id !== $ich): ?>
          <form method="post" action="/admin/admins/<?= $id ?>/loeschen" class="loeschen"><input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <button type="submit" data-name="<?= e($b['name']) ?>">Entfernen</button></form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</main>
<script>
document.querySelectorAll('form.loeschen button').forEach(btn => {
  btn.addEventListener('click', e => {
    if (btn.dataset.sicher === '1') return;
    e.preventDefault();
    btn.dataset.sicher = '1';
    btn.textContent = btn.dataset.name + ' wirklich entfernen?';
    btn.classList.add('warnung');
    setTimeout(() => { btn.dataset.sicher = ''; btn.textContent = 'Entfernen'; btn.classList.remove('warnung'); }, 5000);
  });
});
</script>
</body>
</html>
