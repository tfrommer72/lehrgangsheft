<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

date_default_timezone_set('Europe/Berlin');
mb_internal_encoding('UTF-8');

require __DIR__ . '/Db.php';
require __DIR__ . '/Quelle.php';
require __DIR__ . '/Sync.php';
require __DIR__ . '/Heft.php';
require __DIR__ . '/Pdf.php';
require __DIR__ . '/Hintergrund.php';
require __DIR__ . '/Benutzer.php';
require __DIR__ . '/Mailer.php';

function config(?string $key = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = getenv('LEHRGANGSHEFT_CONFIG') ?: APP_ROOT . '/config.php';
        if (!is_file($file)) {
            throw new RuntimeException('config.php fehlt – bitte config.example.php kopieren und anpassen.');
        }
        $cfg = require $file;
    }
    return $key === null ? $cfg : ($cfg[$key] ?? null);
}

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jetzt(): string
{
    return date('Y-m-d H:i:s');
}

function datum_de(?string $iso): string
{
    if (!$iso) return '';
    $t = strtotime($iso);
    return $t ? date('d.m.Y', $t) : $iso;
}

function json_fuer_html(mixed $daten): string
{
    return json_encode($daten, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
}
