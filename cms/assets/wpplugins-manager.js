/* WordPress-Plugins (CMS → Plugins): installieren, suchen, aktivieren. Server: wp_* in cms/api.php, cms/wp/. */
(function(){
  'use strict';
  var plugins=[],items=[],page=1,pages=1,pmap={},pmapOk=false;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||''}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  async function get(action,q){var r=await fetch('api.php?action='+action+(q||'')+'&_='+Date.now(),{headers:{'X-ElvadoPress-Token':token()}}),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d}
  async function load(){
    bind();autoLoad();
    try{var d=await get('wp_plugins');plugins=d.plugins||[];drawInst();loadMenu()}catch(e){$('wpInst').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  /* Einstellungs- und Verwaltungsseiten je Plugin (wie die Menüs im WordPress-Admin) */
  async function loadMenu(){
    if(!plugins.some(function(p){return p.active})){pmap={};pmapOk=true;drawInst();return}
    try{
      var d=await get('wp_admin_menu');menu=d.groups||[];pmap={};
      menu.forEach(function(g){if(g.label==='options.php')return;g.items.forEach(function(it){if(!it.plugin)return;(pmap[it.plugin]=pmap[it.plugin]||[])[it.top?'unshift':'push']({slug:it.slug,title:it.title})})});
    }catch(e){pmap={}}
    pmapOk=true;drawInst();
  }
  function pageRow(p){
    if(!p.active)return '';var key=String(p.file).split('/')[0],list=pmap[key]||[];
    if(!pmapOk)return '<div class="hint" style="margin-top:6px">Einstellungen werden geladen …</div>';
    if(!list.length)return '';
    var seen={},btns=list.filter(function(x){if(seen[x.slug])return false;seen[x.slug]=1;return true}).slice(0,10).map(function(x,i){return '<button type="button" class="btn-g" style="padding:4px 10px;font-size:.78rem" onclick="WpPlugins.openPlugin(\''+esc(x.slug).replace(/\\/g,'\\\\')+'\')">'+(i===0?'<i class="fas fa-gear"></i> ':'')+esc(x.title)+'</button>'}).join('');
    return '<div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px;align-items:center"><span class="hint">Einstellungen &amp; Verwaltung:</span>'+btns+(list.length>10?'<span class="hint">… und '+(list.length-10)+' weitere unter „Plugin-Seiten“</span>':'')+'</div>';
  }
  function openPlugin(slug){tab('pages');var w=$('wpPages');if(w&&w.scrollIntoView)w.scrollIntoView({behavior:'smooth',block:'start'});openPage(slug,'GET','','')}
  function tab(t){
    $('wpInst').style.display=t==='inst'?'':'none';if($('plInst'))$('plInst').style.display=t==='inst'?'':'none';if($('wpHead'))$('wpHead').style.display=t==='inst'?'':'none';$('wpNew').style.display=t==='new'?'':'none';$('wpPages').style.display=t==='pages'?'':'none';$('wpContent').style.display=t==='content'?'':'none';
    $('wpTabContent').classList.toggle('on',t==='content');
    $('wpTabInst').classList.toggle('on',t==='inst');$('wpTabNew').classList.toggle('on',t==='new');$('wpTabPages').classList.toggle('on',t==='pages');
    if(t==='new'&&!items.length)search(1);
    if(t==='pages')pagesLoad();
    if(t==='content')contentLoad();
  }
  function drawInst(){
    $('wpInstCount').textContent='('+plugins.length+')';
    $('wpInst').innerHTML=plugins.length?plugins.map(function(p,i){
      return '<div style="border:1px solid var(--line);border-radius:12px;padding:11px 13px;margin-bottom:8px"><div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><b>'+esc(p.name)+'</b> <span class="hint">v'+esc(p.version)+(p.author?' · '+esc(p.author):'')+'</span>'+(p.active?' <span style="color:var(--good)">aktiv</span>':'')+autoBox('plugin',(p.file||'').split('/')[0],(p.file||'').indexOf('/')>0)+'</div><div style="display:flex;gap:6px">'
        +(p.active?'<button class="btn-g" onclick="WpPlugins.act('+i+',\'deactivate\')">Deaktivieren</button>':'<button class="btn-a" onclick="WpPlugins.act('+i+',\'activate\')">Aktivieren</button><button class="btn-g" style="color:var(--bad)" onclick="WpPlugins.act('+i+',\'delete\')" title="Löschen"><i class="fas fa-trash"></i></button>')
        +'</div></div><div class="hint" style="margin-top:4px">'+esc(p.description)+'</div>'+pageRow(p)+(p.error?'<div class="hint" style="color:var(--bad);margin-top:4px"><i class="fas fa-triangle-exclamation"></i> Zuletzt deaktiviert wegen: '+esc(p.error)+'</div>':'')+'</div>';
    }).join(''):'<div class="hint">Noch keine WordPress-Plugins installiert. Wechsle zu „Neu hinzufügen“ oder lade ein ZIP hoch.</div>';
  }
  async function act(i,what){
    var p=plugins[i];if(!p)return;
    if(what==='delete'&&!confirm('Plugin „'+p.name+'“ endgültig löschen?'))return;
    try{var r=await post('wp_plugin_'+what,{file:p.file});plugins=r.plugins||plugins;pmapOk=false;drawInst();window.WpNotices&&WpNotices.refresh();if(items.length)drawResults();if(what==='activate')toast('Aktiviert – die Einstellungen des Plugins erscheinen gleich an seiner Karte.');loadMenu()}catch(e){toast(e.message,true);load()}
  }
  async function post(action,body){
    var r=await fetch('api.php?action='+action+'&_='+Date.now(),{method:'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':token()},body:JSON.stringify(body||{})}),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
  }
  async function search(p){
    page=p||1;var box=$('wpResults');box.innerHTML='<div class="hint">Suche läuft …</div>';
    try{var d=await get('wp_plugin_search','&q='+encodeURIComponent($('wpQuery').value.trim())+'&page='+page);items=d.items||[];pages=d.pages||1;drawResults()}
    catch(e){box.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  function stars(r){return r==null?'':' · ★ '+esc(r)}
  /* Installationsstand eines Suchtreffers: nicht installiert / installiert (inaktiv, mit Aktivieren) / aktiv */
  function findPlugin(slug){for(var k=0;k<plugins.length;k++){if(String(plugins[k].file).split('/')[0]===slug)return k}return -1}
  function stateBtn(it,i){
    if(!it.installed)return '<button class="btn-a" onclick="WpPlugins.install('+i+',this)"><i class="fas fa-download"></i> Installieren</button>';
    var k=findPlugin(it.slug),p=k>=0?plugins[k]:null;
    if(p&&p.active)return '<span class="hint" style="color:var(--ok,#34d399)"><i class="fas fa-circle-check"></i> Installiert und aktiv</span>';
    return '<span class="hint" style="color:var(--ok,#34d399)"><i class="fas fa-circle-check"></i> Installiert</span>'+(p?'<div style="margin-top:6px"><button class="btn-g" onclick="WpPlugins.act('+k+',\'activate\')">Aktivieren</button></div>':'');
  }
  function drawResults(){
    $('wpResults').innerHTML=items.length?items.map(function(it,i){
      return '<div style="display:flex;gap:12px;border:1px solid var(--line);border-radius:12px;padding:11px 13px;margin-bottom:8px">'+(it.icon?'<img src="'+esc(it.icon)+'" alt="" width="56" height="56" loading="lazy" referrerpolicy="no-referrer" style="border-radius:10px;flex:none">':'')
        +'<div style="flex:1;min-width:0"><b>'+esc(it.name)+'</b> <span class="hint">v'+esc(it.version)+' · '+esc(it.author)+stars(it.rating)+(it.installs?' · '+esc(it.installs.toLocaleString('de-DE'))+'+ Installationen':'')+'</span><div class="hint" style="margin-top:3px">'+esc(it.description)+'</div><div class="hint" style="margin-top:3px;font-size:.74rem">'+(it.tested?'Getestet bis WordPress '+esc(it.tested):'')+(it.requires_php?' · PHP ab '+esc(it.requires_php):'')+'</div></div>'
        +'<div style="flex:none;text-align:right">'+stateBtn(it,i)+'</div></div>';
    }).join(''):'<div class="hint">Keine Plugins gefunden.</div>';
    $('wpPager').innerHTML=pages>1?'<button class="btn-g" '+(page<=1?'disabled':'')+' onclick="WpPlugins.search('+(page-1)+')">‹</button><span class="hint">Seite '+page+' / '+pages+'</span><button class="btn-g" '+(page>=pages?'disabled':'')+' onclick="WpPlugins.search('+(page+1)+')">›</button>':'';
  }
  async function install(i,btn){
    var it=items[i];if(!it)return;
    if(!confirm('Plugin „'+it.name+'“ installieren? Es ist fremder PHP-Code, der mit den Rechten des CMS läuft.'))return;
    if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Installiere …'}
    try{toast('„'+it.name+'“ wird heruntergeladen und installiert …');var r=await post('wp_plugin_install',{slug:it.slug});plugins=r.plugins||plugins;items[i].installed=true;drawResults();drawInst();toast('Installiert – jetzt unter „Installiert“ aktivieren.')}
    catch(e){toast(e.message,true);if(btn){btn.disabled=false;btn.innerHTML='<i class="fas fa-download"></i> Installieren'}}
  }
  async function upload(file){
    if(!file)return;var fd=new FormData();fd.append('file',file);
    try{var r=await fetch('api.php?action=wp_plugin_upload&_='+Date.now(),{method:'POST',headers:{'X-ElvadoPress-Token':token()},body:fd}),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
      if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');plugins=d.plugins||plugins;drawInst();tab('inst');toast('Plugin installiert.')}
    catch(e){toast(e.message,true)}
    $('wpUpload').value='';
  }
  function bind(){var u=$('wpUpload');if(u&&!u.dataset.bound){u.dataset.bound='1';u.addEventListener('change',function(){upload(u.files&&u.files[0])})}}
  /* ── Plugin-Seiten (iframe + Brücke) ── */
  var menu=[],cur='';
  async function coreStatus(){
    var el=$('wpCore');if(!el)return;
    try{var c=await get('wp_core_status');
      if(c.installed){el.style.display='none';return}
      el.style.display='block';el.innerHTML='Plugin-Seiten, die mit React gebaut sind (z. B. WooCommerce Admin, Yoast SEO), brauchen die WordPress-Kernressourcen (ca. 10 MB, einmalig von wordpress.org). <button class="btn-g" id="wpCoreBtn" style="margin-left:6px">Jetzt laden</button>';
      $('wpCoreBtn').onclick=async function(){var b=this;b.disabled=true;b.textContent='Lade …';try{var r=await post('wp_core_install',{});toast(r.message||'Installiert.');el.style.display='none';if(cur)openPage(cur,'GET','','')}catch(e){toast(e.message,true);b.disabled=false;b.textContent='Jetzt laden'}};
    }catch(e){el.style.display='none'}
  }
  async function pagesLoad(){
    coreStatus();
    try{var d=await get('wp_admin_menu');menu=d.groups||[]}catch(e){$('wpMenu').innerHTML='<span class="hint">'+esc(e.message)+'</span>';return}
    var grp={},order=[];
    menu.forEach(function(g){if(g.label==='options.php')return;g.items.forEach(function(it){var k=it.plugin||('~'+g.label);if(!grp[k]){var pl=plugins.filter(function(p){return String(p.file).split('/')[0]===it.plugin})[0];grp[k]={label:pl?pl.name:g.label,items:[]};order.push(k)}grp[k].items.push(it)})});
    $('wpMenu').innerHTML=order.length?order.map(function(k){return '<div style="width:100%;display:flex;gap:6px;flex-wrap:wrap;align-items:center"><b style="min-width:150px;font-size:.8rem">'+esc(grp[k].label)+'</b>'+grp[k].items.map(function(it){return '<button class="btn-g" data-pg="'+esc(it.slug)+'" style="padding:4px 10px;font-size:.78rem">'+esc(it.title)+'</button>'}).join('')+'</div>'}).join(''):'<span class="hint">Aktive Plugins ohne eigene Seiten. Aktiviere ein Plugin, um seine Einstellungen hier zu sehen.</span>';
    Array.prototype.forEach.call($('wpMenu').querySelectorAll('button[data-pg]'),function(b){b.onclick=function(){openPage(b.dataset.pg,'GET','','')}});
  }
  function qs(s){var q=String(s||'').split('#')[0],i=q.indexOf('?');if(i>=0)q=q.slice(i+1);else if(/^[a-z]+\.php$/i.test(q)||q==='')q='';var o={};q.split('&').forEach(function(p){if(!p)return;var kv=p.split('=');o[decodeURIComponent(kv[0]||'')]=decodeURIComponent((kv[1]||'').replace(/\+/g,' '))});return o}
  function enc(o){return Object.keys(o).map(function(k){return encodeURIComponent(k)+'='+encodeURIComponent(o[k])}).join('&')}
  async function openPage(slug,method,body,query,depth){
    depth=depth||0;if(depth>4)return;var fr=$('wpFrame');cur=slug;
    try{
      var d=await post('wp_admin_page',{page:slug,method:method,body:body,query:query});
      if(d.redirect){var q=qs(d.redirect),pg=q.page;delete q.page;if(pg){if(d.redirect&&q['settings-updated'])toast('Einstellungen gespeichert.');return openPage(pg,'GET','',enc(q),depth+1)}toast('Gespeichert.');return openPage(slug,'GET','','',depth+1)}
      fr.style.display='block';if(d.frame){fr.removeAttribute('srcdoc');fr.src=d.frame}else fr.srcdoc=d.html||'';if(d.notice&&!d.error)toast(d.notice);if(d.error&&d.notice)toast(d.notice,true);
    }catch(e){toast(e.message,true)}
  }
  window.addEventListener('message',async function(e){
    var fr=$('wpFrame');if(!fr||e.source!==fr.contentWindow)return;var m=e.data||{};
    if(m.rrw==='submit'){
      var aq=qs(m.action),b=qs('?'+m.body);var pg=aq.page||b.page||cur;
      if(m.method==='post'){delete aq.page;return openPage(pg,'POST',m.body,enc(aq))}
      return openPage(pg,'GET','',m.body);
    }
    if(m.rrw==='nav'){var q=qs(m.href),pg=q.page||cur;delete q.page;return openPage(pg,'GET','',enc(q))}
    if(m.rrw==='rest'){
      try{var d2=await post('wp_admin_rest',{path:m.path,method:m.method,headers:m.headers,body:m.body});var r2=d2.result||{};fr.contentWindow.postMessage({rrw:'res',id:m.id,status:r2.status,type:r2.type,text:r2.text,headers:r2.headers},'*')}
      catch(err){fr.contentWindow.postMessage({rrw:'res',id:m.id,status:500,type:'application/json',text:JSON.stringify({code:'rrw_bridge',message:String(err.message||err)})},'*')}
      return;
    }
    if(m.rrw==='ajax'){
      try{var d=await post('wp_admin_ajax',{url:m.url,method:m.method,body:m.body});var r=d.result||{};fr.contentWindow.postMessage({rrw:'res',id:m.id,status:r.status,type:r.type,text:r.text},'*')}
      catch(err){fr.contentWindow.postMessage({rrw:'res',id:m.id,status:500,type:'text/plain',text:String(err.message||err)},'*')}
    }
  });
  /* ── Updates ── */
  var upd=[];
  async function updates(){
    $('wpUpdState').textContent='Suche läuft …';
    try{var d=await get('wp_updates');upd=d.updates||[];drawUpd();$('wpUpdState').textContent=upd.length?'':'Alles aktuell.'}catch(e){$('wpUpdState').textContent=e.message}
  }
  function drawUpd(){
    $('wpUpd').innerHTML=upd.map(function(u,i){return '<div style="display:flex;justify-content:space-between;gap:10px;align-items:center;border:1px solid var(--line);border-radius:10px;padding:8px 12px;margin-bottom:6px"><div><b>'+esc(u.name)+'</b> <span class="hint">'+(u.type==='theme'?'Theme':'Plugin')+' · '+esc(u.current)+' → '+esc(u.latest)+'</span></div><button class="btn-a" onclick="WpPlugins.applyUpdate('+i+',this)">Aktualisieren</button></div>'}).join('');
  }
  async function applyUpdate(i,btn){
    var u=upd[i];if(!u||!confirm('„'+u.name+'“ auf Version '+u.latest+' aktualisieren? Vorher am besten ein Backup anlegen.'))return;
    btn.disabled=true;
    try{var d=await post('wp_update_apply',{type:u.type,slug:u.slug});upd=d.updates||[];drawUpd();toast('Aktualisiert.');load();if(window.WpThemes)WpThemes.load()}
    catch(e){toast(e.message,true);btn.disabled=false}
  }
  /* ── Automatische Updates ── */
  var au=null;
  function autoBox(type,slug,ok){
    if(!au||!ok||!slug||au[type==='plugin'?'plugins':'themes']!=='selected')return '';
    var on=(au.selected[type]||[]).indexOf(slug)>=0;
    return ' <label class="hint" style="margin-left:8px;white-space:nowrap"><input type="checkbox" '+(on?'checked ':'')+'onchange="WpPlugins.autoToggle(\''+type+'\',\''+esc(slug)+'\',this.checked)"> Auto-Update</label>';
  }
  async function autoLoad(){
    try{var d=await get('wp_autoupdate_get');au=d.settings;autoFill();verLoad();
      // Ohne Cron-Job: fällige Läufe starten beim Öffnen des Bereichs
      if(d.due)autoTick();
    }catch(e){}
  }
  async function autoTick(){
    try{var d=await post('wp_autoupdate_tick',{});au=d.settings;autoFill();if(d.result&&d.result.ran&&(d.result.updated||d.result.failed)){toast(d.result.updated+' aktualisiert'+(d.result.failed?', '+d.result.failed+' fehlgeschlagen':'')+'.');plugins.length&&load()}}catch(e){}
  }
  function autoFill(){
    if(!au||!$('wpAuP'))return;
    $('wpAuP').value=au.plugins;$('wpAuT').value=au.themes;$('wpAuI').value=String(au.interval_h);$('wpAuL').checked=!!au.translations;$('wpAuW').value=au.wp_core||'off';$('wpAuC').checked=!!au.core_assets;
    var on=au.plugins!=='off'||au.themes!=='off'||au.translations||au.core_assets||(au.wp_core&&au.wp_core!=='off');
    $('wpAutoState').textContent=on?'· an'+(au.last_run?' · letzter Lauf '+new Date(au.last_run*1000).toLocaleString('de-DE'):''):'· aus';
    var nm={plugin:'Plugin',theme:'Theme',translations:'Sprachpakete',core:'Kernressourcen',wordpress:'WordPress-Version',error:'Fehler'};
    $('wpAuLog').innerHTML=(au.log||[]).slice().reverse().slice(0,12).map(function(e){
      return '<div class="hint" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;border-top:1px solid var(--line);padding:5px 0"><span>'+new Date(e.t*1000).toLocaleString('de-DE')+'</span><span style="color:'+(e.ok?'var(--good)':'var(--bad)')+'">'+(e.ok?'✓':'✗')+'</span><span>'+esc(nm[e.type]||e.type)+(e.slug?' „'+esc(e.slug)+'“':'')+(e.from?' '+esc(e.from)+' → '+esc(e.to):'')+(e.msg?' – '+esc(e.msg):'')+'</span>'
        +((e.ok&&(e.type==='plugin'||e.type==='theme'))?'<button class="btn-g" style="padding:2px 8px" onclick="WpPlugins.autoRollback(\''+e.type+'\',\''+esc(e.slug)+'\')">Zurücksetzen</button>':'')+'</div>';
    }).join('');
    if(plugins.length)drawInst();
    if(window.WpThemes&&WpThemes.redraw)WpThemes.redraw();
  }
  async function verLoad(){
    try{var v=await get('wp_version_status');
      $('wpVerNow').textContent='· kompatibel mit WordPress '+v.current;
      var t=[];
      if(v.update)t.push('Neu bei wordpress.org: <b>'+esc(v.latest)+'</b>'+(v.minor?' (Fehlerkorrektur)':''));else t.push('Aktuell.');
      if(v.report)t.push('Bericht zu '+esc(v.report.version)+': '+v.report.missing+' von '+v.report.total+' Funktionen noch unbekannt'+(v.report.sample&&v.report.sample.length?' (z. B. '+v.report.sample.slice(0,4).map(esc).join(', ')+')':'')+'.');
      $('wpVerInfo').innerHTML=t.join(' · ');
      $('wpVerBtn').hidden=!v.update;$('wpVerRb').hidden=!v.can_rollback;if(v.can_rollback)$('wpVerRb').title='Zurück zu '+v.previous;
    }catch(e){$('wpVerInfo').textContent=''}
  }
  async function verUpdate(btn){
    if(!confirm('Neue WordPress-Version übernehmen? Die Kernressourcen werden neu geladen; die vorherige Version bleibt zum Zurücksetzen erhalten.'))return;
    btn.disabled=true;
    try{var d=await post('wp_version_update',{});toast(d.message||'Übernommen.');verLoad();autoLoad()}catch(e){toast(e.message,true)}
    btn.disabled=false;
  }
  async function verRollback(btn){
    if(!confirm('Zur vorherigen WordPress-Version zurückkehren?'))return;
    btn.disabled=true;
    try{var d=await post('wp_version_rollback',{});toast(d.message||'Zurückgesetzt.');verLoad()}catch(e){toast(e.message,true)}
    btn.disabled=false;
  }
  async function autoSave(){
    var s={plugins:$('wpAuP').value,themes:$('wpAuT').value,interval_h:+$('wpAuI').value,translations:$('wpAuL').checked,core_assets:$('wpAuC').checked,wp_core:$('wpAuW').value,selected:au?au.selected:{plugin:[],theme:[]}};
    try{var d=await post('wp_autoupdate_set',{settings:s});au=d.settings;autoFill();toast('Gespeichert.')}catch(e){toast(e.message,true)}
  }
  async function autoToggle(type,slug,on){
    if(!au)return;var l=au.selected[type]||[];l=l.filter(function(x){return x!==slug});if(on)l.push(slug);au.selected[type]=l;
    try{var d=await post('wp_autoupdate_set',{settings:au});au=d.settings}catch(e){toast(e.message,true)}
  }
  async function autoRun(btn){
    btn.disabled=true;
    try{var d=await post('wp_autoupdate_run',{});au=d.settings;autoFill();var r=d.result||{};toast(r.ran?(r.updated+' aktualisiert'+(r.failed?', '+r.failed+' fehlgeschlagen':'')+'.'):'Ein anderer Lauf ist aktiv.');load();if(window.WpThemes)WpThemes.load()}
    catch(e){toast(e.message,true)}
    btn.disabled=false;
  }
  async function autoRollback(type,slug){
    if(!confirm('„'+slug+'“ auf die vorherige Fassung zurücksetzen?'))return;
    try{await post('wp_autoupdate_rollback',{type:type,slug:slug});toast('Zurückgesetzt.');load();if(window.WpThemes)WpThemes.load()}catch(e){toast(e.message,true)}
  }
  /* ── Seiten & Editor (Elementor im eigenen Tab) ── */
  var contentItems=[],elementorOn=false;
  async function contentLoad(){
    var box=$('wpContentList');box.innerHTML='<div class="hint">Lade …</div>';
    try{var d=await get('wp_content_list');contentItems=d.items||[];elementorOn=!!d.elementor;drawContent()}
    catch(e){box.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  function drawContent(){
    var box=$('wpContentList');
    if(!contentItems.length){box.innerHTML='<div class="hint">Noch keine WordPress-Seiten. Lege oben eine neue Seite an.</div>';return}
    var st={publish:'Veröffentlicht',draft:'Entwurf','private':'Privat'};
    box.innerHTML=contentItems.map(function(it,i){
      return '<div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 0;border-top:1px solid var(--line)"><div style="flex:1;min-width:180px"><b>'+esc(it.title||'(ohne Titel)')+'</b><div class="hint">'+(it.type==='page'?'Seite':'Beitrag')+' · '+esc(st[it.status]||it.status)+(it.elementor?' · Elementor':'')+'</div></div>'
        +(elementorOn?'<button class="btn-g" onclick="WpPlugins.editElementor('+i+')"><i class="fas fa-pen-ruler"></i> Mit Elementor bearbeiten</button>':'')+'</div>';
    }).join('')+(elementorOn?'':'<div class="hint" style="margin-top:8px">Aktiviere das Plugin Elementor, um Seiten visuell zu bearbeiten.</div>');
  }
  /* Der Tab wird sofort geöffnet (Popup-Schutz) und erst nach der Antwort des Servers weitergeleitet. */
  async function openEditor(to){
    var w=window.open('','_blank');
    try{var r=await post('wp_session_open',{to:to});if(w)w.location.href=r.url;else location.href=r.url}
    catch(e){if(w)w.close();toast(e.message,true)}
  }
  function editElementor(i){var it=contentItems[i];if(it)openEditor('post.php?post='+encodeURIComponent(it.id)+'&action=elementor')}
  async function newPage(){
    var t=$('wpNewTitle'),title=t.value.trim();
    try{var r=await post('wp_page_create',{title:title});t.value='';await contentLoad();if(elementorOn)openEditor('post.php?post='+encodeURIComponent(r.id)+'&action=elementor');else toast('Seite angelegt.')}
    catch(e){toast(e.message,true)}
  }
  async function translations(btn){
    btn.disabled=true;$('wpUpdState').textContent='Lade Sprachpakete …';
    try{var d=await post('wp_translations',{});$('wpUpdState').textContent='';toast(d.ok+' Sprachpaket(e) geladen'+(d.fail?', '+d.fail+' ohne deutsches Paket':'')+'.')}
    catch(e){$('wpUpdState').textContent='';toast(e.message,true)}
    btn.disabled=false;
  }
  window.WpPlugins={openPlugin:openPlugin,verUpdate:verUpdate,verRollback:verRollback,autoLoad:autoLoad,autoSave:autoSave,autoRun:autoRun,autoToggle:autoToggle,autoRollback:autoRollback,autoBox:autoBox,editElementor:editElementor,newPage:newPage,translations:translations,load:load,tab:tab,act:act,search:search,install:install,updates:updates,applyUpdate:applyUpdate};
})();
