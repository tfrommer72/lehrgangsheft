<?php
declare(strict_types=1);

/**
 * PDF-Export über Chromium (headless). Chromium lädt die Druckansicht des Hefts von der
 * internen Adresse und druckt sie im Format A5. Fertige PDFs werden nach Inhaltsstand
 * zwischengespeichert, sodass sie nur bei Änderungen neu entstehen.
 */
final class Pdf
{
    private static function ordner(): string
    {
        $o = APP_ROOT . '/storage/pdf';
        if (!is_dir($o)) mkdir($o, 0775, true);
        return $o;
    }

    /** Liefert den Pfad zu einer aktuellen PDF-Datei; erzeugt sie bei Bedarf. */
    public static function datei(?int $kursId = null): string
    {
        $stand = Heft::fingerabdruck();
        $name = $kursId ? "kurs-$kursId-$stand.pdf" : "heft-$stand.pdf";
        $ziel = self::ordner() . '/' . $name;
        if (is_file($ziel) && filesize($ziel) > 0) return $ziel;

        $sperre = fopen(self::ordner() . '/.lock', 'c');
        flock($sperre, LOCK_EX);
        try {
            if (is_file($ziel) && filesize($ziel) > 0) return $ziel;
            $url = rtrim((string)config('intern_url'), '/') . ($kursId ? "/druck/kurs/$kursId" : '/druck/heft');
            self::drucken($url, $ziel);
            self::aufraeumen($stand);
            return $ziel;
        } finally {
            flock($sperre, LOCK_UN);
            fclose($sperre);
        }
    }

    public static function drucken(string $url, string $ziel): void
    {
        $profil = APP_ROOT . '/storage/chromium';
        if (!is_dir($profil)) mkdir($profil, 0775, true);
        $tmp = $ziel . '.tmp';
        $cmd = [
            (string)config('chromium'),
            '--headless=new', '--disable-gpu', '--no-sandbox', '--hide-scrollbars',
            '--no-pdf-header-footer', '--run-all-compositor-stages-before-draw',
            '--no-first-run', '--disable-extensions', '--disable-background-networking',
            '--disable-component-update', '--disable-dev-shm-usage',
            '--virtual-time-budget=20000',
            '--user-data-dir=' . $profil,
            '--print-to-pdf=' . $tmp,
            $url,
        ];
        $proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $profil . '/chromium.log', 'w'], 2 => ['file', $profil . '/chromium.log', 'a']], $pipes, null, ['HOME' => $profil]);
        if (!is_resource($proc)) throw new RuntimeException('Chromium konnte nicht gestartet werden.');
        $bis = microtime(true) + 90;
        // Chromium beendet sich nach dem Druck selbst; sobald die PDF fertig ist oder die Zeit abläuft, wird aufgeräumt.
        while (($st = proc_get_status($proc))['running']) {
            if (is_file($tmp) && filesize($tmp) > 0 && self::fertig($tmp)) break;
            if (microtime(true) > $bis) break;
            usleep(250_000);
        }
        if (proc_get_status($proc)['running']) { proc_terminate($proc, 15); usleep(500_000); proc_terminate($proc, 9); }
        $code = proc_close($proc);
        if (!is_file($tmp) || filesize($tmp) === 0 || !self::fertig($tmp)) {
            @unlink($tmp);
            $log = @file_get_contents($profil . '/chromium.log') ?: '';
            throw new RuntimeException("PDF-Erzeugung fehlgeschlagen (Code $code): " . mb_substr(trim($log), -400));
        }
        rename($tmp, $ziel);
    }

    /** Eine PDF ist vollständig geschrieben, wenn sie mit %%EOF endet. */
    private static function fertig(string $datei): bool
    {
        $f = fopen($datei, 'rb');
        if (!$f) return false;
        fseek($f, -32, SEEK_END);
        $ende = (string)fread($f, 32);
        fclose($f);
        return str_contains($ende, '%%EOF');
    }

    /** Löscht PDFs älterer Inhaltsstände. */
    private static function aufraeumen(string $stand): void
    {
        foreach (glob(self::ordner() . '/*.pdf') ?: [] as $f) {
            if (!str_contains(basename($f), $stand)) @unlink($f);
        }
    }
}
