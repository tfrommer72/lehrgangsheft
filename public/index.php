<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$pfad = '/' . trim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function ansicht(string $name, array $vars = []): void
{
    extract($vars, EXTR_SKIP);
    require APP_ROOT . '/views/' . $name . '.php';
}

function weiter(string $ziel): never
{
    header('Location: ' . $ziel, true, 303);
    exit;
}

function sitzung(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('lehrgangsheft');
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
        'secure' => str_starts_with((string)config('base_url'), 'https://'),
    ]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(16));
}

function admin(): ?string
{
    sitzung();
    return $_SESSION['admin'] ?? null;
}

function csrf_pruefen(): void
{
    sitzung();
    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sitzung abgelaufen – bitte die Seite neu laden.');
    }
}

function pdf_senden(string $datei, string $name): never
{
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $name . '"');
    header('Content-Length: ' . filesize($datei));
    header('Cache-Control: no-cache');
    readfile($datei);
    exit;
}

try {
    // ---------- Öffentlich ----------
    if ($pfad === '/' && $methode === 'GET') {
        ansicht('heft', ['modus' => 'blaettern', 'kurs' => 0, 'daten' => Heft::daten()]);
        exit;
    }
    if ($pfad === '/druck/heft') {
        ansicht('heft', ['modus' => 'druck-heft', 'kurs' => 0, 'daten' => Heft::daten()]);
        exit;
    }
    if (preg_match('#^/druck/kurs/(\d+)$#', $pfad, $m)) {
        ansicht('heft', ['modus' => 'druck-kurs', 'kurs' => (int)$m[1], 'daten' => Heft::daten()]);
        exit;
    }
    if ($pfad === '/heft.pdf') {
        pdf_senden(Pdf::datei(), 'Lehrgangsheft-DLRG-LV-Baden.pdf');
    }
    if (preg_match('#^/kurs/(\d+)\.pdf$#', $pfad, $m)) {
        $id = (int)$m[1];
        $daten = Heft::daten();
        $k = $daten['courses'][$id] ?? null;
        if (!$k || !$k['offen']) { http_response_code(404); exit('Dieser Lehrgang ist nicht im Heft.'); }
        $name = 'Lehrgang-' . preg_replace('/[^A-Za-z0-9-]+/', '-', $k['nr'] ?: (string)$id) . '.pdf';
        pdf_senden(Pdf::datei($id), $name);
    }

    // ---------- Admin ----------
    if ($pfad === '/admin/login') {
        sitzung();
        $fehler = null;
        if ($methode === 'POST') {
            csrf_pruefen();
            $b = Benutzer::anmelden((string)($_POST['email'] ?? ''), (string)($_POST['passwort'] ?? ''));
            if ($b) {
                session_regenerate_id(true);
                $_SESSION['admin'] = $b['name'];
                $_SESSION['benutzer_id'] = $b['id'];
                weiter('/admin');
            }
            sleep(1);
            $fehler = 'E-Mail-Adresse oder Passwort stimmt nicht.';
        }
        ansicht('login', ['fehler' => $fehler, 'hinweis' => $_SESSION['login_hinweis'] ?? null]);
        unset($_SESSION['login_hinweis']);
        exit;
    }
    if ($pfad === '/admin/passwort-vergessen') {
        sitzung();
        if ($methode === 'POST') {
            csrf_pruefen();
            try {
                Benutzer::passwortVergessen((string)($_POST['email'] ?? ''));
            } catch (Throwable $e) {
                error_log('[lehrgangsheft] Passwort-vergessen-Mail: ' . $e->getMessage());
            }
            sleep(1);
            // Immer dieselbe Antwort – verrät nicht, ob es die Adresse gibt
            $_SESSION['login_hinweis'] = 'Falls es zu dieser Adresse einen Zugang gibt, ist eine E-Mail mit einem Link unterwegs. Der Link gilt eine Stunde.';
            weiter('/admin/login');
        }
        ansicht('passwort-vergessen');
        exit;
    }
    if ($pfad === '/admin/passwort-setzen') {
        sitzung();
        $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        $fehler = null;
        if ($methode === 'POST') {
            csrf_pruefen();
            try {
                $b = Benutzer::passwortSetzen($token, (string)($_POST['pw1'] ?? ''), (string)($_POST['pw2'] ?? ''));
                session_regenerate_id(true);
                $_SESSION['admin'] = $b['name'];
                $_SESSION['benutzer_id'] = $b['id'];
                $_SESSION['hinweis'] = 'Dein Passwort ist gespeichert. Ab jetzt meldest du dich mit ' . $b['email'] . ' an.';
                weiter('/admin');
            } catch (InvalidArgumentException $e) {
                $fehler = $e->getMessage();
            }
        }
        $b = Benutzer::tokenPruefen($token);
        header('Referrer-Policy: no-referrer');
        ansicht('passwort-setzen', ['token' => $token, 'gueltig' => (bool)$b, 'name' => $b['name'] ?? '', 'fehler' => $fehler]);
        exit;
    }
    if ($pfad === '/admin/logout' && $methode === 'POST') {
        csrf_pruefen();
        $_SESSION = [];
        session_destroy();
        weiter('/admin/login');
    }
    if (str_starts_with($pfad, '/admin')) {
        if (!admin()) weiter('/admin/login');

        if ($pfad === '/admin/speichern' && $methode === 'POST') {
            csrf_pruefen();
            $pdo = Db::pdo();
            $ids = array_map('intval', (array)($_POST['ids'] ?? []));
            $frei = array_map('intval', array_keys((array)($_POST['frei'] ?? [])));
            $bereiche = (array)($_POST['bereich'] ?? []);
            $erlaubt = config('bereiche');
            $pdo->beginTransaction();
            $st = $pdo->prepare('UPDATE lehrgaenge SET freigegeben=?, bereich=?, neu=0 WHERE id=?');
            foreach ($ids as $id) {
                $b = (string)($bereiche[$id] ?? '');
                $st->execute([in_array($id, $frei, true) ? 1 : 0, in_array($b, $erlaubt, true) ? $b : null, $id]);
            }
            $pdo->commit();
            $_SESSION['hinweis'] = 'Gespeichert. Das Heft und die PDFs zeigen ab sofort diese Auswahl.';
            weiter('/admin');
        }

        if ($pfad === '/admin/aktualisieren' && $methode === 'POST') {
            csrf_pruefen();
            $_SESSION['hinweis'] = Hintergrund::syncStarten()
                ? 'Aktualisierung läuft. Sie dauert etwa ein bis zwei Minuten – diese Seite lädt sich selbst neu.'
                : 'Eine Aktualisierung läuft bereits.';
            weiter('/admin');
        }

        if ($pfad === '/admin/admins' && $methode === 'GET') {
            $hinweis = $_SESSION['hinweis'] ?? null; $fehler = $_SESSION['fehler'] ?? null;
            unset($_SESSION['hinweis'], $_SESSION['fehler']);
            ansicht('admins', ['liste' => Benutzer::alle(), 'hinweis' => $hinweis, 'fehler' => $fehler,
                'csrf' => $_SESSION['csrf'], 'admin' => admin(), 'ich' => $_SESSION['benutzer_id'] ?? null]);
            exit;
        }
        if ($pfad === '/admin/admins/anlegen' && $methode === 'POST') {
            csrf_pruefen();
            try {
                $id = Benutzer::anlegen((string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''));
                try {
                    Benutzer::einladen($id);
                    $_SESSION['hinweis'] = 'Admin angelegt. Die Einladung mit dem Link zum Passwort-Setzen ist per E-Mail unterwegs.';
                } catch (RuntimeException $e) {
                    $_SESSION['fehler'] = 'Admin angelegt, aber die Einladung konnte nicht verschickt werden: ' . $e->getMessage();
                }
            } catch (InvalidArgumentException $e) {
                $_SESSION['fehler'] = $e->getMessage();
            }
            weiter('/admin/admins');
        }
        if (preg_match('#^/admin/admins/(\d+)/(einladen|loeschen)$#', $pfad, $m) && $methode === 'POST') {
            csrf_pruefen();
            $id = (int)$m[1];
            if ($m[2] === 'loeschen') {
                if ($id === ($_SESSION['benutzer_id'] ?? null)) {
                    $_SESSION['fehler'] = 'Du kannst dich nicht selbst entfernen.';
                } else {
                    Benutzer::loeschen($id);
                    $_SESSION['hinweis'] = 'Admin entfernt.';
                }
            } else {
                try {
                    Benutzer::einladen($id);
                    $_SESSION['hinweis'] = 'Neuer Link per E-Mail verschickt.';
                } catch (Throwable $e) {
                    $_SESSION['fehler'] = 'E-Mail konnte nicht verschickt werden: ' . $e->getMessage();
                }
            }
            weiter('/admin/admins');
        }

        if ($pfad === '/admin' && $methode === 'GET') {
            $pdo = Db::pdo();
            $zeilen = $pdo->query('SELECT * FROM lehrgaenge ORDER BY in_liste DESC, neu DESC, beginn, nr')->fetchAll();
            $log = $pdo->query('SELECT * FROM sync_log ORDER BY id DESC LIMIT 8')->fetchAll();
            $hinweis = $_SESSION['hinweis'] ?? null;
            unset($_SESSION['hinweis']);
            ansicht('admin', [
                'zeilen' => $zeilen, 'log' => $log, 'hinweis' => $hinweis,
                'laeuft' => Hintergrund::syncLaeuft(), 'csrf' => $_SESSION['csrf'], 'admin' => admin(),
            ]);
            exit;
        }
    }

    http_response_code(404);
    echo 'Nicht gefunden.';
} catch (Throwable $e) {
    error_log('[lehrgangsheft] ' . $e);
    http_response_code(500);
    echo 'Es ist ein Fehler aufgetreten. Details stehen im Fehlerprotokoll des Servers.';
}
