/* Radio-Verwaltung (CMS → Radio, nur bei aktivem Radio-Theme): Sender/Datenquelle, Anzeige, Links, Sendeplan.
   Server: radio_state / radio_get / radio_save / radio_test in cms/api.php, Logik in cms/lib/radio.php */
(function(){
  'use strict';
  var cfg=null,sources={},active=false;
  var DAYS=['Montag','Dienstag','Mittwoch','Donnerstag','Freitag','Samstag','Sonntag'];
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function msg(t,bad){var e=$('rdMsg');if(e){e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}}
  /* Menüpunkt „Radio“ nur bei aktivem Radio-Theme zeigen (wird bei jedem Laden der Konfiguration und nach Theme-Wechseln aufgerufen) */
  async function refresh(){
    try{var d=await cmsApi('radio_state');active=!!d.active;document.querySelectorAll('[data-feature="radio"]').forEach(function(el){el.hidden=!active});
      if(!active&&document.getElementById('panel-radio')&&document.getElementById('panel-radio').classList.contains('on'))cmsTab('overview',document.querySelector('.tab[data-tab="overview"]'))}catch(e){}
  }
  function field(label,html,wide){return '<div'+(wide?' style="grid-column:1/-1"':'')+'><label class="news-lbl">'+label+'</label>'+html+'</div>'}
  function inp(i,k,ph,type){var v=cfg.stations[i][k];return '<input class="fc w-100" '+(type?'type="'+type+'" ':'')+'data-st="'+i+'" data-k="'+k+'" placeholder="'+esc(ph||'')+'" value="'+esc(v)+'">'}
  function drawStations(){
    $('rdStations').innerHTML=cfg.stations.map(function(s,i){
      var src=s.source,isDef=cfg.default===s.id;
      return '<div class="card" style="margin-bottom:10px"><div class="th"><div class="tt">'+esc(s.name||'Sender')+(isDef?' <span class="hint">· Standard</span>':'')+'</div>'
        +'<div style="display:flex;gap:6px;flex-wrap:wrap">'+(isDef?'':'<button class="btn-g" data-act="default" data-i="'+i+'">Als Standard</button>')+'<button class="btn-g" data-act="test" data-i="'+i+'"><i class="fas fa-plug"></i> Verbindung testen</button><button class="btn-g" data-act="del" data-i="'+i+'" aria-label="Sender entfernen"><i class="fas fa-trash"></i></button></div></div>'
        +'<div class="section-grid">'
        +field('Name',inp(i,'name','Mein Radio'))
        +field('Datenquelle','<select class="fc w-100" data-st="'+i+'" data-k="source">'+Object.keys(sources).map(function(k){return '<option value="'+k+'"'+(k===src?' selected':'')+'>'+esc(sources[k])+'</option>'}).join('')+'</select>')
        +(src==='lautfm'?field('laut.fm-Sendername (z. B. meinsender)',inp(i,'lautfm_id','sendername'),true):'')
        +(src==='icecast'?field('Status-Adresse (…/status-json.xsl)',inp(i,'status_url','https://stream.example.org/status-json.xsl'))+field('Mount (z. B. /live)',inp(i,'mount','/live')):'')
        +(src==='shoutcast'?field('Server-Adresse (ohne /stats)',inp(i,'status_url','https://stream.example.org:8000'))+field('Stream-ID (SID)',inp(i,'sid','1','number')):'')
        +field('Stream-Adresse'+(src==='lautfm'?' (leer = automatisch laut.fm)':''),inp(i,'stream_url','https://…/stream'),true)
        +field('Slogan',inp(i,'tagline',''))+field('Genre',inp(i,'genre',''))
        +field('Logo / Cover-Ersatz','<div style="display:flex;gap:6px">'+inp(i,'logo','Bild-Adresse')+'<button class="btn-g" data-act="logo" data-i="'+i+'"><i class="fas fa-images"></i></button></div>')+field('Website des Senders',inp(i,'website','https://…'))
        +'</div><div class="hint" data-testout="'+i+'" style="margin-top:6px"></div></div>';
    }).join('')||'<div class="empty"><i class="fas fa-radio"></i>Noch kein Sender. Füge einen hinzu.</div>';
  }
  function drawLinks(){$('rdLinks').innerHTML=cfg.links.map(function(l,i){return '<div class="section-grid" style="margin-bottom:6px;grid-template-columns:1fr 2fr auto"><input class="fc" data-lk="'+i+'" data-k="label" placeholder="Beschriftung" value="'+esc(l.label)+'"><input class="fc" data-lk="'+i+'" data-k="url" placeholder="https://…" value="'+esc(l.url)+'"><button class="btn-g" data-act="dellink" data-i="'+i+'" aria-label="Entfernen"><i class="fas fa-trash"></i></button></div>'}).join('')}
  function drawSched(){$('rdSched').innerHTML=cfg.schedule.map(function(e,i){
    return '<div class="section-grid" style="margin-bottom:6px;grid-template-columns:130px 100px 100px 2fr 1fr auto"><select class="fc" data-sc="'+i+'" data-k="day">'+DAYS.map(function(n,d){return '<option value="'+(d+1)+'"'+(+e.day===d+1?' selected':'')+'>'+n+'</option>'}).join('')+'</select>'
      +'<input class="fc" type="time" data-sc="'+i+'" data-k="from" value="'+esc(e.from)+'"><input class="fc" type="time" data-sc="'+i+'" data-k="to" value="'+esc(e.to)+'"><input class="fc" data-sc="'+i+'" data-k="title" placeholder="Sendung" value="'+esc(e.title)+'"><input class="fc" data-sc="'+i+'" data-k="host" placeholder="Moderation" value="'+esc(e.host)+'"><button class="btn-g" data-act="delslot" data-i="'+i+'" aria-label="Entfernen"><i class="fas fa-trash"></i></button></div>'}).join('')}
  function draw(){
    drawStations();drawLinks();drawSched();
    $('rdHist').value=cfg.history_count;$('rdPoll').value=cfg.poll_seconds;$('rdCover').checked=!!cfg.cover_lookup;
    $('rdShowHistory').checked=!!cfg.show.history;$('rdShowSchedule').checked=!!cfg.show.schedule;$('rdShowStations').checked=!!cfg.show.stations;$('rdShowNews').checked=!!cfg.show.news;
    var n=$('rdNotice');n.style.display=active?'none':'';if(!active)n.innerHTML='Das Theme „ElvadoPress Radio“ ist noch nicht aktiv. Du kannst hier schon einrichten; aktiviere es unter <a href="#" onclick="cmsTab(\'themes\',document.querySelector(\'.tab[data-tab=themes]\'));return false">Design → Themes</a>.';
  }
  async function load(){
    try{var d=await cmsApi('radio_get');cfg=d.config;sources=d.sources||{};active=!!d.active;draw();msg('')}catch(e){msg(e.message||'Laden fehlgeschlagen',true)}
  }
  function collect(){
    cfg.history_count=parseInt($('rdHist').value,10)||8;cfg.poll_seconds=parseInt($('rdPoll').value,10)||15;cfg.cover_lookup=$('rdCover').checked;
    cfg.show={history:$('rdShowHistory').checked,schedule:$('rdShowSchedule').checked,stations:$('rdShowStations').checked,news:$('rdShowNews').checked};
  }
  async function save(){
    collect();
    try{msg('Speichere…');var d=await cmsApi('radio_save',{config:cfg});cfg=d.config;draw();msg('Gespeichert.')}catch(e){msg(e.message||'Speichern fehlgeschlagen',true)}
  }
  async function test(i){
    var out=document.querySelector('[data-testout="'+i+'"]');if(out){out.textContent='Prüfe…';out.style.color='var(--muted)'}
    try{var d=await cmsApi('radio_test',{station:cfg.stations[i]});if(out){out.textContent=(d.ok?'✓ ':'✗ ')+d.message;out.style.color=d.ok?'var(--good)':'var(--bad)'}}catch(e){if(out){out.textContent='✗ '+e.message;out.style.color='var(--bad)'}}
  }
  function onClick(e){
    var b=e.target.closest('[data-act]');if(!b)return;var i=+b.dataset.i,a=b.dataset.act;
    if(a==='del'){if(confirm('Sender entfernen?')){var id=cfg.stations[i].id;cfg.stations.splice(i,1);if(cfg.default===id)cfg.default=cfg.stations[0]?cfg.stations[0].id:'';drawStations()}}
    else if(a==='default'){cfg.default=cfg.stations[i].id;drawStations()}
    else if(a==='test')test(i);
    else if(a==='logo'){if(window.HomeBuilder&&HomeBuilder.pickImage)HomeBuilder.pickImage(function(u){cfg.stations[i].logo=u;drawStations()})}
    else if(a==='dellink'){cfg.links.splice(i,1);drawLinks()}
    else if(a==='delslot'){cfg.schedule.splice(i,1);drawSched()}
  }
  function onInput(e){
    var el=e.target,k=el.dataset&&el.dataset.k;if(!k)return;
    if(el.dataset.st!==undefined){var s=cfg.stations[+el.dataset.st];s[k]=k==='sid'?parseInt(el.value,10)||1:el.value;if(k==='source'&&e.type==='change')drawStations()}
    else if(el.dataset.lk!==undefined)cfg.links[+el.dataset.lk][k]=el.value;
    else if(el.dataset.sc!==undefined)cfg.schedule[+el.dataset.sc][k]=k==='day'?parseInt(el.value,10):el.value;
  }
  function bind(){var r=$('panel-radio');if(!r||r.dataset.bound)return;r.dataset.bound='1';r.addEventListener('click',onClick);r.addEventListener('input',onInput);r.addEventListener('change',onInput)}
  window.RadioAdmin={refresh:refresh,load:function(){bind();load()},save:save,
    addStation:function(){if(cfg.stations.length>=12)return;cfg.stations.push({id:'',name:'',tagline:'',genre:'',logo:'',source:'lautfm',lautfm_id:'',stream_url:'',status_url:'',mount:'',sid:1,website:''});drawStations()},
    addLink:function(){cfg.links.push({label:'',url:''});drawLinks()},addSlot:function(){cfg.schedule.push({day:1,from:'08:00',to:'10:00',title:'',host:''});drawSched()}};
})();
