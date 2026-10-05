'use strict';
/* CMS-Aktualisierung (System → Version & Update): Anzeige der installierten/neuesten Version, Suche, Einspielen, Downgrade, Sicherungen, Einstellungen.
   Serverlogik: cms/src/Update/ (API-Aktionen update_*). Zeigt außerdem die Version im Kopf der Verwaltung (Hinweis bei verfügbarem Update). */
window.UpdateManager=(()=>{
 const tok=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const $=id=>document.getElementById(id);
 const toast=(m,bad=false)=>window.cmsToast?.(m,bad);
 let S=null,busy=false;
 async function api(action,body,quiet){
  const opt=body===undefined?{headers:{'X-AnMaCha-Token':tok()}}:{method:'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':tok()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opt),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401&&!quiet)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 const when=s=>s?new Date(s).toLocaleString('de-DE'):'–';
 // Versions-Abzeichen im Kopf
 async function badge(){
  const el=$('cmsVerBadge');if(!el||!tok())return;
  try{const d=await api('update_badge',undefined,true);
   el.innerHTML='Version <b>'+esc(d.version)+'</b>'+(d.update_available?' · <a href="#" style="color:var(--accent,#c00)" onclick="cmsTab(\'system\',document.querySelector(\'[data-tab=system]\'));UpdateManager.load();return false"><i class="fas fa-circle-up"></i> Update '+esc(d.latest)+' verfügbar</a>':'');
  }catch(e){}
 }
 function row(label,val){return '<div class="stat"><div class="l">'+esc(label)+'</div><div class="v" style="font-size:1rem">'+val+'</div></div>'}
 function render(){
  const h=$('updUi');if(!h||!S)return;
  const i=S.installed,l=S.latest,st=S.settings,p=S.pending;
  let o='<div class="grid">'+row('Installiert','<b>'+esc(i.version)+'</b>'+(i.ref&&i.ref.length>20?' <span class="hint">'+esc(i.ref.slice(0,7))+'</span>':''))
   +row('Neueste',l?('<b>'+esc(l.version)+'</b>'+(S.update_available?' <span style="color:var(--good)">● neu</span>':' <span class="hint">aktuell</span>')):'<span class="hint">unbekannt</span>')
   +row('Zuletzt geprüft',esc(when(S.checked_at)))+'</div>';
  if(S.check_error)o+='<div class="danger-note" style="margin-top:10px">'+esc(S.check_error)+'</div>';
  if(!S.tools.zip||!S.tools.curl||!S.tools.writable)o+='<div class="danger-note" style="margin-top:10px">Voraussetzungen: '+(!S.tools.zip?'PHP-Erweiterung zip fehlt. ':'')+(!S.tools.curl?'PHP-Erweiterung curl fehlt. ':'')+(!S.tools.writable?'Der CMS-Ordner ist für den Webserver nicht beschreibbar. ':'')+'</div>';
  if(p)o+='<div class="hint" style="margin-top:10px;padding:10px;border:1px solid var(--line,#ddd);border-radius:8px"><i class="fas fa-shield-heart"></i> Update <b>'+esc(p.from_version)+' → '+esc(p.to_version)+'</b> wird bis '+esc(when(p.deadline))+' überwacht – bei einem Fehler wird automatisch zurückgespielt. <button class="btn-g" onclick="UpdateManager.confirm()"><i class="fas fa-check"></i> Als gut bestätigen</button></div>';
  if(S.update_available&&l)o+='<div style="margin-top:10px"><b>Version '+esc(l.version)+'</b>'+(l.label&&l.kind!=='release'?' <span class="hint">'+esc(l.label)+'</span>':'')+(l.published?' <span class="hint">· '+esc(when(l.published))+'</span>':'')+(l.notes?'<pre style="white-space:pre-wrap;max-height:140px;overflow:auto;margin:6px 0;font-size:.85rem">'+esc(l.notes)+'</pre>':'')+'</div>';
  o+='<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn-g" '+(busy?'disabled':'')+' onclick="UpdateManager.check()"><i class="fas fa-magnifying-glass"></i> Jetzt nach Updates suchen</button>'
   +'<button class="btn-a" '+(busy||!S.update_available?'disabled':'')+' onclick="UpdateManager.apply()"><i class="fas fa-circle-up"></i> Auf '+(l?esc(l.version):'neueste Version')+' aktualisieren</button></div>';
  // Downgrade / bestimmte Version
  o+='<details style="margin-top:14px"><summary><b>Bestimmte Version installieren (Downgrade)</b></summary><div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><select id="updVer" class="fc" style="min-width:200px"><option value="">Versionen laden …</option></select><button class="btn-d" onclick="UpdateManager.installVersion()"><i class="fas fa-arrow-rotate-left"></i> Diese Version installieren</button></div></details>';
  // Sicherungen
  o+='<details style="margin-top:10px" '+(S.snapshots.length?'open':'')+'><summary><b>Sicherungen vor Updates ('+S.snapshots.length+')</b></summary>'+(S.snapshots.map(x=>'<div class="core-row"><div><b>Version '+esc(x.version||'?')+'</b><div class="hint">'+esc(when(x.created_at))+' · '+Math.max(1,Math.round(x.size/1024))+' KB</div></div><button class="btn-d" onclick="UpdateManager.rollback('+esc(JSON.stringify(x.name))+')"><i class="fas fa-clock-rotate-left"></i> Zurückspielen</button></div>').join('')||'<div class="hint">Noch keine – die erste entsteht vor dem nächsten Update.</div>')+'</details>';
  // Verlauf
  o+='<details style="margin-top:10px"><summary><b>Verlauf</b></summary>'+(S.history.map(x=>'<div class="core-row"><div><b>'+esc(({update:'Update',downgrade:'Downgrade',rollback:'Zurückgespielt',auto_rollback:'Automatisch zurückgenommen',auto_update:'Automatisches Update'})[x.action]||x.action)+'</b> '+esc(x.from||'')+(x.to?' → '+esc(x.to):'')+'<div class="hint">'+esc(when(x.at))+' · '+esc(x.message||'')+'</div></div><span style="color:'+(x.status==='ok'?'var(--good)':'var(--bad)')+'">'+(x.status==='ok'?'✓':'✗')+'</span></div>').join('')||'<div class="hint">Noch keine Einträge.</div>')+'</details>';
  // Einstellungen
  const base=location.origin+location.pathname.replace(/[^/]*$/,'');
  o+='<details style="margin-top:10px"><summary><b>Einstellungen &amp; Automatik</b></summary><div class="section-grid" style="margin-top:10px">'
   +'<div><label class="news-lbl">GitHub-Repository (besitzer/name)</label><input id="updRepo" class="fc w-100" value="'+esc(st.repo)+'" placeholder="besitzer/repository"></div>'
   +'<div><label class="news-lbl">Kanal</label><select id="updChannel" class="fc w-100"><option value="release">Release – höchste veröffentlichte Version</option><option value="beta">Beta – auch Vorabversionen</option><option value="main">Branch – jeweils neuester Stand</option></select></div>'
   +'<div><label class="news-lbl">Branch (nur Kanal „Branch“)</label><input id="updBranch" class="fc w-100" value="'+esc(st.branch)+'"></div>'
   +'<div><label class="news-lbl">CMS-Ordner im Repository (leer = Wurzel)</label><input id="updSubdir" class="fc w-100" value="'+esc(st.subdir)+'" placeholder="cms"></div>'
   +'<div><label class="news-lbl">GitHub-Token (nur für private Repositories, wird nie angezeigt)</label><input id="updToken" type="password" class="fc w-100" autocomplete="off" placeholder="'+(st.token_set?'gespeichert – leer lassen = behalten':'optional')+'"></div>'
   +'<div><label class="news-lbl">Automatisch suchen alle … Stunden (0 = aus)</label><input id="updHours" type="number" min="0" max="168" class="fc w-100" value="'+esc(st.check_hours)+'"></div>'
   +'<div><label class="news-lbl">Überwachung nach dem Update (Minuten)</label><input id="updHealth" type="number" min="2" max="120" class="fc w-100" value="'+esc(st.health_minutes)+'"></div>'
   +'<div><label class="news-lbl">Sicherungen behalten</label><input id="updKeep" type="number" min="1" max="10" class="fc w-100" value="'+esc(st.keep_snapshots)+'"></div></div>'
   +'<label style="display:flex;align-items:center;gap:8px;margin-top:10px"><input id="updAuto" type="checkbox" '+(st.auto_apply?'checked':'')+'> Neue Versionen automatisch einspielen (nur über Webhook oder Cron; mit Gesundheitsprüfung und automatischem Rückschritt)</label>'
   +'<div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn-a" onclick="UpdateManager.saveConfig()"><i class="fas fa-floppy-disk"></i> Einstellungen speichern</button>'
   +'<button class="btn-g" onclick="UpdateManager.token(\'__clear__\')"><i class="fas fa-eraser"></i> Token löschen</button></div>'
   +'<div class="hint" style="margin-top:12px"><b>Automatik per GitHub-Webhook:</b> Repository → Settings → Webhooks: Payload URL <code>'+esc(base)+'update-webhook.php</code>, Content type <code>application/json</code>, Ereignisse „Releases“ (und „Pushes“ im Kanal Branch). Geheimnis: '+(st.secret_set?'gesetzt':'noch nicht erzeugt')+' <button class="btn-g" onclick="UpdateManager.rotateSecret()"><i class="fas fa-key"></i> Neu erzeugen</button><div id="updSecret"></div>'
   +'<br><b>Alternativ Cron</b> (stündlich): <code>0 * * * * php '+esc('<Pfad>')+'/cms/update-cron.php</code><br><b>Notfall</b> (falls die Verwaltung nach einem Update nicht startet): <code>'+esc(base)+'update-rescue.php?token='+esc(S.rescue_token)+'</code> – Adresse sicher aufbewahren.</div></details>';
  h.innerHTML=o;
  $('updChannel').value=st.channel;
  loadVersions();
 }
 async function loadVersions(){
  const sel=$('updVer');if(!sel)return;
  try{const d=await api('update_versions');sel.innerHTML=d.versions.map(v=>'<option value="'+esc(v.ref)+'">'+esc(v.version)+(v.prerelease?' (Beta)':'')+(v.version===S.installed.version?' – installiert':'')+'</option>').join('')||'<option value="">keine Versionen gefunden</option>';}catch(e){sel.innerHTML='<option value="">'+esc(e.message)+'</option>';}
 }
 async function load(){
  const h=$('updUi');if(!h)return;
  try{S=await api('update_status');render();badge();}catch(e){h.innerHTML='<div class="danger-note">'+esc(e.message)+'</div>';}
 }
 async function run(label,fn){
  if(busy)return;busy=true;render();const h=$('updUi');
  try{await fn();}catch(e){toast(e.message,true);alert(e.message);}finally{busy=false;await load();}
 }
 const check=()=>run('Suche',async()=>{S=await api('update_check',{});toast(S.update_available?'Update verfügbar: '+S.latest.version:(S.check_error?S.check_error:'Das CMS ist aktuell'),!!S.check_error);});
 const apply=()=>{
  if(!S?.update_available||!confirm('CMS von '+S.installed.version+' auf '+S.latest.version+' aktualisieren?\nVorher wird eine Sicherung angelegt; bei einem Fehler wird automatisch zurückgespielt.'))return;
  return run('Update',async()=>{const d=await api('update_apply',{});toast('Aktualisiert: '+d.result.from+' → '+d.result.to+' ✓');setTimeout(()=>location.reload(),1200);});
 };
 const installVersion=()=>{
  const ref=$('updVer')?.value;if(!ref)return;
  if(!confirm('Version „'+ref+'“ installieren? Das kann eine ältere Version sein (Downgrade). Vorher wird eine Sicherung angelegt.'))return;
  return run('Version',async()=>{const d=await api('update_apply',{ref,downgrade:true});toast('Installiert: '+d.result.to+' ✓');setTimeout(()=>location.reload(),1200);});
 };
 const rollback=name=>{
  if(!confirm('Sicherung „'+name+'“ zurückspielen? Der aktuelle Code-Stand wird ersetzt (Inhalte und Medien bleiben).'))return;
  return run('Rückschritt',async()=>{const d=await api('update_rollback',{snapshot:name});toast('Zurückgespielt auf '+d.result.to+' ✓');setTimeout(()=>location.reload(),1200);});
 };
 const confirmOk=()=>run('Bestätigen',async()=>{await api('update_confirm',{});toast('Update bestätigt ✓');});
 const saveConfig=()=>run('Speichern',async()=>{
  const s={repo:$('updRepo').value.trim(),channel:$('updChannel').value,branch:$('updBranch').value.trim(),subdir:$('updSubdir').value.trim(),check_hours:+$('updHours').value||0,health_minutes:+$('updHealth').value||10,keep_snapshots:+$('updKeep').value||3,auto_apply:$('updAuto').checked,token:$('updToken').value.trim()};
  await api('update_config_save',{settings:s});toast('Einstellungen gespeichert ✓');await api('update_check',{}).catch(()=>{});
 });
 const token=v=>run('Token',async()=>{await api('update_config_save',{settings:{token:v}});toast('Token gelöscht');});
 const rotateSecret=()=>{
  if(S?.settings?.secret_set&&!confirm('Neues Webhook-Geheimnis erzeugen? Der Webhook in GitHub muss danach angepasst werden.'))return;
  return run('Geheimnis',async()=>{const d=await api('update_secret_rotate',{});alert('Neues Webhook-Geheimnis (wird nur jetzt angezeigt):\n\n'+d.secret);});
 };
 setTimeout(badge,2000);setInterval(badge,1800000);
 return {load,check,apply,installVersion,rollback,confirm:confirmOk,saveConfig,token,rotateSecret,badge};
})();
