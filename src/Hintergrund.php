<?php
declare(strict_types=1);

/** Startet die Synchronisation im Hintergrund („Jetzt aktualisieren“) und erkennt laufende Läufe. */
final class Hintergrund
{
    public static function sperrdatei(): string
    {
        return APP_ROOT . '/storage/sync.lock';
    }

    public static function syncLaeuft(): bool
    {
        $f = @fopen(self::sperrdatei(), 'c');
        if (!$f) return false;
        $frei = flock($f, LOCK_EX | LOCK_NB);
        if ($frei) flock($f, LOCK_UN);
        fclose($f);
        return !$frei;
    }

    public static function syncStarten(): bool
    {
        if (self::syncLaeuft()) return false;
        $php = PHP_BINARY && !str_contains(PHP_BINARY, 'fpm') ? PHP_BINARY : '/usr/bin/php';
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg(APP_ROOT . '/bin/sync.php')
            . ' > ' . escapeshellarg(APP_ROOT . '/storage/sync-letzter-lauf.log') . ' 2>&1 &';
        exec($cmd);
        usleep(300_000);
        return true;
    }
}
