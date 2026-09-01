let phases = [];
let onChange = () => {};
const icons = ['Search','Code','FileText','CheckCircle','Rocket'];
const iconPaths = {
    Search:'<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
    Code:'<path d="m16 18 6-6-6-6M8 6l-6 6 6 6"/>',
    FileText:'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h6"/>',
    CheckCircle:'<path d="M22 11.1V12a10 10 0 1 1-5.9-9.1"/><path d="m22 4-10 10-3-3"/>',
    Rocket:'<path d="M4.5 16.5c-1.5 1.3-2 5-2 5s3.7-.5 5-2c.7-.8.7-2.1-.1-2.9a2.2 2.2 0 0 0-2.9-.1ZM12 15l-3-3a22 22 0 0 1 2-4A12 12 0 0 1 22 2c-1.2 3.4-5.3 7.6-10 10.5M12 15l3 3"/>'
};
const esc = value => Playbooks.escape(value);
const variables = text => [...new Set([...String(text).matchAll(/{{\s*([a-zA-Z0-9_]+)\s*}}/g)].map(match => match[1]))];
const blankField = () => ({key:'eingabe',type:'text',label:'Eingabe',placeholder:'',required:true});

export function configurePhases(changeHandler) { onChange = changeHandler || (() => {}); }
export function setPhases(data) { phases = Array.isArray(data) ? structuredClone(data) : []; renumber(); renderPhases(); }
export function getPhases() { return structuredClone(phases); }
export function addPhase() {
    phases.push({step:phases.length,title:'Neue Phase',description:'',icon:'FileText',aiPrompt:'',fields:[blankField()]});
    renderPhases(phases.length - 1); changed();
}
function renumber(){ phases.forEach((phase,index)=>phase.step=index); }
function changed(){ renumber(); onChange(); document.querySelector('#phaseBadge').textContent=phases.length; }
function icon(name){ return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">${iconPaths[name] || iconPaths.FileText}</svg>`; }
function variableMarkup(phase){
    const keys = new Set((phase.fields || []).map(field=>field.key));
    const vars = variables(phase.aiPrompt);
    return `<div class="variable-list" data-variable-list><span>Prompt-Variablen</span>${vars.length ? vars.map(item=>`<span class="variable-chip ${keys.has(item)?'':'missing'}">{{${esc(item)}}}</span>`).join('') : '<span class="muted">Keine Variablen</span>'}</div>`;
}
export function renderPhases(openIndex = null){
    const container=document.querySelector('#phasesContainer'); if(!container)return;
    document.querySelector('#phaseBadge').textContent=phases.length;
    if(!phases.length){container.innerHTML='<div class="empty-state"><h2>Noch keine Phasen</h2><p>Lege den ersten strukturierten Schritt an.</p></div>';return;}
    container.innerHTML=phases.map((phase,index)=>`<details class="stack-card phase-card" data-phase-index="${index}" ${index===openIndex?'open':''}>
        <summary><span class="stack-index">${index+1}</span><span class="stack-icon">${icon(phase.icon)}</span><span class="stack-summary"><strong>${esc(phase.title || 'Unbenannte Phase')}</strong><small>${esc(phase.description || 'Noch keine Beschreibung')}</small></span><span class="stack-count">${(phase.fields||[]).length} Felder</span><svg class="disclosure" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg></summary>
        <div class="stack-body">
            <div class="row-2"><label class="field"><span>Titel</span><input data-key="title" maxlength="160" value="${esc(phase.title)}"></label><label class="field"><span>Icon</span><select data-key="icon">${icons.map(name=>`<option ${name===phase.icon?'selected':''}>${name}</option>`).join('')}</select></label></div>
            <label class="field"><span>Beschreibung</span><input data-key="description" maxlength="1000" value="${esc(phase.description)}"></label>
            <label class="field"><span>KI-Prompt</span><textarea data-key="aiPrompt" rows="8" placeholder="Rolle, Ziel und Kontext beschreiben. Variablen als {{schluessel}} einsetzen.">${esc(phase.aiPrompt)}</textarea></label>
            ${variableMarkup(phase)}
            <div class="subsection"><div class="subsection-heading"><h3>Eingabefelder</h3><button type="button" class="btn btn-secondary btn-small" data-action="add-field">Feld hinzufügen</button></div><div class="field-list">${(phase.fields||[]).map((field,fieldIndex)=>fieldMarkup(field,fieldIndex,phase.fields.length)).join('')}</div></div>
            <div class="stack-actions"><button type="button" class="btn btn-secondary btn-small" data-action="up" ${index===0?'disabled':''}>Nach oben</button><button type="button" class="btn btn-secondary btn-small" data-action="down" ${index===phases.length-1?'disabled':''}>Nach unten</button><button type="button" class="btn btn-secondary btn-small" data-action="delete">Phase löschen</button></div>
        </div></details>`).join('');
}
function fieldMarkup(field,index,count){return `<div class="field-row" data-field-index="${index}"><label>Schlüssel<input data-field="key" value="${esc(field.key)}" placeholder="projektname"></label><label>Beschriftung<input data-field="label" value="${esc(field.label)}"></label><label>Typ<select data-field="type">${['text','textarea','checkbox'].map(type=>`<option value="${type}" ${field.type===type?'selected':''}>${type}</option>`).join('')}</select></label><label>Platzhalter<input data-field="placeholder" value="${esc(field.placeholder)}"></label><label class="check-compact"><input type="checkbox" data-field="required" ${field.required?'checked':''}> Pflicht</label><button type="button" class="icon-btn danger" data-action="remove-field" aria-label="Feld entfernen" ${count<=1?'disabled':''}>${Playbooks.icon('trash')}</button></div>`;}

document.addEventListener('input',event=>{
    const card=event.target.closest('.phase-card'); if(!card)return; const index=Number(card.dataset.phaseIndex); const phase=phases[index];
    if(event.target.dataset.key){phase[event.target.dataset.key]=event.target.value; if(event.target.dataset.key==='title')card.querySelector('.stack-summary strong').textContent=event.target.value||'Unbenannte Phase'; if(event.target.dataset.key==='description')card.querySelector('.stack-summary small').textContent=event.target.value||'Noch keine Beschreibung'; if(event.target.dataset.key==='aiPrompt')card.querySelector('[data-variable-list]').outerHTML=variableMarkup(phase); changed();}
    if(event.target.dataset.field){const row=event.target.closest('[data-field-index]');const field=phase.fields[Number(row.dataset.fieldIndex)];field[event.target.dataset.field]=event.target.type==='checkbox'?event.target.checked:event.target.value;if(event.target.dataset.field==='key')card.querySelector('[data-variable-list]').outerHTML=variableMarkup(phase);changed();}
});
document.addEventListener('change',event=>{if(event.target.matches('.phase-card select'))event.target.dispatchEvent(new Event('input',{bubbles:true}));});
document.addEventListener('click',async event=>{
    const button=event.target.closest('.phase-card [data-action]'); if(!button)return; const card=button.closest('.phase-card');const index=Number(card.dataset.phaseIndex);const action=button.dataset.action;
    if(action==='add-field'){phases[index].fields.push(blankField());renderPhases(index);changed();}
    if(action==='remove-field'&&phases[index].fields.length>1){phases[index].fields.splice(Number(button.closest('[data-field-index]').dataset.fieldIndex),1);renderPhases(index);changed();}
    if(action==='up'||action==='down'){const target=index+(action==='up'?-1:1);[phases[index],phases[target]]=[phases[target],phases[index]];renderPhases(target);changed();}
    if(action==='delete'&&await Playbooks.confirm(`Phase „${phases[index].title}“ wirklich löschen?`,'Phase löschen')){phases.splice(index,1);renderPhases();changed();}
});
