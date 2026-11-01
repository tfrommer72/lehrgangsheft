<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $pfad = getenv('LEHRGANGSHEFT_DB') ?: APP_ROOT . '/storage/lehrgangsheft.sqlite';
            self::$pdo = new PDO('sqlite:' . $pfad, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode = WAL');
            self::$pdo->exec('PRAGMA busy_timeout = 5000');
            self::schema(self::$pdo);
        }
        return self::$pdo;
    }

    /** Nur für Tests: andere Datenbank verwenden. */
    public static function setze(PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::schema($pdo);
    }

    private static function schema(PDO $pdo): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS lehrgaenge (
                id              INTEGER PRIMARY KEY,   -- ID aus der DLRG-Seminarverwaltung
                nr              TEXT NOT NULL,
                titel           TEXT NOT NULL,
                status          TEXT NOT NULL DEFAULT '',
                ort             TEXT NOT NULL DEFAULT '',
                beginn          TEXT,
                ende            TEXT,
                meldeschluss    TEXT,
                detail_url      TEXT NOT NULL,
                anmeldung_url   TEXT,
                detail          TEXT,                  -- JSON mit allen Feldern der Detailseite
                detail_am       TEXT,
                bereich         TEXT,                  -- vom Admin gesetzt; NULL = Vorschlag nach Nummer
                freigegeben     INTEGER NOT NULL DEFAULT 0,
                in_liste        INTEGER NOT NULL DEFAULT 1, -- steht aktuell auf baden.dlrg.de
                neu             INTEGER NOT NULL DEFAULT 1, -- noch nicht vom Admin angesehen
                erstmals        TEXT NOT NULL,
                zuletzt         TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS benutzer (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                name           TEXT NOT NULL,
                email          TEXT NOT NULL UNIQUE,
                passwort_hash  TEXT,                  -- NULL, solange die Einladung nicht angenommen ist
                aktiv          INTEGER NOT NULL DEFAULT 1,
                erstellt       TEXT NOT NULL,
                letzter_login  TEXT
            );
            CREATE TABLE IF NOT EXISTS passwort_token (
                token_hash   TEXT PRIMARY KEY,         -- nur der SHA-256-Hash wird gespeichert
                benutzer_id  INTEGER NOT NULL,
                gueltig_bis  INTEGER NOT NULL,
                erstellt     INTEGER NOT NULL
            );
            CREATE TABLE IF NOT EXISTS sync_log (
                id        INTEGER PRIMARY KEY AUTOINCREMENT,
                start     TEXT NOT NULL,
                ende      TEXT,
                ok        INTEGER NOT NULL DEFAULT 0,
                anzahl    INTEGER NOT NULL DEFAULT 0,
                neu       INTEGER NOT NULL DEFAULT 0,
                meldung   TEXT
            );
            SQL);
    }
}
