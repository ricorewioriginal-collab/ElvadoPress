/* Sandbox (CMS → Design): Spielraum zwischen Live und Deployment. Server: wp_sandbox_* in cms/api.php, cms/wp/sandbox.php.
   Die Theme-Verwaltung (WpThemes) wechselt über Sandbox.mode(true) auf die Sandbox (Anfragen mit &sandbox=1). */
(function(){
  'use strict';
  var st=null,sbxMode=false,loaded=false;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||''}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  async function call(action,body){
    var o={headers:{'X-AnMaCha-Token':token()}};if(body!==undefined){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
    var r=await fetch('api.php?action='+action+'&_='+Date.now(),o),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
  }
  function when(iso){if(!iso)return '–';var d=new Date(iso);return isNaN(d)?iso:d.toLocaleString('de-DE',{dateStyle:'short',timeStyle:'short'})}
  function draw(){
    var box=$('sbxBody');if(!box||!st)return;
    $('sbxSwitch').hidden=!st.exists;if(!st.exists&&sbxMode){mode(false)}
    if(!st.exists){
      box.innerHTML='<div class="hint" style="margin-bottom:10px">Die Sandbox startet als Abbild deiner Live-Einstellungen (Theme, Anpassungen, Widgets, Link-Struktur). Beiträge, Seiten und Menüs aus dem CMS erscheinen dort unverändert, werden aber nie verändert. In der Sandbox wird nichts gesendet oder gespeichert.</div><button class="btn-a" type="button" onclick="Sandbox.act(\'wp_sandbox_create\')"><i class="fas fa-flask"></i> Sandbox anlegen</button>';return;
    }
    var d=st.diff||{},link=location.origin+st.url,parts=[];
    if(d.sandbox_theme!==d.live_theme||d.sandbox_front!==d.live_front)parts.push('Auslieferung: <b>'+esc(d.sandbox_front?(d.sandbox_theme||'WordPress-Theme'):'Portal-Design')+'</b> statt <b>'+esc(d.live_front?(d.live_theme||'WordPress-Theme'):'Portal-Design')+'</b>');
    if((d.options||[]).length)parts.push((d.options.length)+' geänderte Design-Einstellung'+(d.options.length===1?'':'en'));
    if((d.new_themes||[]).length)parts.push('neue Themes: '+d.new_themes.map(esc).join(', '));
    var h='<div class="sbx-row"><a class="btn-a" href="'+esc(link)+'" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Sandbox live ansehen</a>'
      +'<button class="btn-g" type="button" onclick="Sandbox.copy()"><i class="fas fa-copy"></i> Link kopieren</button>'
      +'<button class="btn-g" type="button" onclick="Sandbox.mode(true,true)"><i class="fas fa-palette"></i> Themes in der Sandbox bearbeiten</button></div>'
      +'<div class="hint" style="margin:8px 0">Geheimer Link (nicht indexierbar, ohne Schreibzugriff): <code>'+esc(link)+'</code></div>'
      +'<div class="sbx-diff '+(d.changed?'chg':'')+'">'+(d.changed?'<b>Unterschiede zur Live-Seite:</b> '+parts.join(' · '):'Die Sandbox entspricht der Live-Seite.')+'</div>'
      +'<div class="sbx-row"><button class="btn-a" type="button" '+(d.changed?'':'disabled')+' onclick="Sandbox.publish()"><i class="fas fa-rocket"></i> Live stellen</button>'
      +'<button class="btn-g" type="button" onclick="Sandbox.act(\'wp_sandbox_reset\',\'Sandbox auf den aktuellen Live-Stand zurücksetzen? Alle Änderungen und Themes der Sandbox gehen verloren.\')"><i class="fas fa-rotate-left"></i> Auf Live-Stand zurücksetzen</button>'
      +'<button class="btn-g" type="button" onclick="Sandbox.act(\'wp_sandbox_rotate\',\'Neuen Link erzeugen? Der alte Link funktioniert danach nicht mehr.\')"><i class="fas fa-key"></i> Link erneuern</button>'
      +'<button class="btn-g" type="button" onclick="Sandbox.act(\'wp_sandbox_delete\',\'Sandbox endgültig löschen? Die Live-Seite bleibt unverändert.\')"><i class="fas fa-trash"></i> Löschen</button></div>';
    if(st.last_publish)h+='<div class="hint">Zuletzt live gestellt: '+esc(when(st.last_publish))+'</div>';
    if((st.backups||[]).length)h+='<details class="sbx-bk"><summary>Sicherungen (zum Zurückrollen)</summary>'+st.backups.map(function(b){return '<div class="sbx-bkrow"><span>'+esc(when(b.created))+' · '+b.options+' Einstellungen</span><button class="btn-g" type="button" onclick="Sandbox.rollback(\''+esc(b.id)+'\')">Live-Stand wiederherstellen</button></div>'}).join('')+'</details>';
    box.innerHTML=h;
  }
  async function load(force){
    if(loaded&&!force)return;loaded=true;
    try{st=await call('wp_sandbox');draw()}catch(e){loaded=false;var b=$('sbxBody');if(b)b.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  async function act(action,ask,body){
    if(ask&&!confirm(ask))return;
    try{st=await call(action,body);draw();toast('Erledigt ✓');if(!st.exists&&sbxMode)mode(false);else if(sbxMode&&window.WpThemes)WpThemes.setSandbox(true)}catch(e){toast(e.message,true)}
  }
  async function publish(){
    var d=st&&st.diff;if(!d||!d.changed)return;
    var msg='Sandbox jetzt live stellen?\n\nDas wird auf der Live-Seite übernommen:\n'
      +(d.sandbox_front!==d.live_front||d.sandbox_theme!==d.live_theme?'• Auslieferung: '+(d.sandbox_front?(d.sandbox_theme||'WordPress-Theme'):'Portal-Design')+'\n':'')
      +((d.options||[]).length?'• '+d.options.length+' Design-Einstellungen (Anpassungen, Widgets, Link-Struktur …)\n':'')
      +((d.new_themes||[]).length?'• neue Themes: '+d.new_themes.join(', ')+'\n':'')
      +'\nVorher wird eine Sicherung angelegt – du kannst jederzeit zurückrollen.';
    if(!confirm(msg))return;
    try{st=await call('wp_sandbox_publish');draw();toast('Die Sandbox ist jetzt live ✓');if(window.WpThemes)WpThemes.setSandbox(sbxMode)}catch(e){toast(e.message,true)}
  }
  function rollback(id){act('wp_sandbox_rollback','Den Live-Stand von vor dieser Veröffentlichung wiederherstellen? Neu hinzugekommene Themes bleiben installiert.',{backup:id}).then(function(){if(window.WpThemes)WpThemes.setSandbox(sbxMode)})}
  function mode(on,scroll){
    sbxMode=!!on&&!!(st&&st.exists);
    document.querySelectorAll('#sbxSwitch button').forEach(function(b){b.classList.toggle('on',(b.dataset.m==='sbx')===sbxMode)});
    var ban=$('sbxBanner');if(ban)ban.hidden=!sbxMode;
    var wp=$('dgWp');if(wp)wp.classList.toggle('sbx-on',sbxMode);
    if(window.WpThemes)WpThemes.setSandbox(sbxMode);
    if(scroll&&wp)wp.scrollIntoView({behavior:'smooth',block:'start'});
  }
  function copy(){
    if(!st||!st.url)return;var url=location.origin+st.url;
    (navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(url):Promise.reject()).then(function(){toast('Link kopiert ✓')},function(){prompt('Link kopieren:',url)});
  }
  window.Sandbox={load:load,act:act,publish:publish,rollback:rollback,mode:mode,copy:copy,refresh:function(){return load(true)}};
})();
