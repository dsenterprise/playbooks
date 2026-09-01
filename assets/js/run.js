const id = new URLSearchParams(location.search).get('id');
let run = null;
let template = null;
let current = 0;
let saveTimer = null;
let changeVersion = 0;
const esc = value => Playbooks.escape(value);

async function load() {
    if (!id) { location.href = 'runs.php'; return; }
    try {
        const data = await Playbooks.request(`api/runs.php?action=get&id=${encodeURIComponent(id)}`);
        run = data.run; template = data.template; current = run.current_phase || 0;
        if (!template) {
            document.querySelector('#runLoading').innerHTML = '<div class="empty-state"><h2>Template nicht mehr vorhanden</h2><p>Diese Durchführung kann ohne ihr Template nicht fortgesetzt werden.</p><a class="btn btn-secondary" href="runs.php">Zurück</a></div>';
            return;
        }
        document.querySelector('#runLoading').hidden = true;
        document.querySelector('#runApp').hidden = false;
        document.querySelector('#runTitle').textContent = run.name;
        document.querySelector('#runTemplateName').textContent = run.template_name;
        document.querySelector('#runVersionMeta').textContent = `Template-Version ${run.template_version || 1} · Durchführungs-Version ${run.run_version || 1}`;
        document.querySelector('#downloadZip').href = `api/runs.php?action=download&id=${encodeURIComponent(id)}`;
        render();
        await loadRunVersions();
    } catch (error) {
        document.querySelector('#runLoading').innerHTML = `<div class="empty-state"><h2>Laden fehlgeschlagen</h2><p>${esc(error.message)}</p><a class="btn btn-secondary" href="runs.php">Zurück</a></div>`;
    }
}

function phaseData(step) {
    const key = `phase_${step}`;
    run.phase_data[key] ||= {};
    return run.phase_data[key];
}

function completedCount() {
    return Object.values(run.phase_data).filter(data => data.completed === true).length;
}

function render() {
    renderProgress(); renderSteps(); renderPhase(); renderFiles();
}

function renderProgress() {
    const done = completedCount(), total = template.phases.length;
    document.querySelector('#runProgressText').textContent = `${done} von ${total} Phasen`;
    document.querySelector('#runProgressBar').style.width = `${total ? done / total * 100 : 0}%`;
}

function renderSteps() {
    document.querySelector('#phaseSteps').innerHTML = template.phases.map((phase, index) => `<button type="button" class="phase-step ${index === current ? 'is-current' : ''} ${phaseData(index).completed ? 'is-complete' : ''}" data-step="${index}"><span>${phaseData(index).completed ? '✓' : index + 1}</span><strong>${esc(phase.title)}</strong></button>`).join('');
}

function variables() {
    const result = {projektname: run.name};
    template.phases.forEach((phase, step) => {
        const data = phaseData(step);
        (phase.fields || []).forEach(field => {
            if (['completed', 'completed_at'].includes(field.key) || !(field.key in data)) return;
            result[field.key] = field.type === 'checkbox' ? (data[field.key] ? 'ja' : 'nein') : data[field.key];
        });
    });
    return result;
}

function resolve(text) {
    const values = variables();
    return String(text || '').replace(/{{\s*([a-zA-Z0-9_]+)\s*}}/g, (whole, key) => Object.hasOwn(values, key) ? values[key] : whole);
}

function unresolved(text) {
    return [...new Set([...String(text).matchAll(/{{\s*([a-zA-Z0-9_]+)\s*}}/g)].map(match => match[1]))];
}

function renderPhase() {
    const phase = template.phases[current];
    if (!phase) { document.querySelector('#phasePanel').innerHTML = '<div class="empty-state"><h2>Keine Phasen</h2><p>Dieses Template enthält keine Phasen.</p></div>'; return; }
    const data = phaseData(current), prompt = resolve(phase.aiPrompt), missing = unresolved(prompt);
    document.querySelector('#phasePanel').innerHTML = `<p class="eyebrow">Phase ${current + 1}</p><h2>${esc(phase.title)}</h2><p class="lead">${esc(phase.description || '')}</p>
        <div class="run-fields">${(phase.fields || []).map(field => fieldMarkup(field, data[field.key])).join('')}</div>
        <section class="prompt-card"><div class="subsection-heading"><h3>KI-Prompt</h3><button type="button" class="btn btn-secondary btn-small" id="copyPrompt">Prompt kopieren</button></div><pre id="resolvedPrompt">${esc(prompt)}</pre><div id="unresolvedVariables" class="variable-list"><span>Nicht aufgelöst</span>${missing.length ? missing.map(key => `<span class="variable-chip missing">{{${esc(key)}}}</span>`).join('') : '<span class="muted">Alle Variablen aufgelöst</span>'}</div></section>
        <div class="phase-complete-actions">${data.completed ? '<button type="button" class="btn btn-secondary" id="reopenPhase">Erneut bearbeiten</button>' : '<button type="button" class="btn btn-primary" id="completePhase">Phase abschließen</button>'}</div>`;
}

