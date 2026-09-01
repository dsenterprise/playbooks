<?php

declare(strict_types=1);

/**
 * _guard.php - Zugangspruefung fuer die schreibenden und lesenden Schnittstellen.
 *
 * ANLASS: Bis 2026-08-17 war /api/templates.php voellig ungeschuetzt. Ein
 * einzelner Aufruf ohne jede Anmeldung konnte Vorlagen anlegen, aendern und
 * loeschen - der gesamte Bestand haette mit einem POST verschwinden koennen.
 *
 * ZWEI WEGE, EIN WAECHTER:
 *   1. Browser  -> die bestehende Session (includes/session.php). Die
 *      Oberflaeche schickt ihr Cookie automatisch mit, weil fetch() bei
 *      gleichem Ursprung standardmaessig 'same-origin' verwendet.
 *   2. Maschine -> Kopfzeile X-Playbooks-Token. Fuer Agenten und Skripte,
 *      die keine Session halten koennen.
 *
 * DER DATEINAME BEGINNT MIT UNTERSTRICH. Das ist kein Zufall: der vhost
 * sperrt `location ~ ^/api/_` - die Datei ist damit nie direkt abrufbar.
 *
 * TOKEN LIEGEN NUR ALS HASH VOR. Wer die Ablage in die Haende bekaeme,
 * haette damit trotzdem keinen gueltigen Schluessel. Der Klartext wird
 * einmal bei der Ausgabe gezeigt und danach nie wieder.
 */

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/session.php';

const TOKEN_KOPFZEILE = 'HTTP_X_PLAYBOOKS_TOKEN';
const TOKEN_ABLAGE = 'api_tokens.json';

/**
 * Laesst die Anfrage durch oder beendet sie mit 401.
 * Muss VOR jeder Verarbeitung stehen.
 */
/**
 * Zweiter Riegel gegen fremd ausgeloeste Schreibzugriffe.
 *
 * Der Sitzungskeks traegt SameSite=Strict, deshalb schickt der Browser ihn bei einer
 * Anfrage von fremder Seite gar nicht erst mit. Das Merkmal deckt die Faelle, in denen
 * das nicht traegt: aeltere Browser, ein spaeter gelockertes SameSite, oder eine
 * Unterseite derselben Site in fremder Hand. Lesende Anfragen bleiben frei, sie
 * aendern nichts. Token-Anfragen ebenso: einen eigenen Kopfzeilen-Wert kann eine
 * fremde Seite gar nicht erst setzen.
 */
function pruefeCsrf(): void
{
    $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (in_array($methode, ['GET', 'HEAD', 'OPTIONS'], true)) {
        return;
    }

    $erwartet = (string) ($_SESSION['csrf_token'] ?? '');
    $gesendet = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($erwartet === '' || $gesendet === '' || !hash_equals($erwartet, $gesendet)) {
        jsonRespond([
            'ok' => false,
            'error' => 'Sicherheitsmerkmal fehlt oder passt nicht. Bitte die Seite neu laden und gegebenenfalls neu anmelden.',
        ], 403);
    }
}

function requireApiAuth(): void
{
    if (tokenGueltig()) {
        return;
    }

    startPlaybookSession();
    if (isset($_SESSION['user_id'])) {
        pruefeCsrf();
        return;
    }

    jsonRespond([
        'ok' => false,
        'error' => 'Nicht angemeldet. Melde dich an oder sende einen gueltigen X-Playbooks-Token.',
    ], 401);
}

/**
 * Prueft die Token-Kopfzeile gegen die hinterlegten Hashes.
 * Ohne Kopfzeile: false (dann entscheidet die Session).
 * Mit falscher Kopfzeile: sofort 401 - ein ungueltiger Schluessel soll
 * nicht stillschweigend auf den Session-Weg zurueckfallen.
 */
function tokenGueltig(): bool
{
    $angeboten = (string) ($_SERVER[TOKEN_KOPFZEILE] ?? '');
    if ($angeboten === '') {
        return false;
    }

    $angebotenHash = hash('sha256', $angeboten);
    foreach (dbRead(TOKEN_ABLAGE, []) as $eintrag) {
        if (!is_array($eintrag) || ($eintrag['aktiv'] ?? true) !== true) {
            continue;
        }
        // hash_equals vergleicht in konstanter Zeit - sonst waere die
        // Laufzeit ein Hinweis darauf, wie viele Zeichen schon stimmen.
        if (hash_equals((string) ($eintrag['token_hash'] ?? ''), $angebotenHash)) {
            return true;
        }
    }

    jsonRespond(['ok' => false, 'error' => 'Ungueltiger Token.'], 401);
}
