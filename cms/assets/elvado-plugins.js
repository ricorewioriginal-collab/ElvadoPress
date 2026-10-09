'use strict';
// Verwaltung der nativen ElvadoPress-Plugins (np_*-API): Karten, Aktionen mit Abhängigkeiten, Plugin-Seiten mit Übersicht, Einstellungen und Aktionen.
window.ElvadoPluginPages=window.ElvadoPluginPages||{pages:{},register:function(id,fn){this.pages[id]=fn;}};
window.ElvadoPlugins=(()=>{
 const S={rows:[],view:'list',filter:'all',detail:null,tab:'overview',busy:false,meta:{}};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const root=()=>document.getElementById('epRoot');
 const toast=(m,e)=>{(window.cmsToast||((x)=>alert(x)))(m,!!e);};
 async function post(action,body){
  const r=await fetch('/cms/api.php?action='+action,{method:body===undefined?'GET':'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:body===undefined?undefined:JSON.stringify(body)});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  return d;
 }
 const call=async(id,name,args)=>{const d=await post('np_call',{id,call:name,args:args||{}});if(d.status==='error'&&!d.ok&&d.message&&!('ok' in d))throw new Error(d.message);return d;};
 function download(id,name,args){const q=Object.keys(args||{}).map(k=>'&args['+encodeURIComponent(k)+']='+encodeURIComponent(args[k])).join('');const a=document.createElement('a');a.href='/cms/api.php?action=np_download&id='+encodeURIComponent(id)+'&call='+encodeURIComponent(name)+q+'&_tok='+encodeURIComponent(tok());a.rel='noopener';document.body.appendChild(a);a.click();a.remove();}
 async function load(force){
  const r=root();if(!r)return;if(force||!S.rows.length)r.innerHTML='<p class="hint">Lade …</p>';
  try{const d=await post('np_list');if(d.status!=='ok')throw new Error(d.message||'Fehler');S.rows=d.plugins||[];S.meta={cms:d.cms_version,php:d.php,mode:d.mode};}
  catch(e){r.innerHTML='<div class="danger-note">'+esc(e.message)+'</div>';return;}
  draw();
 }
 const STATUS={active:['Aktiv','ok'],installed:['Installiert (inaktiv)','grey'],available:['Nicht installiert','grey'],planned:['Noch nicht verfügbar','grey']};
 function badges(p){
  let h='';
  if(p.official)h+='<span class="dm-pill ep-off" title="Vom ElvadoPress-Server geprüft: Das Paket gehört zum offiziellen Release und ist unverändert."><i class="fas fa-circle-check"></i> Offizielles ElvadoPress Plugin</span>';
  else if(p.status!=='planned'&&p.version)h+='<span class="dm-pill ep-warn" title="Die installierte Fassung stimmt nicht mit dem offiziellen Paket überein und wird nicht ausgeführt."><i class="fas fa-triangle-exclamation"></i> Nicht verifiziert</span>';
  if(p.recommended)h+='<span class="dm-pill grey">Empfohlen</span>';
  const s=STATUS[p.status]||['',''];h+='<span class="dm-pill '+(s[1]==='ok'?'':'grey')+'">'+s[0]+'</span>';
  if(p.update_available)h+='<span class="dm-pill ep-upd"><i class="fas fa-arrow-up"></i> Update '+esc(p.available_version)+'</span>';
  if(p.error)h+='<span class="dm-pill ep-bad"><i class="fas fa-circle-exclamation"></i> Fehler</span>';
  return h;
 }
 function card(p){
  const dep=(p.dependencies||[]).map(d=>'<span class="ep-dep'+(d.active?' on':'')+'" title="'+esc(d.name)+' '+esc(d.constraint)+'">'+esc(d.name)+(d.active?' ✓':(d.installed?' (inaktiv)':' (fehlt)'))+'</span>').join('');
  const need=(p.dependents||[]).length?'<div class="hint">Benötigt von: '+p.dependents.map(id=>esc((S.rows.find(x=>x.id===id)||{}).name||id)).join(', ')+'</div>':'';
  const comp=(p.compat&&!p.compat.ok)?'<div class="ep-note bad"><i class="fas fa-triangle-exclamation"></i> '+esc(p.compat.messages.join(' '))+'</div>':'';
  const err=p.error?'<div class="ep-note bad"><i class="fas fa-circle-exclamation"></i> '+esc(p.error)+'</div>':'';
  const i=esc(p.id);let b='';
  if(p.status==='planned')b='<button class="btn-g" disabled>Noch nicht verfügbar</button>';
  else{
   if(p.status==='available')b+='<button class="btn-a" '+(p.compat.ok?'':'disabled')+' onclick="ElvadoPlugins.act(\'install\',\''+i+'\')"><i class="fas fa-download"></i> Installieren</button>';
   if(p.status==='installed')b+='<button class="btn-a" '+(p.compat.ok?'':'disabled')+' onclick="ElvadoPlugins.act(\'activate\',\''+i+'\')"><i class="fas fa-power-off"></i> Aktivieren</button>';
   if(p.status==='active')b+='<button class="btn-g" onclick="ElvadoPlugins.act(\'deactivate\',\''+i+'\')"><i class="fas fa-power-off"></i> Deaktivieren</button>';
   if(p.update_available)b+='<button class="btn-a" onclick="ElvadoPlugins.act(\'update\',\''+i+'\')"><i class="fas fa-arrow-up"></i> Aktualisieren</button>';
   if(p.status==='active'&&p.has_settings)b+='<button class="btn-g" onclick="ElvadoPlugins.open(\''+i+'\')"><i class="fas fa-sliders"></i> Einstellungen</button>';
   if(p.status==='installed'||p.status==='active')b+='<button class="btn-g" '+(p.status==='active'?'disabled title="Zuerst deaktivieren"':'')+' onclick="ElvadoPlugins.act(\'uninstall\',\''+i+'\')"><i class="fas fa-trash"></i> Deinstallieren</button>';
  }
  return '<div class="ep-card"><div class="ep-head"><div class="ep-ic"><i class="fas '+esc(p.icon||'fa-plug')+'"></i></div><div class="ep-title"><b>'+esc(p.name)+'</b><div class="hint">'+(p.version?'Version '+esc(p.version):(p.available_version?'Version '+esc(p.available_version):''))+(p.author?' · '+esc(p.author):'')+(p.license?' · '+esc(p.license):'')+'</div></div></div>'
   +'<div class="ep-badges">'+badges(p)+'</div><p class="ep-desc">'+esc(p.description)+'</p>'
   +(dep?'<div class="ep-deps"><span class="hint">Benötigt:</span> '+dep+'</div>':'')+need+comp+err
   +'<div class="hint ep-meta">'+(p.requires?'ElvadoPress '+esc(p.requires.elvadopress)+(p.tested_up_to?' · getestet bis '+esc(p.tested_up_to):'')+' · PHP '+esc(p.requires.php):'')+(p.update_source?' · Updates: '+({bundled:'mit ElvadoPress',github:'GitHub',manual:'manuell'})[p.update_source]:'')+'</div>'
   +((p.capabilities||[]).length?'<div class="hint ep-meta">Berechtigungen: '+p.capabilities.map(esc).join(', ')+'</div>':'')
   +'<div class="ep-actions">'+b+'</div></div>';
 }
 function list(){
  const f=S.filter,rows=S.rows.filter(p=>f==='all'||(f==='active'&&p.status==='active')||(f==='installed'&&(p.status==='installed'||p.status==='active'))||(f==='available'&&(p.status==='available'||p.status==='planned'))||(f==='updates'&&p.update_available));
  const cnt={a:S.rows.filter(p=>p.status==='active').length,u:S.rows.filter(p=>p.update_available).length};
  const chip=(k,l)=>'<button class="btn-g'+(f===k?' on':'')+'" onclick="ElvadoPlugins.filter(\''+k+'\')">'+l+'</button>';
  const rec=S.rows.filter(p=>p.recommended&&p.status!=='planned'&&p.status!=='active').length;
  const banner=(S.meta.mode==='upgrade'&&rec)?'<div class="ep-note warn" style="margin-bottom:12px"><b>Neu: ElvadoPress Essentials.</b> Die empfohlenen Plugins (SEO, Security, Backup, Performance, Forms, Analytics, Redirects, KI) sind installiert, aber noch nicht eingeschaltet – deine Website verhält sich bis dahin wie bisher. <div class="ap-add" style="margin-top:8px"><button class="btn-a" onclick="ElvadoPlugins.enableRecommended()"><i class="fas fa-power-off"></i> Empfohlene aktivieren</button></div></div>':'';
  return banner+'<div class="ep-bar">'+chip('all','Alle')+chip('active','Aktiv ('+cnt.a+')')+chip('installed','Installiert')+chip('available','Verfügbar')+(cnt.u?chip('updates','Updates ('+cnt.u+')'):'')+'</div>'
   +'<div class="hint" style="margin:6px 0 10px">ElvadoPress '+esc(S.meta.cms||'')+' · PHP '+esc(S.meta.php||'')+(S.meta.mode?' · Installationsart: '+esc({recommended:'Empfohlen',minimal:'Minimal',custom:'Benutzerdefiniert'}[S.meta.mode]||S.meta.mode):'')+'</div>'
   +'<div class="ep-grid">'+rows.map(card).join('')+'</div>'+(rows.length?'':'<p class="hint">Keine Plugins in dieser Ansicht.</p>');
 }
 // ---------------------------------------------------------------- Aktionen
 const name=id=>(S.rows.find(x=>x.id===id)||{}).name||id;
 async function act(kind,id){
  if(S.busy)return;const p=S.rows.find(x=>x.id===id);if(!p)return;let body={id};
  try{
   if(kind==='install'){
    const plan=await post('np_plan',{ids:[id]});if(plan.errors&&plan.errors.length){toast(plan.errors.join(' '),true);return;}
    const extra=(plan.order||[]).filter(x=>x!==id&&!(S.rows.find(r=>r.id===x)||{}).version);
    if(extra.length){if(!confirm(name(id)+' benötigt zusätzlich: '+extra.map(name).join(', ')+'.\nDiese Plugins ebenfalls installieren?'))return;body.with_deps=true;}
   }
   if(kind==='activate'){
    const inactive=(p.dependencies||[]).filter(d=>!d.active);
    if(inactive.length){if(!confirm(name(id)+' benötigt: '+inactive.map(d=>d.name).join(', ')+'.\nDiese Plugins ebenfalls '+(inactive.some(d=>!d.installed)?'installieren und ':'')+'aktivieren?'))return;body.with_deps=true;}
   }
   if(kind==='deactivate'){
    const act=(p.dependents||[]).filter(x=>(S.rows.find(r=>r.id===x)||{}).status==='active');
    if(act.length){if(!confirm('Diese aktiven Plugins benötigen '+p.name+': '+act.map(name).join(', ')+'.\nSie werden mit deaktiviert. Fortfahren?'))return;body.cascade=true;}
   }
   if(kind==='update'&&!confirm(p.name+' von '+p.version+' auf '+p.available_version+' aktualisieren?\nVorher wird der bisherige Stand gesichert; bei einem Fehler wird automatisch zurückgesetzt.'))return;
   if(kind==='uninstall'){
    if(!confirm(p.name+' deinstallieren?\nDas Plugin wird entfernt (eine Sicherung bleibt erhalten). Einstellungen und Daten bleiben zunächst erhalten.'))return;
    body.purge=confirm('Auch die Einstellungen und alle Daten dieses Plugins endgültig löschen?\n(OK = löschen, Abbrechen = behalten)');
   }
   S.busy=true;const d=await post('np_'+kind,body);
   if(d.plugins)S.rows=d.plugins;
   toast(d.message||(d.status==='ok'?'Erledigt':'Fehler'),d.status!=='ok');
   if(kind==='deactivate'||kind==='uninstall'||d.status==='ok'){await load();if(S.view==='detail'&&!S.rows.find(r=>r.id===S.detail&&r.status==='active'))S.view='list';}
  }catch(e){toast(e.message||'Fehler',true);}finally{S.busy=false;draw();}
 }
 // ---------------------------------------------------------------- Plugin-Seite
 function open(id,tab){S.view='detail';S.detail=id;S.tab=tab||'overview';draw();}
 function back(){S.view='list';draw();}
 const api=id=>({id,call:(n,a)=>call(id,n,a),toast,download:(n,a)=>download(id,n,a),esc});
 function stats(b){return '<div class="ep-stats">'+b.items.map(i=>'<div class="ep-stat '+esc(i.level||'')+'"><b>'+esc(i.value)+'</b><span>'+esc(i.label)+'</span></div>').join('')+'</div>';}
 function checks(b){return '<div class="ep-sec">'+(b.title?'<div class="ap-sub">'+esc(b.title)+'</div>':'')+'<div class="ep-checks">'+b.items.map(i=>'<div class="ep-chk '+esc(i.status)+'"><i class="fas '+({ok:'fa-circle-check',warn:'fa-triangle-exclamation',bad:'fa-circle-xmark',info:'fa-circle-info'}[i.status]||'fa-circle-info')+'"></i><div><b>'+esc(i.label)+'</b><div class="hint">'+esc(i.text)+'</div></div></div>').join('')+'</div></div>';}
 function table(b,bi){
  const head='<tr>'+b.columns.map(c=>'<th>'+esc(c)+'</th>').join('')+(b.rows.some(r=>r&&r.actions)?'<th></th>':'')+'</tr>';
  const rows=b.rows.map((r,ri)=>{const cells=Array.isArray(r)?r:r.cells;const acts=(r&&r.actions)||[];return '<tr>'+cells.map(c=>'<td>'+esc(c)+'</td>').join('')+(b.rows.some(x=>x&&x.actions)?'<td class="ep-ra">'+acts.map((a,ai)=>'<button class="btn-g" data-ep-row="'+bi+':'+ri+':'+ai+'">'+esc(a.label)+'</button>').join('')+'</td>':'')+'</tr>';}).join('');
  return '<div class="ep-sec">'+(b.title?'<div class="ap-sub">'+esc(b.title)+'</div>':'')+(b.rows.length?'<div class="ep-tw"><table class="ep-table">'+head+rows+'</table></div>':'<p class="hint">'+esc(b.empty||'Keine Einträge.')+'</p>')+'</div>';
 }
 function bars(b){const m=Math.max(1,...b.items.map(i=>+i.value||0));return '<div class="ep-sec">'+(b.title?'<div class="ap-sub">'+esc(b.title)+'</div>':'')+'<div class="ep-bars">'+b.items.map(i=>'<div class="ep-brow"><span>'+esc(i.label)+'</span><div><i style="width:'+Math.round(100*(+i.value||0)/m)+'%"></i></div><b>'+esc(i.value)+'</b></div>').join('')+'</div></div>';}
 function blocks(bl){return bl.map((b,bi)=>({stats,checks,table:x=>table(x,bi),bars,text:x=>'<div class="ep-sec">'+(x.title?'<div class="ap-sub">'+esc(x.title)+'</div>':'')+'<p class="hint" style="margin:4px 0">'+esc(x.text)+'</p></div>',notice:x=>'<div class="ep-note '+esc(x.level||'info')+'">'+esc(x.text)+'</div>'}[b.type]||(()=>''))(b)).join('');}
 let lastBlocks=[];
 async function detail(){
  const p=S.rows.find(x=>x.id===S.detail);if(!p||p.status!=='active'){S.view='list';return list();}
  const tabs=[['overview','Übersicht']];if((p.settings||[]).length)tabs.push(['settings','Einstellungen']);if((p.actions||[]).length)tabs.push(['actions','Aktionen']);if(p.admin_js)tabs.push(['page','Verwalten']);
  const head='<div class="ep-bar"><button class="btn-g" onclick="ElvadoPlugins.back()">‹ Alle Plugins</button></div><div class="ep-head" style="margin:10px 0"><div class="ep-ic"><i class="fas '+esc(p.icon)+'"></i></div><div class="ep-title"><b style="font-size:1.1rem">'+esc(p.name)+'</b><div class="ep-badges">'+badges(p)+'</div></div></div>'
   +'<div class="ep-bar">'+tabs.map(t=>'<button class="btn-g'+(S.tab===t[0]?' on':'')+'" onclick="ElvadoPlugins.tab(\''+t[0]+'\')">'+t[1]+'</button>').join('')+'</div><div id="epBody" style="margin-top:12px"><p class="hint">Lade …</p></div>';
  setTimeout(()=>fill(p),0);return head;
 }
 async function fill(p){
  const el=document.getElementById('epBody');if(!el)return;
  try{
   if(S.tab==='overview'){const d=await call(p.id,'overview').catch(e=>({blocks:[{type:'notice',level:'info',text:'Dieses Plugin hat keine Übersicht.'}]}));lastBlocks=d.blocks||[];el.innerHTML=blocks(lastBlocks)||'<p class="hint">Keine Daten.</p>';}
   else if(S.tab==='settings')await settingsForm(p,el);
   else if(S.tab==='actions')el.innerHTML='<div class="ap-add">'+p.actions.map(a=>'<button class="btn-g" data-ep-action="'+esc(a.id)+'"><i class="fas '+esc(a.icon)+'"></i> '+esc(a.label)+'</button>').join('')+'</div><div id="epActOut" style="margin-top:10px"></div>';
   else if(S.tab==='page'){el.innerHTML='<p class="hint">Lade …</p>';await loadScript(p.admin_js);const fn=ElvadoPluginPages.pages[p.id];if(!fn)throw new Error('Die Verwaltungsseite des Plugins ist nicht verfügbar.');el.innerHTML='';fn(el,api(p.id));}
  }catch(e){el.innerHTML='<div class="danger-note">'+esc(e.message)+'</div>';}
 }
 const loaded={};function loadScript(src){if(loaded[src])return loaded[src];return loaded[src]=new Promise((ok,no)=>{const s=document.createElement('script');s.src=src+(src.includes('?')?'&':'?')+'v='+Date.now();s.onload=ok;s.onerror=()=>{delete loaded[src];no(new Error('Skript konnte nicht geladen werden'));};document.body.appendChild(s);});}
 async function settingsForm(p,el){
  const d=await post('np_settings_get',{id:p.id});if(d.status!=='ok')throw new Error(d.message||'Fehler');const v=d.settings,schema=d.schema;let g='';
  const fld=f=>{const k=esc(f.key),val=v[f.key];let inp='';
   if(f.type==='toggle')inp='<label class="ap-check"><input type="checkbox" data-k="'+k+'" '+(val?'checked':'')+'> '+esc(f.label)+'</label>';
   else{inp='<label class="news-lbl">'+esc(f.label)+'</label>';
    if(f.type==='select')inp+='<select class="fc w-100" data-k="'+k+'">'+f.options.map(o=>'<option value="'+esc(o.value)+'"'+(String(val)===o.value?' selected':'')+'>'+esc(o.label)+'</option>').join('')+'</select>';
    else if(f.type==='textarea')inp+='<textarea class="fc w-100" rows="4" data-k="'+k+'">'+esc(val)+'</textarea>';
    else if(f.type==='secret')inp+='<input class="fc w-100" type="password" autocomplete="new-password" data-k="'+k+'" value="" placeholder="'+(v[f.key+'_set']?'••••••••  (gesetzt – leer lassen = unverändert)':'nicht gesetzt')+'">'+(v[f.key+'_set']?'<button class="btn-g" type="button" data-ep-clear="'+k+'" style="margin-top:6px">Entfernen</button>':'');
    else inp+='<input class="fc w-100" type="'+(f.type==='number'?'number':(f.type==='email'?'email':'text'))+'" data-k="'+k+'" value="'+esc(val)+'"'+(f.type==='number'?(f.min!=null?' min="'+f.min+'"':'')+(f.max!=null?' max="'+f.max+'"':''):'')+'>';}
   return '<div class="ep-fld" data-show="'+esc(f.show_if||'')+'">'+inp+(f.help?'<div class="hint">'+esc(f.help)+'</div>':'')+'</div>';};
  const groups={};schema.forEach(f=>{(groups[f.group||'Allgemein']=groups[f.group||'Allgemein']||[]).push(f);});
  Object.keys(groups).forEach(n=>{g+='<div class="ep-sec"><div class="ap-sub">'+esc(n)+'</div>'+groups[n].map(fld).join('')+'</div>';});
  el.innerHTML='<div class="ep-form">'+g+'<div class="ap-add"><button class="btn-a" type="button" data-ep-save="1"><i class="fas fa-floppy-disk"></i> Speichern</button></div></div>';
  showIf(el);
 }
 function showIf(el){el.querySelectorAll('.ep-fld[data-show]').forEach(f=>{const k=f.dataset.show;if(!k)return;const c=el.querySelector('[data-k="'+k+'"]');f.style.display=c&&c.checked?'':'none';});}
 function draw(){
  const r=root();if(!r)return;
  if(S.view==='detail'){detail().then(h=>{r.innerHTML=h;});return;}
  r.innerHTML=list();
 }
 function tab(t){S.tab=t;draw();}
 document.addEventListener('change',e=>{const el=document.getElementById('epBody');if(el&&el.contains(e.target)&&e.target.dataset&&e.target.dataset.k)showIf(el);});
 document.addEventListener('click',async e=>{
  const body=document.getElementById('epBody');if(!body||!body.contains(e.target))return;
  const t=e.target.closest('button');if(!t)return;
  try{
   if(t.dataset.epSave){const vals={};body.querySelectorAll('[data-k]').forEach(i=>{vals[i.dataset.k]=i.type==='checkbox'?i.checked:i.value;});const d=await post('np_settings_save',{id:S.detail,values:vals});toast(d.message||(d.status==='ok'?'Gespeichert':'Fehler'),d.status!=='ok');if(d.status==='ok')await load().then(()=>{});}
   else if(t.dataset.epClear){if(!confirm('Gespeicherten Wert entfernen?'))return;const d=await post('np_settings_save',{id:S.detail,values:{[t.dataset.epClear]:'__clear__'}});toast(d.message||'Entfernt',d.status!=='ok');fill(S.rows.find(x=>x.id===S.detail));}
   else if(t.dataset.epAction){const p=S.rows.find(x=>x.id===S.detail),a=p.actions.find(x=>x.id===t.dataset.epAction);if(a.confirm&&!confirm(a.confirm))return;t.disabled=true;const out=document.getElementById('epActOut');out.innerHTML='<p class="hint">Läuft …</p>';
    try{const d=await call(p.id,'action_'+a.id);out.innerHTML='<div class="ep-note '+(d.ok===false?'bad':'ok')+'">'+esc(d.message||'Erledigt')+'</div>'+((d.details||[]).length?'<ul class="ep-list">'+d.details.map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul>':'');}catch(err){out.innerHTML='<div class="ep-note bad">'+esc(err.message)+'</div>';}finally{t.disabled=false;}}
   else if(t.dataset.epRow){const [bi,ri,ai]=t.dataset.epRow.split(':').map(Number),a=((lastBlocks[bi]||{}).rows||[])[ri].actions[ai];let args=Object.assign({},a.args||{});
    if(a.confirm&&!confirm(a.confirm))return;if(a.prompt){const v=prompt(a.prompt);if(v===null)return;args[a.promptKey||'value']=v;}
    if(a.download){download(S.detail,a.download,args);return;}
    t.disabled=true;const d=await call(S.detail,a.call,args);toast(d.message||(d.ok===false?'Fehler':'Erledigt'),d.ok===false);fill(S.rows.find(x=>x.id===S.detail));}
  }catch(err){toast(err.message||'Fehler',true);}
 });
 async function enableRecommended(){if(S.busy)return;if(!confirm('Alle empfohlenen Plugins aktivieren?\nExterne Statistik-Dienste bleiben aus, KI-Funktionen brauchen weiterhin einen Schlüssel in der KI-Zentrale.'))return;S.busy=true;try{const d=await post('np_enable_recommended',{});if(d.plugins)S.rows=d.plugins;toast(d.message||'Erledigt',d.status!=='ok');await load();}catch(e){toast(e.message,true);}finally{S.busy=false;draw();}}
 return {load,act,open,back,tab,enableRecommended,filter:f=>{S.filter=f;draw();},rows:()=>S.rows};
})();
