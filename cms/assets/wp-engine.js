'use strict';
// Verwaltung der WordPress-Engine (Systemprüfung, Core einspielen, Datenbank, Betriebsart, Systemanalyse). Spricht cms/engine-api.php an.
window.WpEngine=(()=>{
 const S={st:null,prep:null,ana:null,busy:'',msg:null,form:null};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>{try{return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';}catch(e){return '';}};
 const root=()=>document.getElementById('wpeRoot');
 async function api(action,body){
  const r=await fetch('/cms/engine-api.php?action='+action,{method:body===undefined?'GET':'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':tok()},body:body===undefined?undefined:JSON.stringify(body)});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  return d;
 }
 const BADGE={ok:['OK','#22a06b'],warn:['Warnung','#e0a100'],fail:['Fehler','#e5484d']};
 function badge(st){const b=BADGE[st]||BADGE.warn;return '<span style="color:#fff;background:'+b[1]+';border-radius:6px;padding:1px 8px;font-size:.74rem;font-weight:700">'+b[0]+'</span>';}
 function reqList(req){
  return '<div style="display:grid;gap:6px">'+(req.items||[]).map(i=>'<div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap"><span style="min-width:62px">'+badge(i.status)+'</span><b>'+esc(i.label)+'</b><span class="hint">'+esc(i.detail)+'</span></div>').join('')+'</div>';
 }
 function step(n,title,ok,body){
  return '<div class="card" style="margin:12px 0;border-left:4px solid '+(ok?'#22a06b':'#8a8fa8')+'"><div style="font-weight:700;margin-bottom:6px">'+n+'. '+esc(title)+(ok?' <span style="color:#22a06b">✓</span>':'')+'</div>'+body+'</div>';
 }
 function counts(c){
  const L={posts:'Beiträge',drafts:'Entwürfe',pages:'Seiten',media:'Medien',comments:'Kommentare',users:'Benutzer',categories:'Kategorien',tags:'Schlagwörter'};
  return L;
 }
 function analysis(){
  const a=S.ana;if(!a)return '';const L=counts();
  const rows=Object.keys(L).map(k=>'<tr><td>'+L[k]+'</td><td style="text-align:right">'+esc(a.native.counts[k])+'</td><td style="text-align:right">'+esc(a.wordpress.counts[k])+'</td></tr>').join('');
  return '<div style="margin-top:10px"><table class="tbl" style="width:100%;max-width:520px"><thead><tr><th style="text-align:left">Bestand</th><th style="text-align:right">ElvadoPress</th><th style="text-align:right">WordPress</th></tr></thead><tbody>'+rows+'</tbody></table>'
   +'<p class="hint" style="margin-top:6px">WordPress '+esc(a.wordpress.info.version)+' · PHP '+esc(a.wordpress.info.php)+' · Start in '+esc(a.boot_ms)+' ms · Theme: '+esc(a.wordpress.info.theme)+' · aktive WordPress-Plugins: '+esc(a.wordpress.info.plugins.length)+'</p></div>';
 }
 function draw(){
  const r=root();if(!r)return;const s=S.st;if(!s){r.innerHTML='<p class="hint">Lade …</p>';return;}
  const e=s.engine,busy=S.busy;const dis=busy?' disabled':'';
  const lat=S.prep&&S.prep.latest&&S.prep.latest.ok?S.prep.latest:null;
  const mode={off:'Aus',installed:'Eingerichtet (nicht in Gebrauch)',active:'Aktiv'}[e.mode]||e.mode;
  let h='';
  if(S.msg)h+='<div class="'+(S.msg.err?'danger-note':'hint')+'" style="margin:8px 0;padding:8px 12px;border-radius:8px;'+(S.msg.err?'':'background:rgba(34,160,107,.12)')+'">'+esc(S.msg.text)+'</div>';
  h+='<p><b>Betriebsart:</b> '+esc(mode)+(e.version?' · WordPress '+esc(e.version):'')+'</p>';
  h+=step(1,'Systemprüfung',s.steps.requirements,reqList(s.requirements));
  const core=e.core_present
   ?'<p>WordPress <b>'+esc(e.version)+'</b> ist eingespielt'+(e.installed_at?' ('+esc(e.installed_at.slice(0,10))+')':'')+'.'+(S.prep&&S.prep.update_available?' Neu verfügbar: <b>'+esc(S.prep.latest.version)+'</b>.':'')+'</p>'
   :'<p class="hint">Der offizielle WordPress-Core wird von wordpress.org geladen, gegen die veröffentlichte Prüfsumme (SHA-1) geprüft, auf gefährliche Inhalte untersucht und erst dann atomar eingespielt. Dein <code>wp-content</code>-Ordner bleibt unberührt.</p>';
  h+=step(2,'WordPress-Core',s.steps.core,core+'<div class="ap-add" style="margin-top:8px"><button class="btn-g" type="button" onclick="WpEngine.prepare()"'+dis+'><i class="fas fa-magnifying-glass"></i> Aktuelle Version prüfen</button>'
   +(e.core_present?'':'<button class="btn-a" type="button" onclick="WpEngine.core()"'+(busy||!s.steps.requirements?' disabled':'')+'><i class="fas fa-download"></i> '+(lat?'WordPress '+esc(lat.version)+' ':'WordPress ')+'herunterladen und einspielen</button>')+'</div>'
   +(lat?'<p class="hint" style="margin-top:6px">Aktuelle Version bei wordpress.org: '+esc(lat.version)+' (verlangt PHP ≥ '+esc(lat.php)+', MySQL ≥ '+esc(lat.mysql)+').</p>':''));
  const db=Object.assign({host:'localhost',name:'',user:'',pass:'',prefix:'wp_'},s.db.configured||{},S.form||{});
  const f=(id,label,val,type)=>'<label class="news-lbl">'+label+'</label><input class="fc" id="wpe'+id+'" type="'+(type||'text')+'" value="'+esc(val)+'" autocomplete="off">';
  const dbForm=s.db.ready
   ?'<p>Datenbank <b>'+esc(db.name)+'</b> auf '+esc(db.host)+' · Präfix <code>'+esc(db.prefix)+'</code> · '+esc(s.db.server)+'.</p>'
   :'<p class="hint">Eine <b>leere</b> MySQL-/MariaDB-Datenbank (beim Hoster anlegen). Vorhandene Daten werden nie überschrieben. Das Passwort wird nur geschützt auf dem Server gespeichert.</p><div style="display:grid;gap:6px;max-width:420px">'
     +f('Host','Datenbank-Server',db.host)+f('Name','Datenbankname',db.name)+f('User','Benutzer',db.user)+f('Pass','Passwort',db.pass||'','password')+f('Prefix','Tabellenpräfix',db.prefix)+'</div>'
     +'<div class="ap-add" style="margin-top:8px"><button class="btn-g" type="button" onclick="WpEngine.dbTest()"'+dis+'><i class="fas fa-plug"></i> Verbindung testen</button><button class="btn-a" type="button" onclick="WpEngine.dbInstall()"'+(busy||!e.core_present?' disabled':'')+'><i class="fas fa-database"></i> WordPress einrichten</button></div>';
  h+=step(3,'Datenbank',s.steps.database,dbForm);
  const act=e.mode==='active'
   ?'<button class="btn-g" type="button" onclick="WpEngine.mode(\'installed\')"'+dis+'>Deaktivieren</button>'
   :'<button class="btn-a" type="button" onclick="WpEngine.mode(\'active\')"'+(busy||e.mode==='off'||!s.db.ready?' disabled':'')+'><i class="fas fa-power-off"></i> Aktivieren</button>';
  h+=step(4,'Betrieb und Systemanalyse',s.steps.active,'<p class="hint">Aktivieren startet WordPress einmal zur Prüfung und merkt sich die Betriebsart. Dieser Schritt verändert die Website nicht; Inhalte und Funktionen werden in späteren Schritten schrittweise übernommen.</p>'
   +'<div class="ap-add">'+act+'<button class="btn-g" type="button" onclick="WpEngine.analyze()"'+(busy||e.mode==='off'||!s.db.ready?' disabled':'')+'><i class="fas fa-scale-balanced"></i> Systemanalyse (ElvadoPress ↔ WordPress)</button></div>'+analysis());
  if(e.core_present)h+='<details style="margin-top:12px"><summary>Engine entfernen</summary><p class="hint">Entfernt WordPress-Dateien, Zugangsdaten und Zustand. Die Datenbank-Tabellen und deine Website bleiben unverändert.</p><button class="btn-g" type="button" onclick="WpEngine.remove()"'+dis+'><i class="fas fa-trash"></i> WordPress-Engine entfernen</button></details>';
  if(busy)h+='<p class="hint" style="margin-top:10px"><i class="fas fa-spinner fa-spin"></i> '+esc(busy)+' …</p>';
  r.innerHTML=h;
 }
 async function run(label,fn){
  if(S.busy)return;if(document.getElementById('wpeHost'))S.form=dbCfg();   // Eingaben vor dem Neuzeichnen merken
  S.busy=label;S.msg=null;draw();
  try{await fn();}catch(e){S.msg={err:true,text:e.message||'Fehler'};}
  S.busy='';draw();
 }
 const val=id=>{const e=document.getElementById('wpe'+id);return e?e.value.trim():'';};
 const dbCfg=()=>({host:val('Host'),name:val('Name'),user:val('User'),pass:(document.getElementById('wpePass')||{}).value||'',prefix:val('Prefix')});
 function apply(d){if(d.engine){S.st=d;}}
 async function load(){try{const d=await api('engine_status');if(d.status==='ok'){S.st=d;}else{S.msg={err:true,text:d.message||'Fehler'};}}catch(e){S.msg={err:true,text:e.message};}draw();}
 return {
  load,
  prepare:()=>run('Prüfe wordpress.org',async()=>{const d=await api('engine_prepare');S.prep=d;S.msg=d.latest&&!d.latest.ok?{err:true,text:d.latest.message}:{text:'Aktuelle WordPress-Version: '+d.latest.version+'.'};}),
  core:()=>run('Lade und prüfe WordPress (das dauert einen Moment)',async()=>{const d=await api('engine_core',{version:(S.prep&&S.prep.latest&&S.prep.latest.version)||''});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  dbTest:()=>run('Teste die Verbindung',async()=>{const d=await api('engine_db_test',{db:dbCfg()});S.msg={err:d.status!=='ok',text:d.message||''};}),
  dbInstall:()=>run('Richte WordPress ein (Tabellen anlegen)',async()=>{const d=await api('engine_db_install',{db:dbCfg()});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  mode:m=>run(m==='active'?'Starte WordPress zur Prüfung':'Schalte um',async()=>{const d=await api('engine_mode',{mode:m});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  analyze:()=>run('Starte WordPress und zähle',async()=>{const d=await api('engine_analyze');if(d.status==='ok'){S.ana=d;S.msg={text:'Systemanalyse fertig.'};}else S.msg={err:true,text:d.message||'Fehler'};}),
  remove:()=>{if(!confirm('WordPress-Engine wirklich entfernen? Die Datenbank-Tabellen bleiben erhalten.'))return;run('Entferne die Engine',async()=>{const d=await api('engine_remove',{confirm:true});apply(d);S.ana=null;S.msg={err:d.status!=='ok',text:d.message||''};});}
 };
})();
