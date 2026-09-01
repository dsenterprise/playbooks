<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/_store.php';

startPlaybookSession();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST' && array_key_exists('login', $_GET)) {
        $body = getRequestBody();
        $username = cleanText($body['username'] ?? '', 120);
        $password = (string) ($body['password'] ?? '');
        if ($username === '' || $password === '') {
            jsonRespond(['ok' => false, 'error' => 'Benutzername und Passwort sind erforderlich.'], 422);
        }

        /* Kein Standard-Zugang im Quelltext. Fehlt users.json, ist keine Anmeldung
           moeglich — das ist die sichere Richtung. Ein erster Benutzer wird von Hand
           angelegt, der Wert in users.json ist immer ein Hash, nie ein Klartext. */
        $users = dbRead('users.json', []);

        /* Bremse gegen Durchprobieren. Der Absender wird nur als Hash gespeichert,
           damit in der Ablage keine Adressen liegen. Ab dem dritten Fehlversuch
           wird verzoegert, ab dem zehnten fuer 15 Minuten abgewiesen. */
        $absender = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? '') . '|playbooks');
        $jetzt = time();

        /* Jeder Zugriff auf den Zaehler laeuft unter dbSperre. Ohne die Sperre lesen
           parallele Versuche denselben alten Stand und erhoehen ihn jeweils nur um
           eins - die Bremse liesse sich durch Gleichzeitigkeit unterlaufen. Das
           Warten am Ende steht bewusst AUSSERHALB der Sperre, sonst wuerde jeder
           Fehlversuch alle anderen Anmeldungen mit blockieren. */
        $eintrag = dbSperre('login_attempts.json', static function () use ($absender, $jetzt) {
            $bremse = dbRead('login_attempts.json', []);
            if (!is_array($bremse)) { $bremse = []; }
            $stand = is_array($bremse[$absender] ?? null) ? $bremse[$absender] : ['versuche' => 0, 'zuletzt' => 0];
            if (($jetzt - (int) $stand['zuletzt']) > 900) { $stand = ['versuche' => 0, 'zuletzt' => 0]; }
            return $stand;
        });
        if ((int) $eintrag['versuche'] >= 10) {
            jsonRespond(['ok' => false, 'error' => 'Zu viele Fehlversuche. Bitte spaeter erneut versuchen.'], 429);
        }
        $user = null;
        foreach ($users as $candidate) {
            if (is_array($candidate) && hash_equals((string) ($candidate['username'] ?? ''), $username)) {
                $user = $candidate;
                break;
            }
        }
        if ($user === null || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            $versuche = dbSperre('login_attempts.json', static function () use ($absender, $jetzt) {
                $bremse = dbRead('login_attempts.json', []);
                if (!is_array($bremse)) { $bremse = []; }
                $stand = is_array($bremse[$absender] ?? null) ? $bremse[$absender] : ['versuche' => 0, 'zuletzt' => 0];
                if (($jetzt - (int) $stand['zuletzt']) > 900) { $stand = ['versuche' => 0, 'zuletzt' => 0]; }
                $stand['versuche'] = (int) $stand['versuche'] + 1;
                $stand['zuletzt'] = $jetzt;
                $bremse[$absender] = $stand;
                dbWriteAtomic('login_attempts.json', $bremse);
                return (int) $stand['versuche'];
            });
            usleep((int) min(2000000, 250000 * $versuche));
            jsonRespond(['ok' => false, 'error' => 'Anmeldung fehlgeschlagen.'], 401);
        }

        dbSperre('login_attempts.json', static function () use ($absender) {
            $bremse = dbRead('login_attempts.json', []);
            if (is_array($bremse) && isset($bremse[$absender])) {
                unset($bremse[$absender]);
                dbWriteAtomic('login_attempts.json', $bremse);
            }
            return null;
        });
        session_regenerate_id(true);
        $_SESSION['user_id'] = (string) ($user['id'] ?? $user['username']);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        jsonRespond(['ok' => true]);
    }

    if ($method === 'GET' && array_key_exists('logout', $_GET)) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonRespond(['ok' => true]);
    }

    if ($method === 'GET' && array_key_exists('status', $_GET)) {
        $payload = ['ok' => true, 'loggedIn' => isset($_SESSION['user_id'])];
        if (isset($_SESSION['user_id'])) $payload['user_id'] = $_SESSION['user_id'];
        jsonRespond($payload);
    }

    jsonRespond(['ok' => false, 'error' => 'Methode oder Aktion nicht erlaubt.'], 405);
} catch (Throwable $error) {
    jsonRespond(['ok' => false, 'error' => 'Authentifizierung ist momentan nicht verfügbar.'], 500);
}
