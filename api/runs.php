<?php

declare(strict_types=1);

require_once __DIR__ . '/_store.php';
require_once __DIR__ . '/_versions.php';
require_once __DIR__ . '/_guard.php';

requireApiAuth();

const RESULTS_DIR = __DIR__ . '/../results';

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body = $method === 'POST' ? getRequestBody() : [];
    $action = cleanText($_GET['action'] ?? $body['action'] ?? '', 40);

    if ($method === 'GET' && isset($_GET['run_id'], $_GET['version'])) {
        $runId = cleanText($_GET['run_id'], 80);
        $currentRun = requireRun($runId);
        $snapshot = readVersionSnapshot('run', (string) $currentRun['slug'], (int) $_GET['version']);
        if ($snapshot === null) jsonRespond(['ok' => false, 'error' => 'Run-Version nicht gefunden.'], 404);
        $versionedRun = snapshotRecord($snapshot);
        jsonRespond(['ok' => true, 'run' => $versionedRun, 'template' => templateForRun($versionedRun, templateMap()), 'read_only' => true]);
    }

    if ($method === 'GET' && $action === 'list') {
        $templates = templateMap();
        $items = [];
        foreach (loadRunsVersioned() as $run) {
            $summary = $run;
            unset($summary['phase_data']);
            $template = templateForRun($run, $templates);
            $summary['phase_count'] = count($template['phases'] ?? []);
            $summary['completed_count'] = completedCount($run['phase_data'] ?? []);
            $items[] = $summary;
        }
        usort($items, static fn(array $a, array $b): int => strcmp($b['updated_at'], $a['updated_at']));
        jsonRespond(['ok' => true, 'runs' => $items]);
    }

    if ($method === 'GET' && $action === 'get') {
        $run = requireRun(cleanText($_GET['id'] ?? '', 80));
        $template = templateForRun($run, templateMap());
        jsonRespond(['ok' => true, 'run' => $run, 'template' => $template]);
    }

    if ($method === 'GET' && $action === 'file') {
        serveResultFile(cleanText($_GET['id'] ?? '', 80), (string) ($_GET['filename'] ?? ''), ($_GET['download'] ?? '') === '1');
    }

    if ($method === 'GET' && $action === 'download') {
        serveZip(cleanText($_GET['id'] ?? '', 80));
    }

    if ($method !== 'POST') {
        jsonRespond(['ok' => false, 'error' => 'Methode oder Aktion nicht erlaubt.'], 405);
    }

    if ($action === 'create') {
        $name = cleanText($body['name'] ?? '', 160);
        if ($name === '') jsonRespond(['ok' => false, 'error' => 'Ein Name ist erforderlich.'], 422);
        $templateId = cleanText($body['template_id'] ?? '', 80);
        $template = templateMap()[$templateId] ?? null;
        if ($template === null) jsonRespond(['ok' => false, 'error' => 'Template nicht gefunden.'], 404);
        $runs = loadRunsVersioned();
        $now = nowIso();
        $run = ['id' => generateId('run'), 'name' => $name, 'slug' => uniqueSlug($name, $runs), 'template_id' => $templateId, 'template_name' => $template['name'], 'template_version' => max(1, (int) ($template['version'] ?? 1)), 'run_version' => 1, 'status' => 'draft', 'current_phase' => 0, 'phase_data' => [], 'generated_files' => [], 'created_at' => $now, 'updated_at' => $now];
        $runs[] = $run;
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'id' => $run['id'], 'run' => $run], 201);
    }

    $id = cleanText($body['id'] ?? '', 80);
    $runs = loadRunsVersioned();
    $index = runIndex($runs, $id);
    if ($index === null) jsonRespond(['ok' => false, 'error' => 'Durchführung nicht gefunden.'], 404);
    $template = templateForRun($runs[$index], templateMap());

    if ($action === 'delete') {
        deleteResultDirectory($runs[$index]);
        array_splice($runs, $index, 1);
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'deleted' => $id]);
    }

    if ($action === 'duplicate') {
        $source = $runs[$index];
        writeVersionSnapshot('run', (string) $source['slug'], max(1, (int) ($source['run_version'] ?? 1)), $source);
        $now = nowIso();
        $copy = $source;
        $copy['id'] = generateId('run');
        $copy['name'] = cleanText($source['name'] . ' (Kopie)', 160);
        $copy['slug'] = uniqueSlug($copy['name'], $runs);
        $copy['run_version'] = 1;
        $copy['parent_run_id'] = $source['id'];
        $copy['generated_files'] = [];
        $copy['created_at'] = $copy['updated_at'] = $now;
        $runs[] = $copy;
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'id' => $copy['id'], 'run' => $copy], 201);
    }

    if ($template === null) jsonRespond(['ok' => false, 'error' => 'Das zugehörige Template wurde gelöscht.'], 409);

    if ($action === 'save') {
        $normalized = normalizePhaseData($body['phase_data'] ?? [], $runs[$index]['phase_data'] ?? [], $template);
        $phaseCount = count($template['phases'] ?? []);
        if (($runs[$index]['status'] ?? '') === 'completed' && $normalized !== ($runs[$index]['phase_data'] ?? [])) {
            $fork = forkRunVersion($runs[$index], $runs);
            $fork['phase_data'] = $normalized;
            $fork['current_phase'] = max(0, min((int) ($body['current_phase'] ?? 0), max(0, $phaseCount - 1)));
            touchRun($fork, $phaseCount);
            $runs[] = $fork;
            dbWriteAtomic('runs.json', $runs);
            jsonRespond(['ok' => true, 'run' => $fork, 'forked' => true], 201);
        }
        $runs[$index]['phase_data'] = $normalized;
        $runs[$index]['current_phase'] = max(0, min((int) ($body['current_phase'] ?? 0), max(0, $phaseCount - 1)));
        touchRun($runs[$index], $phaseCount);
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'run' => $runs[$index]]);
    }

    if ($action === 'complete_phase' || $action === 'reopen_phase') {
        $step = (int) ($body['step'] ?? -1);
        $phaseCount = count($template['phases'] ?? []);
        if ($step < 0 || $step >= $phaseCount) jsonRespond(['ok' => false, 'error' => 'Ungültige Phase.'], 422);
        $key = 'phase_' . $step;
        $runs[$index]['phase_data'][$key] ??= [];
        if ($action === 'complete_phase') {
            $runs[$index]['phase_data'][$key]['completed'] = true;
            $runs[$index]['phase_data'][$key]['completed_at'] = nowIso();
            $runs[$index]['current_phase'] = min($step + 1, max(0, $phaseCount - 1));
        } else {
            if (($runs[$index]['status'] ?? '') === 'completed') {
                $fork = forkRunVersion($runs[$index], $runs);
                $fork['phase_data'][$key]['completed'] = false;
                unset($fork['phase_data'][$key]['completed_at']);
                $fork['current_phase'] = $step;
                touchRun($fork, $phaseCount);
                $runs[] = $fork;
                dbWriteAtomic('runs.json', $runs);
                jsonRespond(['ok' => true, 'run' => $fork, 'forked' => true], 201);
            }
            $runs[$index]['phase_data'][$key]['completed'] = false;
            unset($runs[$index]['phase_data'][$key]['completed_at']);
            $runs[$index]['current_phase'] = $step;
        }
        touchRun($runs[$index], $phaseCount);
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'run' => $runs[$index]]);
    }

    if ($action === 'generate') {
        $states = generateFiles($runs[$index], $template, (bool) ($body['overwrite'] ?? false));
        $runs[$index]['updated_at'] = nowIso();
        dbWriteAtomic('runs.json', $runs);
        jsonRespond(['ok' => true, 'files' => $states, 'run' => $runs[$index]]);
    }

    jsonRespond(['ok' => false, 'error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $error) {
    /* Der genaue Grund gehoert ins Serverprotokoll, nicht zum Aufrufer: Pfade,
       Klassennamen und Zeilennummern sagen einem Fremden mehr ueber die Anlage,
       als er wissen muss. */
    error_log('[playbooks] Fehler: ' . $error);
    jsonRespond(['ok' => false, 'error' => 'Fehler. Bitte im Serverprotokoll nachsehen.'], 500);
}

function templateMap(): array
{
    $map = [];
    foreach (dbRead('templates.json', []) as $template) $map[$template['id']] = $template;
    return $map;
}

function templateForRun(array $run, array $templates): ?array
{
    $current = $templates[$run['template_id']] ?? null;
    $version = max(1, (int) ($run['template_version'] ?? 1));
    if ($current !== null && (int) ($current['version'] ?? 1) === $version) return $current;
    $snapshot = readVersionSnapshot('template', (string) $run['template_id'], $version);
    return $snapshot === null ? $current : snapshotRecord($snapshot);
}

function loadRunsVersioned(): array
{
    $runs = dbRead('runs.json', []);
    $templates = templateMap();
    $changed = false;
    foreach ($runs as &$run) {
        if (!isset($run['run_version']) || (int) $run['run_version'] < 1) { $run['run_version'] = 1; $changed = true; }
        else $run['run_version'] = (int) $run['run_version'];
        if (!isset($run['template_version']) || (int) $run['template_version'] < 1) {
            $run['template_version'] = max(1, (int) ($templates[$run['template_id']]['version'] ?? 1));
            $changed = true;
        } else $run['template_version'] = (int) $run['template_version'];
    }
    unset($run);
    if ($changed) dbWriteAtomic('runs.json', $runs);
    return $runs;
}

function forkRunVersion(array $source, array $runs): array
{
    $sourceVersion = max(1, (int) ($source['run_version'] ?? 1));
    writeVersionSnapshot('run', (string) $source['slug'], $sourceVersion, $source);
    $fork = $source;
    $fork['id'] = generateId('run');
    $fork['slug'] = uniqueSlug((string) $source['name'], $runs);
    $fork['run_version'] = $sourceVersion + 1;
    $fork['parent_run_id'] = $source['id'];
    $fork['generated_files'] = [];
    $fork['created_at'] = $fork['updated_at'] = nowIso();
    return $fork;
}

function runIndex(array $runs, string $id): ?int
{
    foreach ($runs as $index => $run) if (($run['id'] ?? '') === $id) return $index;
    return null;
}

function requireRun(string $id): array
{
    $runs = loadRunsVersioned();
    $index = runIndex($runs, $id);
    if ($index === null) jsonRespond(['ok' => false, 'error' => 'Durchführung nicht gefunden.'], 404);
    return $runs[$index];
}

function completedCount(array $phaseData): int
{
    return count(array_filter($phaseData, static fn(array $data): bool => ($data['completed'] ?? false) === true));
}

function touchRun(array &$run, int $phaseCount): void
{
    $completed = completedCount($run['phase_data'] ?? []);
    $hasValues = false;
    foreach (($run['phase_data'] ?? []) as $data) {
        foreach ($data as $key => $value) if (!in_array($key, ['completed', 'completed_at'], true) && $value !== '' && $value !== false) $hasValues = true;
    }
    $run['status'] = $phaseCount > 0 && $completed >= $phaseCount ? 'completed' : ($hasValues || $completed > 0 ? 'in_progress' : 'draft');
    $run['updated_at'] = nowIso();
}

function normalizePhaseData(mixed $input, array $existing, array $template): array
{
    if (!is_array($input)) return $existing;
    $result = [];
    foreach (($template['phases'] ?? []) as $step => $phase) {
        $key = 'phase_' . $step;
        $source = is_array($input[$key] ?? null) ? $input[$key] : [];
        $current = is_array($existing[$key] ?? null) ? $existing[$key] : [];
        $values = [];
        foreach (($phase['fields'] ?? []) as $field) {
            $fieldKey = (string) ($field['key'] ?? '');
            if ($fieldKey === '' || in_array($fieldKey, ['completed', 'completed_at'], true)) continue;
            $values[$fieldKey] = ($field['type'] ?? '') === 'checkbox' ? (bool) ($source[$fieldKey] ?? false) : cleanText($source[$fieldKey] ?? '', 10000);
        }
        if (($current['completed'] ?? false) === true) {
            $values['completed'] = true;
            $values['completed_at'] = $current['completed_at'] ?? nowIso();
        }
        if ($values !== [] || isset($existing[$key])) $result[$key] = $values;
    }
    return $result;
}

function uniqueSlug(string $name, array $runs): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($name)) ?: '';
    $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
    if ($base === '') $base = 'durchfuehrung';
    $used = array_column($runs, 'slug');
    $slug = $base;
    for ($number = 2; in_array($slug, $used, true) || is_dir(RESULTS_DIR . '/' . $slug); $number++) $slug = $base . '-' . $number;
    return $slug;
}

