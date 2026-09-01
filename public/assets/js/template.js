import {configurePhases,setPhases,getPhases,addPhase} from './phases.js';
import {configureFiles,setFiles,getFiles,addFile} from './files.js';
const id=new URLSearchParams(location.search).get('id');
let template=null,dirty=false;
const status=document.querySelector('#saveStatus');
function markDirty(){dirty=true;status.textContent='Ungespeicherte Änderungen';}
configurePhases(markDirty);configureFiles(markDirty);
document.querySelectorAll('.tab').forEach(button=>button.addEventListener('click',()=>selectTab(button.dataset.tab)));
function selectTab(name){document.querySelectorAll('.tab').forEach(tab=>{const active=tab.dataset.tab===name;tab.classList.toggle('is-active',active);tab.setAttribute('aria-selected',String(active));});document.querySelectorAll('.tab-panel').forEach(panel=>{const active=panel.dataset.panel===name;panel.classList.toggle('is-active',active);panel.hidden=!active;});}
document.querySelector('#addPhase').addEventListener('click',addPhase);document.querySelector('#addMarkdown').addEventListener('click',()=>addFile('md'));document.querySelector('#addJson').addEventListener('click',()=>addFile('json'));
document.querySelector('#templateForm').addEventListener('input',event=>{if(!event.target.closest('.phase-card,.file-card'))markDirty();});
window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
async function load(){
    if(!id){location.href='templates.php';return;}
    try{const [templateData,categoryData]=await Promise.all([Playbooks.request(`api/templates.php?action=get&id=${encodeURIComponent(id)}`),Playbooks.request('api/categories.php')]);template=templateData.template;
        const category=document.querySelector('#basisCategory');category.replaceChildren(...categoryData.categories.map(item=>new Option(item.name,item.id)));
        document.querySelector('#basisName').value=template.name;document.querySelector('#basisDescription').value=template.description||'';category.value=template.category_id;document.querySelector('#basisActive').checked=!!template.is_active;
        document.querySelector('#templateTitle').textContent=template.name;document.querySelector('#templateMeta').textContent=`Version ${template.version} · zuletzt aktualisiert ${new Intl.DateTimeFormat('de-DE',{dateStyle:'medium',timeStyle:'short'}).format(new Date(template.updated_at))}`;document.querySelector('#currentTemplateVersion').textContent=`Aktuelle Version ${template.version}`;
        setPhases(template.phases);setFiles(template.files);document.querySelector('#editorLoading').hidden=true;document.querySelector('#templateForm').hidden=false;dirty=false;status.textContent='Alle Änderungen gespeichert';
        await loadVersions();
    }catch(error){document.querySelector('#editorLoading').innerHTML=`<div class="empty-state"><h2>Template konnte nicht geladen werden</h2><p>${Playbooks.escape(error.message)}</p><a class="btn btn-secondary" href="templates.php">Zurück zur Übersicht</a></div>`;}
}
document.querySelector('#saveTemplate').addEventListener('click',async()=>{
    const button=document.querySelector('#saveTemplate'),name=document.querySelector('#basisName').value.trim();if(!name){selectTab('basis');document.querySelector('#basisName').focus();Playbooks.toast('Bitte einen Namen eingeben.','error');return;}
    button.disabled=true;status.textContent='Wird gespeichert…';
    try{const data=await Playbooks.request('api/templates.php',{method:'POST',body:JSON.stringify({action:'update',id,name,description:document.querySelector('#basisDescription').value,category_id:document.querySelector('#basisCategory').value,is_active:document.querySelector('#basisActive').checked,phases:getPhases(),files:getFiles()})});template=data.template;dirty=false;document.querySelector('#templateTitle').textContent=template.name;document.querySelector('#templateMeta').textContent=`Version ${template.version} · gerade aktualisiert`;document.querySelector('#currentTemplateVersion').textContent=`Aktuelle Version ${template.version}`;status.textContent='Alle Änderungen gespeichert';await loadVersions();Playbooks.toast('Template gespeichert.');}
    catch(error){status.textContent='Speichern fehlgeschlagen';Playbooks.toast(error.message,'error');}finally{button.disabled=false;}
});
async function loadVersions(){
    const container=document.querySelector('#templateVersionList');
    try{const data=await Playbooks.request(`api/template_versions.php?template_id=${encodeURIComponent(id)}`);container.innerHTML=data.versions.length?data.versions.map(item=>`<div class="version-item"><span><strong>Version ${item.version}</strong><small>${item.timestamp?new Intl.DateTimeFormat('de-DE',{dateStyle:'medium',timeStyle:'short'}).format(new Date(item.timestamp)):'Zeitpunkt unbekannt'}${item.author?` · ${Playbooks.escape(item.author)}`:''}</small></span><button class="btn btn-secondary btn-small" type="button" data-template-version="${item.version}">Laden</button></div>`).join(''):'<p class="muted">Noch keine früheren Versionen.</p>';}
    catch(error){container.textContent=error.message;}
}
document.querySelector('#templateVersionList').addEventListener('click',async event=>{
    const button=event.target.closest('[data-template-version]');if(!button)return;
    try{const data=await Playbooks.request(`api/template_versions.php?template_id=${encodeURIComponent(id)}&version=${button.dataset.templateVersion}`),item=data.template;document.querySelector('#templateVersionContent').innerHTML=`<div class="version-readonly"><h2>Version ${item.version}: ${Playbooks.escape(item.name)}</h2><p>${Playbooks.escape(item.description||'Keine Beschreibung')}</p><dl><div><dt>Phasen</dt><dd>${(item.phases||[]).length}</dd></div><div><dt>Dateien</dt><dd>${(item.files||[]).length}</dd></div><div><dt>Status</dt><dd>${item.is_active?'Aktiv':'Inaktiv'}</dd></div></dl><pre>${Playbooks.escape(JSON.stringify({phases:item.phases||[],files:item.files||[]},null,2))}</pre><p class="muted">Diese Version ist schreibgeschützt.</p></div>`;document.querySelector('#templateVersionDialog').showModal();}
    catch(error){Playbooks.toast(error.message,'error');}
});
load();
