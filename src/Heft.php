<?php
declare(strict_types=1);

/** Stellt die Daten für das Heft zusammen (nur freigegebene, aktuell gelistete Lehrgänge). */
final class Heft
{
    public static function bereichVorschlag(string $nr, string $titel = ''): string
    {
        if (preg_match('/^\d{2}-(\d{3})\d{2}$/', trim($nr), $m)) {
            return config('bereich_nach_nummer')[$m[1]] ?? 'Weitere Lehrgänge';
        }
        if (preg_match('/tagung|rat\b|treffen|versammlung/iu', $nr . ' ' . $titel)) return 'Tagungen & Gremien';
        return 'Weitere Lehrgänge';
    }

    public static function bereich(array $zeile): string
    {
        return $zeile['bereich'] ?: self::bereichVorschlag($zeile['nr'], $zeile['titel']);
    }

    public static function istOffen(string $status): bool
    {
        return stripos($status, 'Meldeschluss erreicht') === false;
    }

    /** @return array{cats:list<array{0:string,1:list<int>}>,courses:array<int,array>,meta:array} */
    public static function daten(): array
    {
        $zeilen = Db::pdo()->query('SELECT * FROM lehrgaenge WHERE freigegeben=1 AND in_liste=1 ORDER BY beginn, nr')->fetchAll();
        $kurse = [];
        $nachBereich = [];
        foreach ($zeilen as $z) {
            $d = $z['detail'] ? (json_decode($z['detail'], true) ?: []) : [];
            $offen = self::istOffen($z['status']);
            $bereich = self::bereich($z);
            $k = array_merge([
                'zielgruppe' => [], 'voraussetzungen_bis' => '', 'voraussetzungen' => [], 'inhalt' => [], 'ziele' => '',
                'leitung' => '', 'referierende' => '', 'veranstaltungsort' => '', 'termine' => [], 'meldeschluss' => '',
                'tn_min' => '', 'tn_max' => '', 'gebuehren' => [], 'mitbringen' => [], 'verpflegung' => '',
                'unterbringung' => '', 'sonstiges' => '',
            ], $d, [
                'id' => (int)$z['id'],
                'nr' => $z['nr'],
                'titel' => $z['titel'],
                'status' => $z['status'],
                'ort' => $z['ort'],
                'beginn' => $z['beginn'],
                'ende' => $z['ende'],
                'meldeschluss_list' => $z['meldeschluss'],
                'detail_url' => $z['detail_url'],
                'qr_url' => $z['anmeldung_url'] ?: $z['detail_url'],
                'qr_direct' => (bool)$z['anmeldung_url'],
                'offen' => $offen,
                'cat' => $bereich,
            ]);
            unset($k['anmeldung_url'], $k['ausschreibung_url'], $k['verwalter'], $k['veranstalter']);
            $kurse[$k['id']] = $k;
            if ($offen) $nachBereich[$bereich][] = $k['id'];
        }

        $cats = [];
        $reihenfolge = config('bereiche');
        foreach (array_keys($nachBereich) as $b) if (!in_array($b, $reihenfolge, true)) $reihenfolge[] = $b;
        foreach ($reihenfolge as $b) if (!empty($nachBereich[$b])) $cats[] = [$b, $nachBereich[$b]];

        $stand = Db::pdo()->query('SELECT ende FROM sync_log WHERE ok=1 ORDER BY id DESC LIMIT 1')->fetchColumn();
        $meta = [
            'stand' => $stand ? date('d.m.Y', strtotime((string)$stand)) : date('d.m.Y'),
            'kontakt' => config('kontakt'),
            'quelle' => config('quelle_liste'),
            'base' => rtrim((string)config('base_url'), '/'),
        ];
        return ['cats' => $cats, 'courses' => $kurse, 'meta' => $meta];
    }

    /** Fingerabdruck des öffentlichen Inhalts – ändert sich, sobald sich am Heft etwas ändert. */
    public static function fingerabdruck(?array $daten = null): string
    {
        $daten ??= self::daten();
        return substr(sha1(json_encode($daten, JSON_UNESCAPED_UNICODE)), 0, 16);
    }
}
