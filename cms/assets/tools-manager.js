/* Werkzeuge: Wartungsmodus und Weiterleitungen/404-Protokoll (nur Administratoren). Server: cms/lib/tools.php */
(function(){
  'use strict';
  var rules=[],maint=null;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function note(id,t,bad){var el=$(id);if(!el)return;el.textContent=t||'';el.style.color=bad?'var(--bad)':'var(--muted)'}
  function link(){return location.origin+'/?vorschau='+encodeURIComponent((maint&&maint.key)||'')}

  /* Wartungsmodus */
  function renderMaint(){
    if(!maint||!$('tmEnabled'))return;
    $('tmEnabled').checked=!!maint.enabled;$('tmTitle').value=maint.title||'';$('tmText').value=maint.text||'';$('tmEta').value=maint.eta||'';
    var st=$('tmStatus');if(st){st.className='publish-state '+(maint.enabled?'bad':'');st.innerHTML=maint.enabled?'<i class="fas fa-triangle-exclamation"></i> Wartungsmodus AKTIV':'<i class="fas fa-circle-check"></i> Website normal erreichbar'}
    $('tmLink').value=link();$('tmOpen').href=link();
  }
  async function loadMaint(){
    try{var d=await cmsApi('maintenance_get');maint=d.config;renderMaint()}catch(e){note('tmMsg',e.message||'Laden fehlgeschlagen',true)}
  }
  async function saveMaint(newKey){
    var cfg={enabled:$('tmEnabled').checked,title:$('tmTitle').value,text:$('tmText').value,eta:$('tmEta').value,key:maint&&maint.key};
    if(cfg.enabled&&!(maint&&maint.enabled)&&!confirm('Wartungsmodus einschalten? Besucher sehen dann nur noch die Wartungsseite.'))return;
    try{var d=await cmsApi('maintenance_save',{config:cfg,new_key:!!newKey});maint=d.config;renderMaint();note('tmMsg','Gespeichert.')}catch(e){note('tmMsg',e.message||'Speichern fehlgeschlagen',true)}
  }
  function copyLink(){var el=$('tmLink');if(!el)return;el.select();try{navigator.clipboard.writeText(el.value);note('tmMsg','Link kopiert.')}catch(e){document.execCommand&&document.execCommand('copy')}}
  function newKey(){if(confirm('Neuen Schlüssel erzeugen? Der bisherige Vorschau-Link funktioniert dann nicht mehr.'))saveMaint(true)}

  /* Weiterleitungen */
  function renderRules(){
    var host=$('trList');if(!host)return;
    host.innerHTML=rules.length?'<table class="tr-table"><thead><tr><th>Von</th><th>Nach</th><th>Code</th><th></th></tr></thead><tbody>'+rules.map(function(r,i){
      return '<tr><td><code>'+esc(r.from)+'</code></td><td>'+(r.code===410?'<span class="hint">— entfernt —</span>':'<code>'+esc(r.to)+'</code>')+'</td><td>'+r.code+'</td><td style="text-align:right"><button class="btn-g" title="Entfernen" onclick="ToolsManager.delRule('+i+')"><i class="fas fa-trash"></i></button></td></tr>';
    }).join('')+'</tbody></table>':'<div class="hint">Noch keine Weiterleitungen angelegt.</div>';
  }
  function renderLog(items){
    var host=$('trLog');if(!host)return;
    host.innerHTML=items&&items.length?'<table class="tr-table"><thead><tr><th>Adresse</th><th>Aufrufe</th><th>Zuletzt</th><th></th></tr></thead><tbody>'+items.slice().sort(function(a,b){return (b.count||0)-(a.count||0)}).map(function(it){
      return '<tr><td><code>'+esc(it.path)+'</code>'+(it.ref?'<div class="hint" style="font-size:.66rem">von '+esc(it.ref)+'</div>':'')+'</td><td>'+(it.count||0)+'</td><td>'+esc(it.last||'')+'</td><td style="text-align:right"><button class="btn-g" data-path="'+esc(it.path)+'" onclick="ToolsManager.fromLog(this.dataset.path)"><i class="fas fa-route"></i> Weiterleiten</button></td></tr>';
    }).join('')+'</tbody></table>':'<div class="hint">Keine fehlenden Adressen protokolliert.</div>';
  }
  async function loadRules(){
    try{var d=await cmsApi('redirects_get');rules=d.rules||[];renderRules();renderLog(d.log)}catch(e){note('trMsg',e.message||'Laden fehlgeschlagen',true)}
  }
  function addRule(){
    var from=$('trFrom').value.trim(),to=$('trTo').value.trim(),code=parseInt($('trCode').value,10)||301;
    if(!from||from[0]!=='/'){note('trMsg','„Von“ muss mit / beginnen.',true);return}
    if(code!==410&&!(to[0]==='/'||/^https?:\/\//i.test(to))){note('trMsg','„Nach“ muss mit / oder https:// beginnen.',true);return}
    rules.push({from:from,to:code===410?'':to,code:code});$('trFrom').value='';$('trTo').value='';renderRules();note('trMsg','Noch nicht gespeichert.')
  }
  function delRule(i){rules.splice(i,1);renderRules();note('trMsg','Noch nicht gespeichert.')}
  async function saveRules(){
    try{var d=await cmsApi('redirects_save',{rules:rules});var dropped=rules.length-(d.rules||[]).length;rules=d.rules||[];renderRules();note('trMsg',dropped>0?'Gespeichert. '+dropped+' ungültige/doppelte Regel(n) wurden verworfen.':'Gespeichert.')}catch(e){note('trMsg',e.message||'Speichern fehlgeschlagen',true)}
  }
  function fromLog(p){$('trFrom').value=p;$('trTo').focus();window.scrollTo({top:0,behavior:'smooth'})}
  async function clearLog(){if(!confirm('404-Protokoll leeren?'))return;try{await cmsApi('redirects_log_clear',{});renderLog([])}catch(e){note('trMsg',e.message||'Fehlgeschlagen',true)}}


  /* Datenschutz */
  var found=null;
  function stat(label,v){return '<div class="stat"><div class="l">'+esc(label)+'</div><div class="v">'+esc(v)+'</div></div>'}
  async function loadPrivacy(){
    try{var o=(await cmsApi('privacy_overview')).overview;$('tpOverview').innerHTML=stat('Kommentare',o.comments+(o.comments_pending?' ('+o.comments_pending+' offen)':''))+stat('Redaktions-Konten',o.users)+stat('Aktivitätslog',o.activity+' / 500')+stat('Benachrichtigungen',o.notifications+' / 200')+stat('404-Protokoll',o.not_found_log+' / 300')}catch(e){note('tpMsg',e.message||'Laden fehlgeschlagen',true)}
  }
  async function privacySearch(){
    var q=$('tpQuery').value.trim();if(q.length<2){note('tpMsg','Bitte mindestens 2 Zeichen eingeben.',true);return}
    note('tpMsg','Suche läuft…');
    try{found=await cmsApi('privacy_search',{q:q});note('tpMsg','');renderPrivacy()}catch(e){note('tpMsg',e.message||'Suche fehlgeschlagen',true)}
  }
  function renderPrivacy(){
    var f=found,host=$('tpResult');if(!f||!host)return;
    var h='<div class="card"><div class="th"><div class="tt"><i class="fas fa-comments"></i>Kommentare ('+f.comments.length+')</div>'+(f.comments.length?'<div style="display:flex;gap:8px"><button class="btn-g" onclick="ToolsManager.privacyExport()"><i class="fas fa-download"></i> Alles als JSON</button><button class="btn-g" onclick="ToolsManager.privacyDelete()"><i class="fas fa-trash"></i> Markierte löschen</button></div>':'')+'</div>';
    h+=f.comments.length?'<table class="tr-table"><thead><tr><th></th><th>Name &amp; Text</th><th>Beitrag</th><th>Status</th><th>Datum</th></tr></thead><tbody>'+f.comments.map(function(c){
      return '<tr><td><input type="checkbox" class="tp-ck" value="'+c.id+'"'+(c.is_staff?' disabled title="Redaktions-Antwort"':'')+'></td><td><b>'+esc(c.name)+'</b><div class="hint" style="font-size:.74rem">'+esc(c.text)+'</div></td><td>'+esc(c.article_title||('#'+c.article_id))+'</td><td>'+esc(c.status)+'</td><td>'+esc(c.created_at)+'</td></tr>';
    }).join('')+'</tbody></table><div class="hint" style="margin-top:8px">Beim Löschen eines Kommentars werden Antworten darauf mit entfernt.</div>':'<div class="hint">Keine Kommentare gefunden.</div>';
    h+='</div><div class="card"><div class="th"><div class="tt"><i class="fas fa-inbox"></i>Einsendungen aus Formularen ('+(f.forms||[]).length+')</div>'+((f.forms||[]).length?'<button class="btn-g" onclick="ToolsManager.privacyDeleteForms()"><i class="fas fa-trash"></i> Markierte löschen</button>':'')+'</div>';
    h+=(f.forms||[]).length?'<table class="tr-table"><thead><tr><th></th><th>Art</th><th>Name / E-Mail</th><th>Text</th><th>Datum</th></tr></thead><tbody>'+f.forms.map(function(x){return '<tr><td><input type="checkbox" class="tp-fk" value="'+esc(x.id)+'"></td><td>'+(x.kind==='newsletter'?'Newsletter':'Kontakt')+'</td><td><b>'+esc(x.name)+'</b><div class="hint" style="font-size:.74rem">'+esc(x.email)+'</div></td><td>'+esc(x.text)+'</td><td>'+esc(x.created_at)+'</td></tr>'}).join('')+'</tbody></table>':'<div class="hint">Keine Einsendungen gefunden.</div>';
    h+='</div><div class="card"><div class="tt"><i class="fas fa-users-gear"></i>Redaktions-Konten ('+f.users.length+')</div>';
    h+=f.users.length?'<table class="tr-table"><thead><tr><th>Benutzer</th><th>Name</th><th>E-Mail</th><th>Rolle</th></tr></thead><tbody>'+f.users.map(function(u){return '<tr><td>'+esc(u.username)+'</td><td>'+esc(u.display_name)+'</td><td>'+esc(u.email)+'</td><td>'+esc(u.role)+'</td></tr>'}).join('')+'</tbody></table><div class="hint" style="margin-top:8px">Konten verwaltest du unter Benutzer → Redakteure.</div>':'<div class="hint">Keine Konten gefunden.</div>';
    h+='</div><div class="card"><div class="tt"><i class="fas fa-clock-rotate-left"></i>Weitere Treffer</div><div class="hint" style="margin-top:6px">Aktivitätslog-Einträge: <b>'+f.activity_entries+'</b> (maximal die letzten 500, ältere fallen automatisch weg) · Beiträge mit diesem Autor: <b>'+f.articles.length+'</b></div></div>';
    host.innerHTML=h;
  }
  function privacyExport(){
    if(!found)return;var blob=new Blob([JSON.stringify({exportiert:new Date().toISOString(),suchbegriff:found.q,kommentare:found.comments,konten:found.users,aktivitaetslog_eintraege:found.activity_entries,beitraege:found.articles},null,2)],{type:'application/json'});
    var a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='datenauskunft.json';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove()},500);
  }
  async function privacyDelete(){
    var ids=[].slice.call(document.querySelectorAll('.tp-ck:checked')).map(function(x){return parseInt(x.value,10)});
    if(!ids.length){note('tpMsg','Bitte Kommentare markieren.',true);return}
    if(!confirm(ids.length+' Kommentar(e) endgültig löschen? Antworten darauf werden mit entfernt.'))return;
    try{var d=await cmsApi('privacy_comments_delete',{ids:ids});note('tpMsg',d.removed+' Kommentar(e) gelöscht.');loadPrivacy();privacySearch()}catch(e){note('tpMsg',e.message||'Löschen fehlgeschlagen',true)}
  }


  async function privacyDeleteForms(){
    var ids=[].slice.call(document.querySelectorAll('.tp-fk:checked')).map(function(x){return x.value});
    if(!ids.length){note('tpMsg','Bitte Einsendungen markieren.',true);return}
    if(!confirm(ids.length+' Einsendung(en) endgültig löschen?'))return;
    try{var d=await cmsApi('forms_update',{ids:ids,op:'delete'});note('tpMsg',d.changed+' Einsendung(en) gelöscht.');loadPrivacy();privacySearch()}catch(e){note('tpMsg',e.message||'Löschen fehlgeschlagen',true)}
  }
  window.ToolsManager={privacyDeleteForms:privacyDeleteForms,loadPrivacy:loadPrivacy,privacySearch:privacySearch,privacyExport:privacyExport,privacyDelete:privacyDelete,loadMaint:loadMaint,saveMaint:function(){return saveMaint(false)},copyLink:copyLink,newKey:newKey,loadRules:loadRules,addRule:addRule,delRule:delRule,saveRules:saveRules,fromLog:fromLog,clearLog:clearLog};
})();
