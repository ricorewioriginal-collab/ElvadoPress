/* Homepage-Baukasten (CMS → Design → Homepage-Baukasten): visueller Abschnitts-Editor für das Theme „ElvadoPress Baukasten“.
   Server: wp_bk_get / wp_bk_save in cms/api.php; Schema und Bereinigung: cms/themes/elvado-baukasten/inc/layout.php */
(function(){
  'use strict';
  var layout=[],schema={},active=false,custom=false,dirty=false,open={},dragId=null,loaded=false;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function msg(t,bad){var e=$('hbMsg');if(e){e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}}
  function mark(d){dirty=d;var b=$('hbSave');if(b)b.classList.toggle('btn-a',true);msg(d?'Ungespeicherte Änderungen':'')}
  function uid(){return 's'+Math.random().toString(36).slice(2,10)}
  function defaults(type){
    var o={};((schema[type]||{}).fields||[]).forEach(function(f){o[f.k]=f.default!==undefined?f.default:(f.type==='checkbox'?false:f.type==='items'?[]:f.type==='number'?0:'')});
    if(type==='features')o.items=[{title:'Schnell',text:'Kurze Ladezeiten ohne Ballast.'},{title:'Flexibel',text:'Alles lässt sich anpassen.'},{title:'Eigenständig',text:'Deine Inhalte, dein Design.'}];
    return o;
  }
  function summary(s){var p=s.props||{};return String(p.title||p.text||p.body||p.code||'').replace(/<[^>]*>/g,'').slice(0,80)||'–'}
  function field(s,f){
    var v=(s.props||{})[f.k],id='hb_'+s.id+'_'+f.k,h='<div class="hb-field"><label class="news-lbl" for="'+id+'">'+esc(f.label)+'</label>';
    var at=' data-sid="'+s.id+'" data-k="'+f.k+'" id="'+id+'"';
    if(f.type==='text')h+='<input class="fc w-100" maxlength="300" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='url')h+='<input class="fc w-100" placeholder="https://… oder /seite" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='textarea')h+='<textarea class="fc w-100" rows="4"'+at+'>'+esc(v)+'</textarea>';
    else if(f.type==='number')h+='<input class="fc" type="number" min="'+f.min+'" max="'+f.max+'" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='checkbox')h+='<label style="display:flex;gap:8px;align-items:center"><input class="switch" type="checkbox"'+(v?' checked':'')+at+'> Ja</label>';
    else if(f.type==='select')h+='<select class="fc"'+at+'>'+Object.keys(f.options).map(function(k){return '<option value="'+esc(k)+'"'+(String(v)===k?' selected':'')+'>'+esc(f.options[k])+'</option>'}).join('')+'</select>';
    else if(f.type==='image')h+='<div class="hb-img">'+(v?'<img src="'+esc(v)+'" alt="">':'')+'<input class="fc" style="flex:1" placeholder="Bild-Adresse" value="'+esc(v)+'"'+at+'><button type="button" class="btn-g" data-pick="'+s.id+'|'+f.k+'"><i class="fas fa-images"></i> Mediathek</button></div>';
    else if(f.type==='items'){
      var it=Array.isArray(v)?v:[];
      h+='<div class="hb-items">'+it.map(function(x,i){return '<div class="hb-item"><input class="fc" placeholder="Titel" maxlength="120" value="'+esc(x.title)+'" data-sid="'+s.id+'" data-k="'+f.k+'" data-i="'+i+'" data-f="title"><input class="fc" placeholder="Text" maxlength="600" value="'+esc(x.text)+'" data-sid="'+s.id+'" data-k="'+f.k+'" data-i="'+i+'" data-f="text"><button type="button" class="btn-g" data-itemdel="'+s.id+'|'+f.k+'|'+i+'" aria-label="Entfernen"><i class="fas fa-trash"></i></button></div>'}).join('')
        +(it.length<(f.max||6)?'<div><button type="button" class="btn-g" data-itemadd="'+s.id+'|'+f.k+'"><i class="fas fa-plus"></i> Karte</button></div>':'')+'</div>';
    }
    return h+'</div>';
  }
  function draw(){
    var host=$('hbList');if(!host)return;
    $('hbEmpty').style.display=layout.length?'none':'';
    host.innerHTML=layout.map(function(s,i){
      var sc=schema[s.type]||{label:s.type,icon:'fa-square'};
      return '<div class="hb-sec'+(open[s.id]?' open':'')+(s.hidden?' is-hidden':'')+'" draggable="true" data-id="'+s.id+'">'
        +'<div class="hb-head" data-toggle="'+s.id+'"><span class="hb-grip" title="Ziehen zum Sortieren"><i class="fas fa-grip-vertical"></i></span><i class="fas '+sc.icon+'"></i>'
        +'<div class="hb-name">'+esc(sc.label)+(s.hidden?' (ausgeblendet)':'')+'<small>'+esc(summary(s))+'</small></div>'
        +'<div class="hb-tools"><button type="button" class="btn-g" data-act="up" data-id="'+s.id+'"'+(i===0?' disabled':'')+' aria-label="Nach oben"><i class="fas fa-arrow-up"></i></button>'
        +'<button type="button" class="btn-g" data-act="down" data-id="'+s.id+'"'+(i===layout.length-1?' disabled':'')+' aria-label="Nach unten"><i class="fas fa-arrow-down"></i></button>'
        +'<button type="button" class="btn-g" data-act="hide" data-id="'+s.id+'" aria-label="Ein-/Ausblenden"><i class="fas '+(s.hidden?'fa-eye-slash':'fa-eye')+'"></i></button>'
        +'<button type="button" class="btn-g" data-act="dup" data-id="'+s.id+'" aria-label="Duplizieren"><i class="fas fa-copy"></i></button>'
        +'<button type="button" class="btn-g" data-act="del" data-id="'+s.id+'" aria-label="Löschen"><i class="fas fa-trash"></i></button></div></div>'
        +'<div class="hb-body">'+(open[s.id]?(sc.fields||[]).map(function(f){return field(s,f)}).join(''):'')+'</div></div>';
    }).join('');
  }
  function palette(){
    $('hbPalette').innerHTML=Object.keys(schema).map(function(t){return '<button class="palette-btn" data-add="'+t+'"><i class="fas '+schema[t].icon+'"></i> '+esc(schema[t].label)+'</button>'}).join('');
  }
  function find(id){for(var i=0;i<layout.length;i++)if(layout[i].id===id)return i;return -1}
  function move(from,to){if(from<0||to<0||to>=layout.length||from===to)return;layout.splice(to,0,layout.splice(from,1)[0]);mark(true);draw()}
  async function load(){
    try{
      var d=await cmsApi('wp_bk_get');schema=d.schema||{};layout=d.layout||[];active=!!d.active;custom=!!d.custom;loaded=true;open={};mark(false);palette();draw();notice();
      if(!custom&&layout.length)msg('Die Abschnitte stammen aus den Positionen des Customizers. Speichern übernimmt sie in den Baukasten.');
      frame();
    }catch(e){msg(e.message||'Laden fehlgeschlagen',true)}
  }
  function notice(){
    var n=$('hbNotice');if(!n)return;
    if(active){n.style.display='none';return}
    n.style.display='';n.innerHTML='Das Theme „ElvadoPress Baukasten“ ist noch nicht aktiv. Du kannst hier schon bauen; zum Veröffentlichen <a href="#" data-activate="1">Theme aktivieren</a>.';
  }
  async function frame(){
    try{var d=await cmsApi('wp_theme_preview',{slug:'elvado-baukasten'});var f=$('hbFrame');if(f&&d.url)f.src=d.url}catch(e){}
  }
  async function save(){
    try{msg('Speichere…');var d=await cmsApi('wp_bk_save',{layout:layout});layout=d.layout||layout;custom=true;active=!!d.active;mark(false);draw();notice();msg('Gespeichert.');frame()}
    catch(e){msg(e.message||'Speichern fehlgeschlagen',true)}
  }
  async function reset(){
    if(!confirm('Eigenes Layout verwerfen? Die Startseite folgt dann wieder den Positionen im Customizer.'))return;
    try{var d=await cmsApi('wp_bk_save',{reset:true});layout=d.layout||[];custom=false;mark(false);draw();msg('Zurückgesetzt.');frame()}catch(e){msg(e.message,true)}
  }
  function preview(){var f=$('hbFrame');if(dirty&&!confirm('Die Vorschau zeigt nur den gespeicherten Stand. Erst speichern?'))return;(dirty?save():Promise.resolve()).then(function(){if(f&&f.src&&f.src.indexOf('about:')!==0)window.open(f.src,'_blank','noopener')})}
  function customizer(){
    var go=function(){if(window.cmsTab)cmsTab('themes',document.querySelector('.tab[data-tab="themes"]'));if(window.DesignHub&&DesignHub.load)DesignHub.load();
      Promise.resolve(window.WpThemes&&WpThemes.load&&WpThemes.load()).then(function(){if(WpThemes.customizeSlug)WpThemes.customizeSlug('elvado-baukasten')}).catch(function(){})};
    if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;go();
  }
  function picker(sid,k){
    var old=document.querySelector('.hb-picker');if(old)old.remove();
    var m=document.createElement('div');m.className='hb-picker';
    m.innerHTML='<div class="hb-picker-box"><div style="display:flex;gap:8px;align-items:center"><b style="flex:1">Mediathek</b><label class="btn-a" style="margin:0;cursor:pointer"><i class="fas fa-file-arrow-up"></i> Hochladen<input type="file" accept="image/*" hidden id="hbUp"></label><button type="button" class="btn-g" id="hbStock"><i class="fas fa-images"></i> Freie Bilder</button><button type="button" class="btn-g" id="hbPX" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div><div class="hb-picker-grid" id="hbGrid"><div class="hint">Lade…</div></div></div>';
    document.body.appendChild(m);var items=[];
    function pick(u){if(typeof sid==='function'){sid(u);m.remove();return}var i=find(sid);if(i>=0){layout[i].props[k]=u;mark(true);draw()}m.remove()}
    function drawGrid(){$('hbGrid').innerHTML=items.length?items.map(function(i){return '<button type="button" data-u="'+esc(i.url)+'" title="'+esc(i.name||'')+'"><img loading="lazy" src="'+esc(i.url)+'" alt=""></button>'}).join(''):'<div class="hint">Keine Bilder. Lade eines hoch.</div>'}
    m.addEventListener('click',function(e){if(e.target===m||e.target.closest('#hbPX')){m.remove();return}var b=e.target.closest('[data-u]');if(b)pick(b.getAttribute('data-u'))});
    $('hbStock').onclick=function(){if(!window.StockMedia)return;m.remove();StockMedia.open({tab:'stock',onPick:function(it,i){pick(i.url)}})};
    $('hbUp').onchange=async function(){
      var f=this.files&&this.files[0];if(!f)return;var fd=new FormData();fd.append('file',f);fd.append('sizes','64,128,192,256,512,1024,1600');fd.append('quality','90');
      try{var tk=sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
        var r=await fetch('api.php?action=media_library_upload&_='+Date.now(),{method:'POST',headers:{'X-ElvadoPress-Token':tk},body:fd}),d=await r.json().catch(function(){return {}});
        if(!r.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');var u=d.item&&d.item.original&&d.item.original.url;if(u)pick(u)}catch(x){msg(x.message,true)}
    };
    cmsApi('media_library_list').then(function(l){items=(l.items||[]).filter(function(i){return i.url&&(/^image\//.test(i.mime||'')||/\.(png|jpe?g|webp|gif|svg)$/i.test(i.url))}).sort(function(a,b){return (b.mtime||0)-(a.mtime||0)});drawGrid()}).catch(function(x){$('hbGrid').innerHTML='<div class="hint">'+esc(x.message)+'</div>'});
  }
  function onClick(e){
    var t=e.target;
    var a=t.closest('[data-act]');
    if(a){var id=a.dataset.id,i=find(id),act=a.dataset.act;
      if(act==='up')move(i,i-1);else if(act==='down')move(i,i+1);
      else if(act==='hide'){layout[i].hidden=!layout[i].hidden;mark(true);draw()}
      else if(act==='dup'){var c=JSON.parse(JSON.stringify(layout[i]));c.id=uid();layout.splice(i+1,0,c);open[c.id]=true;mark(true);draw()}
      else if(act==='del'){if(confirm('Abschnitt löschen?')){layout.splice(i,1);mark(true);draw()}}
      return}
    var ad=t.closest('[data-add]');
    if(ad){var ty=ad.dataset.add,s={id:uid(),type:ty,hidden:false,props:defaults(ty)};layout.push(s);open[s.id]=true;mark(true);draw();var el=document.querySelector('.hb-sec[data-id="'+s.id+'"]');if(el)el.scrollIntoView({behavior:'smooth',block:'center'});return}
    var pk=t.closest('[data-pick]');if(pk){var p=pk.dataset.pick.split('|');picker(p[0],p[1]);return}
    var ia=t.closest('[data-itemadd]');if(ia){var q=ia.dataset.itemadd.split('|'),i2=find(q[0]);layout[i2].props[q[1]].push({title:'',text:''});mark(true);draw();return}
    var idl=t.closest('[data-itemdel]');if(idl){var r=idl.dataset.itemdel.split('|'),i3=find(r[0]);layout[i3].props[r[1]].splice(+r[2],1);mark(true);draw();return}
    var ac=t.closest('[data-activate]');
    if(ac){e.preventDefault();cmsApi('wp_theme_activate',{slug:'elvado-baukasten'}).then(function(){active=true;notice();msg('Theme aktiviert.');frame()}).catch(function(x){msg(x.message,true)});return}
    var tg=t.closest('[data-toggle]');
    if(tg&&!t.closest('.hb-tools')){var id2=tg.dataset.toggle;open[id2]=!open[id2];draw()}
  }
  function onInput(e){
    var el=e.target;if(!el.dataset||!el.dataset.sid)return;var i=find(el.dataset.sid);if(i<0)return;var s=layout[i],k=el.dataset.k;
    var v=el.type==='checkbox'?el.checked:el.type==='number'?parseInt(el.value||'0',10):el.value;
    if(el.dataset.i!==undefined)s.props[k][+el.dataset.i][el.dataset.f]=v;else s.props[k]=v;
    mark(true);
    var sm=el.closest('.hb-sec').querySelector('.hb-name small');if(sm)sm.textContent=summary(s);
  }
  function bind(){
    var host=$('hbList');if(!host||host.dataset.bound)return;host.dataset.bound='1';
    var root=$('panel-baukasten');root.addEventListener('click',onClick);root.addEventListener('input',onInput);root.addEventListener('change',onInput);
    host.addEventListener('dragstart',function(e){var s=e.target.closest&&e.target.closest('.hb-sec');if(!s||e.target.closest('input,textarea,select'))return;dragId=s.dataset.id;s.classList.add('dragging');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',dragId)}catch(x){}});
    host.addEventListener('dragend',function(){dragId=null;host.querySelectorAll('.dragging,.drag-over').forEach(function(x){x.classList.remove('dragging','drag-over')})});
    host.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();var s=e.target.closest('.hb-sec');host.querySelectorAll('.drag-over').forEach(function(x){x.classList.remove('drag-over')});if(s&&s.dataset.id!==dragId)s.classList.add('drag-over')});
    host.addEventListener('drop',function(e){if(!dragId)return;e.preventDefault();var s=e.target.closest('.hb-sec');if(s&&s.dataset.id!==dragId)move(find(dragId),find(s.dataset.id));dragId=null});
    window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue=''}});
  }
  /* Layout aus der KI: nur bekannte Typen und Felder übernehmen (die endgültige Bereinigung erfolgt serverseitig beim Speichern) */
  function applyAiLayout(sections){
    if(!Array.isArray(sections)||!sections.length)return;
    var fresh=[];
    sections.forEach(function(x){
      if(!x||!schema[x.type])return;var props=defaults(x.type),known={};
      (schema[x.type].fields||[]).forEach(function(f){known[f.k]=f});
      Object.keys(x.props||{}).forEach(function(k){var f=known[k],v=x.props[k];if(!f)return;
        if(f.type==='items')props[k]=Array.isArray(v)?v.slice(0,f.max||6).map(function(i){return {title:String((i&&i.title)||''),text:String((i&&i.text)||'')}}):[];
        else if(f.type==='checkbox')props[k]=!!v;else if(f.type==='number')props[k]=parseInt(v,10)||0;
        else if(f.type==='select')props[k]=f.options&&f.options[String(v)]!==undefined?String(v):props[k];
        else if(f.type==='image'||f.type==='url')props[k]=/^(https:\/\/|\/)/.test(String(v))?String(v):'';
        else props[k]=String(v==null?'':v)});
      fresh.push({id:uid(),type:x.type,hidden:false,props:props});
    });
    if(!fresh.length){msg('Die KI hat keine verwertbaren Abschnitte geliefert.',true);return}
    if(layout.length&&confirm('Das bestehende Layout durch den KI-Entwurf ersetzen?\n\nOK = ersetzen, Abbrechen = unten anhängen.'))layout=fresh;else layout=layout.concat(fresh);
    open={};mark(true);draw();msg('KI-Entwurf übernommen – prüfen, anpassen und speichern.');
  }
  function aiLayout(){if(!window.EpAi)return msg('KI-Oberfläche nicht verfügbar',true);EpAi.layoutAssistant(applyAiLayout)}
  window.HomeBuilder={aiLayout:aiLayout,applyAiLayout:applyAiLayout,pickImage:function(cb){picker(cb)},load:function(){bind();load()},save:save,reset:reset,preview:preview,customizer:customizer};
})();
