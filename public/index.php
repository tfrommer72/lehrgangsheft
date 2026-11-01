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
            $name = trim((string)($_POST['name'] ?? ''));
            $hash = config('admins')[$name] ?? null;
            if ($hash && password_verify((string)($_POST['passwort'] ?? ''), $hash)) {
                session_regenerate_id(true);
                $_SESSION['admin'] = $name;
                weiter('/admin');
            }
            sleep(1);
            $fehler = 'Name oder Passwort stimmt nicht.';
        }
        ansicht('login', ['fehler' => $fehler]);
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
