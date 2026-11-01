<?php
/** @var array $zeilen @var array $log @var ?string $hinweis @var bool $laeuft @var string $csrf @var string $admin */
$imListe = array_filter($zeilen, fn($z) => (int)$z['in_liste'] === 1);
$entfallen = array_filter($zeilen, fn($z) => (int)$z['in_liste'] === 0);
$neu = count(array_filter($imListe, fn($z) => (int)$z['neu'] === 1));
$frei = count(array_filter($imListe, fn($z) => (int)$z['freigegeben'] === 1));
$letzter = $log[0] ?? null;
$bereiche = config('bereiche');
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex">
<?php if ($laeuft): ?><meta http-equiv="refresh" content="10"><?php endif; ?>
<title>Verwaltung · Lehrgangsheft</title>
<link rel="stylesheet" href="/assets/admin.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/admin.css')) ?>">
</head>
<body>
<?php $aktiv = 'lehrgaenge'; require APP_ROOT . '/views/_kopf.php'; ?>

<main>
  <?php if ($hinweis): ?><p class="hinweis" role="status"><?= e($hinweis) ?></p><?php endif; ?>

  <section class="lage">
    <div class="zahl"><b><?= count($imListe) ?></b><span>auf baden.dlrg.de</span></div>
    <div class="zahl"><b><?= $frei ?></b><span>im Heft freigegeben</span></div>
    <div class="zahl <?= $neu ? 'achtung' : '' ?>"><b><?= $neu ?></b><span>neu, warten auf Entscheidung</span></div>
    <div class="sync">
      <form method="post" action="/admin/aktualisieren">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <button type="submit" class="primaer" <?= $laeuft ? 'disabled' : '' ?>><?= $laeuft ? 'Aktualisierung läuft …' : 'Jetzt aktualisieren' ?></button>
      </form>
      <p>Holt Liste und Detailseiten sofort neu von baden.dlrg.de – z. B. nach einer Korrektur in der Ausschreibung.
        <?php if ($letzter): ?><br>Letzter Lauf: <?= e(date('d.m.Y H:i', strtotime($letzter['start']))) ?> –
          <?= $letzter['ende'] === null ? 'läuft' : ((int)$letzter['ok'] ? 'erfolgreich' : 'fehlgeschlagen') ?><?php endif; ?></p>
    </div>
  </section>

  <form method="post" action="/admin/speichern" id="auswahl">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="werkzeuge">
      <div class="filter" role="group" aria-label="Filter">
        <button type="button" data-filter="alle" class="an">Alle</button>
        <button type="button" data-filter="neu">Neu</button>
        <button type="button" data-filter="frei">Im Heft</button>
        <button type="button" data-filter="aus">Nicht im Heft</button>
      </div>
      <button type="button" id="alleNeuen">Alle neuen anhaken</button>
      <button type="button" id="alleOffenen" title="Alle Lehrgänge, bei denen der Meldeschluss noch nicht erreicht ist">Alle offenen anhaken</button>
      <button type="submit" class="primaer">Auswahl speichern</button>
    </div>

    <div class="tabelle">
    <table>
      <thead><tr><th>Im Heft</th><th>Datum</th><th>Lehrgang</th><th>Status</th><th>Bereich</th><th>Links</th></tr></thead>
      <tbody>
      <?php foreach ($imListe as $z):
        $id = (int)$z['id']; $vorschlag = Heft::bereichVorschlag($z['nr'], $z['titel']); ?>
        <tr data-neu="<?= (int)$z['neu'] ?>" data-frei="<?= (int)$z['freigegeben'] ?>" data-offen="<?= Heft::istOffen($z['status']) ? 1 : 0 ?>">
          <td class="mitte">
            <input type="hidden" name="ids[]" value="<?= $id ?>">
            <input type="checkbox" id="f<?= $id ?>" name="frei[<?= $id ?>]" value="1" <?= (int)$z['freigegeben'] ? 'checked' : '' ?> aria-label="<?= e($z['titel']) ?> im Heft zeigen">
          </td>
          <td class="zahlen"><?= e(datum_de($z['beginn'])) ?><?php if ($z['ende'] && $z['ende'] !== $z['beginn']): ?><br><small>bis <?= e(datum_de($z['ende'])) ?></small><?php endif; ?></td>
          <td><label for="f<?= $id ?>"><b><?= e($z['titel']) ?></b></label><?php if ((int)$z['neu']): ?> <span class="marke">neu</span><?php endif; ?>
            <br><small><?= e($z['nr']) ?> · <?= e($z['ort']) ?> · Meldeschluss <?= e(datum_de($z['meldeschluss'])) ?></small></td>
          <td><small><?= e($z['status']) ?></small><?php if (!Heft::istOffen($z['status'])): ?><br><small class="grau">steht im Anhang</small><?php endif; ?></td>
          <td>
            <select name="bereich[<?= $id ?>]" aria-label="Bereich">
              <option value="">Automatisch (<?= e($vorschlag) ?>)</option>
              <?php foreach ($bereiche as $b): ?><option <?= $z['bereich'] === $b ? 'selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
            </select>
          </td>
          <td class="links"><a href="<?= e($z['detail_url']) ?>" target="_blank" rel="noopener">Ausschreibung</a>
            <?php if ((int)$z['freigegeben'] && Heft::istOffen($z['status'])): ?><a href="/kurs/<?= $id ?>.pdf" target="_blank" rel="noopener">PDF</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <p class="fuss"><button type="submit" class="primaer">Auswahl speichern</button> Neue Lehrgänge erscheinen erst nach dem Anhaken und Speichern im Heft.</p>
  </form>

  <?php if ($entfallen): ?>
  <details class="block">
    <summary><?= count($entfallen) ?> Lehrgänge nicht mehr auf baden.dlrg.de (automatisch ausgeblendet)</summary>
    <ul><?php foreach ($entfallen as $z): ?><li><?= e(datum_de($z['beginn'])) ?> · <?= e($z['titel']) ?> (<?= e($z['nr']) ?>)</li><?php endforeach; ?></ul>
  </details>
  <?php endif; ?>

  <details class="block">
    <summary>Protokoll der letzten Aktualisierungen</summary>
    <table class="log"><tbody>
      <?php foreach ($log as $l): ?>
        <tr><td><?= e(date('d.m.Y H:i', strtotime($l['start']))) ?></td>
          <td><?= $l['ende'] === null ? 'läuft' : ((int)$l['ok'] ? 'ok' : 'Fehler') ?></td>
          <td><?= (int)$l['anzahl'] ?> Lehrgänge, <?= (int)$l['neu'] ?> neu</td>
          <td><small><?= e($l['meldung']) ?></small></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </details>
