<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/_store.php';
require_once __DIR__ . '/../../includes/_versions.php';
require_once __DIR__ . '/../../includes/_guard.php';

requireApiAuth();

try {
    $body = $_SERVER['REQUEST_METHOD'] === 'POST' ? getRequestBody() : [];
    $action = cleanText($_GET['action'] ?? $body['action'] ?? '', 30);

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
        $result = [];
        foreach (loadTemplatesVersioned() as $template) {
            $summary = $template;
            unset($summary['phases'], $summary['files']);
            $summary['phase_count'] = count($template['phases'] ?? []);
            $summary['file_count'] = count($template['files'] ?? []);
            $result[] = $summary;
        }
        jsonRespond(['ok' => true, 'templates' => $result]);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get') {
        $template = findTemplate(loadTemplatesVersioned(), cleanText($_GET['id'] ?? '', 80));
        $template !== null
            ? jsonRespond(['ok' => true, 'template' => $template])
            : jsonRespond(['ok' => false, 'error' => 'Template nicht gefunden.'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonRespond(['ok' => false, 'error' => 'Methode oder Aktion nicht erlaubt.'], 405);
    }

    if ($action === 'create') {
        $name = cleanText($body['name'] ?? '', 160);
        if ($name === '') {
            jsonRespond(['ok' => false, 'error' => 'Ein Name ist erforderlich.'], 422);
        }
        $now = nowIso();
        $template = [
            'id' => generateId('tpl'),
            'name' => $name,
            'description' => cleanText($body['description'] ?? '', 2000),
            'category_id' => validCategory($body['category_id'] ?? 'cat-starter'),
            'is_active' => true,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
            'phases' => [],
            'files' => [],
        ];
        $templates = loadTemplatesVersioned();
        $templates[] = $template;
        dbWriteAtomic('templates.json', $templates);
        jsonRespond(['ok' => true, 'id' => $template['id'], 'template' => $template], 201);
    }

    $id = cleanText($_GET['id'] ?? $body['id'] ?? '', 80);
    if ($id === '') {
        jsonRespond(['ok' => false, 'error' => 'Parameter id fehlt.'], 400);
    }
    $templates = loadTemplatesVersioned();
    $index = findTemplateIndex($templates, $id);
    if ($index === null) {
        jsonRespond(['ok' => false, 'error' => 'Template nicht gefunden.'], 404);
    }

    if ($action === 'delete') {
        array_splice($templates, $index, 1);
        dbWriteAtomic('templates.json', $templates);
        jsonRespond(['ok' => true, 'deleted' => $id]);
    }

    if ($action === 'duplicate') {
        $copy = $templates[$index];
        $copy['id'] = generateId('tpl');
        $copy['name'] = cleanText(($copy['name'] ?? '') . ' (Kopie)', 160);
        $copy['version'] = 1;
        $copy['created_at'] = $copy['updated_at'] = nowIso();
        $templates[] = $copy;
        dbWriteAtomic('templates.json', $templates);
        jsonRespond(['ok' => true, 'id' => $copy['id'], 'template' => $copy], 201);
    }

    if ($action === 'update') {
        $previous = $templates[$index];
        $previousVersion = max(1, (int) ($previous['version'] ?? 1));
        writeVersionSnapshot('template', $id, $previousVersion, $previous);
        $updated = normalizeTemplate($body, $templates[$index]);
        $updated['id'] = $id;
        $updated['created_at'] = $templates[$index]['created_at'];
        $updated['updated_at'] = nowIso();
        $updated['version'] = $previousVersion + 1;
        $templates[$index] = $updated;
        dbWriteAtomic('templates.json', $templates);
        jsonRespond(['ok' => true, 'template' => $updated]);
    }

    jsonRespond(['ok' => false, 'error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $error) {
    /* Der genaue Grund gehoert ins Serverprotokoll, nicht zum Aufrufer: Pfade,
       Klassennamen und Zeilennummern sagen einem Fremden mehr ueber die Anlage,
       als er wissen muss. */
    error_log('[playbooks] Speicherfehler: ' . $error);
    jsonRespond(['ok' => false, 'error' => 'Speicherfehler. Bitte im Serverprotokoll nachsehen.'], 500);
}

function loadTemplatesVersioned(): array
{
    $templates = dbRead('templates.json', []);
    $changed = false;
    foreach ($templates as &$template) {
        if (!isset($template['version']) || (int) $template['version'] < 1) {
            $template['version'] = 1;
            $changed = true;
        } else {
            $template['version'] = (int) $template['version'];
        }
    }
    unset($template);
    if ($changed) dbWriteAtomic('templates.json', $templates);
    return $templates;
}

function findTemplate(array $templates, string $id): ?array
{
    $index = findTemplateIndex($templates, $id);
    return $index === null ? null : $templates[$index];
}

function findTemplateIndex(array $templates, string $id): ?int
{
    foreach ($templates as $index => $template) {
        if (($template['id'] ?? '') === $id) {
            return $index;
        }
    }
    return null;
}

function validCategory(mixed $categoryId): string
{
    $id = cleanText($categoryId, 80);
    $valid = array_column(dbRead('categories.json', defaultCategories()), 'id');
    return in_array($id, $valid, true) ? $id : 'cat-starter';
}

function normalizeTemplate(array $input, array $existing): array
{
    $name = cleanText($input['name'] ?? '', 160);
    if ($name === '') {
        jsonRespond(['ok' => false, 'error' => 'Ein Name ist erforderlich.'], 422);
    }

    $phases = [];
    foreach (($input['phases'] ?? []) as $step => $phase) {
        if (!is_array($phase)) continue;
        $fields = [];
        foreach (($phase['fields'] ?? []) as $field) {
            if (!is_array($field)) continue;
            $type = in_array($field['type'] ?? '', ['text', 'textarea', 'checkbox'], true) ? $field['type'] : 'text';
            $fields[] = ['key' => preg_replace('/[^a-zA-Z0-9_]/', '', cleanText($field['key'] ?? '', 80)), 'type' => $type, 'label' => cleanText($field['label'] ?? '', 160), 'placeholder' => cleanText($field['placeholder'] ?? '', 300), 'required' => (bool) ($field['required'] ?? false)];
        }
        if ($fields === []) {
            $fields[] = ['key' => 'eingabe', 'type' => 'text', 'label' => 'Eingabe', 'placeholder' => '', 'required' => true];
        }
        $icon = in_array($phase['icon'] ?? '', ['Search', 'Code', 'FileText', 'CheckCircle', 'Rocket'], true) ? $phase['icon'] : 'FileText';
        $phases[] = ['step' => $step, 'title' => cleanText($phase['title'] ?? '', 160), 'description' => cleanText($phase['description'] ?? '', 1000), 'icon' => $icon, 'aiPrompt' => cleanText($phase['aiPrompt'] ?? '', 50000), 'fields' => $fields];
    }

    $files = [];
    foreach (($input['files'] ?? []) as $file) {
        if (!is_array($file)) continue;
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', cleanText($file['filename'] ?? '', 180));
        if ($filename === '') $filename = 'datei.md';
        $type = in_array($file['type'] ?? '', ['md', 'json'], true) ? $file['type'] : 'md';
        /* Die Endung MUSS zum Typ passen. Ohne diese Zeilen kaeme ein Dateiname wie
           test.php durch und landete spaeter im Ergebnisordner. Dass der Webserver
           ihn dort nicht ausfuehrt, ist eine Frage seiner Konfiguration - und die
           darf nicht die einzige Verteidigung sein. */
        $stamm = (string) pathinfo($filename, PATHINFO_FILENAME);
        if ($stamm === '') $stamm = 'datei';
        $filename = $stamm . '.' . $type;
        $content = mb_substr((string) ($file['content'] ?? ''), 0, 1000000);
        $files[] = ['filename' => $filename, 'type' => $type, 'description' => cleanText($file['description'] ?? '', 1000), 'required' => (bool) ($file['required'] ?? false), 'prompt' => cleanText($file['prompt'] ?? '', 50000), 'content' => $content];
    }

    return ['name' => $name, 'description' => cleanText($input['description'] ?? '', 2000), 'category_id' => validCategory($input['category_id'] ?? ''), 'is_active' => (bool) ($input['is_active'] ?? false), 'phases' => $phases, 'files' => $files] + $existing;
}
