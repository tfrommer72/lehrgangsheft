<?php
declare(strict_types=1);

/**
 * Liest Lehrgangsliste und Detailseiten von baden.dlrg.de.
 *
 * Liste:  Die Übersichtsseite enthält eine Tabelle <table id="DlrgSeminarPublicSeminarList…">,
 *         deren Attribut data-data alle Lehrgänge als JSON-Array enthält.
 * Detail: Jede Detailseite hat <div class="container seminar-detail"> mit einer Überschrift
 *         "Titel (Nr.: XX-XXXXX)" und einer <dl class="seminar-detail"> aus dt/dd-Paaren.
 */
final class Quelle
{
    public static function abrufen(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_ENCODING       => '',
            CURLOPT_USERAGENT      => 'Lehrgangsheft/1.0 (+' . (config('base_url') ?? '') . ')',
        ]);
        $html = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $fehler = curl_error($ch);
        curl_close($ch);
        if ($html === false || $code !== 200) {
            throw new RuntimeException("Abruf fehlgeschlagen ($code) $url $fehler");
        }
        return $html;
    }

    private static function dom(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET);
        libxml_clear_errors();
        return new DOMXPath($doc);
    }

    /** @return list<array<string,mixed>> */
    public static function liste(string $html, string $basis): array
    {
        $xp = self::dom($html);
        $tabelle = $xp->query('//table[starts-with(@id,"DlrgSeminarPublicSeminarList")][@data-data]')->item(0);
        if (!$tabelle instanceof DOMElement) {
            throw new RuntimeException('Lehrgangstabelle (data-data) nicht gefunden – hat sich die Seite geändert?');
        }
        $daten = json_decode($tabelle->getAttribute('data-data'), true);
        if (!is_array($daten)) {
            throw new RuntimeException('data-data ist kein gültiges JSON.');
        }
        $zeilen = array_is_list($daten) ? $daten : ($daten['rows'] ?? $daten['data'] ?? []);

        $out = [];
        foreach ($zeilen as $z) {
            if (!isset($z['id'], $z['titel'])) continue;
            $link = (string)($z['link'] ?? '');
            if (preg_match('/href="([^"]+)"/', $link, $m)) $link = html_entity_decode($m[1]);
            $link = preg_replace('/#.*$/', '', trim($link));
            if ($link !== '' && !preg_match('#^https?://#', $link)) $link = rtrim($basis, '/') . '/' . ltrim($link, '/');
            $out[] = [
                'id'           => (int)$z['id'],
                'nr'           => trim((string)($z['lehrgangNr'] ?? '')),
                'titel'        => self::sauber((string)$z['titel']),
                'status'       => self::sauber(strip_tags((string)($z['anmeldungStatusAsText'] ?? ''))),
                'ort'          => self::sauber((string)($z['ort'] ?? '')),
                'beginn'       => self::iso($z['beginn'] ?? $z['start'] ?? null),
                'ende'         => self::iso($z['ende'] ?? null),
                'meldeschluss' => self::iso($z['meldeschluss'] ?? null),
                'detail_url'   => $link,
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function detail(string $html): array
    {
        $xp = self::dom($html);
        $box = $xp->query('//div[contains(concat(" ",normalize-space(@class)," ")," seminar-detail ")][.//dl]')->item(0);
        if (!$box) throw new RuntimeException('Detailbereich nicht gefunden.');

        $d = [
            'titel' => '', 'nr' => '', 'status' => '', 'zielgruppe' => [], 'voraussetzungen_bis' => '',
            'voraussetzungen' => [], 'inhalt' => [], 'ziele' => '', 'veranstalter' => '', 'verwalter' => '',
            'leitung' => '', 'referierende' => '', 'veranstaltungsort' => '', 'termine' => [], 'meldeschluss' => '',
            'tn_min' => '', 'tn_max' => '', 'teilnehmerkreis' => '', 'gebuehren' => [], 'mitbringen' => [],
            'verpflegung' => '', 'unterbringung' => '', 'sonstiges' => '', 'anmeldung_url' => '', 'ausschreibung_url' => '',
        ];

        $h = $xp->query('.//h3', $box)->item(0);
        if ($h) {
            $t = self::sauber($h->textContent);
            if (preg_match('/^(.*?)\s*\(Nr\.:\s*([^)]+)\)\s*$/u', $t, $m)) {
                $d['titel'] = $m[1];
                $d['nr'] = trim($m[2]);
            } else {
                $d['titel'] = $t;
            }
        }

        foreach ($xp->query('.//dl/dt', $box) as $dt) {
            $dd = $dt->nextSibling;
            while ($dd && !($dd instanceof DOMElement && $dd->nodeName === 'dd')) $dd = $dd->nextSibling;
            if (!$dd) continue;
            $label = self::sauber($dt->firstChild?->textContent ?? $dt->textContent);
            $klein = $xp->query('.//small', $dt)->item(0);
            $liste = self::listenpunkte($xp, $dd);
            $text = self::text($dd);
            $zeilen = self::zeilen($dd);

            switch (mb_strtolower($label)) {
                case 'status':            $d['status'] = $text; break;
                case 'zielgruppe':        $d['zielgruppe'] = $liste ?: ($text ? [$text] : []); break;
                case 'voraussetzungen':
                    $d['voraussetzungen'] = $liste ?: ($text ? [$text] : []);
                    if ($klein && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $klein->textContent, $m)) $d['voraussetzungen_bis'] = $m[1];
                    break;
                case 'inhalt':
                    $absaetze = [];
                    foreach ($xp->query('./p', $dd) as $p) { $s = self::sauber($p->textContent); if ($s !== '') $absaetze[] = $s; }
                    $d['inhalt'] = array_merge($absaetze ? [implode(' ', $absaetze)] : [], $liste);
                    if (!$d['inhalt'] && $text) $d['inhalt'] = [$text];
                    break;
                case 'ziele': case 'ziel': $d['ziele'] = $text; break;
                case 'veranstalter':      $d['veranstalter'] = $text; break;
                case 'verwalter':         $d['verwalter'] = $text; break;
                case 'leitung':           $d['leitung'] = $text; break;
                case 'referierende': case 'referenten': $d['referierende'] = $text; break;
                case 'veranstaltungsort': $d['veranstaltungsort'] = $text; break;
                case 'termine':
                    $t = [];
                    foreach ($zeilen as $z) {
                        if (preg_match('/^Adresse/i', $z)) break;
                        if (preg_match('/^\d+\s+Termin(e)?\s+insgesamt/i', $z)) continue;
                        $t[] = $z;
                    }
                    $d['termine'] = $t;
                    break;
                case 'meldeschluss':      $d['meldeschluss'] = $text; break;
                case 'teilnehmerzahl':
                    if (preg_match('/Minimal:\s*(\S+)/i', $text, $m)) $d['tn_min'] = $m[1];
                    if (preg_match('/Maximal:\s*(\S+)/i', $text, $m)) $d['tn_max'] = $m[1];
                    break;
                case 'teilnehmerkreis':   $d['teilnehmerkreis'] = $text; break;
                case 'gebühren':          $d['gebuehren'] = $liste ?: ($text ? [$text] : []); break;
                case 'mitzubringen sind': case 'mitzubringen': $d['mitbringen'] = $liste ?: ($text ? [$text] : []); break;
                case 'verpflegung':       $d['verpflegung'] = $text; break;
                case 'unterbringung':     $d['unterbringung'] = $text; break;
                case 'sonstiges':
                    $s = mb_substr(implode("\n", $zeilen), 0, 1200);
                    $d['sonstiges'] = $s;
                    break;
                case 'dokumente':
                    foreach ($xp->query('.//a', $dd) as $a) {
                        if (stripos($a->textContent, 'Ausschreibung') !== false) $d['ausschreibung_url'] = $a->getAttribute('href');
                    }
                    break;
            }
        }

        foreach ($xp->query('.//a', $box) as $a) {
            if (stripos($a->textContent, 'Jetzt teilnehmen') !== false || stripos($a->getAttribute('href'), 'page=anmeldung') !== false) {
                $d['anmeldung_url'] = $a->getAttribute('href');
                break;
            }
        }
        return $d;
    }

    /** @return list<string> */
    private static function listenpunkte(DOMXPath $xp, DOMElement $dd): array
    {
        $out = [];
        foreach ($xp->query('./ul/li | ./ol/li', $dd) as $li) {
            $s = self::sauber($li->textContent);
            if ($s !== '') $out[] = $s;
        }
        return $out;
    }

    private static function text(DOMElement $dd): string
    {
        return self::sauber(implode(' ', self::zeilen($dd)));
    }

    /** Text des Elements in Zeilen, getrennt an <br>, <p>, <li>. @return list<string> */
    private static function zeilen(DOMNode $n): array
    {
        $puffer = '';
        $zeilen = [];
        $lauf = function (DOMNode $k) use (&$lauf, &$puffer, &$zeilen): void {
            foreach ($k->childNodes as $c) {
                if ($c instanceof DOMText) { $puffer .= $c->textContent; continue; }
                if (!$c instanceof DOMElement) continue;
                $tag = strtolower($c->nodeName);
                if ($tag === 'br') { $zeilen[] = $puffer; $puffer = ''; continue; }
                $block = in_array($tag, ['p', 'li', 'div', 'ul', 'ol'], true);
                if ($block) { $zeilen[] = $puffer; $puffer = $tag === 'li' ? '• ' : ''; }
                $lauf($c);
                if ($block) { $zeilen[] = $puffer; $puffer = ''; }
            }
        };
        $lauf($n);
        $zeilen[] = $puffer;
        return array_values(array_filter(array_map([self::class, 'sauber'], $zeilen), fn($z) => $z !== '' && $z !== '•'));
    }

    public static function sauber(string $s): string
    {
        $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = str_replace("\u{00A0}", ' ', $s);
        return trim(preg_replace('/\s+/u', ' ', $s) ?? '');
    }

    private static function iso(mixed $v): ?string
    {
        if (!$v) return null;
        $v = trim((string)$v);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $v, $m)) return $m[1];
        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})/', $v, $m)) return "$m[3]-$m[2]-$m[1]";
        return null;
    }
}
