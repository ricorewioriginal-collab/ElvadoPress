'use strict';
// Apps verwalten: Übersicht der gebauten Apps (downloads/*.json), Funktionsschalter, Hinweis und Mindestversion je Marke und Plattform.
// Die Einstellungen liegen in der Sektion "apps" (managed) und werden von den Apps über die öffentliche Aktion app_config abgeholt.
window.AppsManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const S={ov:null,managed:{},busy:false,err:'',tel:{usage:false,errors:false,listen:false,geo:true},stats:null};
 const api=(a,b)=>window.cmsApi(a,b);
 const toast=(m,bad)=>{try{window.cmsToast(m,!!bad);}catch(e){}};
 const size=n=>{n=+n||0;return n>=1048576?(n/1048576).toFixed(1).replace('.',',')+' MB':n>=1024?Math.round(n/1024)+' KB':n?n+' B':'–';};
 const when=iso=>{try{const d=new Date(iso);if(isNaN(d))return '–';return d.toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});}catch(e){return '–';}};
 const root=()=>{try{return (typeof CMS!=='undefined'&&CMS)?CMS:(window.CMS||{});}catch(e){return window.CMS||{};}};
 const key=r=>r.brand+':'+r.platform;
 const FEATURES={directory:'Radioverzeichnis (Suche, Fremd-Streams)',assistant:'KI-Assistent',report:'Sender melden',cast:'Cast (nur Android)'};
 async function load(){
  S.err='';
  try{S.ov=await api('apps_overview');}catch(e){S.err=e.message||'Laden fehlgeschlagen';S.ov=null;}
  S.managed={};
  (S.ov?S.ov.items:[]).forEach(r=>{S.managed[key(r)]=JSON.parse(JSON.stringify(r.config));});
  const t=(root().apps&&root().apps.telemetry)||{};S.tel={usage:!!t.usage,errors:!!t.errors,listen:!!t.listen,geo:t.geo!==false};
  try{S.stats=await api('apps_stats');}catch(e){S.stats=null;}
 }
 function dlTxt(name){const x=(((S.stats||{}).downloads||{}).files||{})[name];return x?` · <b>${x.total}</b> Downloads (7 Tage ${x.last7}, 30 Tage ${x.last30})`:'';}

 const HC={error:'#e0245e',warn:'#d99a00',info:'#8a93b8'},HI={error:'⛔',warn:'⚠️',info:'ℹ️'};
 function health(r){
  const h=(r.health||[]);
  if(!h.length)return (r.meta&&r.meta.available)?'<div class="dm-meta" style="color:#18a36b"><b>✓ Alles in Ordnung:</b> Datei vorhanden, Größe und Prüfsumme stimmen'+(r.platform==='android'?', Signatur stabil':'')+'.</div>':'';
  return '<div class="ap-health">'+h.map(x=>`<div class="dm-meta" style="color:${HC[x.level]||HC.info}">${HI[x.level]||''} ${esc(x.text)}</div>`).join('')+'</div>';
 }
 function card(r){
  const m=r.meta||{},files=m.files||[];
  const state=!m.available?'<span class="dm-pill grey">noch nicht gebaut</span>':files.length&&files.every(f=>f.exists&&f.size_ok&&f.sha_ok!==false)?'<span class="dm-pill">online</span>':'<span class="dm-pill red">Datei fehlt oder unvollständig</span>';
  const rows=files.map(f=>`<div class="ap-file"><span><b>${esc(f.label)}</b> ${esc(f.name)} · ${size(f.size)}${dlTxt(f.name)}${f.exists?'':' · <b style="color:#e0245e">fehlt auf dem Server</b>'}${f.exists&&!f.size_ok?' · <b style="color:#e0245e">Größe stimmt nicht</b>':''}</span>${f.exists?`<a class="btn-g" href="${esc((r.origin||'')+f.url)}" target="_blank" rel="noopener"><i class="fas fa-download"></i></a>`:''}${f.sha256?`<button class="btn-g" title="Prüfsumme kopieren" onclick="AppsManager.copy('${esc(f.sha256)}')"><i class="fas fa-fingerprint"></i></button>`:''}</div>`).join('');
  const sign=r.platform==='android'?(m.signing==='stable'?'<span class="dm-pill" title="Die APK ist mit dem festen Schlüssel signiert">Signatur stabil · Selbst-Update möglich</span>':m.signing==='debug'?'<span class="dm-pill red" title="Schlüssel fehlt: Updates lassen sich nicht über die installierte App einspielen">Debug-Signatur · Selbst-Update nicht möglich</span>':m.available?'<span class="dm-pill grey">Signatur wird beim nächsten Build angezeigt</span>':''):'';
  return `<div class="dm-row"><div class="dm-head"><b>${esc(r.brand_name)} · ${esc(r.platform_name)}</b>${state}${sign}${r.shown?'':'<span class="dm-pill grey">im Portal ausgeblendet</span>'}</div>
   <div class="dm-meta">Version <b>${esc(m.version||'–')}</b> · gebaut ${m.built_at?esc(when(m.built_at)):'–'}${m.package?` · Paket ${esc(m.package)}`:''}${m.cert_sha256?` · Zertifikat <code title="${esc(m.cert_sha256)}">${esc(m.cert_sha256.slice(0,8))}…${esc(m.cert_sha256.slice(-4))}</code>`:''}</div>${health(r)}${rows}</div>`;
 }
 function cfgBlock(r){
  const c=S.managed[key(r)]||r.config,k=key(r);
  const feats=Object.keys(FEATURES).filter(f=>f!=='directory'||r.directory).map(f=>`<label class="ap-check"><input type="checkbox" ${c.features[f]!==false?'checked':''} onchange="AppsManager.set('${esc(k)}','features.${f}',this.checked)"> ${esc(FEATURES[f])}</label>`).join('');
  const n=c.notice||{};
  return `<details class="ap-det"><summary><b>${esc(r.brand_name)} · ${esc(r.platform_name)}</b> <span class="dm-meta">${n.enabled?'Hinweis aktiv · ':''}${c.min_version?'Mindestversion '+esc(c.min_version):'keine Mindestversion'}</span></summary>
   <div class="ap-body">
    <div class="ap-sub">Funktionen in der App</div><div class="ap-checks">${feats}</div>
    <div class="ap-sub">Hinweis an alle Nutzer dieser App</div>
    <label class="ap-check"><input type="checkbox" ${n.enabled?'checked':''} onchange="AppsManager.set('${esc(k)}','notice.enabled',this.checked)"> Hinweis anzeigen (einmal pro Gerät, bis du den Text änderst)</label>
    <div class="ap-grid">
     <div><label class="news-lbl">Art</label><select class="fc w-100" onchange="AppsManager.set('${esc(k)}','notice.level',this.value)"><option value="info" ${n.level!=='warn'?'selected':''}>Information</option><option value="warn" ${n.level==='warn'?'selected':''}>Wichtig / Wartung</option></select></div>
     <div><label class="news-lbl">Überschrift</label><input class="fc w-100" maxlength="80" value="${esc(n.title||'')}" oninput="AppsManager.set('${esc(k)}','notice.title',this.value)"></div>
     <div class="ap-wide"><label class="news-lbl">Text</label><textarea class="fc w-100" rows="3" maxlength="600" oninput="AppsManager.set('${esc(k)}','notice.text',this.value)">${esc(n.text||'')}</textarea></div>
     <div><label class="news-lbl">Link (https, optional)</label><input class="fc w-100" value="${esc(n.url||'')}" placeholder="https://…" oninput="AppsManager.set('${esc(k)}','notice.url',this.value)"></div>
     <div><label class="news-lbl">Beschriftung des Links</label><input class="fc w-100" maxlength="40" value="${esc(n.url_label||'')}" placeholder="Mehr erfahren" oninput="AppsManager.set('${esc(k)}','notice.url_label',this.value)"></div>
    </div>
    <div class="ap-sub">Wartungsmodus</div>
    <label class="ap-check"><input type="checkbox" ${(c.maintenance||{}).enabled?'checked':''} onchange="AppsManager.set('${esc(k)}','maintenance.enabled',this.checked)"> App sperren und Wartungsmeldung zeigen (Starten der App nicht möglich, bis du es wieder ausschaltest)</label>
    <div class="ap-grid"><div><label class="news-lbl">Überschrift</label><input class="fc w-100" maxlength="80" value="${esc((c.maintenance||{}).title||'')}" placeholder="Wartungsarbeiten" oninput="AppsManager.set('${esc(k)}','maintenance.title',this.value)"></div>
     <div class="ap-wide"><label class="news-lbl">Text</label><textarea class="fc w-100" rows="2" maxlength="600" oninput="AppsManager.set('${esc(k)}','maintenance.text',this.value)">${esc((c.maintenance||{}).text||'')}</textarea></div></div>
    <div class="ap-sub">Update erzwingen</div>
    <div class="ap-grid"><div><label class="news-lbl">Mindestversion (z. B. 1.9.0)</label><input class="fc w-100" maxlength="14" value="${esc(c.min_version||'')}" placeholder="leer = kein Zwang" oninput="AppsManager.set('${esc(k)}','min_version',this.value)"></div>
     <div class="dm-meta" style="align-self:end">Aktuell gebaut: <b>${esc((r.meta&&r.meta.version)||'–')}</b>. Ältere Apps zeigen einen Hinweis „Update erforderlich“ mit Download-Link; ohne Eintrag wird nur auf neuere Versionen hingewiesen.</div></div>
    <div class="ap-sub">Update stufenweise ausrollen</div>
    <div class="ap-grid"><div><label class="news-lbl">Anteil der Geräte, denen ein neues Update angeboten wird: <b id="apr-${esc(k)}">${c.rollout===undefined?100:+c.rollout}%</b></label><input type="range" min="0" max="100" step="5" value="${c.rollout===undefined?100:+c.rollout}" oninput="document.getElementById('apr-${esc(k)}').textContent=this.value+'%';AppsManager.set('${esc(k)}','rollout',+this.value)"></div>
     <div class="dm-meta" style="align-self:end">Jedes Gerät hat eine feste Gruppe; mit 10 % bekommen zuerst 10 % der Nutzer das Update, bei Problemen stoppst du hier auf 0 %. Pflicht-Updates (Mindestversion) gehen immer an alle.</div></div>
    <div class="ap-sub">Update-Sicherheit &amp; Inhalte</div>
    <div class="ap-grid">
     <div><label class="news-lbl">Gesperrte Versionen (durch Komma getrennt)</label><input class="fc w-100" value="${esc((c.blocked||[]).join(', '))}" placeholder="z. B. 3.0.1, 3.0.2" oninput="AppsManager.setList('${esc(k)}','blocked',this.value)"></div>
     <div class="dm-meta" style="align-self:end">Apps in genau diesen Versionen müssen sofort aktualisieren (z. B. bei einem schweren Fehler). Gilt nur, wenn es eine neuere Version gibt.</div>
     <div class="ap-wide"><label class="news-lbl">Was ist neu? (erscheint im Update-Dialog, max. 600 Zeichen)</label><textarea class="fc w-100" rows="3" maxlength="600" oninput="AppsManager.set('${esc(k)}','notes',this.value)">${esc(c.notes||'')}</textarea></div>
     ${r.platform==='android'?`<div class="ap-wide"><label class="news-lbl">Erwartetes Signaturzertifikat (SHA-256) – Updates gibt es nur, wenn der Build damit signiert ist</label>
      <div style="display:flex;gap:8px;flex-wrap:wrap"><input class="fc" style="flex:1;min-width:240px" id="apcert-${esc(k)}" value="${esc(c.cert_pin||'')}" placeholder="leer = nicht festgelegt" oninput="AppsManager.set('${esc(k)}','cert_pin',this.value)">
      <button class="btn-g" type="button" ${(r.meta&&r.meta.cert_sha256)?'':'disabled'} onclick="AppsManager.pinCert('${esc(k)}')"><i class="fas fa-thumbtack"></i> Aktuelles Zertifikat festlegen</button></div>
      <div class="dm-meta">Schützt vor einem versehentlich oder bösartig getauschten Schlüssel: Weicht das Zertifikat eines neuen Builds ab, wird kein Update angeboten und es erscheint eine rote Warnung.</div></div>`:''}
    </div>
    <div class="ap-sub">App-Builder – Aussehen &amp; Startseite</div>
    ${window.AppBuilder?window.AppBuilder.html(k):''}
   </div></details>`;
 }
 const hms=s=>{s=Math.round(+s||0);const h=Math.floor(s/3600),m=Math.floor(s%3600/60);return h?`${h} h ${m} min`:m?`${m} min`:`${s} s`;};
 const bytes=n=>n>=1048576?Math.round(n/1048576)+' MB':n>=1024?Math.round(n/1024)+' KB':n+' B';
 function geoBox(){
  const g=(S.stats&&S.stats.geo_db)||{};
  return `<div class="dm-meta">Geodatenbank: ${g.installed?`<b>installiert</b> (${bytes(g.size)}, Stand ${esc(when(g.updated))})`:'<b>nicht installiert</b> – ohne sie wird keine Herkunft erfasst'} · <button class="btn-g" onclick="AppsManager.geoUpdate(this)"><i class="fas fa-download"></i> ${g.installed?'Aktualisieren':'Herunterladen'} (ca. 60 MB)</button><br>IP-Adressen werden nur für die Umrechnung genutzt und nicht gespeichert; gespeichert wird nur das Gebiet. Gruppen unter 3 Geräten werden nicht einzeln gezeigt. <span style="opacity:.8">IP Geolocation by DB-IP (CC BY 4.0).</span></div>`;
 }
 function listenBox(r){
  const l=((S.stats&&S.stats.listen)||{})[key(r)];if(!l)return '';
  const max=Math.max(1,...(l.stations||[]).map(x=>x.seconds));
  const bars=(l.stations||[]).slice(0,10).map(x=>`<div class="ap-bar"><span>${esc(x.id)}</span><i style="width:${Math.max(2,Math.round(x.seconds/max*100))}%"></i><b>${hms(x.seconds)} · ${x.listeners} Hörer</b></div>`).join('');
  const dmax=Math.max(1,...(l.daily||[]).map(x=>x.seconds));
  const days=(l.daily||[]).slice(-30).map(x=>`<u title="${esc(x.date)}: ${hms(x.seconds)}, ${x.listeners} Hörer" style="height:${Math.max(3,Math.round(x.seconds/dmax*100))}%"></u>`).join('');
  return `<div class="ap-sub">Hörstatistik (30 Tage)</div><div class="dm-meta">Hörer <b>${l.listeners}</b> · Hördauer gesamt <b>${hms(l.seconds)}</b> · je Hörer <b>${hms(l.avg_per_listener)}</b> (pro Tag <b>${hms(l.avg_per_listener_day)}</b>) · Ø Sitzung <b>${hms(l.avg_session)}</b> · ${l.sessions} Sitzungen</div><div class="ap-days">${days}</div><div class="ap-bars">${bars}</div>`;
 }
 function geoRows(r){
  const g=((S.stats&&S.stats.geo)||{})[key(r)];if(!g)return '';
  const col=(t,o)=>`<div><div class="ap-sub">${t}</div>${(o.items||[]).map(x=>`<div class="dm-meta">${esc(x.name)}: <b>${x.count}</b></div>`).join('')||'<div class="dm-meta">–</div>'}${o.other?`<div class="dm-meta">weitere (kleine Gruppen): ${o.other}</div>`:''}</div>`;
  if(!(g.countries.items.length||g.countries.other||g.unknown))return '';
  return `<div class="ap-sub">Herkunft der aktiven Geräte (30 Tage, intern)</div><div class="ap-geo">${col('Länder',g.countries)}${col('Bundesländer / Regionen',g.regions)}${col('Städte',g.cities)}</div>${g.unknown?`<div class="dm-meta">ohne Standort: ${g.unknown}</div>`:''}`;
 }
 async function geoUpdate(btn){btn.disabled=true;toast('Lade Geodatenbank … das kann eine Minute dauern');try{await api('apps_geo_update');toast('Geodatenbank installiert');await render();}catch(e){toast(e.message||'Download nicht möglich',true);btn.disabled=false;}}
 function keyHelp(){
  const any=(S.ov?S.ov.items:[]).some(r=>r.platform==='android'&&r.meta&&r.meta.signing==='debug');
  return `<details class="ap-det" ${any?'open':''} style="margin-top:14px"><summary><b>Android-Signaturschlüssel (einmalig einrichten)</b> <span class="dm-meta">${any?'noch nicht eingerichtet':'für Updates in der App'}</span></summary><div class="ap-body">
   <p class="hint">Damit sich die Android-App selbst aktualisieren kann, muss jede Version mit <b>demselben</b> Schlüssel signiert sein. Der Schlüssel gehört bewusst <b>nicht</b> ins CMS oder auf den Webserver: Wer ihn hat, kann Apps in eurem Namen signieren. Er liegt als geheimer Wert bei GitHub, wo die Apps gebaut werden.</p>
   <ol class="alx-steps"><li>Auf dem PC im Repository <code>scripts/create-developer-keystore.ps1</code> ausführen (PowerShell). Es erzeugt den Schlüssel, fragt zweimal ein Passwort und gibt die vier Werte aus.</li>
   <li>GitHub → Repository → <i>Settings → Secrets and variables → Actions → New repository secret</i>, viermal anlegen: <code>ANDROID_DEVELOPER_KEYSTORE_BASE64</code>, <code>ANDROID_DEVELOPER_KEYSTORE_PASSWORD</code>, <code>ANDROID_DEVELOPER_KEY_ALIAS</code>, <code>ANDROID_DEVELOPER_KEY_PASSWORD</code>.</li>
   <li>Den Schlüssel (<code>.jks</code>-Datei) <b>zusätzlich sicher sichern</b> (z. B. Passwortmanager/USB). Geht er verloren, können bestehende Installationen nicht mehr aktualisiert werden.</li>
   <li>Nächsten Android-Build abwarten: oben erscheint „Signatur stabil“. <b>Einmalig</b> muss die App danach neu installiert werden (alter Schlüssel ≠ neuer Schlüssel); ab dann klappen die Updates.</li></ol></div></details>`;
 }
 function stats(){
  const st=S.stats||{usage:{},errors:[]};
  const rows=S.ov.items.map(r=>{const u=(st.usage||{})[key(r)];if(!u)return '';const v=Object.keys(u.versions||{}).map(x=>`${esc(x)}: <b>${u.versions[x]}</b>`).join(' · ');return `<div class="dm-row"><div class="dm-head"><b>${esc(r.brand_name)} · ${esc(r.platform_name)}</b></div><div class="dm-meta">Geräte gesamt <b>${u.total}</b> · aktiv 7 Tage <b>${u.active7}</b> · aktiv 30 Tage <b>${u.active30}</b></div><div class="dm-meta">Neue Geräte (7 Tage) <b>${u.new7||0}</b> · Versionen (30 Tage): ${v||'–'}</div>${listenBox(r)}${geoRows(r)}</div>`;}).join('');
  const errs=(st.errors||[]).map(e=>`<div class="dm-row"><div class="dm-head"><b>${esc(e.message)}</b><span class="dm-pill grey">${esc(e.kind)}</span><span class="dm-pill">${e.count}×</span></div><div class="dm-meta">${esc(e.brand)} · ${esc(e.platform)}${e.where?' · '+esc(e.where):''} · zuletzt ${esc(when(e.last*1000))} · Versionen: ${esc(Object.keys(e.versions||{}).join(', ')||'–')}${Object.keys(e.os||{}).length?' · '+esc(Object.keys(e.os).join(', ')):''}</div>${e.stack?`<details><summary class="dm-meta">Details</summary><pre class="ap-pre">${esc(e.stack)}</pre></details>`:''}</div>`).join('');
  return `<div class="ap-h">Datenschutz &amp; Statistik</div>
   <p class="hint">Beides ist standardmäßig <b>aus</b>. Es werden keine Namen, IP-Adressen oder Hörverläufe gespeichert: Jede App erzeugt eine zufällige Kennung, die auf dem Server zusätzlich gesalzen und gehasht wird; gezählt wird nur „Gerät war an Tag X mit Version Y aktiv“. Fehlerberichte enthalten nur Fehlertext, Version und Android-/Windows-Version. Bitte nenne das in der Datenschutzerklärung.</p>
   <label class="ap-check"><input type="checkbox" ${S.tel.usage?'checked':''} onchange="AppsManager.tel('usage',this.checked)"> Anonyme Nutzungszahlen (Versionen, aktive Geräte)</label>
   <label class="ap-check"><input type="checkbox" ${S.tel.errors?'checked':''} onchange="AppsManager.tel('errors',this.checked)"> Fehlerberichte aus den Apps sammeln (Abspielfehler, Abstürze)</label>
   <label class="ap-check"><input type="checkbox" ${S.tel.listen?'checked':''} onchange="AppsManager.tel('listen',this.checked)"> Hörstatistik (meistgehörte Sender, Hördauer – nur zusammen mit „Nutzungszahlen“)</label>
   <label class="ap-check"><input type="checkbox" ${S.tel.geo?'checked':''} onchange="AppsManager.tel('geo',this.checked)"> Herkunft der Geräte (Bundesland, Stadt – nur intern im CMS, nur zusammen mit „Nutzungszahlen“)</label>
   ${geoBox()}
   <div class="ap-h" style="font-size:.9rem">Nutzung</div><div class="dm-list">${rows||'<div class="dm-empty">Noch keine Daten.</div>'}</div>
   <div class="ap-h" style="font-size:.9rem">Fehler <button class="btn-g" onclick="AppsManager.clear('errors')"><i class="fas fa-trash"></i> Fehler leeren</button> <button class="btn-g" onclick="AppsManager.clear('usage')"><i class="fas fa-trash"></i> Zahlen leeren</button></div><div class="dm-list">${errs||'<div class="dm-empty">Keine Fehler gemeldet.</div>'}</div>`;
 }
 function images(){
  const B=root().branding||{},items=(root().brands&&root().brands.items)||[];
  const img=(u,l)=>`<figure class="ap-img">${u?`<img src="${esc(u)}" alt="">`:'<div class="ap-none">–</div>'}<figcaption>${esc(l)}</figcaption></figure>`;
  const main=items[0]||{};
  let h=`<div class="dm-row"><div class="dm-head"><b>${esc(main.name||((window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'RicoReWi Radio':'Mein Radio'))}</b><span class="dm-pill grey">Hauptmarke</span></div><div class="ap-imgs">${img(B.android_app_icon,'App-Icon')}${img(B.android_startscreen,'Startbild')}${img(B.windows_logo||B.android_inapp_logo,'Logo (Windows/In-App)')}</div><div class="dm-actions"><button class="btn-g" onclick="cmsTab('branding')"><i class="fas fa-pen"></i> Im Branding ändern</button></div></div>`;
  items.slice(1).forEach(b=>{h+=`<div class="dm-row"><div class="dm-head"><b>${esc(b.name||b.id)}</b></div><div class="ap-imgs">${img(b.touch_icon||b.favicon,'App-Icon')}${img(b.social_image||b.og_image,'Startbild / Social')}${img(b.logo,'Logo')}</div><div class="dm-actions"><button class="btn-g" onclick="cmsTab('brands')"><i class="fas fa-pen"></i> Unter Domains &amp; Branding ändern</button></div></div>`;});
  return h;
 }
 function draw(){
  const host=document.getElementById('appsManager');if(!host)return;
  if(!S.ov){host.innerHTML=`<div class="dm-empty">${esc(S.err||'Lädt …')}</div>`;return;}
  host.innerHTML=`<div class="ap-h">Übersicht <button class="btn-g" onclick="AppsManager.refresh()"><i class="fas fa-rotate"></i> Aktualisieren</button></div><div class="dm-list">${S.ov.items.map(card).join('')}</div>
   <div class="ap-h">Funktionen, Hinweise, Wartung, Ausrollen &amp; App-Builder</div><p class="hint">Gilt je Marke und Plattform. „Speichern“ oben rechts übernimmt alles; die Apps lesen es beim nächsten Start.</p><div class="dm-list">${S.ov.items.map(cfgBlock).join('')}</div>
   ${stats()}
   ${keyHelp()}
   <div class="ap-h">Bilder der Apps</div><p class="hint">Icon, Startbild und Logo werden beim nächsten App-Bau übernommen.</p><div class="dm-list">${images()}</div>`;
 }
 function set(k,path,val){
  const c=S.managed[k];if(!c)return;const p=path.split('.');let o=c;
  for(let i=0;i<p.length-1;i++){o=o[p[i]]=o[p[i]]||{};}
  o[p[p.length-1]]=val;
 }
 function setList(k,path,val){set(k,path,String(val||'').split(/[\s,;]+/).filter(Boolean));}
 function pinCert(k){
  const r=(S.ov?S.ov.items:[]).find(x=>key(x)===k),c=r&&r.meta&&r.meta.cert_sha256;if(!c)return;
  set(k,'cert_pin',c);const i=document.getElementById('apcert-'+k);if(i)i.value=c;toast('Zertifikat übernommen – mit „Speichern“ festlegen');
 }
 function tel(w,on){S.tel[w]=!!on;}
 async function clear(what){if(!confirm(what==='errors'?'Alle gesammelten Fehlerberichte löschen?':'Alle gesammelten Nutzungszahlen löschen?'))return;try{await api('apps_stats_clear',{what});toast('Gelöscht');await render();}catch(e){toast(e.message||'Fehler',true);}}
 async function render(){await load();draw();}
 async function refresh(){await render();toast('Aktualisiert');}
 function collect(){return {android_enabled:!!document.getElementById('cmsAndroid')?.checked,windows_enabled:!!document.getElementById('cmsWindows')?.checked,telemetry:S.tel,managed:S.managed};}
 function copy(t){try{navigator.clipboard.writeText(t);toast('Prüfsumme kopiert');}catch(e){toast('Kopieren nicht möglich',true);}}
 return {render,refresh,set,setList,pinCert,collect,copy,tel,clear,geoUpdate,get:k=>S.managed[k]};
})();
window.saveApps=function(){return window.saveSection('apps',window.AppsManager.collect());};
