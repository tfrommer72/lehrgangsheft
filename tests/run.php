<?php
declare(strict_types=1);
// Tests ohne Netz: php tests/run.php
// Nutzt Testseiten in tests/fixtures, die die Struktur von baden.dlrg.de nachbilden.

$tmp = sys_get_temp_dir() . '/lehrgangsheft-test-' . getmypid();
@mkdir($tmp);
file_put_contents("$tmp/config.php", '<?php $c = require ' . var_export(__DIR__ . '/../config.example.php', true) . '; $c["abruf_pause"] = 0; $c["base_url"] = "http://test"; return $c;');
putenv("LEHRGANGSHEFT_CONFIG=$tmp/config.php");
putenv("LEHRGANGSHEFT_DB=$tmp/test.sqlite");
require __DIR__ . '/../src/bootstrap.php';

$fehler = 0; $tests = 0;
function pruefe(bool $ok, string $was): void
{
    global $fehler, $tests;
    $tests++;
    if (!$ok) { $fehler++; echo "  ✗ $was\n"; } else { echo "  ✓ $was\n"; }
}

$fx = __DIR__ . '/fixtures';
$abruf = function (string $url) use ($fx): string {
    if ($url === config('quelle_liste')) return file_get_contents("$fx/liste.html");
    if (preg_match('/-(\d+)-s\/?$/', $url, $m) && is_file("$fx/detail-$m[1].html")) return file_get_contents("$fx/detail-$m[1].html");
    throw new RuntimeException("Unbekannte URL $url");
};

echo "Liste\n";
$liste = Quelle::liste(file_get_contents("$fx/liste.html"), 'https://baden.dlrg.de');
pruefe(count($liste) === 32, '32 Lehrgänge aus data-data gelesen');
$wf = array_values(array_filter($liste, fn($l) => $l['id'] === 373422))[0] ?? null;
pruefe($wf && $wf['nr'] === '27-40431' && $wf['beginn'] === '2027-01-22', 'Wachführer: Nummer und Beginn');
pruefe($wf && !str_contains($wf['detail_url'], '#'), 'Detail-URL ohne #seminarDetail');

echo "Detailseite\n";
$d = Quelle::detail(file_get_contents("$fx/detail-373422.html"));
pruefe($d['titel'] === 'Wachführer' && $d['nr'] === '27-40431', 'Titel und Nr. aus Überschrift');
pruefe(count($d['voraussetzungen']) >= 8, 'Voraussetzungen als Liste');
pruefe($d['voraussetzungen_bis'] === '01.01.2027', 'Frist der Voraussetzungen');
pruefe(count($d['termine']) === 4 && !str_contains(implode(' ', $d['termine']), 'insgesamt'), '4 Termine, ohne Zählzeile und Adressen');
pruefe($d['tn_min'] === '5' && $d['tn_max'] === '16', 'Teilnehmerzahl min/max');
pruefe(str_contains($d['anmeldung_url'], 'page=anmeldung'), 'Anmelde-Link erkannt');
pruefe(count($d['gebuehren']) === 2, 'Gebühren');
$voll = Quelle::detail(file_get_contents("$fx/detail-369107.html"));
pruefe($voll['anmeldung_url'] === '', 'Ausgebucht: kein Anmelde-Link');

echo "Synchronisation\n";
$r = Sync::lauf(null, false, $abruf);
pruefe($r['ok'] && $r['anzahl'] === 32 && $r['neu'] === 32, 'Erster Lauf: 32 neu');
$pdo = Db::pdo();
pruefe((int)$pdo->query('SELECT COUNT(*) FROM lehrgaenge WHERE freigegeben=1')->fetchColumn() === 0, 'Neue Lehrgänge sind NICHT freigegeben');
pruefe(count(Heft::daten()['courses']) === 0, 'Heft ist leer, solange nichts freigegeben ist');

$pdo->exec('UPDATE lehrgaenge SET freigegeben=1, neu=0 WHERE id IN (373422, 369107, 382890, 317740)');
$h = Heft::daten();
pruefe(count($h['courses']) === 4, 'Nur freigegebene im Heft');
pruefe($h['courses'][373422]['qr_direct'] === true, 'QR führt direkt zur Anmeldung, wenn offen');
pruefe($h['courses'][369107]['qr_direct'] === false, 'Ausgebucht: QR zur Lehrgangsseite');
pruefe($h['courses'][317740]['offen'] === false, 'Meldeschluss erreicht → Anhang');
$bereiche = array_column($h['cats'], 0);
pruefe($bereiche === ['Grundausbildung', 'Wasserrettungsdienst', 'Tagungen & Gremien'], 'Bereiche in Konfig-Reihenfolge: ' . implode(', ', $bereiche));

// Zweiter Lauf: Freigaben bleiben, ein Lehrgang verschwindet von der Seite, ein Status ändert sich
$listeHtml = file_get_contents("$fx/liste.html");
$abruf2 = function (string $url) use ($abruf, $listeHtml): string {
    if ($url === config('quelle_liste')) {
        $doc = new DOMDocument(); @$doc->loadHTML('<?xml encoding="UTF-8">' . $listeHtml);
        $t = $doc->getElementsByTagName('table')->item(0);
        $rows = json_decode($t->getAttribute('data-data'), true);
        $rows = array_values(array_filter($rows, fn($r) => $r['id'] !== '382890'));
        foreach ($rows as &$r) if ($r['id'] === '373422') $r['anmeldungStatusAsText'] = 'Warteliste verfügbar';
        $t->setAttribute('data-data', json_encode($rows, JSON_UNESCAPED_UNICODE));
        return $doc->saveHTML();
    }
    return $abruf($url);
};
$r2 = Sync::lauf(null, false, $abruf2);
pruefe($r2['ok'] && $r2['neu'] === 0, 'Zweiter Lauf: nichts neu');
$h2 = Heft::daten();
pruefe(!isset($h2['courses'][382890]), 'Von der Seite entfernter Lehrgang ist ausgeblendet');
pruefe($h2['courses'][373422]['status'] === 'Warteliste verfügbar', 'Status aktualisiert');
pruefe((int)$pdo->query('SELECT freigegeben FROM lehrgaenge WHERE id=373422')->fetchColumn() === 1, 'Freigabe bleibt erhalten');

$r3 = Sync::lauf(null, false, fn($u) => '<html><body>Wartung</body></html>');
pruefe(!$r3['ok'] && count(Heft::daten()['courses']) === 3, 'Kaputte Quellseite: Fehler, Heft bleibt unverändert');

echo "Bereichsvorschlag\n";
pruefe(Heft::bereichVorschlag('27-40811') === 'Strömungsrettung & Hochwasser', '408xx → Strömungsrettung');
pruefe(Heft::bereichVorschlag('LV-Rat 2-26', 'LV Rat 2 2026') === 'Tagungen & Gremien', 'LV-Rat → Tagungen');

echo "\n$tests Prüfungen, $fehler fehlgeschlagen.\n";
exit($fehler ? 1 : 0);
