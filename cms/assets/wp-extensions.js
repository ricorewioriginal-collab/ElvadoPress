'use strict';
// Plugins und Themes der WordPress-Engine: installierte verwalten (aktivieren, deaktivieren, entfernen), im Verzeichnis suchen, ZIP hochladen, abgesicherter Modus. Spricht cms/engine-api.php (ext_*) an.
window.WpExt=(()=>{
 const S={kind:'plugin',items:null,res:null,q:'',busy:'',msg:null,safe:false,incident:null,inactive:false};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>{try{return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';}catch(e){return '';}};
 const root=()=>document.getElementById('wpxRoot');
 async function api(action,body,query){
  const r=await fetch('/cms/engine-api.php?action='+action+(query||''),{method:body===undefined?'GET':'POST',headers:body instanceof FormData?{'X-ElvadoPress-Token':tok()}:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:body===undefined?undefined:(body instanceof FormData?body:JSON.stringify(body))});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  return d;
 }
 function take(d){if(d.items)S.items=d.items;if('safe_mode' in d)S.safe=!!d.safe_mode;if('incident' in d)S.incident=d.incident;}
 async function load(){
  try{
   const d=await api('ext_list',undefined,'&kind='+S.kind);
   if(d.status!=='ok'){S.inactive=true;S.items=null;S.msg={err:true,text:d.message||'Nicht verfügbar'};draw();return;}
   S.inactive=false;take(d);
  }catch(e){S.msg={err:true,text:e.message};}
  draw();
 }
 async function run(label,fn){
  if(S.busy)return;S.busy=label;S.msg=null;draw();
  try{const d=await fn();if(d.status==='ok'){take(d);S.msg={text:d.message||'Erledigt.'};}else S.msg={err:true,text:d.message||'Fehlgeschlagen'};}
  catch(e){S.msg={err:true,text:e.message};}
  S.busy='';
  if(S.msg&&S.msg.err&&S.msg.text.indexOf('abgestürzt')>=0)setTimeout(load,300);   // nächster Aufruf stellt wieder her
  draw();
 }
 const act=(a,id,extra)=>run('Arbeite …',()=>api(a,Object.assign({kind:S.kind,id:id},extra||{})));
 function item(i){
  const act1=S.kind==='plugin'
   ?(i.active?'<button class="btn-g" data-a="deactivate" data-id="'+esc(i.id)+'">Deaktivieren</button>':'<button class="btn-a" data-a="activate" data-id="'+esc(i.id)+'">Aktivieren</button>')
   :(i.active?'<span class="hint" style="padding:6px 10px">Aktiv</span>':(i.error?'':'<button class="btn-a" data-a="activate" data-id="'+esc(i.id)+'">Aktivieren</button>'));
  const del=i.active&&S.kind==='theme'?'':'<button class="btn-g" data-a="delete" data-id="'+esc(i.id)+'" title="Entfernen"><i class="fas fa-trash"></i></button>';
  return '<div class="card" style="margin:8px 0;padding:10px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap'+(i.active?';border-left:4px solid #22a06b':'')+'">'
   +'<div style="flex:1;min-width:220px"><b>'+esc(i.name||i.slug)+'</b> <span class="hint">'+esc(i.version)+(i.author?' · '+esc(i.author):'')+(i.parent?' · Kind-Theme von '+esc(i.parent):'')+(i.block_theme?' · Block-Theme':'')+'</span>'
   +(i.description?'<div class="hint" style="margin-top:3px">'+esc(i.description)+'</div>':'')+(i.error?'<div class="danger-note" style="margin-top:4px;padding:4px 8px">'+esc(i.error)+'</div>':'')+'</div>'
   +'<div style="display:flex;gap:6px">'+act1+del+'</div></div>';
 }
 function results(){
  const r=S.res;if(!r)return '';
  if(!r.ok)return '<div class="danger-note" style="margin:8px 0;padding:8px 12px">'+esc(r.message)+'</div>';
  if(!r.items.length)return '<p class="hint">Keine Treffer.</p>';
  return r.items.map(i=>'<div class="card" style="margin:8px 0;padding:10px 14px;display:flex;gap:12px;align-items:center;flex-wrap:wrap"><div style="flex:1;min-width:220px"><b>'+esc(i.name)+'</b> <span class="hint">'+esc(i.version)+(i.author?' · '+esc(i.author):'')+(i.installs?' · '+esc(i.installs.toLocaleString('de-DE'))+'+ Installationen':'')+'</span><div class="hint" style="margin-top:3px">'+esc(i.description)+'</div></div>'
   +(i.installed?'<span class="hint">Installiert</span>':'<button class="btn-a" data-a="install" data-slug="'+esc(i.slug)+'">Installieren</button>')+'</div>').join('');
 }
 function draw(){
  const r=root();if(!r)return;const dis=S.busy?' disabled':'';
  let h='';
  if(S.msg)h+='<div class="'+(S.msg.err?'danger-note':'hint')+'" style="margin:8px 0;padding:8px 12px;border-radius:8px;'+(S.msg.err?'':'background:rgba(34,160,107,.12)')+'">'+esc(S.msg.text)+'</div>';
  if(S.inactive){r.innerHTML=h+'<p class="hint">Die WordPress-Engine ist nicht aktiv. Richte sie unter <b>System → WordPress-Engine</b> ein.</p>';return;}
  if(S.incident)h+='<div class="danger-note" style="margin:8px 0;padding:8px 12px"><b>Absturzschutz:</b> '+esc(S.incident.what)+' <span class="hint">('+esc(String(S.incident.when).replace('T',' ').slice(0,16))+')</span></div>';
  h+='<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:8px">'
   +'<button class="'+(S.kind==='plugin'?'btn-a':'btn-g')+'" data-k="plugin">Plugins</button><button class="'+(S.kind==='theme'?'btn-a':'btn-g')+'" data-k="theme">Themes</button>'
   +'<span style="flex:1"></span><label class="hint" style="display:flex;gap:6px;align-items:center" title="WordPress startet ohne Plugins und ohne Theme-Funktionen"><input type="checkbox" class="switch" data-safe="1"'+(S.safe?' checked':'')+dis+'> Abgesicherter Modus</label></div>';
  if(S.safe)h+='<div class="danger-note" style="margin:8px 0;padding:8px 12px">Abgesicherter Modus ist an: WordPress läuft ohne Plugins und Theme-Funktionen.</div>';
  h+='<h3 style="margin:14px 0 4px;font-size:1rem">Installiert</h3>';
  h+=S.items?(S.items.length?S.items.map(item).join(''):'<p class="hint">Noch nichts installiert.</p>'):'<p class="hint">Lade …</p>';
  h+='<h3 style="margin:18px 0 4px;font-size:1rem">Neu hinzufügen</h3><div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px"><input class="fc" id="wpxQ" style="flex:1;min-width:200px" placeholder="Im Verzeichnis von wordpress.org suchen …" value="'+esc(S.q)+'"><button class="btn-a" data-a="search"'+dis+'><i class="fas fa-magnifying-glass"></i> Suchen</button>'
   +'<label class="btn-g" style="margin:0;cursor:pointer"><i class="fas fa-file-zipper"></i> ZIP hochladen<input type="file" id="wpxZip" accept=".zip,application/zip" hidden'+dis+'></label></div>';
  h+=results();
  if(S.busy)h+='<p class="hint"><i class="fas fa-spinner fa-spin"></i> '+esc(S.busy)+'</p>';
  r.innerHTML=h;
 }
 function bind(){
  const r=root();if(!r||r.dataset.bound)return;r.dataset.bound='1';
  r.addEventListener('click',e=>{
   const k=e.target.closest('[data-k]');if(k){S.kind=k.dataset.k;S.res=null;S.items=null;S.msg=null;draw();load();return;}
   const b=e.target.closest('[data-a]');if(!b)return;const a=b.dataset.a,id=b.dataset.id;
   if(a==='activate'||a==='deactivate')act('ext_'+a,id);
   else if(a==='delete'){if(confirm('Wirklich entfernen? Die Dateien werden vorher im Zustandsordner gesichert.'))act('ext_delete',id,S.kind==='plugin'&&confirm('Auch die Daten dieses Plugins in der Datenbank aufräumen (Uninstall)?\n\nOK = ja, Abbrechen = Daten behalten.')?{purge:true}:{});}
   else if(a==='install')run('Installiere …',()=>api('ext_install',{kind:S.kind,slug:b.dataset.slug}));
   else if(a==='search'){const q=(document.getElementById('wpxQ')||{}).value||'';S.q=q;run('Suche …',async()=>{const d=await api('ext_search',undefined,'&kind='+S.kind+'&q='+encodeURIComponent(q));S.res=d;return {status:'ok',message:''};});}
  });
  r.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.id==='wpxQ'){e.preventDefault();r.querySelector('[data-a="search"]').click();}});
  r.addEventListener('change',e=>{
   if(e.target.dataset.safe)run('Ändere …',()=>api('ext_safe',{on:e.target.checked}).then(d=>{if(d.status==='ok')d.safe_mode=!!(d.engine&&d.engine.safe);return d;}));
   if(e.target.id==='wpxZip'&&e.target.files[0]){const f=e.target.files[0];const fd=new FormData();fd.append('file',f);fd.append('kind',S.kind);run('Lade hoch …',()=>api('ext_upload',fd));}
  });
 }
 return {load:()=>{bind();load();}};
})();
