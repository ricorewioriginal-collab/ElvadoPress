'use strict';
// Alexa-Skill verwalten: Betrieb, Themen (Antworten, Aussprachen, Reihenfolge), Neuigkeiten, Texte, Statistik und die Dateien für Amazon (Downloads).
// Die Einstellungen liegen in der Sektion "alexa" und werden vom Skill über die öffentliche Aktion alexa_config abgeholt.
window.AlexaManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const S={d:null,cfg:null,err:''};
 const api=(a,b)=>window.cmsApi(a,b);
 const toast=(m,bad)=>{try{window.cmsToast(m,!!bad);}catch(e){}};
 const tok=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const when=ts=>{try{return new Date(ts*1000).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return '–';}};
 const ago=ts=>{const m=Math.round((Date.now()/1000-ts)/60);return m<1?'gerade eben':m<60?`vor ${m} Min.`:m<1440?`vor ${Math.round(m/60)} Std.`:`vor ${Math.round(m/1440)} Tagen`;};
 const hms=s=>{s=Math.round(+s||0);return s;};
 const DL=[
  ['package','Alles für Amazon (ZIP)','Sprachmodell, Manifest, Backend, Icons und Anleitung – der komplette Skill in einer Datei.','fa-file-zipper',true],
  ['model','Sprachmodell (de-DE.json)','Aussprachen der Themen und Sätze. In der Developer Console unter „Interaction Model → JSON Editor“ einfügen.','fa-microphone-lines'],
  ['manifest','Skill-Angaben (skill.json)','Name, Beschreibung, Beispielsätze, Testhinweise, Länder, Datenschutz.','fa-file-lines'],
  ['lambda','Backend (index.js)','Der Programmcode des Skills (Reiter „Code“ in der Developer Console).','fa-code'],
  ['fallback','Rückfall-Konfiguration (fallback.json)','Wird genutzt, wenn das CMS einmal nicht erreichbar ist.','fa-life-ring'],
  ['cms','CMS-Verbindung (cms.json)','Adresse und geheimes Zähler-Token. Nicht weitergeben.','fa-key']
 ];
 async function load(){
  S.err='';
  try{S.d=await api('alexa_get');S.cfg=JSON.parse(JSON.stringify(S.d.config));}catch(e){S.err=e.message||'Laden fehlgeschlagen';S.d=null;}
 }
 const chk=(path,label,on)=>`<label class="ap-check"><input type="checkbox" ${on?'checked':''} onchange="AlexaManager.set('${path}',this.checked)"> ${label}</label>`;
 const inp=(path,label,val,max,ph)=>`<div><label class="news-lbl">${label}</label><input class="fc w-100" maxlength="${max||200}" value="${esc(val)}" placeholder="${esc(ph||'')}" oninput="AlexaManager.set('${path}',this.value)"></div>`;
 function status(){
  const d=S.d,lf=d.last_fetch;
  const pill=lf?(Date.now()/1000-lf<86400*3?'<span class="dm-pill">verbunden</span>':'<span class="dm-pill grey">länger nicht abgerufen</span>'):'<span class="dm-pill red">noch nie abgerufen</span>';
  const rev=d.model_rev!==d.exported_rev?`<div class="alx-warn"><i class="fas fa-triangle-exclamation"></i> Das <b>Sprachmodell hat sich geändert</b> (neue Themen oder Aussprachen) seit dem letzten Download. Lade es unten herunter und spiele es bei Amazon ein – sonst versteht Alexa die Änderung nicht.</div>`:'';
  return `<div class="dm-row"><div class="dm-head"><b>Status</b>${pill}</div>
   <div class="dm-meta">Aufrufname: <b>„Alexa, öffne ${esc(d.invocation)}“</b> · Skill hat die Einstellungen zuletzt abgerufen: ${lf?`<b>${esc(when(lf))}</b> (${ago(lf)})`:'noch nie – der Skill ist noch nicht eingerichtet oder noch nicht gestartet worden'}</div>${rev}</div>`;
 }
 function own(){
  const c=S.cfg;
  return `<div class="ap-h">Dein Skill</div><p class="hint">Name und Aufrufname gehören zu deinem Skill bei Amazon. Der Aufrufname darf keine Ziffern enthalten und wird so geschrieben, wie man ihn spricht (z. B. „meine website“). Eine Änderung betrifft das Sprachmodell.</p>
   <div class="ap-grid">${inp('app_name','Name des Skills',c.app_name,40,S.d.app_name)}${inp('invocation','Aufrufname („Alexa, öffne …“)',c.invocation,50,S.d.invocation)}</div>`;
 }
 function ops(){
  const c=S.cfg;
  return `<div class="ap-h">Betrieb</div>
   <div class="ap-checks">${chk('enabled','Skill aktiv',c.enabled)}${chk('maintenance.enabled','Wartungsmodus (Skill antwortet nur mit dem Text unten)',c.maintenance.enabled)}</div>
   <div class="ap-grid"><div class="ap-wide">${inp('maintenance.text','Text im Wartungsmodus',c.maintenance.text,300)}</div></div>`;
 }
 function news(){
  const c=S.cfg.news||{enabled:true,count:3};const list=(S.d.news||[]).map((n,i)=>`<div class="dm-meta">${i+1}. <b>${esc(n.title)}</b>${n.text?` – ${esc(n.text.slice(0,100))}${n.text.length>100?' …':''}`:''}</div>`).join('');
  return `<div class="ap-h">Neuigkeiten</div><p class="hint">Der Skill liest auf „Was gibt es Neues?“ die neuesten veröffentlichten Beiträge deiner Website vor („Lies Beitrag eins“ liest einen Beitrag). Das Sprachmodell ändert sich dadurch nicht.</p>
   <div class="ap-checks">${chk('news.enabled','Neuigkeiten vorlesen',c.enabled!==false)}</div>
   <div class="ap-grid"><div><label class="news-lbl">Anzahl der Beiträge (1–10)</label><input class="fc w-100" type="number" min="1" max="10" value="${esc(c.count||3)}" oninput="AlexaManager.set('news.count',parseInt(this.value,10)||3)"></div></div>
   <div style="margin-top:8px">${list||'<div class="dm-empty">Noch keine veröffentlichten Beiträge.</div>'}</div>`;
 }
 function topics(){
  const list=S.d.topics;
  const rows=list.map((t,i)=>`<div class="alx-st${t.enabled?'':' off'}">
    <label class="ap-check"><input type="checkbox" ${t.enabled?'checked':''} onchange="AlexaManager.topic('${esc(t.id)}','enabled',this.checked)"> <b>${esc(t.id)}</b></label>
    <input class="fc" maxlength="60" placeholder="Titel (${esc(t.title)})" value="${esc(t.custom_title||'')}" oninput="AlexaManager.topic('${esc(t.id)}','title',this.value)">
    <input class="fc" placeholder="weitere Aussprachen, mit Komma trennen" value="${esc((t.extra||[]).join(', '))}" onchange="AlexaManager.extra('${esc(t.id)}',this.value)">
    <textarea class="fc" style="grid-column:1/-1" rows="3" maxlength="600" placeholder="Antwort, die Alexa vorliest (max. 600 Zeichen)" oninput="AlexaManager.topic('${esc(t.id)}','text',this.value)">${esc(t.text||'')}</textarea>
    <span class="ap-ctl"><button class="btn-g" title="Thema entfernen" onclick="AlexaManager.removeTopic('${esc(t.id)}')"><i class="fas fa-trash"></i></button><button class="btn-g" ${i<=0?'disabled':''} onclick="AlexaManager.move(${i},-1)"><i class="fas fa-arrow-up"></i></button><button class="btn-g" ${i>=list.length-1?'disabled':''} onclick="AlexaManager.move(${i},1)"><i class="fas fa-arrow-down"></i></button></span>
    <div class="dm-meta" style="grid-column:1/-1">Alexa versteht z. B.: ${(t.speakable||[]).map(x=>`„${esc(x)}“`).join(', ')}</div></div>`).join('');
  const add=`<div class="alx-add" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0"><input id="alxNewTitle" class="fc" maxlength="60" placeholder="Titel des Themas, z. B. Öffnungszeiten" style="max-width:340px"><button class="btn-g" onclick="AlexaManager.addTopic()"><i class="fas fa-plus"></i> Thema hinzufügen</button></div>`;
  return `<div class="ap-h">Themen</div><p class="hint">Jedes Thema ist eine Frage, die Alexa mit deinem Text beantwortet (z. B. Öffnungszeiten, Kontakt, Über uns, Preise). Sag: „Erzähl mir etwas über &lt;Titel&gt;“. Neue Themen, Titel und Aussprachen betreffen das Sprachmodell – danach bitte neu einspielen; die Antwort-Texte wirken sofort.</p>
   ${add}<div class="alx-sts">${rows||'<div class="dm-empty">Noch kein Thema – füge oben dein erstes hinzu.</div>'}</div>`;
 }
 function texts(){
  const t=S.cfg.texts;
  return `<div class="ap-h">Texte</div><p class="hint">Platzhalter: <code>{beispiele}</code> = Beispiele (deine ersten Themen) für die Hilfe. Sonderzeichen werden für die Sprachausgabe entschärft.</p>
   <div class="ap-grid">${inp('texts.welcome','Begrüßung beim Start',t.welcome,400)}${inp('texts.goodbye','Verabschiedung (Stopp)',t.goodbye,400)}${inp('texts.unknown','Thema nicht gefunden',t.unknown,400)}${inp('texts.nonews','Keine Neuigkeiten',t.nonews,400)}
   <div class="ap-wide"><label class="news-lbl">Hilfetext</label><textarea class="fc w-100" rows="3" maxlength="400" oninput="AlexaManager.set('texts.help',this.value)">${esc(t.help)}</textarea></div></div>`;
 }
 function stats(){
  const st=S.d.stats,c=S.cfg;
  const max=Math.max(1,...Object.values(st.topics||{}));
  const bars=Object.entries(st.topics||{}).slice(0,12).map(([k,v])=>`<div class="ap-bar"><span>${esc(k)}</span><i style="width:${Math.max(2,Math.round(v/max*100))}%"></i><b>${v}×</b></div>`).join('');
  const dmax=Math.max(1,...(st.daily||[]).map(x=>x.requests));
  const days=(st.daily||[]).map(x=>`<u title="${esc(x.date)}: ${x.requests}" style="height:${Math.max(3,Math.round(x.requests/dmax*100))}%"></u>`).join('');
  const it=Object.entries(st.intents||{}).slice(0,10).map(([k,v])=>`<span class="dm-pill grey">${esc(k.replace('Intent','').replace('AMAZON.',''))} ${v}</span>`).join(' ');
  return `<div class="ap-h">Statistik</div>
   <p class="hint">Standardmäßig aus. Gezählt werden nur das abgefragte Thema und der Name des Befehls – keine Geräte-, Konto- oder Nutzer-IDs, keine Sprachaufnahmen. Der Skill meldet es mit dem geheimen Token aus <code>cms.json</code>.</p>
   <div class="ap-checks">${chk('stats','Anonyme Zähler einschalten',c.stats)}</div>
   <div class="dm-meta" style="margin:8px 0">Letzte 30 Tage: <b>${st.total||0}</b> Anfragen</div>${days?`<div class="ap-days">${days}</div>`:''}<div class="ap-bars">${bars||'<div class="dm-empty">Noch keine Daten.</div>'}</div>
   ${it?`<div style="margin:6px 0">${it}</div>`:''}
   <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><button class="btn-g" onclick="AlexaManager.clearStats()"><i class="fas fa-trash"></i> Zahlen leeren</button><button class="btn-g" onclick="AlexaManager.resetToken()"><i class="fas fa-rotate"></i> Token erneuern (danach Paket neu laden und einspielen)</button></div>`;
 }
 function downloads(){
  const w=(S.d.warnings||[]).length?`<div class="alx-warn"><i class="fas fa-triangle-exclamation"></i> Hinweise zum Sprachmodell:<br>${S.d.warnings.map(esc).join('<br>')}</div>`:'';
  const items=DL.map(([k,t,desc,ic,main])=>`<div class="dm-row"><div class="dm-head"><b><i class="fas ${ic}"></i> ${t}</b></div><div class="dm-meta">${desc}</div><div class="dm-actions"><a class="${main?'btn-a':'btn-g'}" href="api.php?action=alexa_download&file=${k}&_tok=${encodeURIComponent(tok())}"><i class="fas fa-download"></i> Herunterladen</a></div></div>`).join('');
  return `<div class="ap-h">Dateien für Amazon</div>
   <p class="hint">Immer mit dem aktuellen Stand der Einstellungen erzeugt. <b>Neu bei Amazon einspielen</b> musst du nur das Sprachmodell (bei neuen Themen, Titeln oder Aussprachen); Antworttexte, Neuigkeiten, Ein/Aus und Wartung wirken sofort.</p>
   <div class="dm-row"><div class="dm-head"><b><i class="fas fa-images"></i> Skill-Icon für Amazon</b></div><div class="dm-meta">Lade das quadratische Original einmal hoch. Das CMS erzeugt daraus automatisch die benötigten Dateien in 108 × 108 und 512 × 512 Pixel. Diese Icons werden auch vom Skill-Paket verwendet.</div><div class="dm-actions" style="align-items:center;gap:12px;flex-wrap:wrap"><span style="display:flex;align-items:center;gap:7px"><img id="alxIcon108" src="../assets/img/alexa/icon-108.png" width="54" height="54" style="border-radius:11px;object-fit:cover" alt="">108 × 108</span><span style="display:flex;align-items:center;gap:7px"><img id="alxIcon512" src="../assets/img/alexa/icon-512.png" width="54" height="54" style="border-radius:11px;object-fit:cover" alt="">512 × 512</span><input id="alxIconFile" type="file" accept="image/png,image/jpeg,image/webp" hidden onchange="AlexaManager.uploadIcon(this)"><button class="btn-a" type="button" onclick="document.getElementById('alxIconFile').click()"><i class="fas fa-upload"></i> Neues Skill-Icon hochladen</button><a class="btn-g" href="../assets/img/alexa/icon-108.png" download="alexa-skill-108.png"><i class="fas fa-download"></i> 108 px</a><a class="btn-g" href="../assets/img/alexa/icon-512.png" download="alexa-skill-512.png"><i class="fas fa-download"></i> 512 px</a></div></div>
   ${w}<div class="dm-list">${items}</div>
   <details class="ap-det" style="margin-top:10px"><summary><b>So reichst du den Skill ein</b> <span class="dm-meta">Kurzfassung – ausführlich in README.md im ZIP</span></summary><div class="ap-body"><ol class="alx-steps">
    <li>Konto auf <b>developer.amazon.com/alexa/console/ask</b> → <i>Create Skill</i> → Name „${esc(S.d.app_name||'Meine Website')}“, Sprache Deutsch (DE), Typ Custom, Hosting <b>Alexa-hosted (Node.js)</b>, Region EU.</li>
    <li><i>Build → Interaction Model → JSON Editor</i>: Inhalt von <code>de-DE.json</code> einfügen, speichern, <i>Build Skill</i>.</li>
    <li><i>Code</i>: <code>index.js</code>, <code>package.json</code>, <code>fallback.json</code> und <code>cms.json</code> aus dem ZIP (Ordner <code>lambda</code>) einfügen, <i>Deploy</i>.</li>
    <li><i>Test</i>: „Alexa, öffne ${esc(S.d.invocation)}“ ausprobieren; danach <i>Distribution</i> mit den Texten aus <code>skill.json</code> und den Icons ausfüllen und <i>Submit for review</i>.</li>
    <li>Nach der Freigabe zeigt oben „Status“, dass der Skill die Einstellungen abruft.</li></ol></div></details>`;
 }
 const EXTRA=[];   // Zusatzabschnitte, die ein Projekt-Paket anmeldet (cms/packs/<paket>/admin.js) – der Kern kennt keine Projektinhalte
 function registerSection(fn){if(typeof fn==='function')EXTRA.push(fn)}
 function extraSections(){return EXTRA.map(f=>{try{return String(f()||'')}catch(e){return ''}}).join('')}
 function draw(){
  const host=document.getElementById('alexaManager');if(!host)return;
  if(!S.d){host.innerHTML=`<div class="dm-empty">${esc(S.err||'Lädt …')}</div>`;return;}
  host.innerHTML=status()+own()+ops()+news()+topics()+texts()+stats()+extraSections()+downloads();
 }
 function set(path,val){
  const p=path.split('.');let o=S.cfg;for(let i=0;i<p.length-1;i++)o=o[p[i]]=o[p[i]]||{};o[p[p.length-1]]=val;
 }
 function topic(id,key,val){
  const s=(S.cfg.topics=Array.isArray(S.cfg.topics)?{}:S.cfg.topics);
  const o=s[id]=s[id]||{enabled:true,title:'',text:'',extra:[]};o[key]=val;
  if(key==='enabled'){const f=S.d.topics.find(x=>x.id===id);if(f)f.enabled=!!val;const row=document.activeElement?.closest('.alx-st');row&&row.classList.toggle('off',!val);}
  if(key==='text'){const f=S.d.topics.find(x=>x.id===id);if(f)f.text=val;}
 }
 function addTopic(){
  const title=(document.getElementById('alxNewTitle')?.value||'').trim();
  const id=title.toLowerCase().replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss').normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,40);
  if(id.length<2)return toast('Bitte einen Titel eingeben (mindestens zwei Buchstaben)',true);
  if(S.d.topics.some(t=>t.id===id))return toast('Dieses Thema gibt es schon',true);
  if(S.d.topics.length>=40)return toast('Höchstens 40 Themen',true);
  S.cfg.topics=Array.isArray(S.cfg.topics)?{}:S.cfg.topics;S.cfg.topics[id]={enabled:true,title,text:'',extra:[]};
  S.d.topics.push({id,title,text:'',enabled:true,extra:[],custom_title:title,speakable:[title.toLowerCase()]});
  S.cfg.order=S.d.topics.map(t=>t.id);draw();
 }
 function removeTopic(id){
  if(!confirm('Thema „'+id+'“ aus dem Skill entfernen?'))return;
  if(!Array.isArray(S.cfg.topics))delete S.cfg.topics[id];
  S.d.topics=S.d.topics.filter(t=>t.id!==id);S.cfg.order=S.d.topics.map(t=>t.id);draw();
 }
 function extra(id,v){topic(id,'extra',String(v).split(',').map(x=>x.trim()).filter(Boolean));}
 function move(i,d){
  const ids=S.d.topics.map(t=>t.id),j=i+d;if(j<0||j>=ids.length)return;
  [ids[i],ids[j]]=[ids[j],ids[i]];S.cfg.order=ids;[S.d.topics[i],S.d.topics[j]]=[S.d.topics[j],S.d.topics[i]];draw();
 }
 function collect(){
  const c=JSON.parse(JSON.stringify(S.cfg));
  if(!Array.isArray(c.order)||!c.order.length)c.order=S.d.topics.map(t=>t.id);
  return c;
 }
 async function render(){await load();draw();}
 async function uploadIcon(input){
  const file=input?.files?.[0];if(!file)return;
  if(!/^image\/(png|jpeg|webp)$/.test(file.type)){toast('Bitte PNG, JPG oder WebP auswählen',true);input.value='';return;}
  const fd=new FormData();fd.append('file',file);
  try{
   const res=await fetch('api.php?action=alexa_icon_upload&_tok='+encodeURIComponent(tok()),{method:'POST',body:fd,credentials:'same-origin'});
   const d=await res.json();if(!res.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');
   if(!d.checks?.['108']?.sha256||!d.checks?.['512']?.sha256||!d.original?.sha256)throw new Error('Server hat den Upload nicht vollständig bestätigt');
   const v=(d.version||Date.now())+'-'+d.checks['108'].sha256.slice(0,10);const a=document.getElementById('alxIcon108'),b=document.getElementById('alxIcon512');
   if(a)a.src='../assets/img/alexa/icon-108.png?v='+v;if(b)b.src='../assets/img/alexa/icon-512.png?v='+v;
   toast('Skill-Icon dauerhaft gespeichert und beide Amazon-Größen geprüft');
  }catch(e){toast(e.message||'Skill-Icon konnte nicht hochgeladen werden',true);}
  finally{input.value='';}
 }
 async function save(){
  try{await window.saveSection('alexa',collect());}catch(e){toast(e.message||'Speichern fehlgeschlagen',true);}
 }
 async function clearStats(){if(!confirm('Alle Alexa-Zahlen löschen?'))return;try{await api('alexa_stats_clear',{});toast('Gelöscht');await render();}catch(e){toast(e.message,true);}}
 async function resetToken(){if(!confirm('Neues Token erzeugen? Der laufende Skill kann dann bis zum erneuten Einspielen von cms.json nichts mehr zählen.'))return;try{await api('alexa_token_reset',{});toast('Token erneuert – bitte das Paket neu laden');await render();}catch(e){toast(e.message,true);}}
 return {render,save,set,topic,extra,move,clearStats,resetToken,addTopic,removeTopic,registerSection,uploadIcon};
})();