function variableMap(array $run, array $template): array
{
    $variables = ['projektname' => $run['name']];
    foreach (($template['phases'] ?? []) as $step => $phase) {
        $data = $run['phase_data']['phase_' . $step] ?? [];
        foreach (($phase['fields'] ?? []) as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '' || in_array($key, ['completed', 'completed_at'], true) || !array_key_exists($key, $data)) continue;
            $variables[$key] = ($field['type'] ?? '') === 'checkbox' ? ($data[$key] ? 'ja' : 'nein') : (string) $data[$key];
        }
    }
    return $variables;
}

function resolveVariables(string $content, array $variables): string
{
    return preg_replace_callback('/{{\s*([a-zA-Z0-9_]+)\s*}}/', static fn(array $match): string => array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : $match[0], $content);
}

function validFilename(string $filename): bool
{
    /* Zweites Netz: auch Vorlagen, die vor der Endungspruefung gespeichert wurden,
       koennen hier keine ausfuehrbare Datei erzeugen. Erlaubt sind genau die zwei
       Formate, die die Anwendung schreibt. */
    $endung = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
    return $filename !== '' && $filename[0] !== '.' && !str_contains($filename, '..')
        && preg_match('/^[A-Za-z0-9._-]+$/', $filename) === 1
        && in_array($endung, ['md', 'json'], true);
}

