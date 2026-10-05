// Produktbezeichnungen kommen aus cms/lib/product.php (index.php setzt window.RRW_PRODUCT); Rückfall = bisherige Anzeige.
const RRW_P=Object.assign({name:'RicoReWi CMS',title:'RicoReWi Radio CMS',access_name:'RicoReWi-Radio-CMS',control_center:'AnMaCha Control Center',standalone:false},window.RRW_PRODUCT||{});
function cmsEsc(v){return String(v==null?'':v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]))}
'use strict';
const CRON='api.php'; let CMS=null, newsMounted=false, CMS_IS_SA=false, CMS_USER='', CMS_ROLE='admin', SERVICE_STATUS=[], CURRENT_PAGE_ID=null, DRAG_BLOCK=null, DRAG_MENU=null, MENU_EDITING='top';
document.getElementById('navbarContainer').innerHTML='<nav class="navbar navbar-custom fixed-top" style="background:#0b0b12;border-bottom:1px solid rgba(255,255,255,.08);padding:9px 16px"><div class="container-fluid"><button type="button" class="cms-nav-toggle" aria-label="Menü" onclick="cmsToggleNav()"><i class="fas fa-bars"></i></button><a class="navbar-brand" href="'+(RRW_P.standalone?'/':'/control/')+'" style="color:#fff;text-decoration:none;font-weight:900"><i class="fas fa-arrow-left me-2"></i>'+(RRW_P.standalone?'Zur Website':cmsEsc(RRW_P.control_center))+'</a><div class="d-flex align-items-center gap-2 cms-navbar-actions"><span class="cms-navbar-sublabel" style="color:#9ca3b8;font-size:.75rem">'+cmsEsc(RRW_P.title)+'</span><div style="position:relative"><button class="btn btn-sm btn-outline-light" style="position:relative" onclick="toggleNotifications()"><i class="fas fa-bell"></i><span id="cmsNotifBadge" style="display:none;position:absolute;top:-6px;right:-6px;background:#ff4d6d;color:#fff;border-radius:999px;min-width:17px;height:17px;font-size:.62rem;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 3px;">0</span></button><div id="cmsNotifPanel" style="display:none;position:absolute;right:0;top:calc(100% + 8px);width:340px;max-height:420px;overflow-y:auto;background:#0f1538;border:1px solid #2b3370;border-radius:12px;box-shadow:0 20px 55px rgba(0,0,0,.4);z-index:2000;"></div></div><span id="cmsUserIdentity" class="cms-navbar-identity" style="color:#c9cdea;font-size:.72rem;font-weight:700"></span><button class="btn btn-sm btn-outline-light" title="Abmelden" onclick="cmsLogout()"><i class="fas fa-right-from-bracket"></i></button><a class="btn btn-sm btn-outline-light cms-navbar-website" href="/" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> <span class="cms-navbar-website-label">Website</span></a></div></div></nav>';
const escCms=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
function cmsToken(){return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||''}
function cmsHeaders(json=true){const h={};const t=cmsToken();if(t)h['X-AnMaCha-Token']=t;if(json)h['Content-Type']='application/json';return h}
// Versionskennungen der Bereiche (vom Server): verhindern, dass ein veralteter Stand einen neueren überschreibt
let CMS_REVS={};
async function cmsApi(action,body){
 if(action==='save'&&body&&body.section&&CMS_REVS[body.section]&&!body.base_rev)body={...body,base_rev:CMS_REVS[body.section]};
 const opts=body===undefined?{headers:cmsHeaders(false)}:{method:'POST',headers:cmsHeaders(true),body:JSON.stringify(body)};
 const r=await fetch(CRON+'?action='+encodeURIComponent(action)+'&_='+Date.now(),opts);
 const d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
 if(r.status===401&&action!=='login'&&action!=='local_auth_setup')cmsSessionExpired();
 if(r.status===409&&d.status==='conflict'){const e=new Error(d.message||'Konflikt');e.httpStatus=409;e.conflict=true;throw e}
 if(!r.ok||d.status==='error'){const e=new Error(d.message||'Fehler');e.httpStatus=r.status;throw e}
 if(d.revs&&typeof d.revs==='object')CMS_REVS=d.revs;
 else if(action==='save'&&d.rev&&body&&body.section)CMS_REVS={...CMS_REVS,[body.section]:d.rev};
 else if(body!==undefined&&action!=='save'&&!/^(news_|media_|alexa_|apps_|login|local_auth|comment|seo_)/.test(action)){
  // Andere Aktionen (Theme, Branding, Import, Wiederherstellen …) ändern Bereiche selbst: Versionskennungen neu holen, damit kein falscher Konflikt entsteht
  try{const rr=await fetch(CRON+'?action=revs&_='+Date.now(),{headers:cmsHeaders(false)});const rd=await rr.json();if(rd&&rd.revs)CMS_REVS=rd.revs}catch(e){}
 }
 return d;
}
// Zentrale Behandlung einer abgelaufenen Sitzung: Jede Modul-API meldet einen 401 hierher, damit
// statt vieler einzelner Widget-Fehler ("Statistik nicht verfügbar", "Dateisystem-Prüfung
// fehlgeschlagen", ...) genau einmal das Login-Formular erscheint. Der localStorage-Token gehört
// dem Control Center und wird bewusst nicht angefasst - nur die Sitzungskopie wird verworfen.
let CMS_SESSION_EXPIRED=false;
function cmsSessionExpired(){
 if(CMS_SESSION_EXPIRED||!cmsToken())return;
 CMS_SESSION_EXPIRED=true;
 try{sessionStorage.removeItem('anmacha_session_token');}catch(e){}
 cmsToast('Sitzung abgelaufen – bitte erneut anmelden',true);
 cmsShowLogin();
}
function cmsToast(msg,bad=false){let x=document.getElementById('cmsToast');if(!x){x=document.createElement('div');x.id='cmsToast';Object.assign(x.style,{position:'fixed',right:'18px',bottom:'18px',zIndex:9999,padding:'11px 15px',borderRadius:'10px',fontWeight:'800',boxShadow:'0 12px 35px #0008'});document.body.appendChild(x)}x.textContent=msg;x.style.background=bad?'#5c1822':'#173c31';x.style.color=bad?'#ffc1c8':'#b9ffe7';x.style.display='block';clearTimeout(x._t);x._t=setTimeout(()=>x.style.display='none',2600)}
async function checkCmsFilesystem(){
 try{
   const d=await cmsApi('health');
   const el=document.getElementById('cmsFsState'); if(!el)return;
   el.classList.toggle('bad',!d.healthy);el.hidden=!!d.healthy;   // nur bei Problemen sichtbar
   {const labels={cms_dir:'CMS-Ordner',data_dir:'Datenordner',generated_dir:'Erzeugte Dateien',media_dir:'Medien',site_file:'Website-Daten',news_file:'Beiträge',site_root:'Website-Ordner',index_file:'Startseite',rss_file:'Feed'};const bad=Object.entries(d.checks||{}).filter(([k,v])=>v&&(v.writable===false||v.exists===false)).map(([k])=>labels[k]||k);el.innerHTML='<i class="fas fa-triangle-exclamation"></i> Nicht beschreibbar'+(bad.length?': '+escCms(bad.join(', ')):'')+' – hier kann nichts gespeichert werden';}
   const host=document.getElementById('cmsHealthDetails');
   if(host){const labels={cms_dir:'CMS-Ordner',data_dir:'Datenordner',generated_dir:'Generiert',media_dir:'Medien',site_file:'site.json',news_file:'news.json',site_root:'Website-Root',index_file:'index.html',rss_file:'rss.php'};host.innerHTML=Object.entries(d.checks||{}).map(([k,v])=>'<div class="stat"><div class="l">'+escCms(labels[k]||k)+'</div><div class="v" style="font-size:.82rem;color:'+(v.writable===false?'var(--bad)':'var(--good)')+'">'+(v.exists===false?'FEHLT':v.writable===false?'NICHT BESCHREIBBAR':'OK')+'</div></div>').join('');}
   if(!d.healthy) console.warn('CMS filesystem checks',d.checks);
 }catch(e){
   const el=document.getElementById('cmsFsState'); if(el){el.hidden=false;el.classList.add('bad');el.innerHTML='<i class="fas fa-triangle-exclamation"></i> Speicher-Prüfung fehlgeschlagen';}
 }
}
/* Design-Pakete (lib/pack.php): ohne das RicoReWi-Portal als Design verschwinden die Radio-Teile (Elemente mit data-pack) */
function cmsPackApply(p){window.CMS_PACKS=p||{};document.body.classList.toggle('rrw-standalone',!!(typeof RRW_P!=='undefined'&&RRW_P.standalone));Object.keys(window.CMS_PACKS).forEach(k=>document.body.classList.toggle('no-pack-'+k,!window.CMS_PACKS[k]));window.WidgetsManager?.render?.();window.RadioAdmin?.refresh?.();window.ThemeConf?.refresh?.()}
async function cmsPackRefresh(){try{const d=await cmsApi('pack_status');cmsPackApply(d.packs)}catch(e){}}
function cmsGoto(id){cmsTab(id,document.querySelector('[data-tab="'+id+'"]'))}
async function loadDashboardStats(){
 const host=document.getElementById('dashboardStats'); if(!host)return;
 try{
   const d=await cmsApi('dashboard_stats');
   const n=d.news||{},c=d.comments||{};
   const item=(icon,label,value,color)=>'<div class="stat" style="cursor:pointer" onclick="cmsGoto(\'news\')"><div class="l"><i class="fas '+icon+'" style="margin-right:5px;color:'+(color||'var(--muted)')+'"></i>'+escCms(label)+'</div><div class="v">'+value+'</div></div>';
   host.innerHTML=
     item('fa-newspaper','Veröffentlicht',n.published||0,'var(--good)')+
     item('fa-clock','Geplant',n.scheduled||0,'#7cc9ff')+
     item('fa-pen','Entwürfe',n.draft||0,'var(--accent)')+
     item('fa-trash','Papierkorb',n.trash||0,'var(--bad)')+
     item('fa-comments','Kommentare offen',c.pending||0,c.pending?'var(--accent)':'var(--muted)')+
     item('fa-comment-dots','Kommentare freigegeben',c.approved||0,'var(--good)');
   const topHost=document.getElementById('dashboardTopViewed');
   if(topHost){
     const top=d.top_viewed||[];
     topHost.innerHTML=top.length?top.map((a,i)=>'<div class="stat" style="cursor:pointer" onclick="cmsGoto(\'news\')"><div class="l">'+(i+1)+'. '+escCms(a.title)+'</div><div class="v">'+a.views+'</div></div>').join(''):'<div class="hint">Noch keine Aufrufe gezählt.</div>';
   }
 }catch(e){host.innerHTML='<div class="stat"><div class="l" style="color:#ff8080">Statistik nicht verfügbar</div><div class="v" style="font-size:.78rem;color:#ff8080">'+escCms(e?.message||'Unbekannter Fehler')+'</div></div>';const topHost=document.getElementById('dashboardTopViewed');if(topHost)topHost.innerHTML='<div class="hint" style="color:var(--bad)">'+escCms(e?.message||'Nicht verfügbar')+'</div>';}
}
async function quickDraftSave(){
 const title=document.getElementById('quickDraftTitle')?.value.trim();
 const text=document.getElementById('quickDraftText')?.value.trim();
 const msg=document.getElementById('quickDraftMsg');
 if(!title){ if(msg){msg.textContent='Bitte einen Titel eingeben.';msg.style.color='var(--bad)';} return; }
 try{
   await cmsApi('news_save',{title,body_html:text?'<p>'+escCms(text)+'</p>':'',excerpt:text.slice(0,200),status:'draft'});
   document.getElementById('quickDraftTitle').value='';
   document.getElementById('quickDraftText').value='';
   if(msg){msg.textContent='Als Entwurf gespeichert ✓';msg.style.color='var(--good)';setTimeout(()=>{if(msg)msg.textContent='';},3000);}
   loadDashboardStats();
   loadDashboardActivity();
   if(newsMounted&&window.NewsMagazine)NewsMagazine.reload();
 }catch(e){ if(msg){msg.textContent=e.message;msg.style.color='var(--bad)';} }
}
const ACTIVITY_ICONS={news_create:'fa-plus',news_update:'fa-pen',news_trash:'fa-trash',news_restore:'fa-rotate-left',news_delete_permanent:'fa-trash-can',comment_approve:'fa-check',comment_delete:'fa-comment-slash',comment_reply:'fa-reply',user_add:'fa-user-plus',user_update:'fa-user-pen',user_delete:'fa-user-minus'};
async function loadDashboardComments(){
 const host=document.getElementById('dashboardComments'); if(!host)return;
 try{
   const d=await cmsApi('comments_admin_list');
   const items=(d.comments||[]).slice(0,5);
   host.innerHTML=items.length?items.map(c=>'<div class="stat" style="display:block">'
     +'<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">'
     +'<div class="l" style="display:flex;align-items:center;gap:6px;"><i class="fas fa-comment"'+(c.status==='pending'?' style="color:var(--accent)"':'')+'></i>'+escCms(c.name||'Anonym')+(c.status==='pending'?' <span style="color:var(--accent);font-weight:800;text-transform:none;">· wartet auf Freigabe</span>':'')+'</div>'
     +'<div style="display:flex;gap:5px;">'+(c.status==='pending'?'<button class="btn-g" style="padding:3px 8px" onclick="dashboardApproveComment('+c.id+')" title="Freigeben"><i class="fas fa-check"></i></button>':'')+'<button class="btn-d" style="padding:3px 8px" onclick="dashboardDeleteComment('+c.id+')" title="Löschen"><i class="fas fa-trash"></i></button></div>'
     +'</div>'
     +'<div class="v" style="font-size:.8rem;font-weight:500;margin-top:4px;line-height:1.4;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">'+escCms(c.text||'')+'</div>'
     +'<div style="font-size:.66rem;color:var(--muted);margin-top:3px;">zu „'+escCms(c.article_title||'')+'“</div>'
     +'</div>').join(''):'<div class="hint">Noch keine Kommentare.</div>';
 }catch(e){host.innerHTML='<div class="hint" style="color:var(--bad)">Kommentare nicht verfügbar: '+escCms(e?.message||'Unbekannter Fehler')+'</div>';}
}
async function dashboardApproveComment(id){try{await cmsApi('comment_approve',{id});cmsToast('Kommentar freigegeben');loadDashboardComments();if(newsMounted&&window.NewsMagazine)NewsMagazine.reload();}catch(e){cmsToast(e.message,true);}}
async function dashboardDeleteComment(id){if(!confirm('Kommentar wirklich löschen?'))return;try{await cmsApi('comment_delete',{id});cmsToast('Kommentar gelöscht');loadDashboardComments();if(newsMounted&&window.NewsMagazine)NewsMagazine.reload();}catch(e){cmsToast(e.message,true);}}
async function loadSiteHealth(force=false){
 const host=document.getElementById('siteHealthList'),badge=document.getElementById('siteHealthBadge'); if(!host)return;
 host.innerHTML='<div class="hint">Prüfung läuft…</div>';
 try{
  const d=await cmsApi('site_health',force?{force:1}:undefined);
  const labels={good:'Alles in Ordnung',warn:'Sollte geprüft werden',critical:'Kritische Probleme'};
  const icons={good:'fa-circle-check',warn:'fa-triangle-exclamation',critical:'fa-circle-xmark'};
  if(badge){badge.className='publish-state'+(d.overall==='good'?'':d.overall==='warn'?' warn':' bad');badge.innerHTML='<i class="fas '+icons[d.overall]+'"></i> '+labels[d.overall]+' · '+d.summary.good+' OK'+(d.summary.warn?' · '+d.summary.warn+' Hinweise':'')+(d.summary.critical?' · '+d.summary.critical+' kritisch':'');}
  const groups=[];(d.items||[]).forEach(i=>{let g=groups.find(x=>x.name===i.group);if(!g){g={name:i.group,items:[]};groups.push(g);}g.items.push(i);});
  host.innerHTML=groups.map(g=>'<div class="health-group"><div class="widget-category-title">'+escCms(g.name)+'</div>'+g.items.map(i=>'<div class="health-row"><span class="svc-dot '+(i.status==='good'?'ok':i.status==='warn'?'warn':'bad')+'"></span><b>'+escCms(i.label)+'</b><span class="health-detail">'+escCms(i.detail)+'</span></div>').join('')+'</div>').join('')+'<div class="hint" style="margin-top:8px">Zuletzt geprüft: '+escCms(d.checked_at||'')+'</div>';
 }catch(e){
  if(badge){badge.className='publish-state bad';badge.innerHTML='<i class="fas fa-triangle-exclamation"></i> Prüfung nicht möglich';}
  host.innerHTML='<div class="hint" style="color:var(--bad)">'+escCms(e.message||'Website-Zustand konnte nicht ermittelt werden')+'</div>';
 }
}
async function loadProfile(){
 try{
  const d=await cmsApi('profile_get_self');
  const isLocal=d.source==='local';
  const note=document.getElementById('profileExternalNote'),form=document.getElementById('profileForm');
  if(note)note.style.display=isLocal?'none':'';
  if(form)form.style.display=isLocal?'':'none';
  document.getElementById('profileUsername').value=d.user||'';
  document.getElementById('profileRole').value=d.role==='admin'?'Administrator (voller Zugriff)':'Autor (nur eigene Beiträge)';
  document.getElementById('profileDisplayName').value=d.display_name||'';
  document.getElementById('profileEmail').value=d.email||'';
  document.getElementById('profilePassword').value='';
  document.getElementById('profilePasswordConfirm').value='';
 }catch(e){cmsToast(e.message,true);}
}
async function saveProfile(){
 const displayName=document.getElementById('profileDisplayName').value.trim();
 const email=document.getElementById('profileEmail').value.trim();
 const password=document.getElementById('profilePassword').value;
 const passwordConfirm=document.getElementById('profilePasswordConfirm').value;
 if(password&&password!==passwordConfirm)return cmsToast('Passwörter stimmen nicht überein',true);
 if(password&&password.length<8)return cmsToast('Passwort muss mindestens 8 Zeichen haben',true);
 try{
  await cmsApi('profile_update_self',{display_name:displayName,email,password:password||undefined});
  cmsToast('Profil gespeichert ✓');
  document.getElementById('profilePassword').value='';
  document.getElementById('profilePasswordConfirm').value='';
  const idEl=document.getElementById('cmsUserIdentity');
  if(idEl){CMS_USER=displayName||CMS_USER;idEl.textContent=CMS_USER?CMS_USER+(CMS_ROLE==='admin'?' · Admin':' · Autor'):'';}
 }catch(e){cmsToast(e.message,true);}
}
async function loadDashboardActivity(){
 const card=document.getElementById('dashboardActivityCard'),host=document.getElementById('dashboardActivity');
 if(!card||!host||!CMS_IS_SA)return;
 card.style.display='';
 try{
   const d=await cmsApi('activity_log_list');
   const entries=(d.entries||[]).slice(0,5);
   host.innerHTML=entries.length?entries.map(e=>'<div class="stat" style="cursor:pointer" onclick="cmsGoto(\'activity\')"><div class="l"><i class="fas '+(ACTIVITY_ICONS[e.action]||'fa-circle-info')+'" style="margin-right:5px;color:var(--accent)"></i>'+escCms(e.summary)+'</div><div class="v" style="font-size:.66rem;font-weight:700;color:var(--muted)">'+escCms(e.user)+'</div></div>').join(''):'<div class="hint">Noch keine Aktivität protokolliert.</div>';
 }catch(e){host.innerHTML='<div class="hint" style="color:var(--bad)">Aktivität nicht verfügbar</div>';}
}
let NOTIFICATIONS=[];
const notifFmt=d=>{if(!d)return'–';try{return new Date(String(d).replace(' ','T')).toLocaleString('de-DE',{dateStyle:'medium',timeStyle:'short'});}catch(e){return d;}};
async function loadNotifications(){
 try{
   const d=await cmsApi('notifications_list');
   NOTIFICATIONS=d.notifications||[];
   const badge=document.getElementById('cmsNotifBadge');
   if(badge){const n=d.unread||0;badge.textContent=n>9?'9+':String(n);badge.style.display=n>0?'flex':'none';}
   renderNotifPanel();
 }catch(e){}
}
function renderNotifPanel(){
 const panel=document.getElementById('cmsNotifPanel'); if(!panel||panel.style.display==='none')return;
 if(!NOTIFICATIONS.length){panel.innerHTML='<div style="padding:22px 16px;text-align:center;color:#9ca3b8;font-size:.78rem;">Keine Benachrichtigungen.</div>';return;}
 const header='<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #2b3370;"><b style="color:#fff;font-size:.82rem">Benachrichtigungen</b><button class="btn btn-sm btn-outline-light" style="font-size:.66rem;padding:2px 8px;" onclick="markAllNotificationsRead()">Alle gelesen</button></div>';
 const rows=NOTIFICATIONS.map(n=>{
   const unread=!n.read;
   const icon=n.type==='comment'?'fa-comment':'fa-bell';
   const text=n.type==='comment'
     ?'<b style="color:#fff">'+escCms(n.comment_name)+'</b> hat zu „'+escCms(n.article_title)+'" kommentiert'
     :escCms(n.text||'');
   return '<div onclick="openNotification(\''+n.id+'\')" style="display:flex;gap:10px;padding:11px 14px;border-bottom:1px solid #1c2350;cursor:pointer;background:'+(unread?'rgba(47,184,255,.06)':'transparent')+';">'
     +'<i class="fas '+icon+'" style="color:'+(unread?'#2fb8ff':'#6b7399')+';margin-top:2px;"></i>'
     +'<div style="min-width:0;flex:1"><div style="font-size:.76rem;color:#dfe2f5;line-height:1.4;">'+text+'</div>'
     +(n.comment_excerpt?'<div style="font-size:.7rem;color:#9ca3b8;margin-top:3px;">„'+escCms(n.comment_excerpt)+'"</div>':'')
     +'<div style="font-size:.66rem;color:#6b7399;margin-top:4px;">'+notifFmt(n.created_at)+'</div></div>'
     +(unread?'<span style="width:7px;height:7px;border-radius:999px;background:#2fb8ff;margin-top:6px;flex-shrink:0;"></span>':'')
     +'</div>';
 }).join('');
 panel.innerHTML=header+rows;
}
function toggleNotifications(){
 const panel=document.getElementById('cmsNotifPanel'); if(!panel)return;
 const opening=panel.style.display==='none';
 panel.style.display=opening?'block':'none';
 if(opening)loadNotifications();
}
async function markAllNotificationsRead(){
 try{await cmsApi('notifications_mark_read',{all:true});await loadNotifications();}catch(e){}
}
async function openNotification(id){
 try{await cmsApi('notifications_mark_read',{id});}catch(e){}
 await loadNotifications();
 const panel=document.getElementById('cmsNotifPanel'); if(panel)panel.style.display='none';
 cmsGoto('news');
}
document.addEventListener('click',e=>{
 const panel=document.getElementById('cmsNotifPanel'); if(!panel||panel.style.display==='none')return;
 if(e.target.closest('#cmsNotifPanel')||e.target.closest('[onclick="toggleNotifications()"]'))return;
 panel.style.display='none';
});
setInterval(()=>{ if(cmsToken())loadNotifications(); },45000);
function cmsLoginRedirect(){
 try{sessionStorage.setItem('anmacha_login_redirect','/cms/');}catch(e){}
 location.replace('/control/login.html');
}
async function cmsShowLogin(){
 document.getElementById('cmsDenied').style.display='none';
 document.getElementById('cmsApp').style.display='none';
 const box=document.getElementById('cmsLogin');if(!box)return;
 box.style.display='block';
 let configured=true;
 try{
   const r=await fetch(CRON+'?action=local_auth_status&_='+Date.now());
   const d=await r.json();
   configured=!!d.configured;
   // Frische Installation: Einrichtungsassistent statt offener Kontoanlage
   if(d.install_needed){location.replace('install.php');return;}
 }catch(e){}
 document.getElementById('cmsLoginForm').dataset.mode=configured?'login':'setup';
 document.getElementById('cmsLoginTitle').textContent=configured?'Anmeldung erforderlich':'Ersten CMS-Zugang einrichten';
 document.getElementById('cmsLoginDesc').textContent=configured
   ?(RRW_P.standalone?'Melde dich mit deinem lokalen '+RRW_P.access_name+'-Zugang an.':'Melde dich mit dem lokalen '+RRW_P.access_name+'-Zugang an oder nutze das '+RRW_P.control_center+'.')
   :'Es ist noch kein lokaler CMS-Zugang eingerichtet. Lege jetzt Benutzername und Passwort (mind. 8 Zeichen) fest.';
 document.getElementById('cmsLoginSubmit').innerHTML=configured?'<i class="fas fa-right-to-bracket"></i> Anmelden':'<i class="fas fa-user-plus"></i> Zugang einrichten';
 const msg=document.getElementById('cmsLoginMsg');if(msg)msg.textContent='';
}
async function cmsHandleLogin(ev){
 ev.preventDefault();
 const mode=document.getElementById('cmsLoginForm').dataset.mode||'login';
 const user=document.getElementById('cmsLoginUser').value.trim();
 const pass=document.getElementById('cmsLoginPass').value;
 const msg=document.getElementById('cmsLoginMsg');if(msg)msg.textContent='';
 try{
   const d=await cmsApi(mode==='setup'?'local_auth_setup':'login',{username:user,password:pass});
   if(!d.token)throw new Error('Zugang eingerichtet, bitte anmelden');
   sessionStorage.setItem('anmacha_session_token',d.token);
   document.getElementById('cmsLogin').style.display='none';
   await initCms();
 }catch(e){ if(msg)msg.textContent=e.message||'Anmeldung fehlgeschlagen'; }
 return false;
}
// Hinweis für die Admin-Leiste auf der öffentlichen Website (enthält KEIN Token, nur Name/Rolle/Ablauf; siehe assets/js/adminbar.js)
function cmsBarMark(on){
 try{if(on)localStorage.setItem('rrw_cms_bar',JSON.stringify({u:CMS_USER||'',r:CMS_ROLE||'',exp:Date.now()+12*3600*1000}));else localStorage.removeItem('rrw_cms_bar');}catch(e){}
}
function cmsLogout(){
 cmsBarMark(false);
 try{sessionStorage.removeItem('anmacha_session_token');localStorage.removeItem('anmacha_session_token');}catch(e){}
 location.reload();
}
async function initCms(){
 try{
   const a=await cmsApi('access');
   if(!a.allowed)throw new Error('Kein Zugriff');
   CMS_SESSION_EXPIRED=false;
   CMS_IS_SA=!!a.superadmin;
   CMS_ROLE=a.role||'admin';
   CMS_USER=a.display_name||a.user||'';
   const idEl=document.getElementById('cmsUserIdentity');
   if(idEl)idEl.textContent=CMS_USER?CMS_USER+(CMS_ROLE==='admin'?' · Admin':' · Autor'):'';
   const usersTab=document.getElementById('tab-users'); if(usersTab)usersTab.style.display=CMS_IS_SA?'':'none';
   const activityTab=document.getElementById('tab-activity'); if(activityTab)activityTab.style.display=CMS_IS_SA?'':'none';
   ['apps','alexa','assistant','services','brands','plugins','plugins-upd','backups','database','directory','maintenance','redirects','privacy','community','system','contents','settings','wptools'].forEach(id=>{document.querySelectorAll('.tab[data-tab="'+id+'"]').forEach(t=>{t.hidden=!CMS_IS_SA;});});
   document.querySelectorAll('.wp-switch-sa').forEach(e=>{e.style.display=CMS_IS_SA?'flex':'none';});document.querySelectorAll('.sa-only').forEach(e=>{e.hidden=!CMS_IS_SA;});
   if(window.cmsNavRefresh)cmsNavRefresh();
   document.getElementById('cmsLogin').style.display='none';
   document.getElementById('cmsDenied').style.display='none';
   document.getElementById('cmsApp').style.display='';
   cmsBarMark(true);
   const mig=document.getElementById('legacyImportCard');if(mig)mig.style.display=(CMS_IS_SA&&!RRW_P.standalone)?'':'none';
   await cmsReload();
 }catch(e){
   cmsBarMark(false);
   document.getElementById('cmsApp').style.display='none';
   if(!cmsToken()||e?.httpStatus===401||/Nicht eingeloggt|Sitzung abgelaufen|Berechtigungsprüfung nicht erreichbar/i.test(e?.message||'')){
     try{sessionStorage.removeItem('anmacha_session_token');}catch(_e){}
     await cmsShowLogin();
     return;
   }
   document.getElementById('cmsDenied').style.display='block';
   const p=document.querySelector('#cmsDenied p');if(p)p.textContent=e?.message||'Für diese Verwaltung fehlt die Berechtigung.';
 }
}
const PUBLIC_CMS_SECTIONS=new Set(['navigation','portal','social','apps','branding','core_network','pages','menus','widgets','widget_areas','feed_sources','rss','legal','brands']);
function cmsCanon(v){if(Array.isArray(v))return '['+v.map(cmsCanon).join(',')+']';if(v&&typeof v==='object')return '{'+Object.keys(v).sort().map(k=>JSON.stringify(k)+':'+cmsCanon(v[k])).join(',')+'}';return JSON.stringify(v)}
function setPublishState(ok,text){
 const el=document.getElementById('cmsPublishState');if(!el)return;el.classList.toggle('bad',!ok);el.innerHTML='<i class="fas '+(ok?'fa-circle-check':'fa-triangle-exclamation')+'"></i> '+escCms(text|| (ok?'Alles gespeichert':'Nicht synchron'));
}
async function verifyPublicSection(section,expected){
 if(!PUBLIC_CMS_SECTIONS.has(section)){setPublishState(true,'Gespeichert');return true}
 try{
   const r=await fetch(location.origin+'/cms/api.php?action=public&_='+Date.now(),{cache:'no-store'});
   const d=await r.json();
   const ok=d?.status==='ok'&&cmsCanon(d.config?.[section])===cmsCanon(expected);
   setPublishState(ok,ok?'Live veröffentlicht':'Gespeichert, aber öffentlich noch abweichend');
   return ok;
 }catch(e){setPublishState(false,'Live-Prüfung fehlgeschlagen');return false}
}
async function importLegacyCms(){
 if(!CMS_IS_SA)return cmsToast('Nur Superadmins dürfen Altdaten importieren',true);
 if(!confirm('Vorhandene CMS-Bereiche und News wirklich einmalig aus dem alten Control-Center-Speicher nach /cms/ übernehmen?'))return;
 try{
   setPublishState(true,'Importiere…');
   const d=await cmsApi('import_legacy',{});
   CMS=d.config||CMS;renderCms();setPublishState(true,'Altdaten übernommen');
   cmsToast('Altdaten übernommen: '+(d.news_count||0)+' News-Beiträge ✓');
 }catch(e){setPublishState(false,'Import fehlgeschlagen');cmsToast(e.message,true)}
}
async function cmsReload(){try{const d=await cmsApi('get');CMS=d.config||{};cmsPackApply(d.packs);window.WidgetsManager?.sync();renderCms();checkCmsFilesystem();loadDashboardStats();loadDashboardActivity();loadDashboardComments();loadSiteHealth();loadNotifications();window.NewsMagazine?.setCategories?.(CMS.news_categories||[]);if(newsMounted&&window.NewsMagazine)NewsMagazine.reload();}catch(e){cmsToast(e.message,true)}}
function renderCms(){
 const nav=(CMS.navigation||[]).slice().sort((a,b)=>(a.order||0)-(b.order||0));
 document.getElementById('navEditor').innerHTML=nav.map((x,i)=>`<div class="navrow" data-id="${escCms(x.id)}"><div><input class="switch nav-vis" type="checkbox" ${x.visible?'checked':''}></div><div><input class="fc w-100 nav-label" maxlength="32" value="${escCms(x.label)}"></div><div><input class="fc w-100 nav-order" type="number" min="0" max="999" value="${Number(x.order)||0}"></div><div class="nav-id" style="color:var(--muted);font-family:monospace">${escCms(x.id)}</div></div>`).join('');
 const p=CMS.portal||{};cmsSiteName.value=p.site_name||'';cmsNewsTitle.value=p.news_title||'';cmsNewsIntro.value=p.news_intro||'';cmsHeroEyebrow.value=p.hero_eyebrow||'';cmsHeroTitle.value=p.hero_title||'';cmsHeroText.value=p.hero_text||'';cmsFooterText.value=p.footer_text||'';cmsNoticeEnabled.checked=!!p.notice_enabled;cmsNoticeText.value=p.notice_text||'';
 const s=CMS.social||{};cmsRt.value=s.ricorewi_tiktok||'';cmsRi.value=s.ricorewi_instagram||'';cmsAt.value=s.anmacha_tiktok||'';cmsAi.value=s.anmacha_instagram||'';
 const a=CMS.apps||{};cmsAndroid.checked=a.android_enabled!==false;cmsWindows.checked=a.windows_enabled!==false;
 renderBranding(); window.BrandsManager?.render(); window.DirectoryManager?.refreshBadge?.(); window.AppsManager?.render?.(); window.AlexaManager?.render?.(); window.ThemeManager?.refreshBrandSelect?.(); renderCoreNetwork(); renderServices(); renderPages(); renderMenus(); renderWidgets(); renderWidgetAreas(); renderFeeds(); renderLegal();
 const cc=document.getElementById('coreCountOverview');if(cc)cc.textContent=(CMS.core_network?.stations||[]).length;
}
function cmsFilterNav(q){
 q=(q||'').trim().toLowerCase();
 let anyVisible=false;
 document.querySelectorAll('.tab-group').forEach(group=>{
   let groupHasMatch=false;
   group.querySelectorAll('.tab').forEach(tab=>{
     const match=!q||tab.textContent.toLowerCase().includes(q);
     tab.style.display=match?'':'none';
     if(match)groupHasMatch=true;
   });
   group.style.display=groupHasMatch?'':'none';
   if(groupHasMatch)anyVisible=true;
 });
 if(window.cmsNavRefresh&&!q)cmsNavRefresh();
 const empty=document.getElementById('cmsNavEmpty'); if(empty)empty.style.display=(q&&!anyVisible)?'':'none';
}
function cmsTab(id,btn){document.querySelectorAll('.panel').forEach(x=>x.classList.remove('on'));document.querySelectorAll('.tab').forEach(x=>x.classList.remove('on'));document.getElementById('panel-'+id)?.classList.add('on');btn?.classList.add('on');if(id==='news'&&!newsMounted){newsMounted=true;setTimeout(()=>NewsMagazine.mount(),0)}if(id==='menus')requestAnimationFrame(()=>switchMenuEditor(sessionStorage.getItem('rrw_cms_menu_editor')||MENU_EDITING||'top'));if(id==='widgets')requestAnimationFrame(()=>renderWidgets());if(id==='architecture')loadArchitecture();if(id==='services')loadServiceStatus();if(id==='apps'&&window.AppBuild)AppBuild.load();if(id==='comments'&&window.CommentsManager)CommentsManager.load();cmsCloseNav();window.scrollTo({top:0,behavior:'smooth'})}
function cmsToggleNav(){document.querySelector('.tabs')?.classList.toggle('open');document.getElementById('cmsNavBackdrop')?.classList.toggle('on')}
function cmsCloseNav(){document.querySelector('.tabs')?.classList.remove('open');document.getElementById('cmsNavBackdrop')?.classList.remove('on')}
async function saveSection(section,value){const scrollY=window.scrollY;try{setPublishState(true,'Speichere…');const d=await cmsApi('save',{section,value});CMS[section]=d.value;renderCms();requestAnimationFrame(()=>window.scrollTo({top:scrollY,left:0,behavior:'auto'}));const live=await verifyPublicSection(section,d.value);cmsToast(live?'Gespeichert & live veröffentlicht ✓':'Gespeichert, Live-Stand bitte prüfen',!live)}catch(e){setPublishState(false,'Speichern fehlgeschlagen');cmsToast(e.message,true)}}
function saveNavigation(){const value=[...document.querySelectorAll('.navrow')].map(r=>({id:r.dataset.id,label:r.querySelector('.nav-label').value.trim(),visible:r.querySelector('.nav-vis').checked,order:parseInt(r.querySelector('.nav-order').value||'100')}));saveSection('navigation',value)}
async function savePortal(){
 const portal={site_name:cmsSiteName.value.trim(),news_title:cmsNewsTitle.value.trim(),news_intro:cmsNewsIntro.value.trim(),hero_eyebrow:cmsHeroEyebrow.value.trim(),hero_title:cmsHeroTitle.value.trim(),hero_text:cmsHeroText.value.trim(),footer_text:cmsFooterText.value.trim(),notice_enabled:cmsNoticeEnabled.checked,notice_text:cmsNoticeText.value.trim()};
 const start=(CMS.pages||[]).find(p=>p.type==='system'&&p.system_target==='start');
 if(start){start.headline=portal.hero_title;start.intro=portal.hero_text;}
 await saveSection('portal',portal);
 if(start)await saveSection('pages',CMS.pages||[]);
}
function saveSocial(){saveSection('social',{ricorewi_tiktok:cmsRt.value.trim(),ricorewi_instagram:cmsRi.value.trim(),anmacha_tiktok:cmsAt.value.trim(),anmacha_instagram:cmsAi.value.trim()})}
function saveApps(){saveSection('apps',{android_enabled:cmsAndroid.checked,windows_enabled:cmsWindows.checked})}
function uid(prefix){return prefix+'_'+Date.now().toString(36)+'_'+Math.random().toString(36).slice(2,7)}
function currentPage(){return (CMS?.pages||[]).find(p=>p.id===CURRENT_PAGE_ID)||null}
function renderPages(){
 const list=document.getElementById('pageList');if(!list)return;const pages=CMS?.pages||[];
 if(!CURRENT_PAGE_ID&&pages.length)CURRENT_PAGE_ID=pages[0].id;
 list.innerHTML=pages.map((p,i)=>`<div class="builder-item ${p.id===CURRENT_PAGE_ID?'on':''}" draggable="true" ondragstart="dragPage(event,${i})" ondragover="event.preventDefault()" ondrop="dropPage(event,${i})" onclick="selectPage('${escCms(p.id)}')"><i class="fas ${p.type==='system'?'fa-cube':'fa-file'}" style="color:${p.type==='system'?'var(--cyan)':'var(--accent)'}"></i><div class="grow"><b>${escCms(p.title)}</b><small>/#${p.type==='system'?escCms(p.system_target):'seite/'+escCms(p.slug)} · ${p.type==='system'?'Systemseite':'eigene Seite'}${window.pageBadge?pageBadge(p):''}</small></div></div>`).join('');
 renderPageEditor();
}
function selectPage(id){const y=window.scrollY;CURRENT_PAGE_ID=id;renderPages();requestAnimationFrame(()=>window.scrollTo({top:y,left:0,behavior:'auto'}))}
function renderPageEditor(){
 const p=currentPage(),ed=document.getElementById('pageEditor'),empty=document.getElementById('pageEditorEmpty');if(!ed||!empty)return;
 if(!p){ed.style.display='none';empty.style.display='';return}
 ed.style.display='';empty.style.display='none';peTitle.value=p.title||'';peSlug.value=p.slug||'';peType.value=p.type==='system'?'Systemseite':'Eigene Seite';peNative.checked=p.native_enabled!==false;peNative.disabled=p.type!=='system';peEnabled.checked=p.enabled!==false;if(window.pageStatusLoad)pageStatusLoad(p);
 const isStart=p.type==='system'&&p.system_target==='start';
 peHeadline.value=isStart?(CMS.portal?.hero_title||p.headline||''):(p.headline||'');
 peIntro.value=isStart?(CMS.portal?.hero_text||p.intro||''):(p.intro||'');
 nativeMarker.style.display=p.type==='system'?'':'none';deletePageBtn.style.display=p.type==='custom'?'':'none';renderBlocks('before');renderBlocks('after');if(p.type==='system'){const f=document.getElementById('nativePreviewFrame');if(f&&!f.src)f.src='about:blank';const te=document.getElementById('nativeTextEditor');if(te){te.style.display='none';te.innerHTML='';}}
}
function pageFieldChanged(){const p=currentPage();if(!p)return;p.title=peTitle.value.trim();p.slug=peSlug.value.trim().toLowerCase().replace(/[^a-z0-9äöüß-]+/g,'-').replace(/^-+|-+$/g,'')||p.slug;p.native_enabled=peNative.checked;if(window.pageStatusSave)pageStatusSave(p);else p.enabled=peEnabled.checked;p.headline=peHeadline.value.trim();p.intro=peIntro.value.trim();if(p.type==='system'&&p.system_target==='start'){CMS.portal=CMS.portal||{};CMS.portal.hero_title=p.headline;CMS.portal.hero_text=p.intro;const ht=document.getElementById('cmsHeroTitle'),hx=document.getElementById('cmsHeroText');if(ht)ht.value=p.headline;if(hx)hx.value=p.intro;}const active=document.querySelector('.builder-item.on b');if(active)active.textContent=p.title||p.slug}
function cmsAddPage(){const title=prompt('Name der neuen Seite:','Neue Seite');if(!title)return;const id=uid('page'),slug=title.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g,'').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'')||'seite';CMS.pages=CMS.pages||[];CMS.pages.push({id,slug,title,type:'custom',system_target:'',enabled:true,native_enabled:false,headline:'',intro:'',text_overrides:[],blocks_before:[],blocks_after:[]});CURRENT_PAGE_ID=id;renderPages()}
function deleteCurrentPage(){const p=currentPage();if(!p||p.type!=='custom')return;if(!confirm('Eigene Seite „'+p.title+'“ wirklich löschen? Menüeinträge auf diese Seite müssen danach ebenfalls entfernt werden.'))return;CMS.pages=CMS.pages.filter(x=>x.id!==p.id);CURRENT_PAGE_ID=CMS.pages[0]?.id||null;renderPages()}
function blockArray(zone){const p=currentPage();return !p?[]:(zone==='before'?(p.blocks_before||(p.blocks_before=[])):(p.blocks_after||(p.blocks_after=[])))}
function addBlock(type){const p=currentPage();if(!p)return;let b={id:uid('blk'),type,enabled:true};if(type==='heading')Object.assign(b,{text:'Neue Überschrift',level:2});if(type==='text'||type==='quote')b.text='Neuer Inhalt';if(type==='html')b.html='<p>Eigener HTML-Inhalt</p>';if(type==='image')Object.assign(b,{url:'',alt:'',caption:''});if(type==='button')Object.assign(b,{label:'Mehr erfahren',url:'#'});if(type==='widget')b.widget_id=(CMS.widgets||[])[0]?.id||'';if(type==='spacer')b.size=32;blockArray('before').push(b);renderBlocks('before')}
function renderBlocks(zone){
 const host=document.getElementById(zone==='before'?'blocksBefore':'blocksAfter');if(!host)return;const arr=blockArray(zone);
 host.innerHTML=arr.length?arr.map((b,i)=>blockHtml(b,zone,i)).join(''):'<div class="empty" style="padding:18px">Blöcke hierher ziehen oder oben hinzufügen.</div>';
 host.ondragover=e=>{e.preventDefault();host.classList.add('dragover')};host.ondragleave=()=>host.classList.remove('dragover');host.ondrop=e=>{e.preventDefault();host.classList.remove('dragover');if(!DRAG_BLOCK)return;moveBlock(DRAG_BLOCK.zone,DRAG_BLOCK.index,zone,arr.length);DRAG_BLOCK=null};
}
function blockHtml(b,zone,i){
 let editor='';
 if(b.type==='heading')editor=`<input class="fc w-100" value="${escCms(b.text||'')}" oninput="updBlock('${zone}',${i},'text',this.value)"><select class="fc" onchange="updBlock('${zone}',${i},'level',+this.value)"><option value="2" ${b.level===2?'selected':''}>H2</option><option value="3" ${b.level===3?'selected':''}>H3</option><option value="4" ${b.level===4?'selected':''}>H4</option></select>`;
 else if(b.type==='text'||b.type==='quote')editor=`<textarea class="fc w-100" rows="3" oninput="updBlock('${zone}',${i},'text',this.value)">${escCms(b.text||'')}</textarea>`;
 else if(b.type==='html')editor=`<textarea class="fc w-100" rows="4" oninput="updBlock('${zone}',${i},'html',this.value)">${escCms(b.html||'')}</textarea>`;
 else if(b.type==='image')editor=`<input class="fc w-100" placeholder="Bild-URL" value="${escCms(b.url||'')}" oninput="updBlock('${zone}',${i},'url',this.value)"><div style="display:flex;gap:6px"><input id="imgfile-${zone}-${i}" type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="fc w-100"><button class="btn-g" onclick="uploadBlockImage('${zone}',${i})"><i class="fas fa-upload"></i></button></div><input class="fc w-100" placeholder="Alt-Text" value="${escCms(b.alt||'')}" oninput="updBlock('${zone}',${i},'alt',this.value)"><input class="fc w-100" placeholder="Bildunterschrift" value="${escCms(b.caption||'')}" oninput="updBlock('${zone}',${i},'caption',this.value)">`;
 else if(b.type==='button')editor=`<input class="fc w-100" placeholder="Beschriftung" value="${escCms(b.label||'')}" oninput="updBlock('${zone}',${i},'label',this.value)"><input class="fc w-100" placeholder="URL" value="${escCms(b.url||'')}" oninput="updBlock('${zone}',${i},'url',this.value)">`;
 else if(b.type==='widget')editor=`<select class="fc w-100" onchange="updBlock('${zone}',${i},'widget_id',this.value)">${(CMS.widgets||[]).map(w=>'<option value="'+escCms(w.id)+'" '+(w.id===b.widget_id?'selected':'')+'>'+escCms(w.name)+'</option>').join('')}</select>`;
 else if(b.type==='spacer')editor=`<input type="range" min="8" max="160" value="${Number(b.size)||32}" oninput="updBlock('${zone}',${i},'size',+this.value);this.nextElementSibling.textContent=this.value+' px'"><span class="hint">${Number(b.size)||32} px</span>`;
 else editor='<span class="hint">'+escCms(b.type)+'</span>';
 return `<div class="cms-block" draggable="true" ondragstart="dragBlock(event,'${zone}',${i})" ondragover="event.preventDefault()" ondrop="dropBlockAt(event,'${zone}',${i})"><div class="drag-handle"><i class="fas fa-grip-vertical"></i></div><div class="block-editor"><div class="hint" style="text-transform:uppercase;font-weight:900">${escCms(b.type)}</div>${editor}</div><div class="block-tools"><label title="anzeigen"><input type="checkbox" ${b.enabled!==false?'checked':''} onchange="updBlock('${zone}',${i},'enabled',this.checked)"></label><button class="btn-d" style="padding:6px 8px" onclick="removeBlock('${zone}',${i})"><i class="fas fa-trash"></i></button></div></div>`;
}
function updBlock(zone,i,k,v){const a=blockArray(zone);if(a[i])a[i][k]=v}
function removeBlock(zone,i){blockArray(zone).splice(i,1);renderBlocks(zone)}
function dragBlock(e,zone,index){DRAG_BLOCK={zone,index};e.currentTarget.classList.add('dragging');e.dataTransfer.effectAllowed='move'}
function dropBlockAt(e,zone,index){e.preventDefault();if(!DRAG_BLOCK)return;moveBlock(DRAG_BLOCK.zone,DRAG_BLOCK.index,zone,index);DRAG_BLOCK=null}
function moveBlock(fromZone,fromIndex,toZone,toIndex){const from=blockArray(fromZone),to=blockArray(toZone),item=from.splice(fromIndex,1)[0];if(!item)return;if(from===to&&fromIndex<toIndex)toIndex--;to.splice(Math.max(0,Math.min(toIndex,to.length)),0,item);renderBlocks('before');renderBlocks('after')}
let NATIVE_TEXT_CANDIDATES=[];
function nativePreviewUrl(page){
 const base=location.origin+'/?cms_native_preview=1&cms_target='+encodeURIComponent(page?.system_target||'start')+'&t='+Date.now();
 if(!page||page.type!=='system')return base;
 const t=page.system_target;
 if(['start','sender','voting'].includes(t))return base;
 if(t==='senderdetail')return base+'#ricorewi';
 return base+'#'+encodeURIComponent(t);
}
function refreshNativePreview(){
 const p=currentPage(),f=document.getElementById('nativePreviewFrame');if(!p||p.type!=='system'||!f)return;
 NATIVE_TEXT_CANDIDATES=[];
 try{
   sessionStorage.setItem('rrw_page_preview_override',JSON.stringify(p));
   const portal={
     site_name:document.getElementById('cmsSiteName')?.value||CMS.portal?.site_name||'',
     news_title:document.getElementById('cmsNewsTitle')?.value||CMS.portal?.news_title||'',
     news_intro:document.getElementById('cmsNewsIntro')?.value||CMS.portal?.news_intro||'',
     hero_eyebrow:document.getElementById('cmsHeroEyebrow')?.value||CMS.portal?.hero_eyebrow||'',
     hero_title:document.getElementById('cmsHeroTitle')?.value||CMS.portal?.hero_title||'',
     hero_text:document.getElementById('cmsHeroText')?.value||CMS.portal?.hero_text||'',
     footer_text:document.getElementById('cmsFooterText')?.value||CMS.portal?.footer_text||'',
     notice_enabled:document.getElementById('cmsNoticeEnabled')?.checked??CMS.portal?.notice_enabled,
     notice_text:document.getElementById('cmsNoticeText')?.value||CMS.portal?.notice_text||''
   };
   sessionStorage.setItem('rrw_portal_preview_override',JSON.stringify(portal));
 }catch(e){}
 f.onload=()=>{try{const d=f.contentDocument;if(!d)return;const sc=d.getElementById('app-scroll');if(!sc)return;let target=null;if(p.system_target==='sender')target=d.getElementById('sec-sender');else if(p.system_target==='voting')target=d.getElementById('sec-voting');sc.scrollTo({top:target?Math.max(0,target.offsetTop-12):0,left:0,behavior:'auto'});}catch(e){}};
 f.src=nativePreviewUrl(p);
 const te=document.getElementById('nativeTextEditor');if(te){te.style.display='none';te.innerHTML='';}
}
function selectorForNative(el){
 if(!el)return''; if(el.id)return '#'+CSS.escape(el.id);
 const parts=[];let cur=el;
 while(cur&&cur.nodeType===1&&cur!==cur.ownerDocument.body){
   if(cur.id){parts.unshift('#'+CSS.escape(cur.id));break}
   const tag=cur.tagName.toLowerCase();const same=cur.parentElement?Array.from(cur.parentElement.children).filter(x=>x.tagName===cur.tagName):[];
   const nth=same.length>1?':nth-of-type('+(same.indexOf(cur)+1)+')':'';
   parts.unshift(tag+nth);cur=cur.parentElement;
 }
 return parts.join(' > ');
}
function ownVisibleText(el){
 const direct=Array.from(el.childNodes).filter(n=>n.nodeType===Node.TEXT_NODE).map(n=>n.nodeValue.replace(/\s+/g,' ').trim()).filter(Boolean).join(' ').trim();
 return direct;
}
function nativeScanRoots(doc,target){
 if(target==='start')return [doc.getElementById('sec-start')].filter(Boolean);
 if(target==='sender')return [doc.getElementById('sec-sender')].filter(Boolean);
 if(target==='voting')return [doc.getElementById('sec-voting'),doc.getElementById('network-voting-wrapper')].filter(Boolean);
 return [doc.getElementById('full-view')].filter(Boolean);
}
window.addEventListener('message',function(e){
 const d=e.data;if(!d||d.type!=='rrw-cms-native-texts'||!Array.isArray(d.rows))return;
 const p=currentPage();if(!p||p.type!=='system'||d.target!==p.system_target)return;
 NATIVE_TEXT_CANDIDATES=d.rows.map(r=>{const saved=(p.text_overrides||[]).find(x=>x.selector===r.selector);return {...r,text:saved?saved.text:r.original};});
 const host=document.getElementById('nativeTextEditor');if(host&&host.style.display!=='none')renderNativeTextCandidates();
});
function renderNativeTextCandidates(){
 const host=document.getElementById('nativeTextEditor'),p=currentPage();if(!host||!p)return;
 if(!NATIVE_TEXT_CANDIDATES.length){host.style.display='block';host.innerHTML='<div class="empty">Keine editierbaren Texte erkannt. Vorschau ggf. kurz neu laden.</div>';return}
 host.style.display='block';
 host.innerHTML='<div class="th"><div><div class="tt"><i class="fas fa-i-cursor"></i>Vorhandene Texte bearbeiten</div><div class="hint">Direkt aus der echten Seite erkannt. Änderungen werden als CMS-Textänderungen gespeichert.</div></div><button class="btn-g" onclick="resetNativeTexts()"><i class="fas fa-rotate-left"></i> Zurücksetzen</button></div>'+NATIVE_TEXT_CANDIDATES.map((r,i)=>'<div class="native-text-row"><div class="where">'+escCms(r.tag)+' · '+escCms(r.selector)+'</div><textarea class="fc w-100" rows="'+(r.text.length>130?3:2)+'" oninput="nativeTextChanged('+i+',this.value)">'+escCms(r.text)+'</textarea><div class="hint" style="margin-top:4px">Original: '+escCms(r.original)+'</div></div>').join('');
}
function scanNativeTexts(){
 const p=currentPage(),f=document.getElementById('nativePreviewFrame'),host=document.getElementById('nativeTextEditor');if(!p||p.type!=='system'||!f||!host)return;
 try{
   const doc=f.contentDocument;if(!doc)throw new Error('Vorschau noch nicht geladen');
   const seen=new Set(),rows=[];
   nativeScanRoots(doc,p.system_target).forEach(root=>{
     const els=[root,...root.querySelectorAll('h1,h2,h3,h4,p,a,button,span,small,b,strong,em,i,label')];
     els.forEach(el=>{
       if(!el||el.closest('script,style,noscript'))return;
       const text=ownVisibleText(el);if(text.length<2||text.length>800)return;
       const selector=selectorForNative(el);if(!selector||seen.has(selector))return;seen.add(selector);
       const saved=(p.text_overrides||[]).find(x=>x.selector===selector);
       rows.push({selector,original:text,text:saved?saved.text:text,tag:el.tagName.toLowerCase()});
     });
   });
   NATIVE_TEXT_CANDIDATES=rows.slice(0,120);
   renderNativeTextCandidates();
 }catch(e){if(NATIVE_TEXT_CANDIDATES.length)renderNativeTextCandidates();else{host.style.display='block';host.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Texte werden über die Live-Preview geladen …</div>';setTimeout(()=>{if(NATIVE_TEXT_CANDIDATES.length)renderNativeTextCandidates()},700)}}
}
function nativeTextChanged(i,value){
 const p=currentPage(),r=NATIVE_TEXT_CANDIDATES[i];if(!p||!r)return;
 p.text_overrides=p.text_overrides||[];
 const idx=p.text_overrides.findIndex(x=>x.selector===r.selector);
 const obj={selector:r.selector,text:value,original:r.original};
 if(idx>=0)p.text_overrides[idx]=obj;else p.text_overrides.push(obj);
 r.text=value;
}
function resetNativeTexts(){const p=currentPage();if(!p)return;p.text_overrides=[];NATIVE_TEXT_CANDIDATES=[];refreshNativePreview();cmsToast('Text-Overrides zurückgesetzt – Vorschau neu laden, dann speichern')}
let DRAG_PAGE=null;
function dragPage(e,index){DRAG_PAGE=index;e.dataTransfer.effectAllowed='move'}
function dropPage(e,index){e.preventDefault();if(DRAG_PAGE===null)return;const a=CMS.pages,it=a.splice(DRAG_PAGE,1)[0];let at=index;if(DRAG_PAGE<index)at--;a.splice(at,0,it);DRAG_PAGE=null;renderPages()}
async function uploadBlockImage(zone,i){const input=document.getElementById('imgfile-'+zone+'-'+i),file=input?.files?.[0];if(!file)return cmsToast('Bitte Bild auswählen',true);const fd=new FormData();fd.append('file',file);try{const r=await fetch(CRON+'?action=media_upload',{method:'POST',headers:cmsHeaders(false),body:fd});const d=await r.json();if(!r.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');updBlock(zone,i,'url',d.url);renderBlocks(zone);cmsToast('Bild hochgeladen ✓')}catch(e){cmsToast(e.message,true)}}
function previewCurrentPage(){const p=currentPage();if(!p)return cmsToast('Keine Seite gewählt',true);if(p.type==='custom'){window.open(location.origin+'/'+encodeURIComponent(p.slug)+'.html','_blank','noopener');return}const hash=p.system_target==='start'?'':p.system_target;window.open(location.origin+'/' +(hash?'#'+hash:''),'_blank','noopener')}
async function savePages(){const p=currentPage();if(p?.type==='system'&&p.system_target==='start')await saveSection('portal',CMS.portal||{});await saveSection('pages',CMS.pages||[])}

function pageTargetOptions(cur){
 const sys=(CMS.pages||[]).filter(p=>p.type==='system'&&p.enabled!==false).map(p=>'<option value="system:'+escCms(p.system_target)+'" '+(cur==='system:'+p.system_target?'selected':'')+'>System: '+escCms(p.title)+'</option>').join('');
 const own=(CMS.pages||[]).filter(p=>p.type==='custom'&&p.enabled!==false).map(p=>'<option value="page:'+escCms(p.slug)+'" '+(cur==='page:'+p.slug?'selected':'')+'>Seite: '+escCms(p.title)+'</option>').join('');
 const fixed=['system:impressum','system:datenschutz','action:favoriten','action:mehr'];
 const isKnown=cur.startsWith('system:')||cur.startsWith('page:')||fixed.includes(cur);
 const custom=!isKnown&&cur?'<option value="'+escCms(cur)+'" selected>Individueller Link: '+escCms(cur)+'</option>':'';
 return sys+own+'<option value="system:impressum" '+(cur==='system:impressum'?'selected':'')+'>Rechtliches: Impressum</option><option value="system:datenschutz" '+(cur==='system:datenschutz'?'selected':'')+'>Rechtliches: Datenschutz</option><option value="action:favoriten" '+(cur==='action:favoriten'?'selected':'')+'>Aktion: Favoriten</option><option value="action:mehr" '+(cur==='action:mehr'?'selected':'')+'>Aktion: Mehr</option>'+custom;
}
function menuTargetLabel(target){
 target=String(target||'');
 if(target.startsWith('system:')){const id=target.slice(7);if(id==='impressum')return'Impressum';if(id==='datenschutz')return'Datenschutz';return (CMS.pages||[]).find(p=>p.system_target===id)?.title||id}
 if(target.startsWith('page:'))return (CMS.pages||[]).find(p=>p.slug===target.slice(5))?.title||target.slice(5);
 if(target==='action:favoriten')return'Favoriten';if(target==='action:mehr')return'Mehr';
 return target||'Kein Ziel';
}
function currentMenuEditor(){
 const stored=sessionStorage.getItem('rrw_cms_menu_editor');
 const v=stored||MENU_EDITING||document.getElementById('menuEditingSelect')?.value||'top';
 return v==='bottom'?'bottom':'top';
}
function switchMenuEditor(which){
 MENU_EDITING=which==='bottom'?'bottom':'top';
 sessionStorage.setItem('rrw_cms_menu_editor',MENU_EDITING);
 const sel=document.getElementById('menuEditingSelect');if(sel&&sel.value!==MENU_EDITING)sel.value=MENU_EDITING;
 renderMenuAvailablePages();
 renderMenu(MENU_EDITING);
 const hint=document.getElementById('menuEditingHint');if(hint)hint.textContent=MENU_EDITING==='top'?'Obere Hauptnavigation auf Desktop und großen Displays.':'Feste Navigation am unteren Rand auf Mobilgeräten.';
 const title=document.getElementById('menuStructureTitle');if(title)title.textContent=MENU_EDITING==='top'?'Top Navigation':'Bottom Navigation';
}
function renderMenus(){
 MENU_EDITING=currentMenuEditor();
 const sel=document.getElementById('menuEditingSelect');if(sel)sel.value=MENU_EDITING;
 const hint=document.getElementById('menuEditingHint');if(hint)hint.textContent=MENU_EDITING==='top'?'Obere Hauptnavigation auf Desktop und großen Displays.':'Feste Navigation am unteren Rand auf Mobilgeräten.';
 renderMenuAvailablePages();renderMenu(MENU_EDITING);
}
function renderMenuAvailablePages(){
 const host=document.getElementById('menuAvailablePages');if(!host)return;
 const pages=(CMS.pages||[]).filter(p=>p.enabled!==false);
 let html=pages.map(p=>'<label class="wp-menu-check"><input type="checkbox" class="menu-page-pick" data-label="'+escCms(p.title)+'" data-target="'+escCms(p.type==='system'?'system:'+p.system_target:'page:'+p.slug)+'"><span>'+escCms(p.title)+'</span><small style="margin-left:auto;color:var(--muted)">'+(p.type==='system'?'System':'Eigene')+'</small></label>').join('');
 html+='<label class="wp-menu-check"><input type="checkbox" class="menu-page-pick" data-label="Impressum" data-target="system:impressum"><span>Impressum</span></label><label class="wp-menu-check"><input type="checkbox" class="menu-page-pick" data-label="Datenschutz" data-target="system:datenschutz"><span>Datenschutz</span></label>';
 host.innerHTML=html;
}
function renderMenu(which){
 const host=document.getElementById('menuStructure');if(!host)return;const arr=CMS?.menus?.[which]||[];
 document.getElementById('menuStructureTitle').textContent=which==='top'?'Top Navigation':'Bottom Navigation';
 host.innerHTML=arr.length?arr.map((m,i)=>`<div class="menu-item ${m.parent_id?'child':''}" data-menu-id="${escCms(m.id)}" draggable="true" ondragstart="dragMenu(event,'${which}',${i})" ondragover="event.preventDefault()" ondrop="dropMenu(event,'${which}',${i})"><div class="drag-handle"><i class="fas fa-grip-vertical"></i></div><div><div style="display:flex;gap:6px;align-items:center"><input class="fc w-100" value="${escCms(m.label)}" oninput="updMenu('${which}',${i},'label',this.value)"><span class="hint" style="white-space:nowrap">${escCms(menuTargetLabel(m.target))}</span></div><select class="fc w-100" style="margin-top:5px" onchange="updMenu('${which}',${i},'target',this.value);renderMenu('${which}')">${pageTargetOptions(m.target)}</select><label class="hint" style="display:block;margin-top:5px">Übergeordnet / Untermenü</label><select class="fc w-100" onchange="updMenu('${which}',${i},'parent_id',this.value);renderMenu('${which}')"><option value="">— Hauptebene —</option>${arr.filter(x=>x.id!==m.id&&!x.parent_id).map(x=>'<option value="'+escCms(x.id)+'" '+(x.id===m.parent_id?'selected':'')+'>'+escCms(x.label)+'</option>').join('')}</select></div><div><input class="fc" value="${escCms(m.icon||'fa-circle')}" oninput="updMenu('${which}',${i},'icon',this.value)" title="FontAwesome Icon"><label style="display:flex;gap:5px;align-items:center;margin-top:5px;font-size:.68rem;color:var(--muted)"><input type="checkbox" ${m.enabled!==false?'checked':''} onchange="updMenu('${which}',${i},'enabled',this.checked)"> sichtbar</label></div><button class="btn-d" style="padding:7px" onclick="removeMenuItem('${which}',${i})"><i class="fas fa-trash"></i></button></div>`).join(''):'<div class="empty"><i class="fas fa-bars"></i>Dieses Menü ist leer. Links Seiten auswählen und hinzufügen.</div>';
}
function addSelectedPagesToMenu(){
 CMS.menus=CMS.menus||{top:[],bottom:[]};const arr=CMS.menus[MENU_EDITING]||(CMS.menus[MENU_EDITING]=[]);
 document.querySelectorAll('.menu-page-pick:checked').forEach(cb=>{arr.push({id:uid(MENU_EDITING==='top'?'m':'b'),label:cb.dataset.label||'Seite',target:cb.dataset.target||'system:start',icon:'fa-circle',parent_id:'',enabled:true});cb.checked=false;});
 renderMenu(MENU_EDITING);
}
function addCustomMenuLink(){
 const label=document.getElementById('customMenuLabel').value.trim(),url=document.getElementById('customMenuUrl').value.trim();if(!label||!url)return cmsToast('Linktext und URL eingeben',true);
 CMS.menus=CMS.menus||{top:[],bottom:[]};CMS.menus[MENU_EDITING].push({id:uid(MENU_EDITING==='top'?'m':'b'),label,target:url,icon:'fa-link',parent_id:'',enabled:true});customMenuLabel.value='';customMenuUrl.value='';renderMenu(MENU_EDITING);
}
function addMenuItem(which){CMS.menus=CMS.menus||{top:[],bottom:[]};CMS.menus[which]=CMS.menus[which]||[];CMS.menus[which].push({id:uid(which==='top'?'m':'b'),label:'Neuer Menüpunkt',target:'system:start',icon:'fa-circle',parent_id:'',enabled:true});renderMenu(which)}
function updMenu(which,i,k,v){if(CMS.menus?.[which]?.[i])CMS.menus[which][i][k]=v}
function removeMenuItem(which,i){CMS.menus[which].splice(i,1);renderMenu(which)}
function dragMenu(e,which,index){DRAG_MENU={which,index};e.dataTransfer.effectAllowed='move'}
function dropMenu(e,which,index){
 e.preventDefault();if(!DRAG_MENU||DRAG_MENU.which!==which)return;
 const a=CMS.menus[which],dragIndex=DRAG_MENU.index,it=a[dragIndex],targetId=e.currentTarget?.dataset?.menuId||'',target=a.find(x=>x.id===targetId);
 if(!it){DRAG_MENU=null;return}
 const rect=e.currentTarget?.getBoundingClientRect(),x=rect?e.clientX-rect.left:45;
 a.splice(dragIndex,1);
 let at=a.findIndex(x=>x.id===targetId);if(at<0)at=Math.min(index,a.length);
 if(target&&target.id!==it.id){
   if(x>90)it.parent_id=target.parent_id||target.id;
   else if(x<45)it.parent_id='';
 }
 a.splice(Math.min(at+1,a.length),0,it);
 DRAG_MENU=null;renderMenu(which);
}
function saveMenus(){saveSection('menus',CMS.menus||{top:[],bottom:[]})}

// Widget-Verwaltung: siehe widgets-manager.js (eigenständige Widget-Instanzen wie bei WordPress)
function renderWidgets(){window.WidgetsManager?.render()}
function renderWidgetAreas(){window.WidgetsManager?.render()}
async function resetThemeAreas(){
 if(!confirm('Widget-Anordnung auf den Standard des aktiven Themes zurücksetzen? Deine aktuelle Anordnung für dieses Theme wird dabei überschrieben.'))return;
 try{const d=await cmsApi('theme_reset_areas');CMS.widget_areas=d.widget_areas||[];renderWidgetAreas();cmsToast('Widget-Anordnung des Themes wiederhergestellt ✓');}catch(e){cmsToast(e.message,true);}
}
async function saveWidgetsAndAreas(){return window.WidgetsManager?.save()}

function renderFeeds(){
 const r=CMS?.rss||{},sources=CMS?.feed_sources||[];
 const g=id=>document.getElementById(id);
 if(g('rssEnabled'))g('rssEnabled').checked=r.enabled!==false;
 if(g('rssTitle'))g('rssTitle').value=r.title||'';
 if(g('rssDescription'))g('rssDescription').value=r.description||'';
 if(g('rssMaxItems'))g('rssMaxItems').value=r.max_items||50;
 if(g('rssIncludeExternal'))g('rssIncludeExternal').checked=!!r.include_external;
 const host=g('feedSources');if(!host)return;
 host.innerHTML=sources.length?sources.map((s,i)=>`<div class="card" style="margin-bottom:9px;background:var(--surface2)"><div class="th"><div><b>${escCms(s.name||'Externer Feed')}</b><div class="hint">${escCms(s.url||'')}</div></div><div style="display:flex;gap:6px"><button class="btn-g" onclick="testFeedSource(${i})"><i class="fas fa-vial"></i> Testen</button><button class="btn-d" onclick="removeFeedSource(${i})"><i class="fas fa-trash"></i></button></div></div><div class="section-grid"><div><label class="news-lbl">Name / Quelle</label><input class="fc w-100" value="${escCms(s.name||'')}" oninput="updFeedSource(${i},'name',this.value)"></div><div><label class="news-lbl">Magazin-Kategorie</label><input class="fc w-100" value="${escCms(s.category||'Extern')}" oninput="updFeedSource(${i},'category',this.value)"></div><div style="grid-column:1/-1"><label class="news-lbl">RSS-/Atom-URL</label><input class="fc w-100" value="${escCms(s.url||'')}" oninput="updFeedSource(${i},'url',this.value)" placeholder="https://example.org/feed.xml"></div><div><label class="news-lbl">Max. Beiträge</label><input class="fc w-100" type="number" min="1" max="25" value="${Number(s.max_items)||5}" oninput="updFeedSource(${i},'max_items',+this.value)"></div><div><label style="display:flex;align-items:center;gap:7px;margin-top:22px"><input type="checkbox" ${s.enabled!==false?'checked':''} onchange="updFeedSource(${i},'enabled',this.checked)"> Quelle aktiv</label></div></div><div id="feedTest-${i}" class="hint" style="grid-column:1/-1;margin-top:6px"></div></div></div>`).join(''):'<div class="empty"><i class="fas fa-rss"></i>Noch keine externe Quelle. RSS oder Atom hinzufügen, um das Magazin automatisch zu erweitern.</div>';
}
async function testFeedSource(i){
 const s=CMS.feed_sources?.[i],out=document.getElementById('feedTest-'+i);if(!s||!out)return;
 out.innerHTML='<i class="fas fa-spinner fa-spin"></i> Feed wird geprüft…';
 try{const d=await cmsApi('feed_test',{source:s});out.innerHTML='<span style="color:var(--good)"><i class="fas fa-circle-check"></i> '+d.count+' Einträge lesbar</span>'+(d.sample?.length?'<br>'+d.sample.map(x=>'• '+escCms(x.title)).join('<br>'):'');}
 catch(e){out.innerHTML='<span style="color:var(--bad)"><i class="fas fa-triangle-exclamation"></i> '+escCms(e.message)+'</span>';}
}
function addFeedSource(){CMS.feed_sources=CMS.feed_sources||[];CMS.feed_sources.push({id:uid('feed'),name:'Neue Feed-Quelle',url:'',category:'Extern',enabled:true,max_items:5});renderFeeds()}
function updFeedSource(i,k,v){if(CMS.feed_sources?.[i])CMS.feed_sources[i][k]=v}
function removeFeedSource(i){CMS.feed_sources?.splice(i,1);renderFeeds()}
async function saveFeeds(){
 const rss={enabled:rssEnabled.checked,title:rssTitle.value.trim(),description:rssDescription.value.trim(),max_items:+rssMaxItems.value||50,include_external:rssIncludeExternal.checked};
 await saveSection('rss',rss);await saveSection('feed_sources',CMS.feed_sources||[]);
}

function renderLegal(){const l=CMS?.legal||{};if(!document.getElementById('legalImprintMode'))return;legalImprintMode.value=l.imprint_mode||'link';legalImprintUrl.value=l.imprint_url||'';legalImprintTitle.value=l.imprint_title||'Impressum';legalImprintContent.value=l.imprint_content||'';legalPrivacyMode.value=l.privacy_mode||'link';legalPrivacyUrl.value=l.privacy_url||'';legalPrivacyTitle.value=l.privacy_title||'Datenschutz';legalPrivacyContent.value=l.privacy_content||'';renderLegalMode()}
function renderLegalMode(){legalImprintLink.style.display=legalImprintMode.value==='link'?'':'none';legalImprintCustom.style.display=legalImprintMode.value==='custom'?'':'none';legalPrivacyLink.style.display=legalPrivacyMode.value==='link'?'':'none';legalPrivacyCustom.style.display=legalPrivacyMode.value==='custom'?'':'none'}
function saveLegal(){saveSection('legal',{imprint_mode:legalImprintMode.value,imprint_url:legalImprintUrl.value.trim(),imprint_title:legalImprintTitle.value.trim(),imprint_content:legalImprintContent.value,privacy_mode:legalPrivacyMode.value,privacy_url:legalPrivacyUrl.value.trim(),privacy_title:legalPrivacyTitle.value.trim(),privacy_content:legalPrivacyContent.value})}

const BRANDING_ITEMS=[
 ['portal_logo','Portal-Logo','Website · Header & Footer','Ändert das sichtbare Hauptlogo oben und im Footer der Website.'],
 ['portal_icon','Portal-Icon','Website · Teilen & Fallback','Wird als Portal-/Fallback-Bild verwendet, z. B. wenn kein Sendercover vorhanden ist.'],
 ['favicon','Favicon','Browser-Tab & Lesezeichen','Das kleine Symbol im Browser-Tab, in Lesezeichen und teilweise auf Homescreens.'],
 ['android_inapp_logo','Android In-App-Logo','Android · innerhalb der App','Ändert das Logo innerhalb der Android-App; bestehende Installationen nutzen es als Runtime-Branding.'],
 ['android_startscreen','Android Startscreen','Android · App-Start','Ändert das Start-/Splash-Bild der Android-App. Wird zur Laufzeit gecacht und beim nächsten Build eingebettet.'],
 ['android_app_icon','Android App-Icon','Android · Launcher','Quelle für das Launcher-Icon. Das Betriebssystem-Icon ändert sich bei installierten Apps erst nach einem neuen APK-Build/Update.'],
 ['windows_logo','Windows App-Logo','Windows · App-Branding','Ändert das Logo in der Windows-App und dient als Quelle für kommende Windows-Builds.']
];
function renderBranding(){
 const b=CMS?.branding||{},h=document.getElementById('brandingGrid');if(!h)return;
 h.innerHTML=BRANDING_ITEMS.map(([k,t,where,desc])=>`<div class="asset-card">
   <div class="asset-preview">${b[k]?'<img src="'+escCms(b[k])+'?_='+(Date.now())+'" alt="">':'<span class="hint">Noch kein eigenes Asset</span>'}</div>
   <b>${escCms(t)}</b><div style="font-size:.68rem;font-weight:900;color:var(--cyan);margin-top:3px">${escCms(where)}</div>
   <div class="hint" style="margin:5px 0 10px;line-height:1.45">${escCms(desc)}</div>
   <div class="hint" style="margin-bottom:8px"><i class="fas fa-photo-film"></i> Verknüpft mit dem Medien-Hub. Ein Upload erzeugt dort automatisch mehrere Größen.</div>
   <input id="asset-${k}" type="file" accept="${k==='favicon'?'image/png,image/svg+xml,image/x-icon,image/vnd.microsoft.icon,.ico':'image/png,image/jpeg,image/webp,image/gif,image/svg+xml'}" class="fc w-100" style="font-size:.72rem;margin-bottom:7px">
   <div style="display:flex;gap:6px;flex-wrap:wrap"><button class="btn-g" onclick="uploadBranding('${k}')"><i class="fas fa-upload"></i> Hochladen & anwenden</button><button class="btn-g" onclick="window.MediaHub?.beginBrandingPick('${k}')"><i class="fas fa-photo-film"></i> Aus Medien-Hub</button></div>
   <div class="hint" style="margin-top:7px;word-break:break-all">${escCms(b[k]||'Standarddatei aktiv')}</div>
 </div>`).join('');
}
async function uploadBranding(kind){
 const input=document.getElementById('asset-'+kind),file=input?.files?.[0];if(!file)return cmsToast('Bitte zuerst eine Datei wählen',true);
 const fd=new FormData();fd.append('file',file);fd.append('sizes','64,128,192,256,512,1024,1600');fd.append('quality','90');
 try{
  setPublishState(true,'Upload läuft…');
  const up=await fetch(CRON+'?action=media_library_upload&_='+Date.now(),{method:'POST',headers:cmsHeaders(false),body:fd});
  const ud=await up.json();if(!up.ok||ud.status!=='ok')throw new Error(ud.message||'Upload fehlgeschlagen');
  const path=ud.item?.original?.path?String(ud.item.original.path).replace(/\/original\.[^.]+$/,''):('library/'+(ud.item?.id||''));
  const d=await cmsApi('branding_assign',{kind,path,size:'auto'});
  CMS.branding=d.branding;renderBranding();window.MediaHub?.load(true);
  const live=await verifyPublicSection('branding',d.branding);
  const files=Array.isArray(d.updated_files)&&d.updated_files.length?' · aktualisiert: '+d.updated_files.join(', '):'';
  const warnings=[...(ud.warnings||[]),...(d.warnings||[])];
  cmsToast((live?'Im Medien-Hub gespeichert & Branding live ✓':'Branding gespeichert, öffentliche Ausgabe prüfen')+files+(warnings.length?' · '+warnings.join(' | '):''),!live||!!warnings.length);
 }catch(e){setPublishState(false,'Upload fehlgeschlagen');cmsToast(e.message,true)}
}


function renderCoreNetwork(){
 const h=document.getElementById('coreStationList');if(!h)return;const list=CMS?.core_network?.stations||[];
 h.innerHTML=list.map(st=>`<div class="core-row"><div><div class="core-name"><i class="fas fa-radio" style="color:var(--cyan);margin-right:7px"></i>${escCms(st)}</div><div class="core-meta">Core-Netzwerk · laut.fm</div></div><div>${st==='ricorewi'?'<span class="hint"><i class="fas fa-lock"></i> geschützt</span>':CMS_IS_SA?'<button class="btn-d" onclick="removeCore(\''+escCms(st)+'\')"><i class="fas fa-trash"></i> Entfernen</button>':'<span class="hint"><i class="fas fa-shield-halved"></i> Superadmin nötig</span>'}</div></div>`).join('')||'<div class="empty">Keine Sender.</div>';
}
async function validateAndAddCore(){
 const st=(document.getElementById('coreNewStation')?.value||'').trim().toLowerCase(),out=document.getElementById('coreValidation');
 if(!st)return cmsToast('Sendernamen eingeben',true);
 out.innerHTML='<i class="fas fa-spinner fa-spin"></i> Prüfe bei laut.fm …';
 try{
  const v=await fetch(CRON+'?action=core_validate&station='+encodeURIComponent(st),{headers:cmsHeaders(false)}).then(async r=>{const d=await r.json();if(!r.ok||d.status!=='ok')throw new Error(d.message||'Nicht gefunden');return d});
  out.innerHTML='<span style="color:var(--good)"><i class="fas fa-circle-check"></i> '+escCms(v.station.display_name||v.station.name)+' gefunden.</span>';
  if(!confirm('Sender „'+st+'“ wirklich als Core-Netzwerk-Sender aufnehmen?'))return;
  const d=await cmsApi('core_add',{station:st});CMS.core_network={stations:d.stations};renderCoreNetwork();document.getElementById('coreCountOverview').textContent=d.stations.length;document.getElementById('coreNewStation').value='';cmsToast('Core-Sender aufgenommen ✓');
 }catch(e){out.innerHTML='<span style="color:var(--bad)"><i class="fas fa-triangle-exclamation"></i> '+escCms(e.message)+'</span>'}
}
async function removeCore(st){
 if(!CMS_IS_SA)return cmsToast('Nur Superadmins dürfen Core-Sender entfernen',true);
 const typed=prompt('Sicherheitsprüfung 1/2\n\nGib den Sendernamen exakt ein:\n'+st);
 if(typed!==st)return cmsToast('Abgebrochen: Sendername stimmt nicht',true);
 const phrase=prompt('Sicherheitsprüfung 2/2\n\nGib exakt ein:\nENTFERNEN '+st);
 if(phrase!=='ENTFERNEN '+st)return cmsToast('Abgebrochen: Bestätigung stimmt nicht',true);
 try{const d=await cmsApi('core_remove',{station:st,typed,confirm:phrase});CMS.core_network={stations:d.stations};renderCoreNetwork();document.getElementById('coreCountOverview').textContent=d.stations.length;cmsToast('Core-Sender entfernt')}catch(e){cmsToast(e.message,true)}
}
const SERVICE_FIELDS=[
 ['radio_portal','Radioportal'],['control_center','Control Center'],['public_api','Public API'],['news_api','News API'],['tracker','Tracker'],
 ['apps_page','App-Seite'],['nextcloud','Nextcloud / Medien-Cloud'],['owncast','Owncast / Video'],['castopod','Castopod / Podcast'],['airdeck','AirDeck']
];
function renderServices(){
 if(window.ServicesManager?.active()){ServicesManager.render();return}
 const h=document.getElementById('servicesEditor');if(!h)return;const s=CMS?.services||{};
 h.innerHTML=SERVICE_FIELDS.map(([k,l])=>`<div class="service-row"><label for="svc-${k}">${escCms(l)}</label><input id="svc-${k}" class="fc w-100" value="${escCms(s[k]||'')}" placeholder="URL oder relativer Pfad"><button class="btn-g" onclick="openService('${k}')"><i class="fas fa-arrow-up-right-from-square"></i></button></div>`).join('');
}
function normalizeServiceUrl(v){v=String(v||'').trim();if(!v)return'';if(v.startsWith('/'))return location.origin+v;return v}
function openService(k){const v=document.getElementById('svc-'+k)?.value.trim()||'';const u=normalizeServiceUrl(v);if(!u)return cmsToast('Für diesen Dienst ist keine Adresse hinterlegt',true);window.open(u,'_blank','noopener')}
async function loadServiceStatus(){
 if(window.ServicesManager?.active())return ServicesManager.check();
 const h=document.getElementById('serviceStatusHost');if(!h)return;
 h.innerHTML='<div class="empty" style="grid-column:1/-1"><i class="fas fa-spinner fa-spin"></i>Dienste werden geprüft…</div>';
 try{
  const d=await cmsApi('services_status');SERVICE_STATUS=d.services||[];
  h.innerHTML=SERVICE_STATUS.map(s=>`<div class="svc-card"><div class="svc-head"><b><span class="svc-dot ${s.online?'ok':s.configured?'bad':''}"></span>${escCms(s.name)}</b><span class="hint">${s.configured?(s.online?'online':'nicht erreichbar'):'nicht konfiguriert'}</span></div><div class="svc-meta">${escCms(s.url||'–')}</div><div class="svc-meta">${s.configured?'HTTP '+(s.http||0)+' · '+(s.ms||0)+' ms':''}</div><div class="svc-actions">${s.url?'<button class="btn-g" onclick="openStatusService(\''+escCms(s.id)+'\')"><i class="fas fa-arrow-up-right-from-square"></i> Öffnen</button>':''}</div></div>`).join('');
 }catch(e){h.innerHTML='<div class="empty" style="grid-column:1/-1;color:var(--bad)">'+escCms(e.message)+'</div>'}
}
function openStatusService(id){const s=SERVICE_STATUS.find(x=>x.id===id);const u=normalizeServiceUrl(s?.url||'');if(!u)return cmsToast('Keine Adresse hinterlegt',true);window.open(u,'_blank','noopener')}
function saveServices(){
 if(window.ServicesManager?.active())return ServicesManager.save();
 const v={};SERVICE_FIELDS.forEach(([k])=>v[k]=document.getElementById('svc-'+k)?.value.trim()||'');saveSection('services',v);
}
async function loadArchitecture(){
 const h=document.getElementById('architectureHost');if(!h)return;h.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Lade Architektur…</div>';
 try{
  const d=await cmsApi('architecture');
  h.innerHTML='<div class="grid" style="margin-bottom:12px"><div class="stat"><div class="l">Komponenten</div><div class="v">'+d.components.length+'</div></div>'+(window.CMS_PACKS_AVAILABLE&&window.CMS_PACKS_AVAILABLE['ricorewi-radio']===false?'':'<div class="stat"><div class="l">Core-Sender</div><div class="v">'+d.core_stations.length+'</div></div>')+'</div><div class="arch-grid">'+d.components.map(x=>`<div class="arch-card"><b><i class="fas fa-cube" style="color:var(--accent);margin-right:6px"></i>${escCms(x.name)} ${x.health?'<span class="svc-dot '+(x.health.online?'ok':'bad')+'" title="'+(x.health.online?'erreichbar':'nicht erreichbar')+'"></span>':''}</b><div class="hint">${escCms(x.type)}</div><code>${escCms(x.path)}</code><div class="arch-deps">${x.depends_on?.length?'Abhängig von: '+x.depends_on.map(escCms).join(', '):'Keine internen Abhängigkeiten'}${x.health?' · HTTP '+(x.health.http||0)+' · '+(x.health.ms||0)+' ms':''}</div></div>`).join('')+'</div><div class="hint" style="margin-top:12px">Stand: '+new Date(d.updated_at).toLocaleString('de-DE')+'</div>';
 }catch(e){h.innerHTML='<div class="empty" style="color:var(--bad)">'+escCms(e.message)+'</div>'}
}

window.cmsReload=cmsReload;window.renderBranding=renderBranding;window.cmsTab=cmsTab;window.cmsHandleLogin=cmsHandleLogin;window.cmsLoginRedirect=cmsLoginRedirect;
document.addEventListener('DOMContentLoaded',initCms);
