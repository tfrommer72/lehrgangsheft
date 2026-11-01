<?php
/** @var string $modus  @var int $kurs  @var array $daten */
$daten['meta']['web'] = '/';
$offen = count(array_filter($daten['courses'], fn($k) => $k['offen']));
?><!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Lehrgänge DLRG LV Baden</title>
<meta name="description" content="Seminarheft des DLRG Landesverbands Baden: alle Lehrgänge mit Terminen, Voraussetzungen und Anmeldung.">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Arimo:ital,wght@0,400;0,600;0,700;1,400&display=swap">
<link rel="stylesheet" href="/assets/heft.css?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/heft.css')) ?>">
<link rel="preload" href="/assets/univers-bold.woff2" as="font" type="font/woff2" crossorigin>
<style>@font-face{font-family:"DLRG Univers";font-weight:700;font-display:block;src:url(/assets/univers-bold.woff2) format("woff2")}
:root{padding-top:env(safe-area-inset-top,0px);padding-bottom:env(safe-area-inset-bottom,0px)}
body{margin:0;font-size:14px}</style>
</head>
<body data-modus="<?= e($modus) ?>" data-kurs="<?= (int)$kurs ?>" data-basis="/">
<div class="shell">
  <div class="bar">
    <div><h1>Seminarheft · DLRG Landesverband Baden</h1><p><?= $offen ?> <?= $offen === 1 ? 'Lehrgang' : 'Lehrgänge' ?> · Stand <?= e($daten['meta']['stand']) ?></p></div>
    <div class="nav">
      <button id="prev" type="button" aria-label="Zurückblättern">‹ Zurück</button>
      <span class="pgl" id="pgl" aria-live="polite">Titel</span>
      <button id="next" type="button" aria-label="Weiterblättern">Weiter ›</button>
      <button id="toToc" type="button">Inhalt</button>
      <label class="nur-web"><span class="visually-hidden" hidden>Zu Lehrgang springen</span><select id="sprung" aria-label="Zu Lehrgang springen"></select></label>
      <a class="btn rot" href="/heft.pdf">Heft als PDF</a>
    </div>
  </div>
  <div class="stage"><div id="book"></div></div>
  <div class="hint">Blättern mit den Knöpfen, den Pfeiltasten oder per Wischen. Einträge im Inhalt und in der Terminübersicht springen direkt zum Lehrgang.</div>
</div>
<script type="application/json" id="daten"><?= json_fuer_html($daten) ?></script>
<script src="/assets/qrcode.js"></script>
<?php if ($modus === 'blaettern'): ?><script src="/assets/page-flip.js"></script><?php endif; ?>
<script src="/assets/heft.js?v=<?= e((string)@filemtime(APP_ROOT . '/public/assets/heft.js')) ?>"></script>
</body>
</html>
