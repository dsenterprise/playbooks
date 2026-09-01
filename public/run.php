<?php
require_once __DIR__ . '/../includes/session.php';
requirePlaybookLogin();
$activePage = 'runs';
$pageTitle = 'Durchführung';
include __DIR__ . '/../includes/header.php';
?>
<div id="runLoading" class="editor-loading">Durchführung wird geladen…</div>
<div id="runApp" hidden>
    <section class="run-heading"><div><a class="back-link" href="runs.php">Alle Durchführungen</a><p class="eyebrow" id="runTemplateName"></p><h1 id="runTitle"></h1><p id="runVersionMeta" class="muted"></p></div><div class="run-progress"><strong id="runProgressText"></strong><div class="progress"><i id="runProgressBar"></i></div><span id="autosaveStatus" class="save-status" aria-live="polite">Alle Eingaben gespeichert</span></div></section>
    <nav id="phaseSteps" class="phase-steps" aria-label="Phasen"></nav>
    <div class="run-layout">
        <main class="run-main"><section id="phasePanel" class="run-panel"></section></main>
        <aside class="run-aside"><section class="run-panel result-panel"><p class="eyebrow">Ergebnis</p><h2>Dateien</h2><p class="muted">Vorlagen mit den bisherigen Eingaben erzeugen.</p><label class="check-line"><input id="overwriteFiles" type="checkbox"> Vorhandene ersetzen</label><button id="generateFiles" class="btn btn-primary" type="button">Dateien erzeugen</button><div id="resultFiles" class="result-files"></div><a id="downloadZip" class="btn btn-secondary zip-link" href="#">Alles als ZIP herunterladen</a></section><section class="run-panel version-history compact"><p class="eyebrow">Nachvollziehbarkeit</p><h2>Versionsverlauf</h2><div id="runVersionList" class="version-list" aria-live="polite"></div></section></aside>
    </div>
</div>
<dialog id="runVersionDialog" class="modal version-dialog"><form method="dialog" class="modal-card"><p class="eyebrow">Schreibgeschützte Ansicht</p><div id="runVersionContent"></div><div class="modal-actions"><button class="btn btn-secondary" value="close">Schließen</button></div></form></dialog>
<script type="module" src="assets/js/run.js?v=5"></script>
<script>document.addEventListener('click',event=>{const button=event.target.closest('#copyPrompt');if(button)button.textContent='Kopiert';},true);</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
