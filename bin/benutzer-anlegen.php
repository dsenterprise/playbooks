<?php

declare(strict_types=1);

/**
 * Legt einen Benutzer an oder setzt sein Passwort neu.
 *
 * Im Quelltext steht bewusst kein Zugang: Fehlt database/users.json, ist keine
 * Anmeldung moeglich. Der erste Benutzer wird hier erzeugt.
 *
 * Aufruf:  php bin/benutzer-anlegen.php <benutzername>
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, 'Dieses Skript laeuft nur auf der Kommandozeile.' . PHP_EOL);
    exit(1);
}

$name = trim((string) ($argv[1] ?? ''));
if ($name === '') {
    echo 'Aufruf: php bin/benutzer-anlegen.php <benutzername>' . PHP_EOL;
    exit(1);
}

echo 'Passwort fuer "' . $name . '": ';
@shell_exec('stty -echo 2>/dev/null');
$passwort = rtrim((string) fgets(STDIN), PHP_EOL);
@shell_exec('stty echo 2>/dev/null');
echo PHP_EOL;

if (strlen($passwort) < 12) {
    fwrite(STDERR, 'Mindestens 12 Zeichen, bitte.' . PHP_EOL);
    exit(1);
}

$datei = __DIR__ . '/../database/users.json';
$benutzer = [];
if (is_file($datei)) {
    $gelesen = json_decode((string) file_get_contents($datei), true);
    if (is_array($gelesen)) {
        $benutzer = $gelesen;
    }
}

$gefunden = false;
foreach ($benutzer as $i => $eintrag) {
    if (is_array($eintrag) && ($eintrag['username'] ?? '') === $name) {
        $benutzer[$i]['password_hash'] = password_hash($passwort, PASSWORD_BCRYPT);
        $gefunden = true;
        break;
    }
}
if (!$gefunden) {
    $benutzer[] = [
        'id' => 'user-' . bin2hex(random_bytes(6)),
        'username' => $name,
        'password_hash' => password_hash($passwort, PASSWORD_BCRYPT),
    ];
}

$inhalt = json_encode($benutzer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$temp = $datei . '.tmp';
if (file_put_contents($temp, $inhalt) === false || !rename($temp, $datei)) {
    fwrite(STDERR, 'Schreiben fehlgeschlagen. Ist database/ beschreibbar?' . PHP_EOL);
    exit(1);
}
@chmod($datei, 0640);

echo ($gefunden ? 'Passwort geaendert' : 'Benutzer angelegt') . ': ' . $name . PHP_EOL;
