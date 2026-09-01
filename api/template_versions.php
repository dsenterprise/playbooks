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
    jsonRespond(['ok' => false, 'error' => 'Versionsfehler: ' . $error->getMessage()], 500);
}
