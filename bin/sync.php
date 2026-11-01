<?php
declare(strict_types=1);
// Synchronisation mit baden.dlrg.de – täglich per Cron oder über „Jetzt aktualisieren“ im Admin-Bereich.
//   php bin/sync.php                  normaler Lauf (neue Lehrgänge warten auf Freigabe)
//   php bin/sync.php --alle-freigeben Erstbefüllung: alle gefundenen Lehrgänge sofort freigeben

require __DIR__ . '/../src/bootstrap.php';

$sperre = fopen(Hintergrund::sperrdatei(), 'c');
if (!flock($sperre, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Es läuft bereits eine Synchronisation.\n");
    exit(1);
}

$alle = in_array('--alle-freigeben', $argv, true);
$ergebnis = Sync::lauf(fn(string $s) => print(date('H:i:s') . " $s\n"), $alle);

// Heft-PDF gleich vorbereiten, damit der erste Abruf schnell ist
if ($ergebnis['ok']) {
    try {
        Pdf::datei();
        echo date('H:i:s') . " Heft-PDF aktuell.\n";
    } catch (Throwable $e) {
        echo date('H:i:s') . ' PDF-Hinweis: ' . $e->getMessage() . "\n";
    }
}

flock($sperre, LOCK_UN);
exit($ergebnis['ok'] ? 0 : 2);
