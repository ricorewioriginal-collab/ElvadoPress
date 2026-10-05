'use strict';
// Radioverzeichnis-Verwaltung: Meldungen prüfen, Sender/Webseiten ausschließen, Quellen und Funktionen steuern.
// Daten liegen serverseitig (cms/data/.directory/admin.json) und werden über die Aktionen directory_admin_get/save gepflegt.
window.DirectoryManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const S={tab:'reports',filter:'open',data:null,busy:false,results:null,q:''};
 const SETTINGS=[
  ['laut','laut.fm-Sender anzeigen','Sender aus der öffentlichen laut.fm-Schnittstelle in Suche, „Überrasch mich“ und World Radio.'],
  ['world','Weltradio anzeigen (radio-browser.info)','Sender aus der Community-Datenbank radio-browser.info.'],
  ['foreign_play','Fremd-Streams im Portal abspielen','Aus: Fremd-Sender werden nur gelistet und verlinkt, es gibt kein „Hören“ (Schnellschalter, falls es Beanstandungen gibt).'],
  ['preview','Webseite/Vorschau im Vollbild','Aus: Im Vollbild eines Fremd-Senders wird keine fremde Webseite geladen und keine Vorschau erzeugt.'],
  ['report','„Melden“-Button anzeigen','Besucher können Sender mit Grund melden; die Meldungen erscheinen hier.']
 ];
 const date=ts=>{try{return new Date(ts*1000).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return '';}};
 const api=(action,body)=>window.cmsApi(action,body);
 const toast=(m,bad)=>{try{window.cmsToast(m,!!bad);}catch(e){}};
 const srcPill=s=>`<span class="dm-pill ${s==='laut'?'':'grey'}">${s==='laut'?'laut.fm':'Weltradio'}</span>`;
 const openCount=()=>S.data?(S.data.reports||[]).filter(r=>r.status==='open').length:0;
 function badge(){const n=openCount();const b=document.getElementById('dmTabBadge');if(b){b.textContent=String(n);b.hidden=!n;}}
 async function load(){
  try{S.data=await api('directory_admin_get');}catch(e){toast(e.message||'Laden fehlgeschlagen',true);S.data=S.data||{settings:{},blocked:[],reports:[],reasons:{}};}
  badge();
 }
 async function send(body,ok){
  if(S.busy)return;S.busy=true;
  try{S.data=await api('directory_admin_save',body);toast(ok||'Gespeichert');}catch(e){toast(e.message||'Speichern fehlgeschlagen',true);}
  S.busy=false;badge();draw();
 }
 // ---------- Meldungen
 function reportsHtml(){
  const all=S.data.reports||[],open=all.filter(r=>r.status==='open'),done=all.filter(r=>r.status!=='open');
  const list=S.filter==='open'?open:done;
  const chips=`<div class="dm-tabs" style="border:0;margin:0 0 10px;padding:0"><button class="dm-tab ${S.filter==='open'?'on':''}" onclick="DirectoryManager.filter('open')">Offen (${open.length})</button><button class="dm-tab ${S.filter==='done'?'on':''}" onclick="DirectoryManager.filter('done')">Erledigt (${done.length})</button></div>`;
  if(!list.length)return chips+`<div class="dm-empty">${S.filter==='open'?'Keine offenen Meldungen.':'Noch nichts erledigt.'}</div>`;
  return chips+'<div class="dm-list">'+list.map(r=>{
   const reason=(S.data.reasons||{})[r.reason]||r.reason;
   const st=r.status==='blocked'?'<span class="dm-pill red">ausgeschlossen</span>':r.status==='dismissed'?'<span class="dm-pill grey">verworfen</span>':'';
   const link=r.link?`<a class="btn-g" href="${esc(r.link)}" target="_blank" rel="noopener nofollow"><i class="fas fa-arrow-up-right-from-square"></i> Webseite</a>`:'';
   const lautLink=r.source==='laut'?`<a class="btn-g" href="https://laut.fm/${encodeURIComponent(r.station_id)}" target="_blank" rel="noopener nofollow"><i class="fas fa-arrow-up-right-from-square"></i> laut.fm</a>`:'';
   const acts=r.status==='open'?`<button class="btn-a" onclick="DirectoryManager.reportBlock('${esc(r.id)}',false)"><i class="fas fa-ban"></i> Sender ausschließen</button>${r.link&&r.source==='world'?`<button class="btn-g" onclick="DirectoryManager.reportBlock('${esc(r.id)}',true)" title="Alle Sender dieser Webseite ausschließen"><i class="fas fa-globe"></i> Ganze Webseite ausschließen</button>`:''}<button class="btn-g" onclick="DirectoryManager.reportDismiss('${esc(r.id)}')"><i class="fas fa-check"></i> Verwerfen</button>`:`<button class="btn-g" onclick="DirectoryManager.reportDelete('${esc(r.id)}')"><i class="fas fa-trash"></i> Löschen</button>`;
   return `<div class="dm-row"><div class="dm-head"><b>${esc(r.name||r.station_id)}</b>${srcPill(r.source)}<span class="dm-pill red">${esc(reason)}</span>${st}</div>
    ${r.text?`<div class="dm-txt">${esc(r.text)}</div>`:''}
    <div class="dm-meta">${r.count>1?r.count+'× gemeldet · ':''}zuletzt ${esc(date(r.last||r.ts))} · ID ${esc(r.station_id)}</div>
    <div class="dm-actions">${acts}${link}${lautLink}</div></div>`;
  }).join('')+'</div>';
 }
 // ---------- Ausschlüsse
 function blockedHtml(){
  const rows=S.data.blocked||[];
  const typeLab=k=>k.startsWith('laut:')?'laut.fm-Sender':k.startsWith('world:')?'Weltradio-Sender':'Webseite/Domain';
  const form=`<div class="dm-form">
   <div><label class="news-lbl">Art</label><select id="dmType" class="fc w-100"><option value="laut">laut.fm-Sender (Name aus der Adresse)</option><option value="world">Weltradio-Sender (UUID)</option><option value="host">Webseite / Domain (alle Sender dieser Seite)</option></select></div>
   <div><label class="news-lbl">Wert</label><input id="dmValue" class="fc w-100" placeholder="z. B. eisfm  oder  beispiel.de"></div>
   <div><label class="news-lbl">Grund (intern)</label><input id="dmReason" class="fc w-100" placeholder="z. B. Hinweis vom 12.10."></div>
   <div><button class="btn-a" onclick="DirectoryManager.blockAdd()"><i class="fas fa-ban"></i> Ausschließen</button></div></div>`;
  const search=`<div style="margin-bottom:14px"><label class="news-lbl">Sender suchen und ausschließen</label>
   <div style="display:flex;gap:8px"><input id="dmQ" class="fc w-100" placeholder="Sendername, Genre oder Stadt …" value="${esc(S.q)}" onkeydown="if(event.key==='Enter')DirectoryManager.search()"><button class="btn-g" onclick="DirectoryManager.search()"><i class="fas fa-magnifying-glass"></i> Suchen</button></div>
   <div id="dmResults" style="margin-top:8px">${resultsHtml()}</div></div>`;
  const list=rows.length?'<div class="dm-list">'+rows.map(b=>`<div class="dm-row"><div class="dm-head"><b>${esc(b.name||b.key)}</b><span class="dm-pill red">${esc(typeLab(b.key))}</span></div>
   <div class="dm-meta">${esc(b.key)}${b.reason?' · '+esc(b.reason):''} · ${esc(date(b.added))}${b.by?' · '+esc(b.by):''}</div>
   <div class="dm-actions"><button class="btn-g" onclick="DirectoryManager.blockRemove('${esc(b.key)}')"><i class="fas fa-rotate-left"></i> Ausschluss aufheben</button></div></div>`).join('')+'</div>':'<div class="dm-empty">Noch keine Ausschlüsse.</div>';
  return form+search+`<div class="news-lbl" style="margin-bottom:6px">Aktive Ausschlüsse (${rows.length})</div>`+list;
 }
 function resultsHtml(){
  if(S.results===null)return '';
  if(!S.results.length)return '<div class="hint">Keine Treffer.</div>';
  return '<div class="dm-list">'+S.results.map((r,i)=>`<div class="dm-row"><div class="dm-head"><b>${esc(r.name)}</b>${srcPill(r.source)}</div>
   <div class="dm-meta">${esc([r.country,(r.genres||[]).join(', ')].filter(Boolean).join(' · '))}</div>
   <div class="dm-actions"><button class="btn-g" onclick="DirectoryManager.blockResult(${i},false)"><i class="fas fa-ban"></i> Sender ausschließen</button>${r.source==='world'&&r.link?`<button class="btn-g" onclick="DirectoryManager.blockResult(${i},true)"><i class="fas fa-globe"></i> Ganze Webseite</button>`:''}<a class="btn-g" href="${esc(r.link)}" target="_blank" rel="noopener nofollow"><i class="fas fa-arrow-up-right-from-square"></i></a></div></div>`).join('')+'</div>';
 }
 // ---------- Einstellungen
 function settingsHtml(){
  const st=S.data.settings||{};
  return '<div class="dm-set">'+SETTINGS.map(([k,l,d])=>`<label><input type="checkbox" ${st[k]!==false?'checked':''} onchange="DirectoryManager.setting('${k}',this.checked)"><span><b>${esc(l)}</b><small>${esc(d)}</small></span></label>`).join('')+'</div>';
 }
 function draw(){
  const host=document.getElementById('directoryManager');if(!host||!S.data)return;
  const n=openCount();
  const tab=(k,l,extra)=>`<button class="dm-tab ${S.tab===k?'on':''}" onclick="DirectoryManager.tab('${k}')">${l}${extra||''}</button>`;
  host.innerHTML=`<div class="dm-tabs">${tab('reports','Meldungen',n?`<span class="dm-badge">${n}</span>`:'')}${tab('blocked','Ausschlüsse',` <span class="dm-meta">(${(S.data.blocked||[]).length})</span>`)}${tab('settings','Einstellungen')}</div>`
   +(S.tab==='reports'?reportsHtml():S.tab==='blocked'?blockedHtml():settingsHtml());
 }
 async function render(){
  const host=document.getElementById('directoryManager');if(host&&!S.data)host.innerHTML='<div class="dm-empty">Lädt …</div>';
  await load();draw();
 }
 return {
  render,
  async refreshBadge(){if(window.CMS_PACKS_AVAILABLE&&window.CMS_PACKS_AVAILABLE['ricorewi-radio']===false)return;try{S.data=await api('directory_admin_get');badge();}catch(e){}},
  tab(k){S.tab=k;draw();},
  filter(f){S.filter=f;draw();},
  reportBlock(id,host){if(confirm(host?'Alle Sender dieser Webseite aus dem Verzeichnis ausschließen?':'Diesen Sender aus dem Verzeichnis ausschließen?'))send({op:'report_block',id,host:host?1:0},'Ausgeschlossen');},
  reportDismiss(id){send({op:'report_dismiss',id},'Meldung verworfen');},
  reportDelete(id){send({op:'report_delete',id},'Meldung gelöscht');},
  blockAdd(){
   const type=document.getElementById('dmType').value,value=document.getElementById('dmValue').value.trim(),reason=document.getElementById('dmReason').value.trim();
   if(!value){toast('Bitte einen Wert eintragen',true);return;}
   send({op:'block_add',type,value,name:value,reason},'Ausgeschlossen');
  },
  blockRemove(key){if(confirm('Ausschluss aufheben?'))send({op:'block_remove',key},'Ausschluss aufgehoben');},
  async search(){
   const q=(document.getElementById('dmQ')||{}).value||'';S.q=q.trim();if(S.q.length<2){toast('Mindestens 2 Zeichen',true);return;}
   try{const base=(typeof CRON!=='undefined'?CRON:'api.php');const r=await fetch(base+'?action=directory_search&scope=all&limit=10&q='+encodeURIComponent(S.q));const d=await r.json();S.results=(d.results||[]).filter(x=>!x.own);}catch(e){S.results=[];}
   const box=document.getElementById('dmResults');if(box)box.innerHTML=resultsHtml();
  },
  blockResult(i,host){
   const r=(S.results||[])[i];if(!r)return;
   if(host){send({op:'block_add',type:'host',value:r.link,name:r.name,reason:'Ausschluss über Suche'},'Webseite ausgeschlossen');}
   else send({op:'block_add',type:r.source,value:r.id,name:r.name,reason:'Ausschluss über Suche'},'Ausgeschlossen');
  },
  setting(k,v){const st=Object.assign({},S.data.settings||{});st[k]=!!v;send({op:'settings',settings:st},'Einstellung gespeichert');}
 };
})();
