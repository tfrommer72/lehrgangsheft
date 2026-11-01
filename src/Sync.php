<?php
declare(strict_types=1);

/**
 * Tägliche Synchronisation mit baden.dlrg.de.
 * - Neue Lehrgänge werden angelegt, aber NICHT freigegeben (freigegeben = 0, neu = 1).
 * - Bekannte Lehrgänge bekommen aktuelle Daten; die Freigabe bleibt, wie der Admin sie gesetzt hat.
 * - Lehrgänge, die nicht mehr auf der Seite stehen, werden mit in_liste = 0 markiert und erscheinen nicht mehr im Heft.
 */
final class Sync
{
    /** @param callable(string):void|null $log */
    public static function lauf(?callable $log = null, bool $alleFreigeben = false, ?callable $abruf = null): array
    {
        $log ??= fn(string $s) => null;
        $abruf ??= [Quelle::class, 'abrufen'];
        $pdo = Db::pdo();
        $start = jetzt();
        $pdo->prepare('INSERT INTO sync_log (start) VALUES (?)')->execute([$start]);
        $logId = (int)$pdo->lastInsertId();

        $neu = 0; $anzahl = 0; $fehlerDetails = [];
        try {
            $liste = Quelle::liste($abruf(config('quelle_liste')), config('quelle_basis'));
            if (!$liste) throw new RuntimeException('Die Lehrgangsliste ist leer – Abbruch, damit nichts versehentlich ausgeblendet wird.');
            $log(count($liste) . ' Lehrgänge in der Liste gefunden.');

            $vorhanden = $pdo->query('SELECT id FROM lehrgaenge')->fetchAll(PDO::FETCH_COLUMN);
            $vorhanden = array_flip(array_map('intval', $vorhanden));
            $gesehen = [];

            foreach ($liste as $l) {
                $anzahl++;
                $gesehen[] = $l['id'];
                $detail = null;
                try {
                    if ($l['detail_url'] !== '') {
                        $detail = Quelle::detail($abruf($l['detail_url']));
                        usleep((int)(((float)config('abruf_pause')) * 1_000_000));
                    }
                } catch (Throwable $e) {
                    $fehlerDetails[] = $l['nr'] . ': ' . $e->getMessage();
                    $log('  ! Detailseite ' . $l['nr'] . ': ' . $e->getMessage());
                }

                $felder = [
                    'nr' => $l['nr'] ?: ($detail['nr'] ?? ''),
                    'titel' => $l['titel'],
                    'status' => $l['status'],
                    'ort' => $l['ort'],
                    'beginn' => $l['beginn'],
                    'ende' => $l['ende'],
                    'meldeschluss' => $l['meldeschluss'],
                    'detail_url' => $l['detail_url'],
                ];

                if (isset($vorhanden[$l['id']])) {
                    $sql = 'UPDATE lehrgaenge SET nr=:nr, titel=:titel, status=:status, ort=:ort, beginn=:beginn, ende=:ende,
                            meldeschluss=:meldeschluss, detail_url=:detail_url, in_liste=1, zuletzt=:zuletzt WHERE id=:id';
                    $pdo->prepare($sql)->execute($felder + ['zuletzt' => jetzt(), 'id' => $l['id']]);
                } else {
                    $sql = 'INSERT INTO lehrgaenge (id, nr, titel, status, ort, beginn, ende, meldeschluss, detail_url, freigegeben, neu, in_liste, erstmals, zuletzt)
                            VALUES (:id,:nr,:titel,:status,:ort,:beginn,:ende,:meldeschluss,:detail_url,:frei,1,1,:jetzt,:jetzt)';
                    $pdo->prepare($sql)->execute($felder + ['id' => $l['id'], 'frei' => $alleFreigeben ? 1 : 0, 'jetzt' => jetzt()]);
                    $neu++;
                    $log('  + neu: ' . $l['nr'] . ' ' . $l['titel']);
                }

                if ($detail !== null) {
                    $pdo->prepare('UPDATE lehrgaenge SET detail=?, detail_am=?, anmeldung_url=? WHERE id=?')
                        ->execute([json_encode($detail, JSON_UNESCAPED_UNICODE), jetzt(), $detail['anmeldung_url'] ?: null, $l['id']]);
                }
            }

            // Nicht mehr gelistete Lehrgänge ausblenden
            $platzhalter = implode(',', array_fill(0, count($gesehen), '?'));
            $pdo->prepare("UPDATE lehrgaenge SET in_liste=0 WHERE id NOT IN ($platzhalter)")->execute($gesehen);

            $meldung = $fehlerDetails ? 'Detailseiten mit Fehler: ' . implode('; ', $fehlerDetails) : 'ok';
            $pdo->prepare('UPDATE sync_log SET ende=?, ok=1, anzahl=?, neu=?, meldung=? WHERE id=?')
                ->execute([jetzt(), $anzahl, $neu, $meldung, $logId]);
            $log("Fertig: $anzahl Lehrgänge, davon $neu neu.");
            return ['ok' => true, 'anzahl' => $anzahl, 'neu' => $neu, 'meldung' => $meldung];
        } catch (Throwable $e) {
            $pdo->prepare('UPDATE sync_log SET ende=?, ok=0, anzahl=?, neu=?, meldung=? WHERE id=?')
                ->execute([jetzt(), $anzahl, $neu, $e->getMessage(), $logId]);
            $log('FEHLER: ' . $e->getMessage());
            return ['ok' => false, 'anzahl' => $anzahl, 'neu' => $neu, 'meldung' => $e->getMessage()];
        }
    }
}