</main>
<script>
(function () {
  const rows = [...document.querySelectorAll('tbody tr[data-neu]')];
  document.querySelectorAll('[data-filter]').forEach(b => b.addEventListener('click', () => {
    document.querySelectorAll('[data-filter]').forEach(x => x.classList.toggle('an', x === b));
    const f = b.dataset.filter;
    rows.forEach(r => {
      const cb = r.querySelector('input[type=checkbox]');
      r.hidden = !(f === 'alle' || (f === 'neu' && r.dataset.neu === '1') || (f === 'frei' && cb.checked) || (f === 'aus' && !cb.checked));
    });
  }));
  document.getElementById('alleNeuen').addEventListener('click', () => {
    rows.filter(r => r.dataset.neu === '1').forEach(r => r.querySelector('input[type=checkbox]').checked = true);
  });
  document.getElementById('alleOffenen').addEventListener('click', () => {
    rows.filter(r => r.dataset.offen === '1').forEach(r => r.querySelector('input[type=checkbox]').checked = true);
    geaendert = true;
  });
  let geaendert = false;
  document.getElementById('auswahl').addEventListener('change', () => geaendert = true);
  document.getElementById('auswahl').addEventListener('submit', () => geaendert = false);
  window.addEventListener('beforeunload', e => { if (geaendert) { e.preventDefault(); e.returnValue = ''; } });
})();
</script>
</body>
</html>