function resultBase(): string
{
    $base = realpath(RESULTS_DIR);
    if ($base === false || !is_dir($base)) throw new RuntimeException('Ergebnisverzeichnis fehlt.');
    return $base;
}

function resultDirectory(array $run, bool $create = false): string
{
    $base = resultBase();
    $slug = (string) ($run['slug'] ?? '');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) throw new RuntimeException('Ungültiger Ergebnisordner.');
    $directory = $base . '/' . $slug;
    if ($create && !is_dir($directory) && !mkdir($directory, 0775)) throw new RuntimeException('Ergebnisordner konnte nicht angelegt werden.');
    if (is_dir($directory) && !str_starts_with((string) realpath($directory), $base . DIRECTORY_SEPARATOR)) throw new RuntimeException('Unsicherer Ergebnisordner.');
    return $directory;
}

function generateFiles(array &$run, array $template, bool $overwrite): array
{
    $directory = resultDirectory($run, true);
    $variables = variableMap($run, $template);
    $metadata = [];
    $states = [];
    foreach (($template['files'] ?? []) as $file) {
        $filename = (string) ($file['filename'] ?? '');
        if (!validFilename($filename)) throw new RuntimeException('Unsicherer Dateiname: ' . $filename);
        $path = $directory . '/' . $filename;
        $exists = is_file($path);
        $state = $exists && !$overwrite ? 'existing' : 'generated';
        if (!$exists || $overwrite) {
            $content = resolveVariables((string) ($file['content'] ?? ''), $variables);
            if (file_put_contents($path, $content, LOCK_EX) === false) throw new RuntimeException('Datei konnte nicht geschrieben werden: ' . $filename);
        }
        clearstatcache(true, $path);
        $item = ['filename' => $filename, 'generated_at' => nowIso(), 'bytes' => filesize($path) ?: 0];
        $metadata[] = $item;
        $states[] = $item + ['state' => $state];
    }
    $run['generated_files'] = $metadata;
    return $states;
}

