<?php

declare(strict_types=1);

/* Die Ablage liegt ausserhalb des Wurzelverzeichnisses des Webservers. Damit kann
   ein Konfigurationsfehler sie nicht ausliefern - vorher war eine nginx-Regel die
   einzige Verteidigung. */
const DB_DIR = __DIR__ . '/../storage/database';

function ensureDbDir(): void
{
    if (!is_dir(DB_DIR) && !mkdir(DB_DIR, 0775, true) && !is_dir(DB_DIR)) {
        throw new RuntimeException('Datenverzeichnis konnte nicht angelegt werden.');
    }
}

function dbPath(string $filename): string
{
    if (!preg_match('/^[a-z0-9_-]+\.json$/i', $filename)) {
        throw new InvalidArgumentException('Ungültiger Speichername.');
    }

    return DB_DIR . '/' . $filename;
}

function dbRead(string $filename, mixed $default): mixed
{
    ensureDbDir();
    $path = dbPath($filename);

    if (!is_file($path)) {
        dbWriteAtomic($filename, $default);
        return $default;
    }

    $json = file_get_contents($path);
    if ($json === false) {
        throw new RuntimeException('Daten konnten nicht gelesen werden.');
    }

    try {
        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('Die gespeicherten Daten sind ungültig.');
    }
}

/**
 * Fuehrt Lesen, Rechnen und Schreiben unter EINER Sperre aus.
 *
 * dbWriteAtomic schreibt zwar unteilbar (Temp-Datei plus rename), aber zwischen
 * dem Lesen und dem Schreiben liegt eine Luecke: parallele Aufrufe sehen denselben
 * alten Stand und erhoehen ihn jeweils nur um eins. Bei einem Fehlversuchszaehler
 * heisst das, zehn gleichzeitige Versuche zaehlen als einer.
 */
function dbSperre(string $filename, callable $arbeit): mixed
{
    ensureDbDir();
    $pfad = dbPath($filename) . '.lock';
    $griff = fopen($pfad, 'c');
    if ($griff === false) {
        throw new RuntimeException('Sperre konnte nicht angelegt werden.');
    }

    try {
        if (!flock($griff, LOCK_EX)) {
            throw new RuntimeException('Sperre konnte nicht gesetzt werden.');
        }
        return $arbeit();
    } finally {
        @flock($griff, LOCK_UN);
        fclose($griff);
    }
}

function dbWriteAtomic(string $filename, mixed $data): void
{
    ensureDbDir();
    $path = dbPath($filename);
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

    try {
        if (file_put_contents($temporary, $json, LOCK_EX) === false) {
            throw new RuntimeException('Daten konnten nicht geschrieben werden.');
        }
        if (!rename($temporary, $path)) {
            throw new RuntimeException('Daten konnten nicht atomar gespeichert werden.');
        }
    } finally {
        if (is_file($temporary)) {
            unlink($temporary);
        }
    }
}

function generateId(string $prefix = 'id'): string
{
    return $prefix . '-' . bin2hex(random_bytes(8));
}

function nowIso(): string
{
    return (new DateTimeImmutable())->format(DATE_ATOM);
}

function getRequestBody(): array
{
    $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
    if (str_contains($contentType, 'application/json')) {
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return [];
        }
        try {
            $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            return is_array($body) ? $body : [];
        } catch (JsonException) {
            jsonRespond(['ok' => false, 'error' => 'Ungültiges JSON.'], 400);
        }
    }

    return $_POST;
}

function jsonRespond(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cleanText(mixed $value, int $maximum = 5000): string
{
    $text = trim((string) $value);
    return mb_substr($text, 0, $maximum);
}

function defaultCategories(): array
{
    return [
        ['id' => 'cat-integration', 'name' => 'Integration', 'slug' => 'integration', 'color' => '#3b82f6', 'sort_order' => 10],
        ['id' => 'cat-software', 'name' => 'Software', 'slug' => 'software', 'color' => '#8b5cf6', 'sort_order' => 20],
        ['id' => 'cat-business', 'name' => 'Business', 'slug' => 'business', 'color' => '#10b981', 'sort_order' => 30],
        ['id' => 'cat-marketing', 'name' => 'Marketing', 'slug' => 'marketing', 'color' => '#f59e0b', 'sort_order' => 40],
        ['id' => 'cat-prozess', 'name' => 'Prozess', 'slug' => 'prozess', 'color' => '#6366f1', 'sort_order' => 50],
        ['id' => 'cat-research', 'name' => 'Research', 'slug' => 'research', 'color' => '#ec4899', 'sort_order' => 60],
        ['id' => 'cat-starter', 'name' => 'Starter Templates', 'slug' => 'starter', 'color' => '#64748b', 'sort_order' => 70],
    ];
}
