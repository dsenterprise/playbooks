<?php

declare(strict_types=1);

require_once __DIR__ . '/_store.php';

function versionKeyIsValid(string $type, string $key): bool
{
    return $type === 'template'
        ? preg_match('/^tpl-[a-zA-Z0-9_-]+$/', $key) === 1
        : preg_match('/^[a-z0-9-]+$/', $key) === 1;
}

function versionDirectory(string $type, string $key, bool $create = false): string
{
    if (!in_array($type, ['template', 'run'], true) || !versionKeyIsValid($type, $key)) {
        throw new InvalidArgumentException('Ungültiger Versionspfad.');
    }
    ensureDbDir();
    $root = DB_DIR . '/' . $type . '_versions';
    $directory = $root . '/' . $key;
    if ($create) {
        if (!is_dir($root) && !mkdir($root, 0775) && !is_dir($root)) {
            throw new RuntimeException('Versionsverzeichnis konnte nicht angelegt werden.');
        }
        if (!is_dir($directory) && !mkdir($directory, 0775) && !is_dir($directory)) {
            throw new RuntimeException('Versionsordner konnte nicht angelegt werden.');
        }
    }
    return $directory;
}

function writeVersionSnapshot(string $type, string $key, int $version, array $record, ?string $author = null): void
{
    if ($version < 1) throw new InvalidArgumentException('Ungültige Versionsnummer.');
    $directory = versionDirectory($type, $key, true);
    $path = $directory . '/' . $version . '.json';
    if (is_file($path)) return;
    $record['_snapshot'] = ['version' => $version, 'timestamp' => nowIso(), 'author' => $author];
    $temporary = $path . '.tmp-' . bin2hex(random_bytes(8));
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    try {
        if (file_put_contents($temporary, $json, LOCK_EX) === false || !rename($temporary, $path)) {
            throw new RuntimeException('Version konnte nicht atomar gespeichert werden.');
        }
    } finally {
        if (is_file($temporary)) unlink($temporary);
    }
}

function readVersionSnapshot(string $type, string $key, int $version): ?array
{
    if ($version < 1) return null;
    $path = versionDirectory($type, $key) . '/' . $version . '.json';
    if (!is_file($path)) return null;
    $json = file_get_contents($path);
    if ($json === false) throw new RuntimeException('Version konnte nicht gelesen werden.');
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function listVersionSnapshots(string $type, string $key): array
{
    $directory = versionDirectory($type, $key);
    if (!is_dir($directory)) return [];
    $versions = [];
    foreach (scandir($directory) ?: [] as $filename) {
        if (!preg_match('/^([1-9][0-9]*)\.json$/', $filename, $match)) continue;
        $record = readVersionSnapshot($type, $key, (int) $match[1]);
        if ($record === null) continue;
        $meta = $record['_snapshot'] ?? [];
        $versions[] = [
            'version' => (int) $match[1],
            'timestamp' => $meta['timestamp'] ?? $record['updated_at'] ?? $record['created_at'] ?? null,
            'author' => $meta['author'] ?? null,
            'name' => $record['name'] ?? null,
        ];
    }
    usort($versions, static fn(array $a, array $b): int => $b['version'] <=> $a['version']);
    return $versions;
}

function snapshotRecord(array $snapshot): array
{
    unset($snapshot['_snapshot']);
    return $snapshot;
}