function safeFilePath(array $run, string $filename): string
{
    if (!validFilename($filename) || !in_array($filename, array_column($run['generated_files'] ?? [], 'filename'), true)) throw new RuntimeException('Datei nicht freigegeben.');
    $directory = resultDirectory($run);
    $path = realpath($directory . '/' . $filename);
    if ($path === false || !str_starts_with($path, resultBase() . DIRECTORY_SEPARATOR)) throw new RuntimeException('Datei nicht gefunden.');
    return $path;
}

function serveResultFile(string $id, string $filename, bool $download): never
{
    $run = requireRun($id);
    $path = safeFilePath($run, $filename);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . ($download ? 'attachment' : 'inline') . '; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

function serveZip(string $id): never
{
    $run = requireRun($id);
    if (($run['generated_files'] ?? []) === []) throw new RuntimeException('Noch keine Dateien erzeugt.');
    $temporary = resultBase() . '/.zip-' . bin2hex(random_bytes(8)) . '.zip';
    $zip = new ZipArchive();
    if ($zip->open($temporary, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP konnte nicht erstellt werden.');
    foreach ($run['generated_files'] as $file) {
        $path = safeFilePath($run, $file['filename']);
        $zip->addFile($path, $file['filename']);
    }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($temporary));
    header('Content-Disposition: attachment; filename="' . $run['slug'] . '.zip"');
    readfile($temporary);
    unlink($temporary);
    exit;
}

function deleteResultDirectory(array $run): void
{
    $directory = resultDirectory($run);
    if (!is_dir($directory)) return;
    foreach (scandir($directory) ?: [] as $filename) {
        if ($filename === '.' || $filename === '..') continue;
        $path = $directory . '/' . $filename;
        if (is_file($path)) unlink($path);
    }
    rmdir($directory);
}
