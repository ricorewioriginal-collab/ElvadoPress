/* Bildauswahl für das ganze CMS: Mediathek + freie Bilder (Pixabay, Pexels, Unsplash, Openverse, Wikimedia Commons).
   StockMedia.open({tab:'library'|'stock', onPick:function(item,info){…}}) – item = Mediathek-Eintrag (url, name, variants, credit …),
   info = {url, urlFor(breite), credit (Bildnachweis-Text), attribution (true = Namensnennung Pflicht), alt}.
   Server: stock_status / stock_search / stock_import / stock_config_save (cms/api.php), Logik in cms/lib/stockmedia.php. */
(function(){
  'use strict';
  var providers=null,admin=false,state=null;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function api(a,b){return window.cmsApi(a,b)}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,!!bad)}
  function token(){try{return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||''}catch(e){return ''}}
  function info(item,credit,attr){
    var vars=Array.isArray(item.variants)?item.variants:[],orig=(item.original&&item.original.url)||item.url||'';
    return {url:orig,credit:credit||(item.credit&&item.credit.text)||'',attribution:attr!=null?attr:!!(item.credit&&item.credit.attribution_required),alt:String(item.name||'').replace(/[-_]+/g,' ').replace(/\s+\d+$/,'').trim(),
      urlFor:function(w){if(!vars.length)return orig;var b=vars.slice().sort(function(a,c){return Math.abs(a.width-w)-Math.abs(c.width-w)})[0];return (b&&b.url)||orig}};
  }
  async function loadStatus(force){
    if(providers&&!force)return;
    try{var d=await api('stock_status');providers=d.providers||[];admin=!!d.admin}catch(e){providers=[]}
  }
  /* ───────── Chooser ───────── */
  function close(){var m=$('stockModal');if(m)m.remove();state=null}
  async function open(opts){
    opts=opts||{};await loadStatus(true);close();
    state={opts:opts,tab:opts.tab==='stock'?'stock':'library',prov:'',q:'',orient:'',page:1,res:null,busy:false,lib:null};
    var usable=providers.filter(function(p){return p.usable});state.prov=usable.length?usable[0].id:'';
    var m=document.createElement('div');m.id='stockModal';m.className='hb-picker';
    m.innerHTML='<div class="hb-picker-box" style="width:min(940px,96vw);max-height:88vh"><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="flex:1;min-width:140px">Bild auswählen</b>'
      +'<button type="button" class="btn-g" data-tab="library">Mediathek</button><button type="button" class="btn-g" data-tab="stock"><i class="fas fa-images"></i> Freie Bilder</button><button type="button" class="btn-g" id="stkX" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div><div id="stkBody" style="margin-top:12px"></div></div>';
    document.body.appendChild(m);
    m.addEventListener('click',function(e){
      if(e.target===m||e.target.closest('#stkX')){close();return}
      var t=e.target.closest('[data-tab]');if(t){state.tab=t.getAttribute('data-tab');draw();return}
      var l=e.target.closest('[data-lib]');if(l){var it=state.lib[+l.getAttribute('data-lib')];done(it,null);return}
      var s=e.target.closest('[data-stk]');if(s){take(s.getAttribute('data-stk'));return}
      var pg=e.target.closest('[data-page]');if(pg){state.page=Math.max(1,state.page+(+pg.getAttribute('data-page')));search();return}
      if(e.target.closest('#stkGo')){state.page=1;search();return}
      if(e.target.closest('#stkSetup')){close();if(window.cmsTab)cmsTab('media',document.querySelector('.tab[data-tab="media"]'));if(window.MediaHub)MediaHub.load();setTimeout(function(){var c=$('stockSettings');if(c)c.scrollIntoView({behavior:'smooth',block:'start'})},500)}
    });
    m.addEventListener('change',function(e){
      if(e.target.id==='stkProv'){state.prov=e.target.value;state.res=null;draw()}
      if(e.target.id==='stkOrient')state.orient=e.target.value;
      if(e.target.id==='stkUp'){upload(e.target.files&&e.target.files[0])}
    });
    m.addEventListener('keydown',function(e){if(e.target.id==='stkQ'&&e.key==='Enter'){state.q=e.target.value;state.page=1;search()}});
    m.addEventListener('input',function(e){if(e.target.id==='stkQ')state.q=e.target.value});
    draw();
  }
  function done(item,credit){var cb=state&&state.opts.onPick;var i=info(item,credit&&credit.text,credit&&credit.attr);close();if(cb)cb(item,i)}
  function draw(){
    var b=$('stkBody');if(!b)return;
    document.querySelectorAll('#stockModal [data-tab]').forEach(function(x){x.classList.toggle('btn-a',x.getAttribute('data-tab')===state.tab)});
    if(state.tab==='library'){drawLibrary();return}
    var usable=providers.filter(function(p){return p.usable});
    if(!usable.length){
      b.innerHTML='<div class="empty"><i class="fas fa-images"></i>Noch keine Bildquelle eingerichtet.<div class="hint" style="margin-top:6px">'+(admin?'Hinterlege kostenlose API-Schlüssel (Pixabay, Pexels, Unsplash) oder schalte Openverse/Wikimedia Commons ein – unter Medien → „Freie Bilder“.':'Bitte einen Administrator, unter Medien → „Freie Bilder“ eine Quelle einzurichten.')+'</div>'+(admin?'<p><button type="button" class="btn-a" id="stkSetup">Bildquellen einrichten</button></p>':'')+'</div>';return}
    var cur=providers.filter(function(p){return p.id===state.prov})[0]||usable[0];
    b.innerHTML='<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><select id="stkProv" class="fc">'+usable.map(function(p){return '<option value="'+p.id+'"'+(p.id===cur.id?' selected':'')+'>'+esc(p.name)+'</option>'}).join('')+'</select>'
      +'<input id="stkQ" class="fc" style="flex:1;min-width:180px" placeholder="Suchbegriff, z. B. Berge, Konzert, Büro …" value="'+esc(state.q)+'"><select id="stkOrient" class="fc"><option value="">Alle Formate</option><option value="landscape"'+(state.orient==='landscape'?' selected':'')+'>Querformat</option><option value="portrait"'+(state.orient==='portrait'?' selected':'')+'>Hochformat</option><option value="square"'+(state.orient==='square'?' selected':'')+'>Quadrat</option></select>'
      +'<button type="button" class="btn-a" id="stkGo"><i class="fas fa-magnifying-glass"></i> Suchen</button></div><div class="hint" style="margin:6px 0">'+esc(cur.license)+'. Ein Klick lädt das Bild in deine Mediathek (inkl. Bildnachweis).</div><div id="stkRes"></div>';
    results();
  }
  function results(){
    var r=$('stkRes');if(!r)return;
    if(state.busy){r.innerHTML='<div class="hint">Lade …</div>';return}
    if(!state.res){r.innerHTML='<div class="hint">Gib einen Suchbegriff ein und drücke Enter.</div>';return}
    var d=state.res;
    r.innerHTML=d.items.length?'<div class="hb-picker-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">'+d.items.map(function(it){
      return '<button type="button" data-stk="'+esc(it.id)+'" title="'+esc(it.title)+'"><img loading="lazy" src="'+esc(it.thumb)+'" alt="" style="height:100px"><span style="display:block;margin-top:3px">'+esc(it.author||'')+(it.attribution_required?' · <b title="Namensnennung nötig">BY</b>':'')+'</span></button>'}).join('')+'</div>'
      +'<div style="display:flex;gap:8px;justify-content:center;margin-top:10px"><button type="button" class="btn-g" data-page="-1"'+(d.page<=1?' disabled':'')+'>‹ Zurück</button><span class="hint" style="align-self:center">Seite '+d.page+(d.total?' · ca. '+d.total+' Treffer':'')+'</span><button type="button" class="btn-g" data-page="1"'+(d.items.length<(d.per_page||24)?' disabled':'')+'>Weiter ›</button></div>'
      :'<div class="empty">Keine Treffer.</div>';
  }
  async function search(){
    if(!state.q.trim()){toast('Bitte einen Suchbegriff eingeben',true);return}
    state.busy=true;results();
    try{var d=await api('stock_search',{provider:state.prov,q:state.q,page:state.page,orientation:state.orient});state.res=d}catch(e){state.res=null;toast(e.message||'Suche fehlgeschlagen',true)}
    state.busy=false;results();
  }
  async function take(id){
    var r=$('stkRes');if(r)r.innerHTML='<div class="hint"><i class="fas fa-spinner fa-spin"></i> Bild wird in die Mediathek geladen …</div>';
    try{var d=await api('stock_import',{provider:state.prov,id:id});toast('Bild übernommen');done(d.item,{text:d.credit,attr:!!d.attribution_required})}
    catch(e){toast(e.message||'Übernahme fehlgeschlagen',true);results()}
  }
  async function drawLibrary(){
    var b=$('stkBody');b.innerHTML='<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><input id="stkLibQ" class="fc" style="flex:1" placeholder="Mediathek durchsuchen …"><label class="btn-a" style="margin:0;cursor:pointer"><i class="fas fa-file-arrow-up"></i> Hochladen<input id="stkUp" type="file" accept="image/*" hidden></label></div><div id="stkLib" class="hb-picker-grid" style="grid-template-columns:repeat(auto-fill,minmax(120px,1fr))"><div class="hint">Lade…</div></div>';
    $('stkLibQ').addEventListener('input',paintLib);
    try{var l=await api('media_library_list');state.lib=(l.items||[]).filter(function(i){return i.url&&(/^image\//.test(i.mime||'')||/\.(png|jpe?g|webp|gif|svg)$/i.test(i.url))}).sort(function(a,c){return (c.mtime||0)-(a.mtime||0)});paintLib()}catch(e){$('stkLib').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  function paintLib(){
    var q=(($('stkLibQ')||{}).value||'').toLowerCase(),g=$('stkLib');if(!g||!state.lib)return;
    var html=state.lib.map(function(i,idx){return {i:i,idx:idx}}).filter(function(x){return !q||String(x.i.name||'').toLowerCase().indexOf(q)>=0}).slice(0,150).map(function(x){return '<button type="button" data-lib="'+x.idx+'" title="'+esc(x.i.name)+'"><img loading="lazy" src="'+esc(x.i.url)+'" alt="">'+(x.i.credit?'<span style="display:block;font-size:.65rem;opacity:.7">'+esc(x.i.credit.provider_name||'')+'</span>':'')+'</button>'}).join('');
    g.innerHTML=html||'<div class="hint">Keine Bilder. Lade eines hoch oder wechsle zu „Freie Bilder“.</div>';
  }
  async function upload(f){
    if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('sizes','64,128,192,256,512,1024,1600');fd.append('quality','90');
    try{var r=await fetch('api.php?action=media_library_upload&_='+Date.now(),{method:'POST',headers:{'X-AnMaCha-Token':token()},body:fd}),d=await r.json().catch(function(){return {}});
      if(!r.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');done(d.item,null)}catch(e){toast(e.message,true)}
  }
  /* ───────── Einstellungen (Medien-Panel) ───────── */
  async function settings(){
    var host=$('stockSettings');if(!host)return;await loadStatus(true);
    host.innerHTML='<div class="th"><div><div class="tt"><i class="fas fa-images"></i>Freie Bilder</div><div class="hint">Suche und Übernahme von Bildern aus Pixabay, Pexels, Unsplash (jeweils kostenloser API-Schlüssel) sowie Openverse und Wikimedia Commons (ohne Schlüssel). Übernommene Bilder landen mit Bildnachweis in der Mediathek und lassen sich in Beiträgen, Seiten und Themes einsetzen.</div></div>'
      +'<button class="btn-a" onclick="StockMedia.open({tab:\'stock\',onPick:function(){window.MediaHub&&MediaHub.load(true)}})"><i class="fas fa-magnifying-glass"></i> Freie Bilder suchen</button></div>'
      +(admin?'<div id="stkCfg">'+providers.map(function(p){
        return '<div class="section-grid" style="grid-template-columns:150px 1fr auto;margin-bottom:6px;align-items:center"><b>'+esc(p.name)+'</b>'
          +(p.needs_key?'<input class="fc" type="password" autocomplete="off" data-key="'+p.id+'" placeholder="'+(p.has_key?'Schlüssel gesetzt – zum Ändern neu eingeben':'API-Schlüssel')+'">':'<span class="hint">kein Schlüssel nötig</span>')
          +'<label style="display:flex;gap:6px;align-items:center"><input class="switch" type="checkbox" data-en="'+p.id+'"'+(p.enabled?' checked':'')+'> an</label>'
          +(p.needs_key?'<div class="hint" style="grid-column:2/-1;margin:-4px 0 4px"><a href="'+esc(p.key_url)+'" target="_blank" rel="noopener">Schlüssel kostenlos beantragen</a>'+(p.has_key?' · <a href="#" data-clear="'+p.id+'">Schlüssel entfernen</a>':'')+'</div>':'')+'</div>'}).join('')
        +'<div style="margin-top:8px"><button class="btn-g" id="stkSave"><i class="fas fa-floppy-disk"></i> Bildquellen speichern</button> <span id="stkMsg" class="hint"></span></div></div>'
        :'<div class="hint" style="margin-top:8px">Die Bildquellen richtet ein Administrator ein. Verfügbar: '+esc(providers.filter(function(p){return p.usable}).map(function(p){return p.name}).join(', ')||'noch keine')+'.</div>');
    var s=$('stkSave');if(s)s.onclick=async function(){
      var keys={},en={};host.querySelectorAll('[data-key]').forEach(function(i){if(i.value.trim())keys[i.getAttribute('data-key')]=i.value.trim()});
      host.querySelectorAll('[data-en]').forEach(function(i){en[i.getAttribute('data-en')]=i.checked});
      try{var d=await api('stock_config_save',{keys:keys,enabled:en});providers=d.providers;toast('Bildquellen gespeichert');settings()}catch(e){var m=$('stkMsg');if(m){m.textContent=e.message;m.style.color='var(--bad)'}}
    };
    host.querySelectorAll('[data-clear]').forEach(function(a){a.onclick=async function(e){e.preventDefault();if(!confirm('Schlüssel entfernen?'))return;var k={};k[a.getAttribute('data-clear')]='-';try{var d=await api('stock_config_save',{keys:k,enabled:{}});providers=d.providers;settings()}catch(x){toast(x.message,true)}}});
  }
  window.StockMedia={open:open,settings:settings,info:info};
})();
