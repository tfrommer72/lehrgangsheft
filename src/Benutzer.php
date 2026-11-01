<?php
declare(strict_types=1);

/**
 * Admins mit E-Mail-Anmeldung. Passwörter setzt jede Person selbst über einen Link per Mail
 * (Einladung oder „Passwort vergessen“). Links gelten begrenzt und nur einmal.
 * Die Einträge unter 'admins' in config.php bleiben als Notfallzugang erhalten.
 */
final class Benutzer
{
    public const MIN_LAENGE = 10;
    public const GUELTIG_RESET = 3600;        // 1 Stunde
    public const GUELTIG_EINLADUNG = 172800;  // 48 Stunden

    public static function normalisiere(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /** @return array{id:int|null,name:string,email:string}|null */
    public static function anmelden(string $kennung, string $passwort): ?array
    {
        $kennung = trim($kennung);
        $st = Db::pdo()->prepare('SELECT * FROM benutzer WHERE email = ? AND aktiv = 1');
        $st->execute([self::normalisiere($kennung)]);
        $b = $st->fetch();
        if ($b && $b['passwort_hash'] && password_verify($passwort, $b['passwort_hash'])) {
            if (password_needs_rehash($b['passwort_hash'], PASSWORD_DEFAULT)) {
                Db::pdo()->prepare('UPDATE benutzer SET passwort_hash=? WHERE id=?')->execute([password_hash($passwort, PASSWORD_DEFAULT), $b['id']]);
            }
            Db::pdo()->prepare('UPDATE benutzer SET letzter_login=? WHERE id=?')->execute([jetzt(), $b['id']]);
            return ['id' => (int)$b['id'], 'name' => $b['name'], 'email' => $b['email']];
        }
        // Notfallzugang aus config.php
        $hash = (config('admins') ?? [])[$kennung] ?? null;
        if ($hash && password_verify($passwort, $hash)) {
            return ['id' => null, 'name' => $kennung, 'email' => ''];
        }
        return null;
    }

    public static function alle(): array
    {
        return Db::pdo()->query('SELECT id, name, email, aktiv, passwort_hash IS NOT NULL AS hat_passwort, erstellt, letzter_login FROM benutzer ORDER BY name')->fetchAll();
    }

    public static function finde(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM benutzer WHERE id=?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Legt einen Admin an und gibt die neue ID zurück. */
    public static function anlegen(string $name, string $email): int
    {
        $name = trim($name);
        $email = self::normalisiere($email);
        if ($name === '' || mb_strlen($name) > 80) throw new InvalidArgumentException('Bitte einen Namen angeben.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Die E-Mail-Adresse ist ungültig.');
        $st = Db::pdo()->prepare('SELECT 1 FROM benutzer WHERE email=?');
        $st->execute([$email]);
        if ($st->fetchColumn()) throw new InvalidArgumentException('Für diese E-Mail-Adresse gibt es schon einen Admin.');
        Db::pdo()->prepare('INSERT INTO benutzer (name, email, aktiv, erstellt) VALUES (?,?,1,?)')->execute([$name, $email, jetzt()]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function loeschen(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM passwort_token WHERE benutzer_id=?')->execute([$id]);
        Db::pdo()->prepare('DELETE FROM benutzer WHERE id=?')->execute([$id]);
    }

    /** Erzeugt einen Einmal-Link-Token und gibt ihn im Klartext zurück (gespeichert wird nur der Hash). */
    public static function token(int $benutzerId, int $gueltigSek): string
    {
        $token = bin2hex(random_bytes(32));
        $pdo = Db::pdo();
        $pdo->prepare('DELETE FROM passwort_token WHERE benutzer_id=?')->execute([$benutzerId]);
        $pdo->prepare('INSERT INTO passwort_token (token_hash, benutzer_id, gueltig_bis, erstellt) VALUES (?,?,?,?)')
            ->execute([hash('sha256', $token), $benutzerId, time() + $gueltigSek, time()]);
        return $token;
    }

    /** @return array|null der Benutzer zu einem gültigen Token */
    public static function tokenPruefen(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
        $st = Db::pdo()->prepare('SELECT b.* FROM passwort_token t JOIN benutzer b ON b.id=t.benutzer_id
                                  WHERE t.token_hash=? AND t.gueltig_bis>=? AND b.aktiv=1');
        $st->execute([hash('sha256', $token), time()]);
        return $st->fetch() ?: null;
    }

    public static function passwortSetzen(string $token, string $pw1, string $pw2): array
    {
        $b = self::tokenPruefen($token);
        if (!$b) throw new InvalidArgumentException('Der Link ist abgelaufen oder wurde schon benutzt. Bitte fordere einen neuen an.');
        if ($pw1 !== $pw2) throw new InvalidArgumentException('Die beiden Passwörter stimmen nicht überein.');
        if (mb_strlen($pw1) < self::MIN_LAENGE) throw new InvalidArgumentException('Das Passwort braucht mindestens ' . self::MIN_LAENGE . ' Zeichen.');
        Db::pdo()->prepare('UPDATE benutzer SET passwort_hash=? WHERE id=?')->execute([password_hash($pw1, PASSWORD_DEFAULT), $b['id']]);
        Db::pdo()->prepare('DELETE FROM passwort_token WHERE benutzer_id=?')->execute([$b['id']]);
        return ['id' => (int)$b['id'], 'name' => $b['name'], 'email' => $b['email']];
    }

    /** Darf für diese Adresse gerade eine Mail verschickt werden? (höchstens alle 5 Minuten) */
    public static function darfMailen(int $benutzerId): bool
    {
        $st = Db::pdo()->prepare('SELECT MAX(erstellt) FROM passwort_token WHERE benutzer_id=?');
        $st->execute([$benutzerId]);
        $letzte = (int)$st->fetchColumn();
        return time() - $letzte > 300;
    }

    public static function einladen(int $id): void
    {
        $b = self::finde($id) ?? throw new InvalidArgumentException('Admin nicht gefunden.');
        $link = rtrim((string)config('base_url'), '/') . '/admin/passwort-setzen?token=' . self::token($id, self::GUELTIG_EINLADUNG);
        Mailer::senden($b['email'], 'Einladung: Lehrgangsheft verwalten',
            "Hallo {$b['name']},\n\n"
            . "du wurdest als Admin für das Lehrgangsheft des DLRG LV Baden eingetragen.\n"
            . "Über diesen Link legst du dein Passwort fest (gültig 48 Stunden):\n\n$link\n\n"
            . "Danach meldest du dich mit deiner E-Mail-Adresse an:\n" . rtrim((string)config('base_url'), '/') . "/admin\n\n"
            . "Wenn du damit nichts anfangen kannst, ignoriere diese Mail einfach.\n");
    }

    /** Passwort vergessen: verschickt still eine Mail, wenn es die Adresse gibt. */
    public static function passwortVergessen(string $email): void
    {
        $st = Db::pdo()->prepare('SELECT * FROM benutzer WHERE email=? AND aktiv=1');
        $st->execute([self::normalisiere($email)]);
        $b = $st->fetch();
        if (!$b || !self::darfMailen((int)$b['id'])) return;
        $link = rtrim((string)config('base_url'), '/') . '/admin/passwort-setzen?token=' . self::token((int)$b['id'], self::GUELTIG_RESET);
        Mailer::senden($b['email'], 'Neues Passwort für das Lehrgangsheft',
            "Hallo {$b['name']},\n\n"
            . "für deinen Zugang zum Lehrgangsheft wurde ein neues Passwort angefordert.\n"
            . "Über diesen Link legst du es fest (gültig 1 Stunde, nur einmal verwendbar):\n\n$link\n\n"
            . "Wenn du das nicht angefordert hast, ignoriere diese Mail. Dein bisheriges Passwort bleibt dann gültig.\n");
    }
}
