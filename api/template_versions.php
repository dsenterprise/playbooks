<?php

declare(strict_types=1);

require_once __DIR__ . '/_versions.php';
require_once __DIR__ . '/_guard.php';

requireApiAuth();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') jsonRespond(['ok' => false, 'error' => 'Methode nicht erlaubt.'], 405);
    $templateId = cleanText($_GET['template_id'] ?? '', 80);
    if (!versionKeyIsValid('template', $templateId)) jsonRespond(['ok' => false, 'error' => 'Ungültige Template-ID.'], 422);
    if (isset($_GET['version'])) {
        $version = (int) $_GET['version'];
        $snapshot = readVersionSnapshot('template', $templateId, $version);
        if ($snapshot === null) jsonRespond(['ok' => false, 'error' => 'Version nicht gefunden.'], 404);
        jsonRespond(['ok' => true, 'template' => snapshotRecord($snapshot), 'metadata' => $snapshot['_snapshot'] ?? null]);
    }
    jsonRespond(['ok' => true, 'template_id' => $templateId, 'versions' => listVersionSnapshots('template', $templateId)]);
} catch (Throwable $error) {
    /* Der genaue Grund gehoert ins Serverprotokoll, nicht zum Aufrufer: Pfade,
       Klassennamen und Zeilennummern sagen einem Fremden mehr ueber die Anlage,
       als er wissen muss. */
    error_log('[playbooks] Versionsfehler: ' . $error);
    jsonRespond(['ok' => false, 'error' => 'Versionsfehler. Bitte im Serverprotokoll nachsehen.'], 500);
}
