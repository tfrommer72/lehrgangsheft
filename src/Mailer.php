<?php
declare(strict_types=1);

/**
 * Kleiner SMTP-Versand ohne Zusatzbibliothek: STARTTLS (Port 587), SSL (Port 465) oder unverschlüsselt (nur Tests).
 * Einstellungen in config.php unter 'mail'.
 */
final class Mailer
{
    /** Für Tests: statt zu senden hier ablegen. */
    public static ?array $abfangen = null;

    public static function senden(string $an, string $betreff, string $text): void
    {
        if (self::$abfangen !== null) {
            self::$abfangen[] = ['an' => $an, 'betreff' => $betreff, 'text' => $text];
            return;
        }
        $m = config('mail') ?? [];
        foreach (['host', 'port', 'absender'] as $k) {
            if (empty($m[$k])) throw new RuntimeException("Mailversand ist nicht eingerichtet (config.php: 'mail' → '$k').");
        }
        $enc = $m['verschluesselung'] ?? 'tls';
        $ziel = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $m['host'] . ':' . (int)$m['port'];
        $s = @stream_socket_client($ziel, $errno, $errstr, 20);
        if (!$s) throw new RuntimeException("SMTP-Server nicht erreichbar ($errstr).");
        stream_set_timeout($s, 20);

        try {
            self::erwarte($s, 220);
            $host = parse_url((string)config('base_url'), PHP_URL_HOST) ?: 'localhost';
            self::befehl($s, "EHLO $host", 250);
            if ($enc === 'tls') {
                self::befehl($s, 'STARTTLS', 220);
                if (!stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new RuntimeException('TLS-Verbindung zum Mailserver fehlgeschlagen.');
                }
                self::befehl($s, "EHLO $host", 250);
            }
            if (!empty($m['benutzer'])) {
                self::befehl($s, 'AUTH LOGIN', 334);
                self::befehl($s, base64_encode((string)$m['benutzer']), 334);
                self::befehl($s, base64_encode((string)$m['passwort']), 235, true);
            }
            self::befehl($s, 'MAIL FROM:<' . $m['absender'] . '>', 250);
            self::befehl($s, 'RCPT TO:<' . $an . '>', [250, 251]);
            self::befehl($s, 'DATA', 354);

            $name = $m['absender_name'] ?? 'Lehrgangsheft';
            $kopf = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . self::kodiere($name) . ' <' . $m['absender'] . '>',
                'To: <' . $an . '>',
                'Subject: ' . self::kodiere($betreff),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
                'Auto-Submitted: auto-generated',
            ];
            $nachricht = implode("\r\n", $kopf) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($text), 76, "\r\n")) . "\r\n.";
            self::befehl($s, $nachricht, 250);
            self::befehl($s, 'QUIT', 221);
        } finally {
            fclose($s);
        }
    }

    private static function kodiere(string $s): string
    {
        return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }

    private static function befehl($s, string $zeile, int|array $erwartet, bool $geheim = false): string
    {
        fwrite($s, $zeile . "\r\n");
        try {
            return self::erwarte($s, $erwartet);
        } catch (RuntimeException $e) {
            if ($geheim) throw new RuntimeException('Anmeldung am Mailserver fehlgeschlagen – Benutzer/Passwort in config.php prüfen.');
            throw $e;
        }
    }

    private static function erwarte($s, int|array $erwartet): string
    {
        $antwort = '';
        while (($zeile = fgets($s, 1024)) !== false) {
            $antwort .= $zeile;
            if (strlen($zeile) < 4 || $zeile[3] === ' ') break;
        }
        $code = (int)substr($antwort, 0, 3);
        if (!in_array($code, (array)$erwartet, true)) {
            throw new RuntimeException('Mailserver meldet: ' . trim($antwort ?: 'keine Antwort'));
        }
        return $antwort;
    }
}
