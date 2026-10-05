/* WordPress-Themes (CMS → Design → Themes): suchen, installieren, Vorschau, aktivieren, anpassen (Live-Customizer im Overlay #themeCustomizer, gemeinsam mit den Portal-Designs).
   Server: wp_theme_* in cms/api.php, cms/wp/, Customizer in cms/wp/customizer-api.php. */
(function(){
  'use strict';
  var themes=[],front=false,items=[],page=1,pages=1,prevSlug='',sbx=false;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function token(){return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||''}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  async function call(action,q,body){
    var o={headers:{'X-AnMaCha-Token':token()}};if(body!==undefined){o.method='POST';o.headers['Content-Type']='application/json';o.body=JSON.stringify(body)}
    var r=await fetch('api.php?action='+action+(q||'')+(sbx&&String(action).indexOf('wp_theme')===0?'&sandbox=1':'')+'&_='+Date.now(),o),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(!r.ok||d.status==='error'){var er=new Error(d.message||'Fehler');er.errors=d.errors;throw er}return d;
  }
  function take(d){themes=d.themes||themes;front=!!d.front;draw();if(sbx&&window.Sandbox)Sandbox.refresh()}
  async function load(){
    bind();if(window.WpPlugins&&WpPlugins.autoLoad&&!sbx)WpPlugins.autoLoad();if(window.Sandbox)Sandbox.load();
    try{take(await call('wp_themes'))}catch(e){$('wtInst').innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  function tab(t){
    if($('wtTabInst')){$('wtInst').style.display=t==='inst'?'':'none';$('wtNew').style.display=t==='new'?'':'none';$('wtTabInst').classList.toggle('on',t==='inst');$('wtTabNew').classList.toggle('on',t==='new')}
    if(t==='new'&&!items.length)search(1);
  }
  function shot(src,name){return src?'<img src="'+esc(src)+'" alt="" loading="lazy" referrerpolicy="no-referrer" style="width:100%;aspect-ratio:4/3;object-fit:cover;object-position:top;background:var(--line)">':'<div style="aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;background:var(--line)" class="hint"><i class="fas fa-palette"></i>&nbsp;'+esc(name)+'</div>'}
  function draw(){
    var act=themes.filter(function(t){return t.active})[0];
    $('wtState').innerHTML=front&&act?'Aktiv: <b>'+esc(act.name)+'</b> – deine Website wird mit diesem WordPress-Theme ausgeliefert.':'Zurzeit ist <b>kein</b> WordPress-Theme aktiv – es gilt die normale CMS-Portal-Oberfläche.';
    $('wtOff').hidden=!front;if($('wtInstCount'))$('wtInstCount').textContent='('+themes.length+')';
    $('wtInst').innerHTML=themes.map(function(t,i){
      return '<div style="border:1px solid '+(t.active?'var(--good)':'var(--line)')+';border-radius:12px;overflow:hidden;display:flex;flex-direction:column">'+shot(t.screenshot,t.name)
        +'<div style="padding:10px 12px;flex:1"><b>'+esc(t.name)+'</b>'+(t.active?' <span style="color:var(--good)">aktiv</span>':'')+(window.WpPlugins?WpPlugins.autoBox('theme',t.slug,!t.bundled):'')+'<div class="hint">v'+esc(t.version)+(t.block_theme?' · Block-Theme':'')+(t.author?' · '+esc(t.author):'')+(t.parent?' · Child von '+esc(t.parent):'')+'</div>'
        +(t.error?'<div class="hint" style="color:var(--bad);margin-top:4px"><i class="fas fa-triangle-exclamation"></i> '+esc(t.error)+'</div>':'')+'</div>'
        +'<div style="display:flex;gap:6px;padding:0 12px 12px;flex-wrap:wrap">'+(t.error?'':'<button class="btn-g" onclick="WpThemes.preview('+i+')"><i class="fas fa-eye"></i> Vorschau</button><button class="btn-g" onclick="WpThemes.customize('+i+')"><i class="fas fa-sliders"></i> Anpassen</button>')
        +(t.active||t.error?'':'<button class="btn-a" onclick="WpThemes.activate('+i+')">Aktivieren</button>')
        +(t.active||t.bundled?'':'<button class="btn-g" style="color:var(--bad)" title="Löschen" onclick="WpThemes.del('+i+')"><i class="fas fa-trash"></i></button>')+'</div></div>';
    }).join('')||'<div class="hint">Keine Themes installiert.</div>';
    if(window.DesignHub)DesignHub.refresh();
  }
  async function activate(i){
    var t=themes[i];if(!t)return;
    if(!confirm(sbx?'Theme „'+t.name+'“ in der Sandbox aktivieren? Die Live-Seite ändert sich nicht.':'Theme „'+t.name+'“ aktivieren? Die Website wird ab sofort damit ausgeliefert (jederzeit rückgängig zu machen).'))return;
    try{take(await call('wp_theme_activate','',{slug:t.slug}));closePreview(true);toast('Theme aktiviert.');window.cmsPackRefresh&&cmsPackRefresh()}catch(e){toast(e.message,true)}
  }
  async function del(i){
    var t=themes[i];if(!t||!confirm('Theme „'+t.name+'“ endgültig löschen?'))return;
    try{take(await call('wp_theme_delete','',{slug:t.slug}));toast('Theme gelöscht.')}catch(e){toast(e.message,true)}
  }
  async function off(){
    if(!confirm('WordPress-Theme-Auslieferung beenden und zur normalen CMS-Portal-Oberfläche zurückkehren?'))return;
    try{take(await call('wp_theme_deactivate','',{}));toast('Zurück beim CMS-Portal.');window.cmsPackRefresh&&cmsPackRefresh()}catch(e){toast(e.message,true)}
  }
  async function preview(i){
    var t=themes[i];if(!t)return;
    try{var d=await call('wp_theme_preview','',{slug:t.slug});prevSlug=t.slug;$('wtPrevName').textContent=t.name;
      var a=$('wtPrevAct');a.hidden=!!t.active;a.onclick=function(){activate(themes.findIndex(function(x){return x.slug===prevSlug}))};
      $('wtPrev').style.display='flex';$('wtFrame').src=d.url}catch(e){toast(e.message,true)}
  }
  /* Live-Demo eines Verzeichnis-Themes vor der Installation (Demo-Seite von wordpress.org im Rahmen) */
  function demo(i){
    var it=items[i];if(!it||!/^https:\/\//.test(it.preview_url||''))return;
    prevSlug='';$('wtPrevName').textContent=it.name+' – Live-Demo';
    var a=$('wtPrevAct');a.hidden=!!it.installed;a.innerHTML='<i class="fas fa-download"></i> Installieren';
    a.onclick=function(){closePreview();install(i,a)};
    $('wtPrevNote').textContent='Live-Demo von wordpress.org';$('wtPrevOpen').href=it.preview_url;$('wtPrevOpen').hidden=false;
    $('wtPrev').style.display='flex';$('wtFrame').src=it.preview_url;
  }
  function closePreview(){
    $('wtPrevOpen').hidden=true;$('wtPrevNote').textContent='Vorschau (15 Minuten gültig)';$('wtPrevAct').innerHTML='<i class="fas fa-check"></i> Aktivieren';
    $('wtPrev').style.display='none';$('wtFrame').src='about:blank';
    if(!prevSlug)return;
    fetch('/?rrw_wp_preview=off',{redirect:'manual',credentials:'same-origin'}).catch(function(){});   // Vorschau-Cookie beenden
  }
  async function search(p){
    page=p||1;var box=$('wtResults');box.innerHTML='<div class="hint">Suche läuft …</div>';
    try{var d=await call('wp_theme_search','&q='+encodeURIComponent($('wtQuery').value.trim())+'&page='+page);items=d.items||[];pages=d.pages||1;drawResults()}
    catch(e){box.innerHTML='<div class="hint">'+esc(e.message)+'</div>'}
  }
  function drawResults(){
    $('wtResults').innerHTML=items.length?items.map(function(it,i){
      return '<div style="border:1px solid var(--line);border-radius:12px;overflow:hidden;display:flex;flex-direction:column">'+shot(it.screenshot,it.name)
        +'<div style="padding:10px 12px;flex:1"><b>'+esc(it.name)+'</b><div class="hint">v'+esc(it.version)+' · '+esc(it.author)+(it.rating!=null?' · ★ '+esc(it.rating):'')+(it.installs?' · '+esc(it.installs.toLocaleString('de-DE'))+'+':'')+'</div>'
        +'<div class="hint" style="margin-top:4px">'+esc(it.description)+'</div>'+(it.tags&&it.tags.length?'<div class="hint" style="margin-top:4px;font-size:.72rem">'+it.tags.map(esc).join(' · ')+'</div>':'')+'</div>'
        +'<div style="padding:0 12px 12px">'+'<div style="display:flex;gap:8px;flex-wrap:wrap">'+(/^https:\/\//.test(it.preview_url||'')?'<button class="btn-g" onclick="WpThemes.demo('+i+')"><i class="fas fa-eye"></i> Live-Demo</button>':'')+(it.installed?'<span class="hint" style="align-self:center">Installiert</span>':'<button class="btn-a" onclick="WpThemes.install('+i+',this)"><i class="fas fa-download"></i> Installieren</button>')+'</div></div></div>';
    }).join(''):'<div class="hint">Keine Themes gefunden.</div>';
    $('wtPager').innerHTML=pages>1?'<button class="btn-g" '+(page<=1?'disabled':'')+' onclick="WpThemes.search('+(page-1)+')">‹</button><span class="hint">Seite '+page+' / '+pages+'</span><button class="btn-g" '+(page>=pages?'disabled':'')+' onclick="WpThemes.search('+(page+1)+')">›</button>':'';
  }
  async function install(i,btn){
    var it=items[i];if(!it)return;
    if(!confirm('Theme „'+it.name+'“ installieren? Es ist fremder PHP-Code, der mit den Rechten des CMS läuft.'))return;
    if(btn)btn.disabled=true;
    try{take(await call('wp_theme_install','',{slug:it.slug}));items[i].installed=true;drawResults();toast('Installiert – unter „Installiert“ ansehen und aktivieren.')}
    catch(e){toast(e.message,true);if(btn)btn.disabled=false}
  }
  async function upload(file){
    if(!file)return;var st=function(m,k){if(window.DesignHub)DesignHub.status(m,k)};
    st('Lade „'+file.name+'“ hoch …','busy');var fd=new FormData();fd.append('file',file);
    try{var r=await fetch('api.php?action=wp_theme_upload&_='+Date.now(),{method:'POST',headers:{'X-AnMaCha-Token':token()},body:fd}),d=await r.json().catch(function(){return null});
      if(r.status===401&&window.cmsSessionExpired)window.cmsSessionExpired();
      if(!d)throw new Error('Upload fehlgeschlagen (Serverantwort '+r.status+'). Möglicherweise ist die Datei größer als das Upload-Limit des Servers.');
      if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');
      take(d);tab('inst');toast('Theme installiert.');
      var t=(themes.filter(function(x){return x.slug===d.slug})[0])||{};
      st('WordPress-Theme „'+(t.name||d.slug||'Theme')+'“ ist installiert. Du findest es unter „WordPress-Themes“ und kannst es dort ansehen oder aktivieren.','ok');
      var w=$('dgWp');if(w&&w.scrollIntoView)w.scrollIntoView({behavior:'smooth',block:'start'});
    }catch(e){toast(e.message,true);st(e.message,'bad')}
    var u=$('wtUpload');if(u)u.value='';
  }
  function bind(){var u=$('wtUpload');if(u&&!u.dataset.bound){u.dataset.bound='1';u.addEventListener('change',function(){upload(u.files&&u.files[0])})}}
  /* ───────── Live-Customizer ───────── */
  var cz=null,czSaved={pub:'',reset:'',hint:''};
  function czState(txt){var st=$('themeCustomizerState');if(st)st.textContent=txt}
  function czDirtyIds(){return Object.keys(cz.values).filter(function(k){return String(cz.values[k])!==String(cz.base[k])})}
  function czChanged(){var o={};czDirtyIds().forEach(function(k){o[k]=cz.values[k]});return o}
  function czPad(n){return (n<10?'0':'')+n}
  function czLocal(ts){var d=new Date(ts*1000);return d.getFullYear()+'-'+czPad(d.getMonth()+1)+'-'+czPad(d.getDate())+'T'+czPad(d.getHours())+':'+czPad(d.getMinutes())}
  function czFmt(ts){var d=new Date(ts*1000);return czPad(d.getDate())+'.'+czPad(d.getMonth()+1)+'.'+d.getFullYear()+' '+czPad(d.getHours())+':'+czPad(d.getMinutes())}
  function czUnsaved(){return czDirtyIds().length>0&&JSON.stringify(czChanged())!==cz.savedSig}
  function czPubLabel(){
    var a=cz.action;
    return a==='draft'?'<i class="fas fa-floppy-disk"></i> Entwurf speichern':a==='schedule'?'<i class="fas fa-clock"></i> Planen':'<i class="fas fa-check"></i> '+(sbx?'In der Sandbox übernehmen':cz.active?'Veröffentlichen':'Aktivieren & Veröffentlichen');
  }
  function czRefreshPub(){var b=$('themeCustomizer').querySelector('.theme-customizer-publish .btn-a');if(b&&cz)b.innerHTML=czPubLabel()}
  async function customize(i){
    var t=themes[i];if(!t||t.error)return;
    var d;try{d=await call('wp_theme_customize','',{slug:t.slug})}catch(e){toast(e.message,true);return}
    var sh=$('themeCustomizer'),pub=sh.querySelector('.theme-customizer-publish .btn-a'),rs=sh.querySelector('.theme-customizer-heading .btn-g'),hd=sh.querySelector('.theme-customizer-heading .hint');
    if(!cz)czSaved={pub:pub.innerHTML,reset:rs.innerHTML,hint:hd?hd.textContent:''};
    cz={slug:t.slug,name:d.theme.name||t.name,block:!!d.theme.block_theme,active:!!d.active&&!!d.front,sections:d.sections||[],unsupported:d.unsupported||0,draft:d.draft,url:d.url,loadedAt:Date.now(),values:{},base:{},rendered:{},timer:null,seq:0,pending:false,acks:{},sec:'',action:'publish',cs:null,savedSig:'',date:''};
    cz.sections.forEach(function(s){s.controls.forEach(function(c){if(c.type!=='note'&&c.type!=='go')cz.values[c.id]=cz.base[c.id]=c.value})});
    cz.rendered={blogname:cz.base.blogname,blogdescription:cz.base.blogdescription};
    var stateTxt=cz.active?'Aktiv':'Vorschau';
    if(d.changeset&&d.changeset.values){   // gespeicherten Entwurf bzw. geplante Änderungen fortsetzen
      cz.cs=d.changeset;var n=0;
      Object.keys(d.changeset.values).forEach(function(k){if(!(k in cz.base))return;var v=d.changeset.values[k];cz.values[k]=typeof cz.base[k]==='boolean'?!!v:String(v);n++});
      cz.savedSig=JSON.stringify(czChanged());cz.pending=n>0;
      if(d.changeset.status==='future'){cz.action='schedule';cz.date=czLocal(d.changeset.date);stateTxt='Geplant für '+czFmt(d.changeset.date)}else{cz.action='draft';stateTxt='Entwurf gespeichert'}
    }
    sh.dataset.mode='wp';$('themeCustomizerName').textContent=cz.base.blogname||cz.name;
    if(hd)hd.textContent='Du passt gerade an';
    pub.innerHTML=czPubLabel();
    var row=$('czThemeRow');row.hidden=false;row.innerHTML='<div><small>Aktives Theme</small><b>'+esc(cz.name)+'</b></div><button type="button" class="btn-g" data-czchange="1">Ändern</button>';
    $('czGear').hidden=false;$('czGear').setAttribute('aria-expanded','false');$('czActions').hidden=true;
    czState(stateTxt);
    $('themePreviewUrl').textContent=location.host+(sbx?' · Sandbox · ':' · ')+cz.name;
    czDraw();
    var box=$('themeCustomizerControls');box.oninput=czOnInput;box.onchange=czOnInput;box.onclick=czOnClick;
    sh.querySelector('.theme-customizer-side').onclick=czSideClick;
    sh.style.display='grid';document.body.style.overflow='hidden';
    $('themeCustomizerFrame').src=cz.url;
    if(cz.pending)czSchedule();
  }
  function czCtl(c){
    if(c.type==='note')return '<div class="hint cz-note">'+esc(c.label)+'</div>';
    if(c.type==='go')return '<button type="button" class="btn-g cz-go" data-czgo="'+esc(c.to)+'"><i class="fas fa-arrow-up-right-from-square"></i> '+esc(c.label)+'</button>';
    var id=esc(c.id),val=cz.values[c.id],h='<div class="customizer-control" data-cid="'+id+'">',lab=function(x){return '<label><span>'+esc(c.label)+'</span>'+(x||'')+'</label>'};
    if(c.type==='checkbox')h+='<label class="cz-check"><input type="checkbox" data-cz="'+id+'"'+(val?' checked':'')+'><span>'+esc(c.label)+'</span></label>';
    else if(c.type==='textarea')h+=lab()+'<textarea class="fc w-100" rows="5" data-cz="'+id+'">'+esc(val)+'</textarea>';
    else if(c.type==='select')h+=lab()+'<select class="fc w-100" data-cz="'+id+'">'+(c.choices||[]).map(function(o){return '<option value="'+esc(o[0])+'"'+(String(o[0])===String(val)?' selected':'')+'>'+esc(o[1])+'</option>'}).join('')+'</select>';
    else if(c.type==='radio')h+=lab()+(c.choices||[]).map(function(o){return '<label class="cz-check"><input type="radio" name="cz_'+id+'" data-cz="'+id+'" value="'+esc(o[0])+'"'+(String(o[0])===String(val)?' checked':'')+'><span>'+esc(o[1])+'</span></label>'}).join('');
    else if(c.type==='color'){var ok=/^#[0-9a-f]{6}$/i.test(String(val));h+=lab('<span class="control-value" data-vfor="'+id+'">'+esc(val||'Standard')+'</span>')+'<div class="customizer-color"><input type="color" data-cz="'+id+'" data-sync="1" value="'+(ok?esc(val):esc(/^#[0-9a-f]{6}$/i.test(c.default||'')?c.default:'#777777'))+'"><input class="fc" data-cz="'+id+'" data-sync="2" value="'+esc(val)+'" placeholder="Standard des Themes"></div>'}
    else if(c.type==='range')h+=lab('<span class="control-value" data-vfor="'+id+'">'+esc(val)+'</span>')+'<input type="range" data-cz="'+id+'" min="'+esc(c.min==null?0:c.min)+'" max="'+esc(c.max==null?100:c.max)+'" step="'+esc(c.step||1)+'" value="'+esc(val)+'">';
    else if(c.type==='number')h+=lab()+'<input class="fc w-100" type="number" data-cz="'+id+'"'+(c.min!=null?' min="'+esc(c.min)+'"':'')+(c.max!=null?' max="'+esc(c.max)+'"':'')+(c.step?' step="'+esc(c.step)+'"':'')+' value="'+esc(val)+'">';
    else if(c.type==='image')h+=lab()+'<div class="cz-media">'+czThumb(val)+'<div class="cz-media-btns"><button type="button" class="btn-g" data-czpick="'+id+'"><i class="fas fa-images"></i> Aus Mediathek wählen</button>'+(val?'<button type="button" class="btn-g" data-czclear="'+id+'"><i class="fas fa-xmark"></i> Entfernen</button>':'')+'</div><input class="fc w-100" data-cz="'+id+'" value="'+esc(val)+'" placeholder="oder Bildadresse (https://… oder /pfad)"></div>';
    else h+=lab()+'<input class="fc w-100" data-cz="'+id+'" value="'+esc(val)+'"'+(c.max_length?' maxlength="'+esc(c.max_length)+'"':'')+'>';
    return h+(c.description?'<div class="hint" style="margin-top:4px">'+esc(c.description)+'</div>':'')+'<div class="hint cz-err" data-errfor="'+id+'" hidden></div></div>';
  }
  /* Abschnitte zum Durchklicken (wie im WordPress-Customizer): erst die Liste, dann ein Abschnitt mit „Zurück“ */
  function czEditorSec(){return {id:'_editor',title:'Website-Editor',description:'',html:'<div class="hint">Dieses Theme gestaltet Kopfzeile, Fußzeile und Vorlagen mit dem Website-Editor.</div>'+(cz.active?'<button class="btn-g" type="button" data-czedit="1"><i class="fas fa-pen-ruler"></i> Website-Editor öffnen</button>':'<div class="hint">Der Website-Editor steht zur Verfügung, sobald das Theme aktiv ist.</div>'),controls:[]}}
  function czAllSections(){var l=cz.sections.slice();if(cz.block)l.splice(Math.min(1,l.length),0,czEditorSec());return l}
  function czDraw(){
    var box=$('themeCustomizerControls'),list=czAllSections(),sec=cz.sec?list.filter(function(x){return x.id===cz.sec})[0]:null,h='';
    if(!sec){
      cz.sec='';
      h='<div class="cz-list">'+list.map(function(x){return '<button type="button" data-czsec="'+esc(x.id)+'"><span>'+esc(x.title)+'</span><i class="fas fa-chevron-right"></i></button>'}).join('')+'</div>';
      if(cz.unsupported)h+='<div class="hint cz-note" style="margin-top:12px">'+(cz.unsupported===1?'Eine weitere Einstellung dieses Themes lässt':cz.unsupported+' weitere Einstellungen dieses Themes lassen')+' sich hier nicht bearbeiten.</div>';
    }else h='<button type="button" class="cz-back" data-czback="1"><i class="fas fa-chevron-left"></i><span><small>Du passt gerade an</small><b>'+esc(sec.title)+'</b></span></button><div class="cz-pane">'+(sec.description?'<div class="hint">'+esc(sec.description)+'</div>':'')+(sec.html||sec.controls.map(czCtl).join(''))+'</div>';
    box.innerHTML=h;box.scrollTop=0;
  }
  function czThumb(v){return v?'<img class="cz-thumb" src="'+esc(v)+'" alt="" onerror="this.style.display=\'none\'">':'<div class="hint cz-nopic">Kein Bild gewählt</div>'}
  function czSetImage(id,url){
    var inp=document.querySelector('#themeCustomizerControls [data-cz="'+id.replace(/"/g,'\\"')+'"]');if(!inp)return;
    inp.value=url;inp.dispatchEvent(new Event('input',{bubbles:true}));
    var root=inp.closest('.customizer-control'),box=root&&root.querySelector('.cz-media');
    if(box){var old=box.querySelector('.cz-thumb,.cz-nopic');var tmp=document.createElement('div');tmp.innerHTML=czThumb(url);if(old)old.replaceWith(tmp.firstChild);
      var btns=box.querySelector('.cz-media-btns'),has=btns.querySelector('[data-czclear]');
      if(url&&!has){var b=document.createElement('button');b.type='button';b.className='btn-g';b.setAttribute('data-czclear',id);b.innerHTML='<i class="fas fa-xmark"></i> Entfernen';btns.appendChild(b)}else if(!url&&has)has.remove()}
  }
  /* Mediathek-Auswahl für Bildfelder (Bibliothek des CMS, auch Hochladen) */
  function czPicker(id){
    var old=$('czPicker');if(old)old.remove();
    var m=document.createElement('div');m.id='czPicker';m.className='cz-picker';
    m.innerHTML='<div class="cz-picker-box"><div class="cz-picker-top"><b>Mediathek</b><input class="fc" id="czPickQ" type="search" placeholder="Suchen …"><label class="btn-a" style="margin:0;cursor:pointer"><i class="fas fa-file-arrow-up"></i> Hochladen<input id="czPickUp" type="file" accept="image/*" hidden></label><button type="button" class="btn-g" id="czPickX" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div><div id="czPickGrid" class="cz-picker-grid"><div class="hint">Lädt …</div></div></div>';
    document.body.appendChild(m);
    var items=[];
    function draw(){var q=($('czPickQ').value||'').toLowerCase(),list=items.filter(function(i){return !q||String(i.name||'').toLowerCase().indexOf(q)>=0});
      $('czPickGrid').innerHTML=list.length?list.map(function(i){return '<button type="button" class="cz-pick-item" data-url="'+esc(i.url)+'" title="'+esc(i.name)+'"><img loading="lazy" src="'+esc(i.url)+'" alt=""><span>'+esc(i.name)+'</span></button>'}).join(''):'<div class="hint">Keine Bilder gefunden. Lade eines hoch.</div>'}
    function close(){m.remove()}
    $('czPickX').onclick=close;m.addEventListener('click',function(e){if(e.target===m)close()});
    $('czPickQ').oninput=draw;
    $('czPickGrid').onclick=function(e){var b=e.target.closest('[data-url]');if(!b)return;czSetImage(id,b.getAttribute('data-url'));close()};
    $('czPickUp').onchange=async function(){
      var f=this.files&&this.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('sizes','64,128,192,256,512,1024,1600');fd.append('quality','90');
      try{$('czPickGrid').innerHTML='<div class="hint">Lade hoch …</div>';
        var r=await fetch('api.php?action=media_library_upload&_='+Date.now(),{method:'POST',headers:{'X-AnMaCha-Token':token()},body:fd}),d=await r.json().catch(function(){return {}});
        if(!r.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');
        var url=d.item&&d.item.original&&d.item.original.url;if(url){czSetImage(id,url);close();return}
        var l=await call('media_library_list');items=(l.items||[]).filter(function(i){return /^image\//.test(i.mime||'')||/\.(png|jpe?g|webp|gif|svg)$/i.test(i.url||'')});draw()
      }catch(x){toast(x.message,true);draw()}
    };
    call('media_library_list').then(function(l){items=(l.items||[]).filter(function(i){return i.url&&(/^image\//.test(i.mime||'')||/\.(png|jpe?g|webp|gif|svg)$/i.test(i.url))}).sort(function(a,b){return (b.mtime||0)-(a.mtime||0)});draw()}).catch(function(x){$('czPickGrid').innerHTML='<div class="hint">'+esc(x.message)+'</div>'});
  }
  function czOnClick(e){
    var t=e.target.closest?e.target:null;
    var sc=cz&&t&&t.closest('[data-czsec]');if(sc){cz.sec=sc.getAttribute('data-czsec');czDraw();return}
    if(cz&&t&&t.closest('[data-czback]')){cz.sec='';czDraw();return}
    var go=cz&&t&&t.closest('[data-czgo]');
    if(go){var to=go.getAttribute('data-czgo');if(czUnsaved()&&!confirm('Es gibt Änderungen, die weder veröffentlicht noch als Entwurf gespeichert sind. Trotzdem zum CMS-Bereich wechseln?'))return;czClose(true);if(window.cmsTab)cmsTab(to,document.querySelector('.tab[data-tab="'+to+'"]'));return}
    var pk=cz&&e.target.closest&&e.target.closest('[data-czpick]');if(pk){czPicker(pk.getAttribute('data-czpick'));return}
    var cl=cz&&e.target.closest&&e.target.closest('[data-czclear]');if(cl){czSetImage(cl.getAttribute('data-czclear'),'');return}
    if(!cz||!e.target.closest||!e.target.closest('[data-czedit]'))return;
    call('wp_session_open','',{to:'admin.php?page=rrw-site-editor'}).then(function(r){window.open(r.url,'_blank','noopener')}).catch(function(x){toast(x.message,true)});
  }
  function czOnInput(e){
    var el=e.target;if(!cz||!el.dataset||!el.dataset.cz||(el.type==='radio'&&!el.checked))return;
    var id=el.dataset.cz,v=el.type==='checkbox'?el.checked:el.value;
    if(el.dataset.sync){var row=el.closest('.customizer-color');if(el.dataset.sync==='1')row.querySelector('[data-sync="2"]').value=v;else if(/^#[0-9a-f]{6}$/i.test(v))row.querySelector('[data-sync="1"]').value=v}
    cz.values[id]=v;
    document.querySelectorAll('[data-vfor="'+id+'"]').forEach(function(x){x.textContent=v===''?'Standard':v});
    var er=document.querySelector('[data-errfor="'+id+'"]');if(er)er.hidden=true;
    czState('Nicht veröffentlicht');
    if((id==='blogname'||id==='blogdescription')&&String(v).trim()!=='')czLive(id,String(v).trim());else cz.pending=true;
    czSchedule();
  }
  /* Titel und Untertitel ändern sich sofort im Vorschaurahmen; alles andere lädt der Rahmen nach kurzer Pause (300 ms) mit dem Entwurf neu. */
  function czLive(id,to){
    var from=cz.rendered[id],f=$('themeCustomizerFrame'),mine=cz;
    if(from===to)return;
    if(!from||!f.contentWindow){cz.pending=true;return}
    var key=id+Date.now();
    cz.acks[key]={id:id,from:from,t:setTimeout(function(){delete mine.acks[key];mine.rendered[id]=from;mine.pending=true;czSchedule()},400)};
    f.contentWindow.postMessage({type:'rrw-wpc-live',id:key,changes:[{from:from,to:to}]},location.origin);
    cz.rendered[id]=to;
  }
  window.addEventListener('message',function(e){
    var d=e.data,a=cz&&d&&d.type==='rrw-wpc-ack'?cz.acks[d.id]:null;if(e.origin!==location.origin||!a)return;
    clearTimeout(a.t);delete cz.acks[d.id];
    if(!d.count){cz.rendered[a.id]=a.from;cz.pending=true;czSchedule()}
  });
  function czSchedule(){clearTimeout(cz.timer);var mine=cz;cz.timer=setTimeout(function(){czSync(mine)},300)}
  async function czSync(mine){
    if(cz!==mine)return;var seq=++cz.seq,vals=czChanged();
    try{
      var d=await call('wp_theme_customize_draft','',{slug:cz.slug,draft:cz.draft,values:vals});
      if(cz!==mine||seq!==cz.seq)return;
      cz.draft=d.draft;cz.url=d.url;
      document.querySelectorAll('.cz-err').forEach(function(x){x.hidden=true});
      Object.keys(d.errors||{}).forEach(function(k){var er=document.querySelector('[data-errfor="'+k.replace(/["\\]/g,'')+'"]');if(er){er.textContent=d.errors[k];er.hidden=false}});
      if(!cz.pending)return;
      cz.pending=false;var f=$('themeCustomizerFrame');
      cz.rendered={blogname:'blogname' in vals?String(vals.blogname).trim():cz.base.blogname,blogdescription:'blogdescription' in vals?String(vals.blogdescription).trim():cz.base.blogdescription};
      if(Date.now()-cz.loadedAt>480000||!f.contentWindow){cz.loadedAt=Date.now();f.src=d.url}   // Vorschau-Schlüssel (15 Minuten) rechtzeitig erneuern
      else{try{f.contentWindow.location.reload()}catch(x){f.src=d.url}}
    }catch(e){if(cz===mine)czState('Vorschau nicht aktualisiert: '+e.message)}
  }
  function czReset(){if(!cz)return;Object.keys(cz.base).forEach(function(k){cz.values[k]=cz.base[k]});cz.pending=true;czDraw();czState('Änderungen verworfen');czSchedule()}
  function czLeave(){
    if(!cz)return;var sh=$('themeCustomizer'),box=$('themeCustomizerControls');
    clearTimeout(cz.timer);Object.keys(cz.acks).forEach(function(k){clearTimeout(cz.acks[k].t)});
    box.oninput=box.onchange=box.onclick=null;sh.querySelector('.theme-customizer-side').onclick=null;delete sh.dataset.mode;
    $('czGear').hidden=true;$('czActions').hidden=true;$('czActions').innerHTML='';$('czThemeRow').hidden=true;
    sh.querySelector('.theme-customizer-publish .btn-a').innerHTML=czSaved.pub;sh.querySelector('.theme-customizer-heading .btn-g').innerHTML=czSaved.reset;
    var h=sh.querySelector('.theme-customizer-heading .hint');if(h&&czSaved.hint)h.textContent=czSaved.hint;
    cz=null;fetch('/?rrw_wp_preview=off',{redirect:'manual',credentials:'same-origin'}).catch(function(){});   // Vorschau-Cookies beenden
  }
  function czClose(force){
    if(!cz)return;
    if(!force&&czUnsaved()&&!confirm('Es gibt Änderungen, die weder veröffentlicht noch als Entwurf gespeichert sind. Wirklich schließen und verwerfen?'))return;
    czLeave();$('themeCustomizer').style.display='none';document.body.style.overflow='';$('themeCustomizerFrame').src='about:blank';
  }
  async function czPublish(){
    if(!cz)return;
    if(cz.action==='draft')return czSaveCs('draft');
    if(cz.action==='schedule')return czSaveCs('future');
    var mine=cz,vals=czChanged();
    if(!mine.active&&!confirm(sbx?'Theme „'+mine.name+'“ in der Sandbox aktivieren und die Änderungen übernehmen? Die Live-Seite ändert sich nicht.':'Theme „'+mine.name+'“ aktivieren und die Änderungen veröffentlichen? Die Website wird ab sofort damit ausgeliefert (jederzeit rückgängig zu machen).'))return;
    try{
      await call('wp_theme_customize_save','',{slug:mine.slug,values:vals,draft:mine.draft});
      take(mine.active?await call('wp_themes'):await call('wp_theme_activate','',{slug:mine.slug}));
      if(cz===mine)czClose(true);toast(mine.active?'Änderungen veröffentlicht ✓':'Theme aktiviert und veröffentlicht ✓');
    }catch(e){
      toast(e.message,true);
      if(cz===mine&&e.errors)Object.keys(e.errors).forEach(function(k){var er=document.querySelector('[data-errfor="'+k.replace(/["\\]/g,'')+'"]');if(er){er.textContent=e.errors[k];er.hidden=false}});
    }
  }
  /* Aktionsfeld (Zahnrad): Veröffentlichen / Entwurf speichern / Planen, Änderungen verwerfen, Vorschau-Link teilen */
  function czRenderActions(){
    var box=$('czActions');if(!cz||!box)return;
    var radio=function(v,l){return '<label class="cz-check"><input type="radio" name="czAct" value="'+v+'"'+(cz.action===v?' checked':'')+'><span>'+l+'</span></label>'};
    var h='<h3>Aktion</h3>'+radio('publish','Veröffentlichen')+radio('draft','Entwurf speichern')+radio('schedule','Planen');
    if(cz.action==='schedule'){if(!cz.date)cz.date=czLocal(Math.floor(Date.now()/1000)+3600);h+='<input class="fc" type="datetime-local" id="czDate" value="'+esc(cz.date)+'">'}
    h+='<button type="button" class="cz-discard" data-czdiscard="1"><i class="fas fa-trash-can"></i> Änderungen verwerfen</button>';
    h+='<h3>Vorschau-Link teilen</h3><div class="hint">So sehen andere, wie die Änderungen auf deiner Website aussehen würden – auch ohne Zugang zum CMS. Der Link gilt 7 Tage.</div>';
    h+=cz.cs&&cz.cs.share?'<div class="cz-share"><input class="fc" id="czShare" readonly value="'+esc(location.origin+cz.cs.share)+'"><button type="button" class="btn-g" data-czcopy="1">Kopieren</button></div>':'<div class="hint cz-note" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px">Bitte speichere deine Änderungen zuerst als Entwurf, um die Vorschau zu teilen.</div>';
    box.innerHTML=h;
  }
  function czToggleActions(){
    if(!cz)return;var box=$('czActions'),open=box.hidden;
    if(open)czRenderActions();box.hidden=!open;$('czGear').setAttribute('aria-expanded',open?'true':'false');
  }
  function czSideClick(e){
    var t=e.target;if(!cz||!t.closest)return;
    if(t.closest('[data-czchange]')){czClose();return}
    if(t.closest('[data-czdiscard]')){czDiscard();return}
    if(t.closest('[data-czcopy]')){var inp=$('czShare');if(!inp)return;inp.select();
      (navigator.clipboard&&navigator.clipboard.writeText?navigator.clipboard.writeText(inp.value):Promise.reject()).then(function(){toast('Link kopiert ✓')},function(){try{document.execCommand('copy');toast('Link kopiert ✓')}catch(x){toast('Bitte den Link markieren und kopieren',true)}})}
  }
  document.addEventListener('change',function(e){
    var el=e.target;if(!cz||!el||!el.closest||!el.closest('#czActions'))return;
    if(el.name==='czAct'){cz.action=el.value;czRefreshPub();czRenderActions()}
    else if(el.id==='czDate')cz.date=el.value;
  });
  async function czSaveCs(mode){
    var mine=cz,vals=czChanged(),when=0;
    if(!Object.keys(vals).length&&!(mine.cs&&mine.cs.id)){toast('Es gibt noch keine Änderungen zu speichern',true);return}
    if(mode==='future'){when=mine.date?Math.floor(new Date(mine.date).getTime()/1000):0;if(!when||when<=Date.now()/1000+30){toast('Bitte einen Zeitpunkt in der Zukunft wählen',true);return}}
    try{
      var d=await call('wp_theme_customize_changeset','',{slug:mine.slug,draft:mine.draft,mode:mode,values:vals,date:when,activate:!mine.active});
      if(cz!==mine)return;
      cz.cs=d.changeset;cz.savedSig=JSON.stringify(vals);
      czState(mode==='future'?'Geplant für '+czFmt(when):'Entwurf gespeichert');
      toast(mode==='future'?'Änderungen geplant ✓':'Entwurf gespeichert ✓');
      if(!$('czActions').hidden)czRenderActions();
    }catch(e){
      toast(e.message,true);
      if(cz===mine&&e.errors)Object.keys(e.errors).forEach(function(k){var er=document.querySelector('[data-errfor="'+k.replace(/["\\]/g,'')+'"]');if(er){er.textContent=e.errors[k];er.hidden=false}});
    }
  }
  async function czDiscard(){
    if(!cz)return;var mine=cz;
    if(!czDirtyIds().length&&!(mine.cs&&mine.cs.id)){toast('Es gibt nichts zu verwerfen');return}
    if(!confirm('Alle nicht veröffentlichten Änderungen (auch einen gespeicherten Entwurf) verwerfen?'))return;
    try{if(mine.cs&&mine.cs.id)await call('wp_theme_customize_changeset','',{slug:mine.slug,draft:mine.draft,mode:'discard'})}catch(e){toast(e.message,true);return}
    if(cz!==mine)return;
    Object.keys(cz.base).forEach(function(k){cz.values[k]=cz.base[k]});cz.cs=null;cz.savedSig='';cz.action='publish';cz.date='';cz.pending=true;
    czRefreshPub();czDraw();czState(cz.active?'Aktiv':'Vorschau');if(!$('czActions').hidden)czRenderActions();czSchedule();toast('Änderungen verworfen');
  }
  window.WpThemes={setSandbox:function(on){sbx=!!on;themes=[];front=false;if($('wtInst'))$('wtInst').innerHTML='<div class="hint">Lädt …</div>';return load()},isSandbox:function(){return sbx},czToggleActions:czToggleActions,customize:customize,czActive:function(){return !!cz},czClose:czClose,czPublish:czPublish,czReset:czReset,czLeave:czLeave,state:function(){var a=themes.filter(function(t){return t.active})[0];return {front:front,name:a?a.name:''}},redraw:function(){if(themes.length)draw()},demo:demo,load:load,tab:tab,search:search,install:install,preview:preview,activate:activate,del:del,off:off,closePreview:closePreview};
})();