function fieldMarkup(field, value) {
    const required = field.required ? ' <b aria-hidden="true">*</b>' : '';
    if (field.type === 'checkbox') return `<label class="switch-field run-field"><span><strong>${esc(field.label)}${required}</strong><small>${esc(field.placeholder || '')}</small></span><input type="checkbox" role="switch" data-field="${esc(field.key)}" ${value ? 'checked' : ''}></label>`;
    if (field.type === 'textarea') return `<label class="field run-field"><span>${esc(field.label)}${required}</span><textarea rows="5" data-field="${esc(field.key)}" placeholder="${esc(field.placeholder || '')}">${esc(value || '')}</textarea></label>`;
    return `<label class="field run-field"><span>${esc(field.label)}${required}</span><input data-field="${esc(field.key)}" value="${esc(value || '')}" placeholder="${esc(field.placeholder || '')}"></label>`;
}

function renderFiles() {
    const generated = new Map((run.generated_files || []).map(file => [file.filename, file]));
    const container = document.querySelector('#resultFiles');
    container.innerHTML = (template.files || []).map(file => { const item = generated.get(file.filename); return `<details class="result-file" data-filename="${esc(file.filename)}"><summary><span><strong>${esc(file.filename)}</strong><small>${esc(file.description || '')}</small></span><em class="${item ? 'ready' : ''}">${item ? 'Erzeugt' : 'Offen'}</em></summary><div><p>${file.required ? 'Pflichtdatei' : 'Optionale Datei'}${item ? ` · ${item.bytes} Bytes` : ''}</p>${item ? `<button type="button" class="btn btn-secondary btn-small" data-view>Inhalt anzeigen</button><a class="btn btn-secondary btn-small" href="api/runs.php?action=file&id=${encodeURIComponent(id)}&filename=${encodeURIComponent(file.filename)}&download=1">Herunterladen</a><pre class="file-preview" hidden></pre>` : ''}</div></details>`; }).join('') || '<p class="muted">Dieses Template enthält keine Dateien.</p>';
    document.querySelector('#downloadZip').hidden = !run.generated_files?.length;
}

async function loadRunVersions() {
    const container=document.querySelector('#runVersionList');
    try {
        const data=await Playbooks.request(`api/run_versions.php?run_id=${encodeURIComponent(id)}`);
        container.innerHTML=`<div class="version-item"><span><strong>Version ${run.run_version || 1}</strong><small>Aktueller Stand</small></span></div>${data.versions.map(item=>`<div class="version-item"><span><strong>Version ${item.version}</strong><small>${item.timestamp?new Intl.DateTimeFormat('de-DE',{dateStyle:'medium',timeStyle:'short'}).format(new Date(item.timestamp)):'Zeitpunkt unbekannt'}</small></span><button type="button" class="btn btn-secondary btn-small" data-run-version="${item.version}">Laden</button></div>`).join('')}`;
    } catch(error) { container.textContent=error.message; }
}

function scheduleSave() {
    clearTimeout(saveTimer); document.querySelector('#autosaveStatus').textContent = 'Wird gleich gespeichert…';
    saveTimer = setTimeout(saveNow, 800);
}

async function saveNow() {
    clearTimeout(saveTimer); saveTimer = null; document.querySelector('#autosaveStatus').textContent = 'Wird gespeichert…';
    const savingVersion = changeVersion;
    try {
        const data = await Playbooks.request('api/runs.php', {method:'POST', body:JSON.stringify({action:'save', id, phase_data:run.phase_data, current_phase:current})});
        if (data.forked) { location.href=`run.php?id=${encodeURIComponent(data.run.id)}`; return; }
        if (savingVersion === changeVersion) run = data.run;
        document.querySelector('#autosaveStatus').textContent = savingVersion === changeVersion ? 'Alle Eingaben gespeichert' : 'Neuere Eingaben werden gespeichert…';
    } catch (error) { document.querySelector('#autosaveStatus').textContent = 'Speichern fehlgeschlagen'; Playbooks.toast(error.message, 'error'); }
}

