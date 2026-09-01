<?php

declare(strict_types=1);

require_once __DIR__ . '/_versions.php';
require_once __DIR__ . '/_guard.php';

requireApiAuth();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') jsonRespond(['ok' => false, 'error' => 'Methode nicht erlaubt.'], 405);
    $runId = cleanText($_GET['run_id'] ?? '', 80);
    $runs = dbRead('runs.json', []);
    $run = null;
    foreach ($runs as $item) if (($item['id'] ?? '') === $runId) { $run = $item; break; }
    if ($run === null) jsonRespond(['ok' => false, 'error' => 'Durchführung nicht gefunden.'], 404);
    $slug = (string) $run['slug'];
    if (isset($_GET['version'])) {
        $version = (int) $_GET['version'];
        $snapshot = readVersionSnapshot('run', $slug, $version);
        if ($snapshot === null) jsonRespond(['ok' => false, 'error' => 'Version nicht gefunden.'], 404);
        jsonRespond(['ok' => true, 'run' => snapshotRecord($snapshot), 'metadata' => $snapshot['_snapshot'] ?? null]);
    }
    jsonRespond(['ok' => true, 'run_id' => $runId, 'run_slug' => $slug, 'versions' => listVersionSnapshots('run', $slug)]);
} catch (Throwable $error) {
    /* Der genaue Grund gehoert ins Serverprotokoll, nicht zum Aufrufer: Pfade,
       Klassennamen und Zeilennummern sagen einem Fremden mehr ueber die Anlage,
       als er wissen muss. */
    error_log('[playbooks] Versionsfehler: ' . $error);
    jsonRespond(['ok' => false, 'error' => 'Versionsfehler. Bitte im Serverprotokoll nachsehen.'], 500);
}
