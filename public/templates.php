<?php
require_once __DIR__ . '/../includes/session.php';
requirePlaybookLogin();
$activePage = 'templates';
$pageTitle = 'Playbook-Templates';
include __DIR__ . '/../includes/header.php';
?>
<section class="page-heading">
    <div><a class="back-link" href="./">Übersicht</a><p class="eyebrow">Bibliothek</p><h1>Playbook-Templates</h1><p class="lead">Wiederverwendbare Abläufe zentral pflegen und schnell auffinden.</p></div>
    <button id="btnCreate" class="btn btn-primary" type="button">Template anlegen</button>
</section>
<section class="filter-bar" aria-label="Templates filtern">
    <label class="search-field"><span class="sr-only">Suche</span><svg aria-hidden="true" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg><input id="searchInput" type="search" placeholder="Name oder Beschreibung durchsuchen"></label>
    <label class="select-field"><span>Kategorie</span><select id="categoryFilter"><option value="">Alle Kategorien</option></select></label>
    <p id="resultCount" class="result-count" aria-live="polite">Wird geladen…</p>
</section>
<section id="templateList" class="template-list" aria-live="polite"><div class="skeleton-grid"><i></i><i></i><i></i></div></section>
<dialog id="createDialog" class="modal"><form id="createForm" class="modal-card"><p class="eyebrow">Neue Vorlage</p><h2>Template anlegen</h2><label class="field"><span>Name</span><input id="createName" required maxlength="160" placeholder="z. B. API-Integration"></label><label class="field"><span>Kategorie</span><select id="createCategory"></select></label><div class="modal-actions"><button type="button" class="btn btn-secondary" data-close="createDialog">Abbrechen</button><button type="submit" class="btn btn-primary">Anlegen</button></div></form></dialog>
<script>
(() => {
    const list = document.querySelector('#templateList'), search = document.querySelector('#searchInput'), filter = document.querySelector('#categoryFilter');
    let templates = [], categories = [];
    const category = id => categories.find(item => item.id === id) || {name:'Ohne Kategorie',color:'#64748b'};
    async function load() {
        try {
            const [t, c] = await Promise.all([Playbooks.request('api/templates.php?action=list'), Playbooks.request('api/categories.php')]);
            templates = t.templates; categories = c.categories;
            const current = filter.value;
            filter.replaceChildren(new Option('Alle Kategorien',''), ...categories.map(item => new Option(item.name,item.id))); filter.value = current;
            document.querySelector('#createCategory').replaceChildren(...categories.map(item => new Option(item.name,item.id))); document.querySelector('#createCategory').value = 'cat-starter';
            render();
        } catch (error) { list.innerHTML = `<div class="empty-state"><h2>Laden fehlgeschlagen</h2><p>${Playbooks.escape(error.message)}</p></div>`; }
    }
    function render() {
        const term = search.value.trim().toLocaleLowerCase('de');
        const visible = templates.filter(item => (!filter.value || item.category_id === filter.value) && (!term || `${item.name} ${item.description || ''}`.toLocaleLowerCase('de').includes(term)));
        document.querySelector('#resultCount').textContent = `${visible.length} von ${templates.length}`;
        if (!visible.length) { list.innerHTML = '<div class="empty-state"><h2>Keine Templates gefunden</h2><p>Suche oder Filter anpassen.</p></div>'; return; }
        list.innerHTML = `<div class="template-grid">${visible.map(item => { const cat = category(item.category_id); return `<article class="template-card" data-id="${Playbooks.escape(item.id)}"><div class="card-top"><span class="category-chip" style="--chip:${cat.color}">${Playbooks.escape(cat.name)}</span><span class="status-dot ${item.is_active ? 'is-active' : ''}">${item.is_active ? 'Aktiv' : 'Inaktiv'}</span></div><div><h2><a href="template.php?id=${encodeURIComponent(item.id)}">${Playbooks.escape(item.name)}</a></h2><p>${Playbooks.escape(item.description || 'Noch keine Beschreibung hinterlegt.')}</p></div><dl class="card-metrics"><div><dt>Phasen</dt><dd>${item.phase_count}</dd></div><div><dt>Dateien</dt><dd>${item.file_count}</dd></div><div><dt>Version</dt><dd>${item.version}</dd></div></dl><div class="card-actions"><a class="btn btn-secondary btn-small" href="template.php?id=${encodeURIComponent(item.id)}">Bearbeiten</a><button class="icon-btn" data-action="duplicate" aria-label="${Playbooks.escape(item.name)} duplizieren" title="Duplizieren">${Playbooks.icon('copy')}</button><button class="icon-btn danger" data-action="delete" aria-label="${Playbooks.escape(item.name)} löschen" title="Löschen">${Playbooks.icon('trash')}</button></div></article>`; }).join('')}</div>`;
    }
    document.querySelector('#btnCreate').addEventListener('click', () => { document.querySelector('#createForm').reset(); document.querySelector('#createCategory').value='cat-starter'; document.querySelector('#createDialog').showModal(); document.querySelector('#createName').focus(); });
    document.querySelector('#createForm').addEventListener('submit', async event => { event.preventDefault(); const button=event.submitter; button.disabled=true; try { const data=await Playbooks.request('api/templates.php',{method:'POST',body:JSON.stringify({action:'create',name:document.querySelector('#createName').value,category_id:document.querySelector('#createCategory').value})}); document.querySelector('#createDialog').close(); location.href=`template.php?id=${encodeURIComponent(data.id)}`; } catch(error){ Playbooks.toast(error.message,'error'); button.disabled=false; } });
    list.addEventListener('click', async event => { const button=event.target.closest('[data-action]'); if(!button)return; const item=templates.find(t=>t.id===button.closest('[data-id]').dataset.id); if(button.dataset.action==='delete' && !await Playbooks.confirm(`Template „${item.name}“ wirklich löschen?`,'Template löschen'))return; button.disabled=true; try{ await Playbooks.request('api/templates.php',{method:'POST',body:JSON.stringify({action:button.dataset.action,id:item.id})}); Playbooks.toast(button.dataset.action==='delete'?'Template gelöscht.':'Kopie angelegt.'); await load(); }catch(error){Playbooks.toast(error.message,'error');button.disabled=false;} });
    document.querySelectorAll('[data-close]').forEach(button=>button.addEventListener('click',()=>document.querySelector(`#${button.dataset.close}`).close())); search.addEventListener('input',render); filter.addEventListener('change',render); load();
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
