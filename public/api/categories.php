<?php

declare(strict_types=1);

require_once __DIR__ . '/../../includes/_store.php';
require_once __DIR__ . '/../../includes/_guard.php';

requireApiAuth();

try {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $body = $method === 'POST' ? getRequestBody() : [];
    $action = cleanText($_GET['action'] ?? $body['action'] ?? '', 30);
    $categories = dbRead('categories.json', defaultCategories());

    if ($method === 'GET') {
        sortCategories($categories);
        jsonRespond(['ok' => true, 'categories' => $categories]);
    }

    if ($method !== 'POST') {
        jsonRespond(['ok' => false, 'error' => 'Methode oder Aktion nicht erlaubt.'], 405);
    }

    if ($action === 'create') {
        $category = normalizeCategory($body, $categories);
        $category['id'] = generateId('cat');
        $categories[] = $category;
        dbWriteAtomic('categories.json', $categories);
        jsonRespond(['ok' => true, 'id' => $category['id'], 'category' => $category], 201);
    }

    $id = cleanText($_GET['id'] ?? $body['id'] ?? '', 80);
    if ($id === '') {
        jsonRespond(['ok' => false, 'error' => 'Parameter id fehlt.'], 400);
    }

    $index = categoryIndex($categories, $id);
    if ($index === null) {
        jsonRespond(['ok' => false, 'error' => 'Kategorie nicht gefunden.'], 404);
    }

    if ($action === 'update') {
        $category = normalizeCategory($body, $categories, $categories[$index]);
        $category['id'] = $id;
        $categories[$index] = $category;
        dbWriteAtomic('categories.json', $categories);
        jsonRespond(['ok' => true, 'category' => $category]);
    }

    if ($action === 'delete') {
        array_splice($categories, $index, 1);
        dbWriteAtomic('categories.json', $categories);
        jsonRespond(['ok' => true, 'deleted' => $id]);
    }

    jsonRespond(['ok' => false, 'error' => 'Unbekannte Aktion.'], 400);
} catch (Throwable $error) {
    /* Der genaue Grund gehoert ins Serverprotokoll, nicht zum Aufrufer: Pfade,
       Klassennamen und Zeilennummern sagen einem Fremden mehr ueber die Anlage,
       als er wissen muss. */
    error_log('[playbooks] Speicherfehler: ' . $error);
    jsonRespond(['ok' => false, 'error' => 'Speicherfehler. Bitte im Serverprotokoll nachsehen.'], 500);
}

function sortCategories(array &$categories): void
{
    usort($categories, static fn(array $a, array $b): int => ($a['sort_order'] ?? 999) <=> ($b['sort_order'] ?? 999));
}

function categoryIndex(array $categories, string $id): ?int
{
    foreach ($categories as $index => $category) {
        if (($category['id'] ?? '') === $id) return $index;
    }
    return null;
}

function normalizeCategory(array $input, array $categories, array $existing = []): array
{
    $name = cleanText($input['name'] ?? $existing['name'] ?? '', 160);
    if ($name === '') {
        jsonRespond(['ok' => false, 'error' => 'Ein Name ist erforderlich.'], 422);
    }

    $slug = categorySlug(cleanText($input['slug'] ?? $existing['slug'] ?? $name, 160));
    if ($slug === '') {
        jsonRespond(['ok' => false, 'error' => 'Ein gültiger Slug ist erforderlich.'], 422);
    }
    foreach ($categories as $category) {
        if (($category['id'] ?? '') !== ($existing['id'] ?? '') && ($category['slug'] ?? '') === $slug) {
            jsonRespond(['ok' => false, 'error' => 'Der Slug wird bereits verwendet.'], 409);
        }
    }

    $color = cleanText($input['color'] ?? $existing['color'] ?? '#64748b', 7);
    if (preg_match('/^#[0-9a-fA-F]{6}$/', $color) !== 1) {
        jsonRespond(['ok' => false, 'error' => 'Die Farbe muss im Format #RRGGBB angegeben werden.'], 422);
    }

    return [
        'name' => $name,
        'slug' => $slug,
        'color' => strtolower($color),
        'sort_order' => (int) ($input['sort_order'] ?? $existing['sort_order'] ?? 999),
    ];
}

function categorySlug(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value)) ?: '';
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $ascii), '-');
}
