'use strict';
// Alexa-Skill verwalten: Betrieb, Sender (Reihenfolge, Aussprachen), Texte, Statistik und die Dateien für Amazon (Downloads).
// Die Einstellungen liegen in der Sektion "alexa" und werden vom Skill über die öffentliche Aktion alexa_config abgeholt.
window.AlexaManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const S={d:null,cfg:null,err:''};
 const api=(a,b)=>window.cmsApi(a,b);
 const toast=(m,bad)=>{try{window.cmsToast(m,!!bad);}catch(e){}};
 const tok=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
 const when=ts=>{try{return new Date(ts*1000).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return '–';}};
 const ago=ts=>{const m=Math.round((Date.now()/1000-ts)/60);return m<1?'gerade eben':m<60?`vor ${m} Min.`:m<1440?`vor ${Math.round(m/60)} Std.`:`vor ${Math.round(m/1440)} Tagen`;};
 const hms=s=>{s=Math.round(+s||0);return s;};
 const DL=[
  ['package','Alles für Amazon (ZIP)','Sprachmodell, Manifest, Backend, Icons und Anleitung – der komplette Skill in einer Datei.','fa-file-zipper',true],
  ['model','Sprachmodell (de-DE.json)','Aussprachen der Sender und Sätze. In der Developer Console unter „Interaction Model → JSON Editor“ einfügen.','fa-microphone-lines'],
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
  const rev=d.model_rev!==d.exported_rev?`<div class="alx-warn"><i class="fas fa-triangle-exclamation"></i> Das <b>Sprachmodell hat sich geändert</b> (neue Sender oder Aussprachen) seit dem letzten Download. Lade es unten herunter und spiele es bei Amazon ein – sonst versteht Alexa die Änderung nicht.</div>`:'';
  return `<div class="dm-row"><div class="dm-head"><b>Status</b>${pill}</div>
   <div class="dm-meta">Aufrufname: <b>„Alexa, öffne ${esc(d.invocation)}“</b> · Skill hat die Einstellungen zuletzt abgerufen: ${lf?`<b>${esc(when(lf))}</b> (${ago(lf)})`:'noch nie – der Skill ist noch nicht eingerichtet oder noch nicht gestartet worden'}</div>${rev}</div>`;
 }
 function own(){
  if(!S.d.neutral)return '';
  const c=S.cfg;
  return `<div class="ap-h">Dein Skill</div><p class="hint">Name und Aufrufname gehören zu deinem Skill bei Amazon. Der Aufrufname darf keine Ziffern enthalten und wird so geschrieben, wie man ihn spricht (z. B. „mein radio“). Eine Änderung betrifft das Sprachmodell.</p>
   <div class="ap-grid">${inp('app_name','Name des Skills',c.app_name,40,S.d.app_name)}${inp('invocation','Aufrufname („Alexa, öffne …“)',c.invocation,50,S.d.invocation)}</div>`;
 }
 function ops(){
  const c=S.cfg;
  return `<div class="ap-h">Betrieb</div>
   <div class="ap-checks">${chk('enabled','Skill aktiv',c.enabled)}${chk('maintenance.enabled','Wartungsmodus (Skill spielt nichts, sagt den Text unten)',c.maintenance.enabled)}${chk('schedule','Sendeplan und Sendungen ansagen („Welche Sendung läuft?“, „Wie ist der Sendeplan?“)',c.schedule)}</div>
   <div class="ap-grid"><div class="ap-wide">${inp('maintenance.text','Text im Wartungsmodus',c.maintenance.text,300)}</div></div>`;
 }
 function stations(){
  const c=S.cfg;const list=S.d.stations;
  const opts=list.map(s=>`<option value="${esc(s.id)}" ${c.default_station===s.id?'selected':''}>${esc(s.title)}</option>`).join('');
  const rows=list.map((s,i)=>{
   const o=c.stations[s.id]||{};
   return `<div class="alx-st${s.enabled?'':' off'}">
    <label class="ap-check"><input type="checkbox" ${s.enabled?'checked':''} onchange="AlexaManager.station('${esc(s.id)}','enabled',this.checked)"> <b>${esc(s.id)}</b></label>
    <input class="fc" maxlength="60" placeholder="Anzeigename (${esc(s.title)})" value="${esc(o.title||'')}" oninput="AlexaManager.station('${esc(s.id)}','title',this.value)">
    <input class="fc" placeholder="weitere Aussprachen, mit Komma trennen" value="${esc((o.extra||[]).join(', '))}" onchange="AlexaManager.extra('${esc(s.id)}',this.value)">
    ${S.d.neutral?`<input class="fc" style="grid-column:1/-1" placeholder="eigene Stream-Adresse (https://…), leer = laut.fm/${esc(s.id)}" value="${esc(o.stream||s.stream||'')}" onchange="AlexaManager.stream('${esc(s.id)}',this.value)">`:''}
    <span class="ap-ctl">${S.d.neutral?`<button class="btn-g" title="Sender entfernen" onclick="AlexaManager.removeStation('${esc(s.id)}')"><i class="fas fa-trash"></i></button>`:''}<button class="btn-g" ${i<=0?'disabled':''} onclick="AlexaManager.move(${i},-1)"><i class="fas fa-arrow-up"></i></button><button class="btn-g" ${i>=list.length-1?'disabled':''} onclick="AlexaManager.move(${i},1)"><i class="fas fa-arrow-down"></i></button></span>
    <div class="dm-meta" style="grid-column:1/-1">Alexa versteht z. B.: ${s.speakable.map(x=>`„${esc(x)}“`).join(', ')}</div></div>`;}).join('');
  const add=S.d.neutral?`<div class="alx-add" style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0"><input id="alxNewId" class="fc" maxlength="60" placeholder="laut.fm-Kennung oder https://-Stream-Adresse" style="max-width:340px"><input id="alxNewTitle" class="fc" maxlength="60" placeholder="Anzeigename (optional)" style="max-width:260px"><button class="btn-a" onclick="AlexaManager.addStation()"><i class="fas fa-plus"></i> Sender hinzufügen</button></div>`:'';
  const intro=S.d.neutral?'Füge die Sender hinzu, die Alexa spielen soll: entweder die laut.fm-Kennung (laut.fm/<b>kennung</b>) oder die https-Adresse eines beliebigen Streams (Alexa spielt nur https; bei eigenen Streams gibt es keine Titel- und Sendeplan-Auskunft). Abgeschaltete':'Die Senderliste kommt aus dem Core-Netzwerk. Abgeschaltete'
  return `<div class="ap-h">Sender</div><p class="hint">${intro} Sender spielt der Skill nicht mehr (auch nicht bei „nächster Sender“). Die Reihenfolge gilt für „nächster/vorheriger Sender“ und die Senderliste. Neue Aussprachen und neue Sender betreffen das Sprachmodell – danach bitte neu einspielen.</p>
   <div class="ap-grid"><div><label class="news-lbl">Beim Start spielen</label><select class="fc w-100" onchange="AlexaManager.set('default_station',this.value)">${opts}</select></div>
   <div style="align-self:end">${chk('daily','Sender des Tages: jeden Tag ein anderer Sender (statt fest)',c.daily)}</div></div>
   ${add}<div class="alx-sts">${rows||(S.d.neutral?'<div class="dm-empty">Noch kein Sender – füge oben deinen ersten hinzu.</div>':'')}</div>`;
 }
 function texts(){
  const t=S.cfg.texts;
  return `<div class="ap-h">Texte</div><p class="hint">Platzhalter: <code>{sender}</code> = Name des Senders, <code>{beispiele}</code> = Beispiele für die Hilfe. Sonderzeichen werden für die Sprachausgabe entschärft.</p>
   <div class="ap-grid">${inp('texts.welcome','Begrüßung beim Start',t.welcome,400)}${inp('texts.play','Ansage vor dem Sender',t.play,400)}${inp('texts.goodbye','Verabschiedung (Stopp)',t.goodbye,400)}${inp('texts.unavailable','Sender abgeschaltet',t.unavailable,400)}
   <div class="ap-wide"><label class="news-lbl">Hilfetext</label><textarea class="fc w-100" rows="3" maxlength="400" oninput="AlexaManager.set('texts.help',this.value)">${esc(t.help)}</textarea></div></div>`;
 }
 function stats(){
  const st=S.d.stats,c=S.cfg;
  const max=Math.max(1,...Object.values(st.plays||{}));
  const bars=Object.entries(st.plays||{}).slice(0,12).map(([k,v])=>`<div class="ap-bar"><span>${esc(k)}</span><i style="width:${Math.max(2,Math.round(v/max*100))}%"></i><b>${v}×</b></div>`).join('');
  const dmax=Math.max(1,...(st.daily||[]).map(x=>x.plays));
  const days=(st.daily||[]).map(x=>`<u title="${esc(x.date)}: ${x.plays}" style="height:${Math.max(3,Math.round(x.plays/dmax*100))}%"></u>`).join('');
  const it=Object.entries(st.intents||{}).slice(0,10).map(([k,v])=>`<span class="dm-pill grey">${esc(k.replace('Intent','').replace('AMAZON.',''))} ${v}</span>`).join(' ');
  return `<div class="ap-h">Statistik</div>
   <p class="hint">Standardmäßig aus. Gezählt werden nur der gespielte Sender und der Name des Befehls – keine Geräte-, Konto- oder Nutzer-IDs, keine Sprachaufnahmen. Der Skill meldet es mit dem geheimen Token aus <code>cms.json</code>.</p>
   <div class="ap-checks">${chk('stats','Anonyme Zähler einschalten',c.stats)}</div>
   <div class="dm-meta" style="margin:8px 0">Letzte 30 Tage: <b>${st.total||0}</b> Wiedergaben</div>${days?`<div class="ap-days">${days}</div>`:''}<div class="ap-bars">${bars||'<div class="dm-empty">Noch keine Daten.</div>'}</div>
   ${it?`<div style="margin:6px 0">${it}</div>`:''}
   <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px"><button class="btn-g" onclick="AlexaManager.clearStats()"><i class="fas fa-trash"></i> Zahlen leeren</button><button class="btn-g" onclick="AlexaManager.resetToken()"><i class="fas fa-rotate"></i> Token erneuern (danach Paket neu laden und einspielen)</button></div>`;
 }
 function downloads(){
  const w=(S.d.warnings||[]).length?`<div class="alx-warn"><i class="fas fa-triangle-exclamation"></i> Hinweise zum Sprachmodell:<br>${S.d.warnings.map(esc).join('<br>')}</div>`:'';
  const items=DL.map(([k,t,desc,ic,main])=>`<div class="dm-row"><div class="dm-head"><b><i class="fas ${ic}"></i> ${t}</b></div><div class="dm-meta">${desc}</div><div class="dm-actions"><a class="${main?'btn-a':'btn-g'}" href="api.php?action=alexa_download&file=${k}&_tok=${encodeURIComponent(tok())}"><i class="fas fa-download"></i> Herunterladen</a></div></div>`).join('');
  return `<div class="ap-h">Dateien für Amazon</div>
   <p class="hint">Immer mit dem aktuellen Stand der Einstellungen erzeugt. <b>Neu bei Amazon einspielen</b> musst du nur das Sprachmodell (bei neuen Sendern oder Aussprachen); Texte, Reihenfolge, Ein/Aus, Standardsender und Wartung wirken sofort.</p>${w}<div class="dm-list">${items}</div>
   <details class="ap-det" style="margin-top:10px"><summary><b>So reichst du den Skill ein</b> <span class="dm-meta">Kurzfassung – ausführlich in README.md im ZIP</span></summary><div class="ap-body"><ol class="alx-steps">
    <li>Konto auf <b>developer.amazon.com/alexa/console/ask</b> → <i>Create Skill</i> → Name „${esc(S.d.app_name||((window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'RicoReWi Radio':'Mein Radio'))}“, Sprache Deutsch (DE), Typ Custom, Hosting <b>Alexa-hosted (Node.js)</b>, Region EU.</li>
    <li><i>Build → Interaction Model → JSON Editor</i>: Inhalt von <code>de-DE.json</code> einfügen, speichern, <i>Build Skill</i>.</li>
    <li><i>Build → Interfaces → Audio Player</i> einschalten.</li>
    <li><i>Code</i>: <code>index.js</code>, <code>package.json</code>, <code>fallback.json</code> und <code>cms.json</code> aus dem ZIP (Ordner <code>lambda</code>) einfügen, <i>Deploy</i>.</li>
    <li><i>Test</i>: „Alexa, öffne ${esc(S.d.invocation)}“ ausprobieren; danach <i>Distribution</i> mit den Texten aus <code>skill.json</code> und den Icons ausfüllen und <i>Submit for review</i>.</li>
    <li>Nach der Freigabe zeigt oben „Status“, dass der Skill die Einstellungen abruft.</li></ol></div></details>`;
 }
 function draw(){
  const host=document.getElementById('alexaManager');if(!host)return;
  if(!S.d){host.innerHTML=`<div class="dm-empty">${esc(S.err||'Lädt …')}</div>`;return;}
  host.innerHTML=status()+own()+ops()+stations()+texts()+stats()+downloads();
 }
 function set(path,val){
  const p=path.split('.');let o=S.cfg;for(let i=0;i<p.length-1;i++)o=o[p[i]]=o[p[i]]||{};o[p[p.length-1]]=val;
 }
 function station(id,key,val){
  const s=(S.cfg.stations=Array.isArray(S.cfg.stations)?{}:S.cfg.stations);
  const o=s[id]=s[id]||{enabled:true,title:'',extra:[]};o[key]=val;
  if(key==='enabled'){const f=S.d.stations.find(x=>x.id===id);if(f)f.enabled=!!val;const row=document.activeElement?.closest('.alx-st');row&&row.classList.toggle('off',!val);}
 }
 function addStation(){
  const raw=(document.getElementById('alxNewId')?.value||'').trim(),title=(document.getElementById('alxNewTitle')?.value||'').trim();
  const isUrl=/^https?:\/\//i.test(raw);let id,stream='';
  if(isUrl){
   if(!/^https:\/\//i.test(raw))return toast('Alexa spielt nur Streams mit https:// – bitte die https-Adresse eingeben',true);
   stream=raw;let host='';try{host=new URL(raw).hostname.replace(/^www\./,'')}catch(e){return toast('Die Stream-Adresse ist ungültig',true)}
   id=(title||host).toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,40);
  }else id=raw.toLowerCase().replace(/^.*laut\.fm\//,'').replace(/[^a-z0-9_-]/g,'');
  if(id.length<2)return toast('Bitte die laut.fm-Kennung oder eine https-Stream-Adresse eingeben',true);
  if(S.d.stations.some(s=>s.id===id))return toast('Dieser Sender ist schon in der Liste',true);
  S.cfg.stations=Array.isArray(S.cfg.stations)?{}:S.cfg.stations;S.cfg.stations[id]={enabled:true,title,extra:[],...(stream?{stream}:{})};
  S.d.stations.push({id,title:title||id.replace(/[-_]+/g,' '),enabled:true,extra:[],speakable:[],stream});
  S.cfg.order=S.d.stations.map(s=>s.id);if(!S.cfg.default_station)S.cfg.default_station=id;draw();
 }
 function stream(id,v){
  v=String(v).trim();if(v!==''&&!/^https:\/\//i.test(v)){toast('Alexa spielt nur https://-Adressen',true);return draw();}
  station(id,'stream',v);const f=S.d.stations.find(x=>x.id===id);if(f)f.stream=v;
 }
 function removeStation(id){
  if(!confirm('Sender „'+id+'“ aus dem Skill entfernen?'))return;
  if(!Array.isArray(S.cfg.stations))delete S.cfg.stations[id];
  S.d.stations=S.d.stations.filter(s=>s.id!==id);S.cfg.order=S.d.stations.map(s=>s.id);
  if(S.cfg.default_station===id)S.cfg.default_station=S.d.stations[0]?.id||'';draw();
 }
 function extra(id,v){station(id,'extra',String(v).split(',').map(x=>x.trim()).filter(Boolean));}
 function move(i,d){
  const ids=S.d.stations.map(s=>s.id),j=i+d;if(j<0||j>=ids.length)return;
  [ids[i],ids[j]]=[ids[j],ids[i]];S.cfg.order=ids;[S.d.stations[i],S.d.stations[j]]=[S.d.stations[j],S.d.stations[i]];draw();
 }
 function collect(){
  const c=JSON.parse(JSON.stringify(S.cfg));
  if(!Array.isArray(c.order)||!c.order.length)c.order=S.d.stations.map(s=>s.id);
  return c;
 }
 async function render(){await load();draw();}
 async function save(){
  try{await window.saveSection('alexa',collect());}catch(e){toast(e.message||'Speichern fehlgeschlagen',true);}
 }
 async function clearStats(){if(!confirm('Alle Alexa-Zahlen löschen?'))return;try{await api('alexa_stats_clear',{});toast('Gelöscht');await render();}catch(e){toast(e.message,true);}}
 async function resetToken(){if(!confirm('Neues Token erzeugen? Der laufende Skill kann dann bis zum erneuten Einspielen von cms.json nichts mehr zählen.'))return;try{await api('alexa_token_reset',{});toast('Token erneuert – bitte das Paket neu laden');await render();}catch(e){toast(e.message,true);}}
 return {render,save,set,station,stream,extra,move,clearStats,resetToken,addStation,removeStation};
})();
