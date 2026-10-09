/* Alle Inhalte (CMS → Inhalte): CMS-Beiträge, CMS-Seiten und WordPress-Seiten in einer Liste, je Quelle mit passendem Editor.
   Server: wp_content_list (scope=all), wp_bridge_set, wp_session_open in cms/api.php; Liste und Schreibbrücke in cms/wp/core/cms-write.php. */
(function(){
  'use strict';
  var items=[],bridge=[],parts=[],elementorOn=false;
  var PART_LABEL={options:'Einstellungen (Titel, Untertitel, Admin-E-Mail)',posts:'Beiträge',pages:'Seiten',media:'Medien',menus:'Menüs'};
  var STATUS={publish:'Veröffentlicht',draft:'Entwurf',future:'Geplant',trash:'Papierkorb','private':'Privat',pending:'Zur Überprüfung'};
  var SOURCE={'cms-news':'CMS · Beitrag','cms-page':'CMS · Seite',wp:'WordPress-Datenbank'};
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||''}
  function note(t,bad){var el=$('ctMsg');if(!el)return;el.textContent=t||'';el.style.color=bad?'var(--bad)':'var(--muted)'}
  async function call(action,q,body){
    var o={headers:{'X-ElvadoPress-Token':token()}};
    if(body!==undefined){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
    var r=await fetch('api.php?action='+action+(q||'')+'&_='+Date.now(),o),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
  }
  async function load(){
    var box=$('ctList');if(box)box.innerHTML='<div class="hint">Lade …</div>';
    try{var d=await call('wp_content_list','&scope=all');items=d.items||[];bridge=d.bridge||[];parts=d.bridge_parts||[];elementorOn=!!d.elementor;note('');render();drawBridge()}
    catch(e){if(box)box.innerHTML='';note(e.message||'Laden fehlgeschlagen',true)}
  }
  function render(){
    var host=$('ctList');if(!host)return;
    var q=(($('ctFilter')||{}).value||'').trim().toLowerCase(),src=($('ctSource')||{}).value||'',ty=($('ctType')||{}).value||'',st=($('ctStatus')||{}).value||'';
    var rows=items.filter(function(it){return (!q||String(it.title).toLowerCase().indexOf(q)>=0)&&(!src||it.source===src)&&(!ty||it.type===ty)&&(!st||it.status===st)});
    if(!rows.length){host.innerHTML='<div class="hint">'+(items.length?'Kein Eintrag passt zum Filter.':'Noch keine Inhalte.')+'</div>';return}
    host.innerHTML='<table class="tr-table"><thead><tr><th>Titel</th><th>Typ</th><th>Quelle</th><th>Status</th><th>Geändert</th><th></th></tr></thead><tbody>'+rows.map(function(it){
      var i=items.indexOf(it),edit='<button class="btn-g" onclick="ContentsManager.edit('+i+')"><i class="fas fa-pen"></i> '+(it.source==='wp'?'Bearbeiten':'Im CMS bearbeiten')+'</button>';
      if(it.source==='wp'&&it.elementor&&elementorOn)edit='<button class="btn-g" onclick="ContentsManager.edit('+i+',true)"><i class="fas fa-pen-ruler"></i> Mit Elementor bearbeiten</button> '+edit;
      else if(it.source==='wp'&&elementorOn)edit+=' <button class="btn-g" onclick="ContentsManager.edit('+i+',true)" title="Mit Elementor bearbeiten"><i class="fas fa-pen-ruler"></i></button>';
      return '<tr><td><b>'+esc(it.title||'(ohne Titel)')+'</b>'+(it.author?'<div class="hint">'+esc(it.author)+'</div>':'')+'</td><td>'+(it.type==='page'?'Seite':'Beitrag')+'</td><td>'+esc(SOURCE[it.source]||it.source)+(it.elementor?' · Elementor':'')+'</td><td>'+esc(STATUS[it.status]||it.status)+'</td><td>'+esc(String(it.modified||'').slice(0,16))+'</td><td style="text-align:right;white-space:nowrap">'+edit+(it.url&&it.status==='publish'?' <a class="btn-g" href="'+esc(it.url)+'" target="_blank" rel="noopener" title="Ansehen"><i class="fas fa-arrow-up-right-from-square"></i></a>':'')+'</td></tr>';
    }).join('')+'</tbody></table>';
  }
  /* Der Tab wird sofort geöffnet (Popup-Schutz) und erst nach der Antwort des Servers weitergeleitet. */
  async function openWp(to){
    var w=window.open('','_blank');
    try{var r=await call('wp_session_open','',{to:to});if(w)w.location.href=r.url;else location.href=r.url}
    catch(e){if(w)w.close();note(e.message,true)}
  }
  function edit(i,elementor){
    var it=items[i];if(!it)return;
    if(it.source==='cms-news'){cmsGoto('news');setTimeout(function(){if(window.NewsMagazine)NewsMagazine.edit(it.id)},250)}
    else if(it.source==='cms-page'){cmsGoto('pages');setTimeout(function(){if(typeof selectPage==='function')selectPage(it.cms_id)},100)}
    else openWp('post.php?post='+encodeURIComponent(it.id)+'&action='+(elementor?'elementor':'edit'));
  }
  function drawBridge(){
    var box=$('ctBridgeBox');if(!box)return;
    box.innerHTML=parts.map(function(p){return '<label class="hint"><input type="checkbox" class="ct-bridge" value="'+esc(p)+'" '+(bridge.indexOf(p)>=0?'checked':'')+'> '+esc(PART_LABEL[p]||p)+'</label>'}).join('');
    var s=$('ctBridgeState');if(s)s.textContent=bridge.length?'· an: '+bridge.map(function(p){return PART_LABEL[p]?PART_LABEL[p].split(' (')[0]:p}).join(', '):'· aus';
  }
  async function saveBridge(){
    var sel=[].slice.call(document.querySelectorAll('.ct-bridge:checked')).map(function(x){return x.value});
    try{var d=await call('wp_bridge_set','',{parts:sel});bridge=d.bridge||[];drawBridge();note('Schreibbrücke gespeichert.');load()}
    catch(e){note(e.message||'Speichern fehlgeschlagen',true)}
  }
  window.ContentsManager={load:load,render:render,edit:edit,saveBridge:saveBridge};
})();
