'use strict';
// Verwaltung der WordPress-Engine (Systemprüfung, Core einspielen, Datenbank, Betriebsart, Systemanalyse). Spricht cms/engine-api.php an.
window.WpEngine=(()=>{
 const S={st:null,prep:null,ana:null,mig:null,run:null,busy:'',msg:null,form:null};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>{try{return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';}catch(e){return '';}};
 const root=()=>document.getElementById('wpeRoot');
 async function api(action,body){
  const r=await fetch('/cms/engine-api.php?action='+action,{method:body===undefined?'GET':'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':tok()},body:body===undefined?undefined:JSON.stringify(body)});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  return d;
 }
 const DEMO=()=>!!(window.RRW_DEMO&&window.RRW_DEMO.engine);
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

 const VERD={ready:['Bereit','#22a06b'],ready_with_warnings:['Bereit mit Hinweisen','#e0a100'],blocked:['Blockiert','#e5484d']};
 const SUMLBL={posts:'Beiträge',pages:'Seiten',media:'Medien',users:'Benutzer'};
 function migration(){
  const r=S.mig;const dis=S.busy?' disabled':'';
  let h='<p class="hint">Der <b>Trockenlauf</b> prüft, was bei einer Übernahme in WordPress passieren würde, und erstellt einen Bericht. Er <b>schreibt nichts</b> in WordPress und verändert weder Inhalte noch Einstellungen deiner Website – gespeichert wird nur der Bericht. Die echte Migration startet erst nach deiner ausdrücklichen Freigabe.</p>'
   +'<div class="ap-add"><button class="btn-a" type="button" onclick="WpEngine.migPlan()"'+dis+'><i class="fas fa-clipboard-check"></i> Trockenlauf starten</button>'
   +(r?'<button class="btn-g" type="button" onclick="WpEngine.migDownload()"><i class="fas fa-download"></i> Bericht (JSON)</button>':'')+'</div>';
  if(!r)return h;
  const v=VERD[r.verdict]||VERD.blocked;
  h+='<p style="margin-top:10px"><span style="color:#fff;background:'+v[1]+';border-radius:6px;padding:2px 10px;font-weight:700">'+esc(v[0])+'</span> <span class="hint">Bericht vom '+esc(String(r.generated_at||'').replace('T',' ').slice(0,16))+' · es wurde nichts geändert</span></p>';
  const t=r.summary||{};
  const rows=Object.keys(SUMLBL).filter(k=>t[k]).map(k=>{const x=t[k];return '<tr><td>'+SUMLBL[k]+'</td><td style="text-align:right">'+esc(x.total)+'</td><td style="text-align:right">'+esc(x.create||0)+'</td><td style="text-align:right">'+esc(x.skip||0)+'</td><td style="text-align:right">'+esc((x.rename||0)+(x.rejected||0)+(x.missing||0))+'</td></tr>';}).join('');
  h+='<table class="tbl" style="width:100%;max-width:560px;margin-top:6px"><thead><tr><th style="text-align:left">Bestand</th><th style="text-align:right">Gesamt</th><th style="text-align:right">Neu anlegen</th><th style="text-align:right">Überspringen</th><th style="text-align:right">Umbenannt/abgelehnt/fehlt</th></tr></thead><tbody>'+rows+'</tbody></table>';
  const c=r.content||{};
  h+='<p class="hint" style="margin-top:6px">Blöcke: '+esc(c.blocks_converted)+' umgewandelt, '+esc(c.blocks_fallback)+' als HTML-Block erhalten · Medienverweise: '+esc(c.media_refs)+' ('+esc(c.media_refs_broken)+' defekt) · Menüs: '+esc((t.menus||{}).menus)+' mit '+esc((t.menus||{}).items)+' Einträgen · Widgets zum manuellen Einrichten: '+esc((t.widgets||{}).manual)+'</p>';
  const li=(a,col)=>a.length?'<ul style="margin:6px 0 0 18px">'+a.map(i=>'<li style="color:'+col+'"><b>'+esc(i.area)+':</b> '+esc(i.message)+'</li>').join('')+'</ul>':'';
  if((r.blockers||[]).length)h+='<p style="margin-top:8px"><b>Blockierend</b></p>'+li(r.blockers,'#e5484d');
  if((r.warnings||[]).length)h+='<p style="margin-top:8px"><b>Hinweise</b></p>'+li(r.warnings,'inherit');
  h+='<details style="margin-top:8px"><summary>Geplante Schritte der echten Migration ('+esc((r.steps||[]).length)+')</summary><ol style="margin:6px 0 0 18px">'+(r.steps||[]).map(x=>'<li><b>'+esc(x.title)+'</b> – '+esc(x.detail)+'</li>').join('')+'</ol></details>';
  h+=runCard();
  h+='<details style="margin-top:6px"><summary>Risiken</summary><ul style="margin:6px 0 0 18px">'+(r.risks||[]).map(x=>'<li>'+esc(x)+'</li>').join('')+'</ul></details>';
  return h;
 }

 function runCard(){
  const r=S.mig,x=S.run;let h='';
  if(r&&r.verdict!=='blocked')h+='<div style="margin-top:12px;padding:10px;border:1px solid #e0a100;border-radius:8px"><b>Echte Migration</b><p class="hint">Legt Benutzer, Medien, Begriffe, Beiträge, Seiten und Menüs in WordPress an. Vorher wird eine Sicherung erstellt. Deine bisherigen Daten und Medien bleiben unverändert; die Website selbst wird <b>nicht</b> umgeschaltet. Der Lauf ist wiederholbar und lässt sich zurückbauen.</p><button class="btn-a" type="button" onclick="WpEngine.migRun()"'+(S.busy?' disabled':'')+'><i class="fas fa-play"></i> Migration ausführen …</button></div>';
  if(x){
   const st={done:'abgeschlossen',partial:'teilweise (Zeitbudget) – erneut starten setzt fort',failed:'abgebrochen'}[x.status]||x.status;
   h+='<div style="margin-top:10px"><b>Letzter Lauf</b> '+esc(x.id)+': '+esc(st)+(x.rolled_back?' · zurückgebaut':'')+'<ul style="margin:6px 0 0 18px">'+(x.steps||[]).map(t=>'<li>'+esc(t.name)+': '+esc(t.status)+(t.detail&&t.detail.created!==undefined?' ('+esc(t.detail.created)+' neu)':'')+'</li>').join('')+'</ul>'
    +((x.errors||[]).length?'<p class="danger-note">'+esc(x.errors.join(' · '))+'</p>':'')
    +(x.rolled_back?'':'<button class="btn-g" type="button" onclick="WpEngine.migRollback(\''+esc(x.id)+'\')"'+(S.busy?' disabled':'')+'><i class="fas fa-rotate-left"></i> Lauf zurückbauen</button>')+'</div>';
  }
  return h;
 }
 function draw(){
  const r=root();if(!r)return;const s=S.st;if(!s){r.innerHTML='<p class="hint">Lade …</p>';return;}
  const e=s.engine,busy=S.busy;const dis=busy?' disabled':'';
  const lat=S.prep&&S.prep.latest&&S.prep.latest.ok?S.prep.latest:null;
  const mode={off:'Aus',installed:'Eingerichtet (nicht in Gebrauch)',active:'Aktiv'}[e.mode]||e.mode;
  let h='';
  if(S.msg)h+='<div class="'+(S.msg.err?'danger-note':'hint')+'" style="margin:8px 0;padding:8px 12px;border-radius:8px;'+(S.msg.err?'':'background:rgba(34,160,107,.12)')+'">'+esc(S.msg.text)+'</div>';
  h+='<p><b>Betriebsart:</b> '+esc(mode)+(e.version?' · WordPress '+esc(e.version):'')+'</p>';
  if(DEMO()){   // Demo: Core, Datenbank und Betriebsart verwaltet die Demo selbst
   const act=e.mode==='active';
   h+=step(1,'Demo-Engine',act,'<p>'+(act?'Der <b>echte WordPress-Core '+esc(e.version||'')+'</b> läuft in dieser Demo. Alle Bereiche der Engine (Inhalte, Medien, Benutzer, Plugins &amp; Themes, Menüs, Widgets, Blöcke, Migration, Live Builder) lassen sich ausprobieren. Nach dem Zurücksetzen der Demo richtet sich die Engine selbst neu ein.':'Die Engine wird für die Demo vorbereitet (Core einspielen, Tabellen anlegen) – das dauert beim ersten Mal etwas.')+'</p><p class="hint">Plugins und Themes: ausgewählte, bekannte Pakete aus dem WordPress-Verzeichnis lassen sich installieren, vorinstallierte aktivieren. Gesperrt bleiben ZIP-Upload und andere Pakete (fremder Programmcode auf dem gemeinsamen Server).</p><div class="ap-add"><button class="btn-a" type="button" onclick="WpEngine.demoSetup()"'+dis+'><i class="fas fa-rotate"></i> '+(act?'Neu einrichten':'Jetzt vorbereiten')+'</button><button class="btn-g" type="button" onclick="WpEngine.analyze()"'+(busy||!act?' disabled':'')+'><i class="fas fa-scale-balanced"></i> Systemanalyse</button></div>'+analysis());
   h+=step(2,'Migration (Trockenlauf und echter Lauf)',!!S.mig&&S.mig.verdict!=='blocked',migration());
   if(busy)h+='<p class="hint" style="margin-top:10px"><i class="fas fa-spinner fa-spin"></i> '+esc(busy)+' …</p>';
   r.innerHTML=h;return;
  }
  h+=step(1,'Systemprüfung',s.steps.requirements,reqList(s.requirements));
  const core=e.core_present
   ?'<p>WordPress <b>'+esc(e.version)+'</b> ist eingespielt'+(e.installed_at?' ('+esc(e.installed_at.slice(0,10))+')':'')+'.'+(S.prep&&S.prep.update_available?' Neu verfügbar: <b>'+esc(S.prep.latest.version)+'</b>.':'')+'</p>'
   :'<p class="hint">Der offizielle WordPress-Core wird von wordpress.org geladen, gegen die veröffentlichte Prüfsumme (SHA-1) geprüft, auf gefährliche Inhalte untersucht und erst dann atomar eingespielt. Dein <code>wp-content</code>-Ordner bleibt unberührt.</p>';
  h+=step(2,'WordPress-Core',s.steps.core,core+'<div class="ap-add" style="margin-top:8px"><button class="btn-g" type="button" onclick="WpEngine.prepare()"'+dis+'><i class="fas fa-magnifying-glass"></i> Aktuelle Version prüfen</button>'
   +(e.core_present?'':'<button class="btn-a" type="button" onclick="WpEngine.core()"'+(busy||!s.steps.requirements?' disabled':'')+'><i class="fas fa-download"></i> '+(lat?'WordPress '+esc(lat.version)+' ':'WordPress ')+'herunterladen und einspielen</button>')+'</div>'
   +(lat?'<p class="hint" style="margin-top:6px">Aktuelle Version bei wordpress.org: '+esc(lat.version)+' (verlangt PHP ≥ '+esc(lat.php)+', MySQL ≥ '+esc(lat.mysql)+').</p>':''));
  const ov=s.db.overview||{shared:false,legacy:false,default_prefix:'wpk_',suggest_prefix:'wpk_',cms_prefix:''};
  const db=Object.assign({host:'localhost',name:'',user:'',pass:'',prefix:ov.suggest_prefix||'wpk_'},s.db.configured||{},S.form||{});
  const f=(id,label,val,type)=>'<label class="news-lbl">'+label+'</label><input class="fc" id="wpe'+id+'" type="'+(type||'text')+'" value="'+esc(val)+'" autocomplete="off">';
  const btns='<div class="ap-add" style="margin-top:8px"><button class="btn-g" type="button" onclick="WpEngine.dbTest()"'+dis+'><i class="fas fa-plug"></i> Verbindung testen</button><button class="btn-a" type="button" onclick="WpEngine.dbInstall()"'+(busy||!e.core_present?' disabled':'')+'><i class="fas fa-database"></i> WordPress einrichten</button></div>';
  const goDb='<a href="#" onclick="cmsTab(\'database\',document.querySelector(\'.tab[data-tab=database]\'));window.SystemManager&&SystemManager.loadDatabase&&SystemManager.loadDatabase();return false">System → Datenbank</a>';
  let dbForm;
  if(s.db.ready){
    dbForm='<p>Datenbank <b>'+esc(db.name)+'</b> auf '+esc(db.host)+' · Präfix <code>'+esc(db.prefix)+'</code> · '+esc(s.db.server)+'.</p>'
      +(ov.shared?'<p class="hint">Gemeinsame Datenbank: CMS und WordPress-Kern nutzen dieselbe Verbindung ('+goDb+'); WordPress hat eigene Tabellen mit dem Präfix <code>'+esc(db.prefix)+'</code>.</p>'
      :'<p class="hint">Diese Verbindung wird noch getrennt von den Datenbank-Einstellungen des CMS gespeichert. <button class="btn-g" type="button" onclick="WpEngine.dbUnify()"'+dis+'><i class="fas fa-link"></i> Gemeinsame Datenbank verwenden</button></p>');
  }else if(ov.shared){
    dbForm='<p><b>Gemeinsame Datenbank</b> <code>'+esc(db.name)+'</code> auf '+esc(db.host)+' – dieselbe Verbindung wie das CMS ('+goDb+'). Es wird nichts überschrieben: WordPress legt eigene Tabellen mit eigenem Präfix an.</p>'
      +'<div style="display:grid;gap:6px;max-width:420px">'+f('Prefix','Tabellenpräfix für WordPress',db.prefix)+'</div>'
      +'<p class="hint" style="margin-top:4px">Das Präfix <code>'+esc(ov.cms_prefix||'wp_')+'</code> nutzt die WordPress-Schicht des CMS – wähle ein anderes (Vorschlag: <code>'+esc(ov.default_prefix||'wpk_')+'</code>).</p>'+btns;
  }else if(ov.legacy){
    dbForm='<p>Datenbank <b>'+esc(db.name)+'</b> auf '+esc(db.host)+' · Präfix <code>'+esc(db.prefix)+'</code> (eigene Verbindung der Engine).</p>'
      +'<p class="hint">Es gibt nur <b>eine</b> Datenbank für CMS und WordPress. Diese Verbindung wird dazu in die Datenbank-Einstellungen des CMS übernommen ('+goDb+'); die Tabellen und das Präfix bleiben unverändert.</p>'
      +'<div class="ap-add" style="margin-top:8px"><button class="btn-a" type="button" onclick="WpEngine.dbUnify()"'+dis+'><i class="fas fa-link"></i> Gemeinsame Datenbank verwenden</button></div>'+btns;
  }else{
    dbForm='<p class="hint">Eine <b>leere</b> MySQL-/MariaDB-Datenbank (beim Hoster anlegen). Vorhandene Daten werden nie überschrieben. Die Verbindung gilt für das ganze CMS und lässt sich später unter '+goDb+' ändern; das Passwort wird nur geschützt auf dem Server gespeichert.</p><div style="display:grid;gap:6px;max-width:420px">'
     +f('Host','Datenbank-Server',db.host)+f('Name','Datenbankname',db.name)+f('User','Benutzer',db.user)+f('Pass','Passwort',db.pass||'','password')+f('Prefix','Tabellenpräfix für WordPress',db.prefix)+'</div>'+btns;
  }
  h+=step(3,'Datenbank',s.steps.database,dbForm);
  const act=e.mode==='active'
   ?'<button class="btn-g" type="button" onclick="WpEngine.mode(\'installed\')"'+dis+'>Deaktivieren</button>'
   :'<button class="btn-a" type="button" onclick="WpEngine.mode(\'active\')"'+(busy||e.mode==='off'||!s.db.ready?' disabled':'')+'><i class="fas fa-power-off"></i> Aktivieren</button>';
  h+=step(4,'Betrieb und Systemanalyse',s.steps.active,'<p class="hint">Aktivieren startet WordPress einmal zur Prüfung und merkt sich die Betriebsart. Dieser Schritt verändert die Website nicht; Inhalte und Funktionen werden in späteren Schritten schrittweise übernommen.</p>'
   +'<div class="ap-add">'+act+'<button class="btn-g" type="button" onclick="WpEngine.analyze()"'+(busy||e.mode==='off'||!s.db.ready?' disabled':'')+'><i class="fas fa-scale-balanced"></i> Systemanalyse (ElvadoPress ↔ WordPress)</button></div>'+analysis());
  h+=step(5,'Migration (Trockenlauf)',!!S.mig&&S.mig.verdict!=='blocked',migration());
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
 const dbCfg=()=>document.getElementById('wpeHost')?{host:val('Host'),name:val('Name'),user:val('User'),pass:(document.getElementById('wpePass')||{}).value||'',prefix:val('Prefix')}:{prefix:val('Prefix')};   // mit gemeinsamer Datenbank zählt nur das Präfix
 function apply(d){if(d.engine){S.st=d;}}
 async function load(){try{const d=await api('engine_status');if(d.status==='ok'){S.st=d;}else{S.msg={err:true,text:d.message||'Fehler'};}try{const m=await api('migration_report');if(m.status==='ok'&&m.report)S.mig=m.report;const q=await api('migration_runs');if(q.status==='ok')S.run=q.run;}catch(e){}}catch(e){S.msg={err:true,text:e.message};}draw();}
 async function demoBoot(){
  if(!DEMO()||window.__epDemoBoot)return;window.__epDemoBoot=1;
  const tick=async()=>{try{const app=document.getElementById('cmsApp');if(!app||app.style.display==='none')return;const st=await api('engine_status');if(st.status==='ok'&&st.engine&&st.engine.mode!=='active'&&!S.busy&&!window.__epDemoBusy){window.__epDemoBusy=1;try{const d=await api('engine_demo_setup',{});if(d.status==='ok'){apply(d);if(window.cmsToast)cmsToast('Demo-Engine bereit ✓');draw()}}finally{window.__epDemoBusy=0}}}catch(e){window.__epDemoBusy=0}};
  setTimeout(tick,2500);setInterval(tick,45000);
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',demoBoot);else demoBoot();
 return {
  load,
  prepare:()=>run('Prüfe wordpress.org',async()=>{const d=await api('engine_prepare');S.prep=d;S.msg=d.latest&&!d.latest.ok?{err:true,text:d.latest.message}:{text:'Aktuelle WordPress-Version: '+d.latest.version+'.'};}),
  core:()=>run('Lade und prüfe WordPress (das dauert einen Moment)',async()=>{const d=await api('engine_core',{version:(S.prep&&S.prep.latest&&S.prep.latest.version)||''});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  dbInfo:async(target)=>{const el=document.getElementById(target);if(!el)return;
   try{const d=await api('engine_status');if(d.status!=='ok'){el.textContent='';return}
    const c=d.db.configured,ov=d.db.overview||{};
    if(!d.engine.core_present&&!c){el.innerHTML='<i class="fas fa-circle-info"></i> WordPress-Kern: noch nicht eingerichtet. Beim Einrichten (Website → WordPress-Engine) wird <b>diese</b> Datenbank mit eigenem Tabellenpräfix genutzt.';return}
    el.innerHTML='<i class="fas fa-circle-info"></i> WordPress-Kern: '+(d.db.ready?'nutzt diese Datenbank':(ov.shared?'bereit, nutzt diese Datenbank':'noch nicht eingerichtet'))+(c?' · Tabellenpräfix <code>'+esc(c.prefix)+'</code>':'')+(ov.legacy?' · <b>eigene, getrennte Verbindung</b> (in Website → WordPress-Engine zusammenführen)':'')+(ov.cms_prefix?' · WordPress-Schicht des CMS: <code>'+esc(ov.cms_prefix)+'</code>':'')+'. Die beiden Präfixe müssen verschieden sein.';
   }catch(e){el.textContent=''}},
  dbUnify:()=>{if(!confirm('Die Verbindung der WordPress-Engine wird zur gemeinsamen Datenbank des CMS (System → Datenbank). Die WordPress-Schicht des CMS nutzt diese Datenbank dann ebenfalls, mit eigenem Tabellenpräfix. Fortfahren?'))return;run('Führe die Datenbank zusammen',async()=>{const d=await api('engine_db_unify',{});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};})},
  dbTest:()=>run('Teste die Verbindung',async()=>{const d=await api('engine_db_test',{db:dbCfg()});S.msg={err:d.status!=='ok',text:d.message||''};}),
  dbInstall:()=>run('Richte WordPress ein (Tabellen anlegen)',async()=>{const d=await api('engine_db_install',{db:dbCfg()});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  mode:m=>run(m==='active'?'Starte WordPress zur Prüfung':'Schalte um',async()=>{const d=await api('engine_mode',{mode:m});apply(d);S.msg={err:d.status!=='ok',text:d.message||''};}),
  demoSetup:()=>run('Bereite die Demo-Engine vor (Core, Tabellen)',async()=>{const d=await api('engine_demo_setup',{});if(d.status==='ok'){apply(d);S.msg={text:d.message||'Bereit.'};}else S.msg={err:true,text:d.message||'Fehler'};}),
  analyze:()=>run('Starte WordPress und zähle',async()=>{const d=await api('engine_analyze');if(d.status==='ok'){S.ana=d;S.msg={text:'Systemanalyse fertig.'};}else S.msg={err:true,text:d.message||'Fehler'};}),
  migPlan:()=>run('Trockenlauf läuft (es wird nichts geändert)',async()=>{const d=await api('migration_plan',{});if(d.status==='ok'){S.mig=d.report;S.msg={text:'Trockenlauf fertig – es wurde nichts geändert.'};}else S.msg={err:true,text:d.message||'Fehler'};}),
  migRun:()=>{if(!confirm('Die Migration legt Inhalte in WordPress an (mit Sicherung vorher). Deine bisherigen Daten bleiben unverändert. Fortfahren?'))return;if((prompt('Zur Bestätigung MIGRIEREN eintippen')||'')!=='MIGRIEREN')return;run('Migration läuft (Sicherung, dann Übernahme)',async()=>{const d=await api('migration_run',{confirm:'MIGRIEREN'});if(d.status==='ok'){S.run=d.run;S.msg={err:d.run.status==='failed',text:'Lauf '+d.run.status+'.'};try{const m=await api('migration_plan',{});if(m.status==='ok')S.mig=m.report;}catch(e){}}else S.msg={err:true,text:d.message||'Fehler'};});},
  migRollback:id=>{if(!confirm('Alles entfernen, was dieser Lauf in WordPress angelegt hat? (Bisherige Daten sind nicht betroffen.)'))return;run('Baue zurück',async()=>{const d=await api('migration_rollback',{run:id,confirm:true});if(d.status==='ok'){S.run=d.run;S.msg={text:'Zurückgebaut: '+JSON.stringify(d.removed)};}else S.msg={err:true,text:d.message||'Fehler'};});},
  migDownload:()=>{if(!S.mig)return;const b=new Blob([JSON.stringify(S.mig,null,2)],{type:'application/json'});const a=document.createElement('a');a.href=URL.createObjectURL(b);a.download='migration-trockenlauf.json';document.body.appendChild(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove();},500);},
  remove:()=>{if(!confirm('WordPress-Engine wirklich entfernen? Die Datenbank-Tabellen bleiben erhalten.'))return;run('Entferne die Engine',async()=>{const d=await api('engine_remove',{confirm:true});apply(d);S.ana=null;S.msg={err:d.status!=='ok',text:d.message||''};});}
 };
})();