document.querySelector('#phaseSteps').addEventListener('click', event => { const button = event.target.closest('[data-step]'); if (!button) return; current = Number(button.dataset.step); run.current_phase = current; scheduleSave(); render(); });
document.querySelector('#phasePanel').addEventListener('input', event => { if (!event.target.dataset.field) return; phaseData(current)[event.target.dataset.field] = event.target.type === 'checkbox' ? event.target.checked : event.target.value; changeVersion++; scheduleSave(); renderProgress(); const prompt = resolve(template.phases[current].aiPrompt); document.querySelector('#resolvedPrompt').textContent = prompt; const missing = unresolved(prompt); document.querySelector('#unresolvedVariables').innerHTML = `<span>Nicht aufgelöst</span>${missing.length ? missing.map(key => `<span class="variable-chip missing">{{${esc(key)}}}</span>`).join('') : '<span class="muted">Alle Variablen aufgelöst</span>'}`; });
document.querySelector('#phasePanel').addEventListener('change', event => { if (event.target.type === 'checkbox') event.target.dispatchEvent(new Event('input', {bubbles:true})); });
document.querySelector('#phasePanel').addEventListener('click', async event => {
    if (event.target.closest('#copyPrompt')) {
        const text = document.querySelector('#resolvedPrompt').textContent, button = event.target.closest('button');
        button.textContent = 'Kopiert';
        try { await navigator.clipboard.writeText(text); } catch { const area=document.createElement('textarea');area.value=text;document.body.append(area);area.select();document.execCommand('copy');area.remove(); }
    }
    if (event.target.closest('#completePhase')) {
        await saveNow(); const phase=template.phases[current],data=phaseData(current);const missing=(phase.fields||[]).filter(field=>field.required && (field.type==='checkbox' ? !data[field.key] : !String(data[field.key]||'').trim()));
        if (missing.length && !await Playbooks.confirm(`${missing.length} Pflichtfeld(er) sind noch leer. Phase trotzdem abschließen?`,'Unvollständige Phase')) return;
        const result=await Playbooks.request('api/runs.php',{method:'POST',body:JSON.stringify({action:'complete_phase',id,step:current})});run=result.run;current=run.current_phase;render();Playbooks.toast('Phase abgeschlossen.');
    }
    if (event.target.closest('#reopenPhase')) { const result=await Playbooks.request('api/runs.php',{method:'POST',body:JSON.stringify({action:'reopen_phase',id,step:current})});if(result.forked){location.href=`run.php?id=${encodeURIComponent(result.run.id)}`;return;}run=result.run;render();Playbooks.toast('Phase wieder geöffnet.'); }
});
document.querySelector('#generateFiles').addEventListener('click', async event => { await saveNow();event.target.disabled=true;try{const data=await Playbooks.request('api/runs.php',{method:'POST',body:JSON.stringify({action:'generate',id,overwrite:document.querySelector('#overwriteFiles').checked})});run=data.run;renderFiles();Playbooks.toast('Ergebnisdateien erzeugt.');}catch(error){Playbooks.toast(error.message,'error');}finally{event.target.disabled=false;} });
document.querySelector('#resultFiles').addEventListener('click', async event => { const button=event.target.closest('[data-view]');if(!button)return;const details=button.closest('[data-filename]'),preview=details.querySelector('.file-preview');if(!preview.hidden){preview.hidden=true;button.textContent='Inhalt anzeigen';return;}const response=await fetch(`api/runs.php?action=file&id=${encodeURIComponent(id)}&filename=${encodeURIComponent(details.dataset.filename)}`);preview.textContent=await response.text();preview.hidden=false;button.textContent='Inhalt ausblenden'; });
document.querySelector('#runVersionList').addEventListener('click',async event=>{const button=event.target.closest('[data-run-version]');if(!button)return;try{const data=await Playbooks.request(`api/run_versions.php?run_id=${encodeURIComponent(id)}&version=${button.dataset.runVersion}`),item=data.run;document.querySelector('#runVersionContent').innerHTML=`<div class="version-readonly"><h2>Durchführungs-Version ${item.run_version || button.dataset.runVersion}</h2><p><strong>${esc(item.name)}</strong></p><dl><div><dt>Template-Version</dt><dd>${item.template_version || 1}</dd></div><div><dt>Status</dt><dd>${esc(item.status)}</dd></div><div><dt>Phase</dt><dd>${Number(item.current_phase||0)+1}</dd></div></dl><pre>${esc(JSON.stringify(item.phase_data||{},null,2))}</pre><p class="muted">Diese Version ist schreibgeschützt.</p></div>`;document.querySelector('#runVersionDialog').showModal();}catch(error){Playbooks.toast(error.message,'error');}});
load();
