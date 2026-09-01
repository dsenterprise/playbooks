<?php
require_once __DIR__ . '/includes/session.php';
requirePlaybookLogin();
$activePage = 'templates';
$pageTitle = 'Template bearbeiten';
include __DIR__ . '/includes/header.php';
?>
<section class="editor-heading">
    <div><a class="back-link" href="templates.php">Alle Templates</a><p class="eyebrow">Template-Editor</p><h1 id="templateTitle">Template wird geladen…</h1><p id="templateMeta" class="muted"></p></div>
    <div class="save-cluster"><span id="saveStatus" class="save-status" aria-live="polite">Noch keine Änderungen</span><button id="saveTemplate" class="btn btn-primary" type="button">Änderungen speichern</button></div>
</section>
<nav class="tabs" aria-label="Bereiche">
    <button class="tab is-active" id="tabBasis" data-tab="basis" aria-selected="true">Basis</button>
    <button class="tab" id="tabPhases" data-tab="phases" aria-selected="false">Phasen <span id="phaseBadge">0</span></button>
    <button class="tab" id="tabFiles" data-tab="files" aria-selected="false">Dateien <span id="fileBadge">0</span></button>
</nav>
<div id="editorLoading" class="editor-loading">Template wird vorbereitet…</div>
<form id="templateForm" hidden>
    <section class="tab-panel is-active" id="panel-basis" data-panel="basis">
        <div class="panel-heading"><div><p class="eyebrow">Grundlagen</p><h2>Basisdaten</h2><p>Diese Angaben erscheinen in der Template-Bibliothek.</p></div></div>
        <div class="form-card form-grid">
            <label class="field field-wide"><span>Name <b aria-hidden="true">*</b></span><input id="basisName" required maxlength="160" autocomplete="off"></label>
            <label class="field field-wide"><span>Beschreibung</span><textarea id="basisDescription" rows="4" maxlength="2000" placeholder="Wofür wird dieses Playbook eingesetzt?"></textarea></label>
            <label class="field"><span>Kategorie</span><select id="basisCategory"></select></label>
            <label class="switch-field"><span><strong>Template aktiv</strong><small>In der Bibliothek als einsatzbereit markieren.</small></span><input id="basisActive" type="checkbox" role="switch"></label>
        </div>
    </section>
    <section class="tab-panel" id="panel-phases" data-panel="phases" hidden>
        <div class="panel-heading"><div><p class="eyebrow">Ablauf</p><h2>Phasen</h2><p>Schritte, KI-Prompts und benötigte Eingaben definieren.</p></div><button id="addPhase" type="button" class="btn btn-primary">Phase hinzufügen</button></div>
        <div id="phasesContainer" class="stack-list"></div>
    </section>
    <section class="tab-panel" id="panel-files" data-panel="files" hidden>
        <div class="panel-heading"><div><p class="eyebrow">Ergebnisse</p><h2>Dateien</h2><p>Vorlagen für Markdown- oder JSON-Ergebnisse hinterlegen.</p></div><div class="split-actions"><button id="addMarkdown" type="button" class="btn btn-secondary">Markdown hinzufügen</button><button id="addJson" type="button" class="btn btn-primary">JSON hinzufügen</button></div></div>
        <div id="filesContainer" class="stack-list"></div>
    </section>
</form>
<section class="version-history" id="templateVersionHistory">
    <div class="version-heading"><div><p class="eyebrow">Nachvollziehbarkeit</p><h2>Versionsverlauf</h2><p class="muted">Frühere Stände können schreibgeschützt geladen werden.</p></div><span class="version-current" id="currentTemplateVersion">Aktuelle Version —</span></div>
    <div id="templateVersionList" class="version-list" aria-live="polite">Versionen werden geladen…</div>
</section>
<dialog id="templateVersionDialog" class="modal version-dialog"><form method="dialog" class="modal-card"><p class="eyebrow">Schreibgeschützte Ansicht</p><div id="templateVersionContent"></div><div class="modal-actions"><button class="btn btn-secondary" value="close">Schließen</button></div></form></dialog>
<link rel="stylesheet" href="/assets/vendor/codemirror/codemirror.min.css">
<script src="/assets/vendor/codemirror/codemirror.min.js"></script>
<script src="/assets/vendor/codemirror/markdown.min.js"></script>
<script src="/assets/vendor/codemirror/javascript.min.js"></script>
<script type="module" src="assets/js/template.js?v=2"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
