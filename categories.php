<?php
require_once __DIR__ . '/includes/session.php';
requirePlaybookLogin();
$activePage = 'categories';
$pageTitle = 'Kategorien';
include __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div><a class="back-link" href="./">Übersicht</a><p class="eyebrow">Bibliothek</p><h1>Kategorien</h1><p class="lead">Kategorien, Farben und Reihenfolge der Playbook-Templates verwalten.</p></div>
    <button id="btnCreate" class="btn btn-primary" type="button">Kategorie anlegen</button>
</section>
<section class="filter-bar" aria-label="Kategorien filtern">
    <label class="search-field"><span class="sr-only">Suche</span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input id="searchInput" type="search" placeholder="Name oder Slug durchsuchen"></label>
    <p id="resultCount" class="result-count" aria-live="polite">Wird geladen…</p>
</section>
<section id="categoryList" class="template-list" aria-live="polite"><div class="skeleton-grid"><i></i><i></i><i></i></div></section>

<dialog id="categoryDialog" class="modal">
    <form id="categoryForm" class="modal-card">
        <p id="dialogEyebrow" class="eyebrow">Neue Kategorie</p>
        <h2 id="dialogTitle">Kategorie anlegen</h2>
        <input id="categoryId" type="hidden">
        <label class="field"><span>Name</span><input id="categoryName" required maxlength="160" placeholder="z. B. Onboarding"></label>
        <label class="field"><span>Slug</span><input id="categorySlug" required maxlength="160" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" placeholder="z. B. onboarding"></label>
        <label class="field"><span>Farbe</span><input id="categoryColor" type="color" value="#64748b" required></label>
        <label class="field"><span>Sortierung</span><input id="categorySortOrder" type="number" step="1" value="999" required></label>
        <div class="modal-actions"><button type="button" class="btn btn-secondary" data-close="categoryDialog">Abbrechen</button><button id="categorySubmit" type="submit" class="btn btn-primary">Anlegen</button></div>
    </form>
</dialog>
<script>
(() => {
    const list = document.querySelector('#categoryList');
    const search = document.querySelector('#searchInput');
    const dialog = document.querySelector('#categoryDialog');
    const form = document.querySelector('#categoryForm');
    const fields = {
        id: document.querySelector('#categoryId'),
        name: document.querySelector('#categoryName'),
        slug: document.querySelector('#categorySlug'),
        color: document.querySelector('#categoryColor'),
        sortOrder: document.querySelector('#categorySortOrder')
    };
    let categories = [];

    async function load() {
        try {
            const data = await Playbooks.request('api/categories.php');
            categories = data.categories || [];
            render();
        } catch (error) {
            document.querySelector('#resultCount').textContent = 'Nicht verfügbar';
            list.innerHTML = `<div class="empty-state"><h2>Laden fehlgeschlagen</h2><p>${Playbooks.escape(error.message)}</p></div>`;
        }
    }

    function render() {
        const term = search.value.trim().toLocaleLowerCase('de');
        const visible = categories.filter(item => !term || `${item.name} ${item.slug}`.toLocaleLowerCase('de').includes(term));
        document.querySelector('#resultCount').textContent = `${visible.length} von ${categories.length}`;
        if (!visible.length) {
            list.innerHTML = '<div class="empty-state"><h2>Keine Kategorien gefunden</h2><p>Suche anpassen oder eine neue Kategorie anlegen.</p></div>';
            return;
        }
        list.innerHTML = `<div class="template-grid">${visible.map(item => {
            const color = /^#[0-9a-f]{6}$/i.test(item.color) ? item.color : '#64748b';
            return `<article class="template-card" data-id="${Playbooks.escape(item.id)}"><div class="card-top"><span class="category-chip" style="--chip:${color}">${Playbooks.escape(item.name)}</span></div><div><h2>${Playbooks.escape(item.name)}</h2><p>${Playbooks.escape(item.slug)}</p></div><dl class="card-metrics"><div><dt>Farbe</dt><dd>${Playbooks.escape(color)}</dd></div><div><dt>Sortierung</dt><dd>${Playbooks.escape(item.sort_order)}</dd></div></dl><div class="card-actions"><button class="btn btn-secondary btn-small" type="button" data-action="edit">Bearbeiten</button><button class="icon-btn danger" type="button" data-action="delete" aria-label="${Playbooks.escape(item.name)} löschen" title="Löschen">${Playbooks.icon('trash')}</button></div></article>`;
        }).join('')}</div>`;
    }

    function openDialog(item = null) {
        form.reset();
        fields.id.value = item?.id || '';
        fields.name.value = item?.name || '';
        fields.slug.value = item?.slug || '';
        fields.color.value = item?.color || '#64748b';
        fields.sortOrder.value = item?.sort_order ?? 999;
        const editing = Boolean(item);
        document.querySelector('#dialogEyebrow').textContent = editing ? 'Kategorie bearbeiten' : 'Neue Kategorie';
        document.querySelector('#dialogTitle').textContent = editing ? item.name : 'Kategorie anlegen';
        document.querySelector('#categorySubmit').textContent = editing ? 'Speichern' : 'Anlegen';
        dialog.showModal();
        fields.name.focus();
    }

    document.querySelector('#btnCreate').addEventListener('click', () => openDialog());
    document.querySelector('[data-close="categoryDialog"]').addEventListener('click', () => dialog.close());
    search.addEventListener('input', render);

    form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = event.submitter;
        button.disabled = true;
        const editing = Boolean(fields.id.value);
        try {
            await Playbooks.request('api/categories.php', {method: 'POST', body: JSON.stringify({
                action: editing ? 'update' : 'create',
                id: fields.id.value,
                name: fields.name.value,
                slug: fields.slug.value,
                color: fields.color.value,
                sort_order: Number.parseInt(fields.sortOrder.value, 10)
            })});
            dialog.close();
            Playbooks.toast(editing ? 'Kategorie gespeichert.' : 'Kategorie angelegt.');
            await load();
        } catch (error) {
            Playbooks.toast(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    });

    list.addEventListener('click', async event => {
        const button = event.target.closest('[data-action]');
        if (!button) return;
        const item = categories.find(category => category.id === button.closest('[data-id]').dataset.id);
        if (!item) return;
        if (button.dataset.action === 'edit') {
            openDialog(item);
            return;
        }
        if (!await Playbooks.confirm(`Kategorie „${item.name}“ wirklich löschen?`, 'Kategorie löschen')) return;
        button.disabled = true;
        try {
            await Playbooks.request('api/categories.php', {method: 'POST', body: JSON.stringify({action: 'delete', id: item.id})});
            Playbooks.toast('Kategorie gelöscht.');
            await load();
        } catch (error) {
            Playbooks.toast(error.message, 'error');
            button.disabled = false;
        }
    });

    load();
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
