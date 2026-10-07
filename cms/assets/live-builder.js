/* Live Builder (Website → Live Builder): Seitenstruktur, Einstellungen des gewählten Bereichs und Live-Vorschau der Startseite.
   Nutzt das Layout des Themes „ElvadoPress Baukasten“: wp_bk_get / wp_bk_draft / wp_bk_publish / wp_bk_discard / wp_bk_save (cms/api.php).
   Änderungen gehen zuerst in einen Entwurf (nur in der geprüften Vorschau sichtbar); „Veröffentlichen“ macht ihn öffentlich. */
(function(){
  'use strict';
  var layout=[],schema={},sel=null,tab='content',active=false,hasDraft=false,dirty=false,loaded=false,dragId=null,timer=null,saving=false,pending=false,previewUrl='';
  var cat={},catCats={};   // Komponenten-Katalog (components-api.php): Kategorie, Beschreibung, Feldgruppen, geräteabhängige Felder
  function fdef(type,k){var c=cat[type];if(!c)return null;for(var i=0;i<c.fields.length;i++)if(c.fields[i].k===k)return c.fields[i];return null}
  function grp(type,k){var f=fdef(type,k);return f?f.group:'content'}
  async function loadCatalog(){
    try{var tk=sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
      var r=await fetch('/cms/components-api.php?action=components_catalog',{headers:{'X-AnMaCha-Token':tk}}),d=await r.json();
      if(d.status==='ok'){cat={};(d.components||[]).forEach(function(c){cat[c.id]=c});catCats=d.categories||{}}}catch(e){}
  }
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function uid(){return 's'+Math.random().toString(36).slice(2,10)}
  function find(id){for(var i=0;i<layout.length;i++)if(layout[i].id===id)return i;return -1}
  function state(t,bad){var e=$('lbState');if(e){e.textContent=t||'';e.className='lb-state'+(bad?' bad':'')}}
  function defaults(type){var o={};((schema[type]||{}).fields||[]).forEach(function(f){o[f.k]=f.default!==undefined?f.default:(f.type==='checkbox'?false:f.type==='items'?[]:f.type==='number'?0:'')});
    if(type==='features')o.items=[{title:'Schnell',text:'Kurze Ladezeiten ohne Ballast.'},{title:'Flexibel',text:'Alles lässt sich anpassen.'},{title:'Eigenständig',text:'Deine Inhalte, dein Design.'}];return o}
  function summary(s){var p=s.props||{};return String(p.title||p.text||p.body||p.code||'').replace(/<[^>]*>/g,'').slice(0,60)}
  function changed(){dirty=true;state('Ungespeicherte Änderungen');clearTimeout(timer);timer=setTimeout(saveDraft,900)}

  function drawList(){
    var h=$('lbList');if(!h)return;
    h.innerHTML=layout.length?layout.map(function(s,i){var sc=schema[s.type]||{label:s.type,icon:'fa-square'};
      return '<div class="lb-item'+(s.id===sel?' on':'')+(s.hidden?' off':'')+'" draggable="true" data-id="'+s.id+'" tabindex="0" role="button" aria-label="'+esc(sc.label)+' bearbeiten">'
        +'<span class="lb-grip" title="Ziehen zum Sortieren"><i class="fas fa-grip-vertical"></i></span><i class="fas '+sc.icon+' lb-ico"></i>'
        +'<span class="lb-name">'+esc(sc.label)+'<small>'+esc(summary(s))+'</small></span>'
        +'<button type="button" class="lb-ic" data-act="hide" data-id="'+s.id+'" aria-label="'+(s.hidden?'Einblenden':'Ausblenden')+'"><i class="fas '+(s.hidden?'fa-eye-slash':'fa-eye')+'"></i></button>'
        +'<span class="lb-more"><button type="button" class="lb-ic" data-act="menu" data-id="'+s.id+'" aria-label="Mehr"><i class="fas fa-ellipsis-vertical"></i></button>'
        +'<div class="lb-pop" data-pop="'+s.id+'" hidden><button type="button" data-act="up" data-id="'+s.id+'"'+(i===0?' disabled':'')+'><i class="fas fa-arrow-up"></i>Nach oben</button>'
        +'<button type="button" data-act="down" data-id="'+s.id+'"'+(i===layout.length-1?' disabled':'')+'><i class="fas fa-arrow-down"></i>Nach unten</button>'
        +'<button type="button" data-act="dup" data-id="'+s.id+'"><i class="fas fa-copy"></i>Duplizieren</button>'
        +'<button type="button" data-act="del" data-id="'+s.id+'"><i class="fas fa-trash"></i>Löschen</button></div></span></div>'}).join('')
      :'<div class="hint" style="padding:10px">Noch keine Bereiche. Füge unten einen hinzu.</div>';
  }
  function field(s,f){
    var v=(s.props||{})[f.k],id='lbf_'+f.k,at=' data-k="'+f.k+'" id="'+id+'"',h='<div class="lb-field"><label for="'+id+'">'+esc(f.label)+'</label>';
    if(f.type==='text')h+='<input class="fc" maxlength="300" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='url')h+='<input class="fc" placeholder="https://… oder /seite" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='textarea')h+='<textarea class="fc" rows="5"'+at+'>'+esc(v)+'</textarea>';
    else if(f.type==='number')h+='<input class="fc" type="number" min="'+f.min+'" max="'+f.max+'" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='checkbox')h+='<label class="lb-sw"><input class="switch" type="checkbox"'+(v?' checked':'')+at+'><span>'+(v?'An':'Aus')+'</span></label>';
    else if(f.type==='select')h+='<select class="fc"'+at+'>'+Object.keys(f.options).map(function(k){return '<option value="'+esc(k)+'"'+(String(v)===k?' selected':'')+'>'+esc(f.options[k])+'</option>'}).join('')+'</select>';
    else if(f.type==='image')h+='<div class="lb-img">'+(v?'<img src="'+esc(v)+'" alt="">':'')+'<input class="fc" placeholder="Bild-Adresse" value="'+esc(v)+'"'+at+'><div class="lb-imgbtn"><button type="button" class="btn-g" data-pick="'+f.k+'">Bild wählen</button>'+(v?'<button type="button" class="btn-g" data-clear="'+f.k+'">Entfernen</button>':'')+'</div></div>';
    else if(f.type==='items'){var it=Array.isArray(v)?v:[];
      h+='<div class="lb-items">'+it.map(function(x,i){return '<div class="lb-card"><input class="fc" placeholder="Titel" maxlength="120" value="'+esc(x.title)+'" data-k="'+f.k+'" data-i="'+i+'" data-f="title">'
        +'<textarea class="fc" rows="2" placeholder="Text" maxlength="400" data-k="'+f.k+'" data-i="'+i+'" data-f="text">'+esc(x.text)+'</textarea><button type="button" class="btn-g" data-itemdel="'+f.k+'|'+i+'" aria-label="Karte löschen"><i class="fas fa-trash"></i></button></div>'}).join('')
        +(it.length<(f.max||6)?'<button type="button" class="btn-g" data-itemadd="'+f.k+'"><i class="fas fa-plus"></i> Karte</button>':'')+'</div>';}
    var cdef=fdef(s.type,f.k);
    if(cdef&&cdef.responsive&&(f.type==='number'||f.type==='select'||f.type==='checkbox')&&tab==='design'){
      var rv=s.responsive||{};
      h+='<div class="lb-resp">'+['tablet','mobile'].map(function(d){var cur=(rv[d]||{})[f.k];
        var inp=f.type==='select'?'<select class="fc" data-k="'+f.k+'" data-dev="'+d+'"><option value="">erbt</option>'+Object.keys(f.options).map(function(o){return '<option value="'+esc(o)+'"'+(cur!==undefined&&String(cur)===o?' selected':'')+'>'+esc(f.options[o])+'</option>'}).join('')+'</select>'
          :'<input class="fc" type="number" min="'+f.min+'" max="'+f.max+'" placeholder="erbt" value="'+(cur===undefined?'':esc(cur))+'" data-k="'+f.k+'" data-dev="'+d+'">';
        return '<label><span>'+(d==='tablet'?'Tablet':'Mobil')+'</span>'+inp+'</label>'}).join('')+'</div>';
    }
    return h+'</div>';
  }
  function visPanel(s){
    var v=Object.assign({devices:['desktop','tablet','mobile'],audience:'all',from:'',until:''},s.visibility||{});
    var dv={desktop:'Desktop',tablet:'Tablet',mobile:'Mobil'};
    var h='<div class="lb-field"><label>Auf diesen Geräten zeigen</label><div class="lb-devs">'+Object.keys(dv).map(function(k){return '<label class="lb-sw"><input class="switch" type="checkbox" data-vis="dev" data-v="'+k+'"'+(v.devices.indexOf(k)>=0?' checked':'')+'><span>'+dv[k]+'</span></label>'}).join('')+'</div></div>';
    h+='<div class="lb-field"><label for="lbvAud">Wer sieht den Bereich?</label><select class="fc" id="lbvAud" data-vis="audience"><option value="all"'+(v.audience==='all'?' selected':'')+'>Alle</option><option value="guests"'+(v.audience==='guests'?' selected':'')+'>Nur Besucher (nicht angemeldet)</option><option value="members"'+(v.audience==='members'?' selected':'')+'>Nur Angemeldete</option></select></div>';
    h+='<div class="lb-field"><label for="lbvFrom">Sichtbar ab</label><input class="fc" type="datetime-local" id="lbvFrom" data-vis="from" value="'+esc((v.from||'').replace(' ','T'))+'"></div>';
    h+='<div class="lb-field"><label for="lbvUntil">Sichtbar bis</label><input class="fc" type="datetime-local" id="lbvUntil" data-vis="until" value="'+esc((v.until||'').replace(' ','T'))+'"></div>';
    return h+'<p class="hint">Ohne Angaben ist der Bereich immer sichtbar. Geräteabhängige Werte (Höhe, Spalten) stehen im Reiter „Design“.</p>';
  }
  function drawFields(){
    var h=$('lbFields'),t=$('lbSetTitle'),i=sel?find(sel):-1;if(!h)return;
    if(i<0){t.textContent='Bereich bearbeiten';h.innerHTML='<div class="hint" style="padding:12px">Wähle links einen Bereich, um ihn zu bearbeiten.</div>';return}
    var s=layout[i],sc=schema[s.type]||{label:s.type,fields:[]};t.textContent=sc.label+' bearbeiten';
    if(tab==='visibility'){h.innerHTML=visPanel(s);return}
    var fs=(sc.fields||[]).filter(function(f){return tab==='design'?grp(s.type,f.k)==='design':grp(s.type,f.k)!=='design'});
    if(!fs.length&&tab==='design'){h.innerHTML='<div class="hint" style="padding:12px">Für diesen Bereich gibt es keine Design-Einstellungen.</div>';return}
    h.innerHTML=fs.map(function(f){return field(s,f)}).join('');
  }
  function draw(){drawList();drawFields()}
  function palette(){
    var p=$('lbPalette'),groups={},order=[];
    Object.keys(schema).forEach(function(t){var c=(cat[t]||{}).category||'content';if(!groups[c]){groups[c]=[];order.push(c)}groups[c].push(t)});
    var keys=Object.keys(catCats).filter(function(k){return groups[k]}).concat(order.filter(function(k){return !catCats[k]}));
    p.innerHTML=keys.map(function(c){return '<div class="lb-pgroup">'+esc(catCats[c]||c)+'</div>'+groups[c].map(function(t){return '<button type="button" data-add="'+t+'" title="'+esc((cat[t]||{}).description||'')+'"><i class="fas '+schema[t].icon+'"></i> '+esc(schema[t].label)+'</button>'}).join('')}).join('');
  }
  function togglePalette(show){var p=$('lbPalette');p.hidden=show===undefined?!p.hidden:!show}
  function pop(){document.querySelectorAll('#panel-livebuilder .lb-pop').forEach(function(x){x.hidden=true})}

  function setFrame(){
    var f=$('lbFrame');if(!f||!previewUrl)return;
    f.src=previewUrl+(previewUrl.indexOf('?')<0?'?':'&')+'rrw_bk_t='+Date.now();$('lbUrl').textContent=location.origin+'/';
  }
  async function getPreview(){try{var d=await cmsApi('wp_theme_preview',{slug:'elvado-baukasten'});if(d.url){previewUrl=d.url;setFrame()}}catch(e){state(e.message||'Vorschau nicht verfügbar',true)}}
  function notice(){var n=$('lbNotice');if(active){n.style.display='none';return}n.style.display='';n.innerHTML='Das Theme „ElvadoPress Baukasten“ ist noch nicht aktiv. Du kannst hier bauen und die Vorschau nutzen; zum Veröffentlichen <a href="#" data-activate="1">Theme aktivieren</a>.'}
  async function load(){
    try{
      if(!Object.keys(cat).length)await loadCatalog();
      var d=await cmsApi('wp_bk_get');schema=d.schema||{};layout=d.layout||[];active=!!d.active;hasDraft=!!d.draft;loaded=true;dirty=false;
      if(!sel||find(sel)<0)sel=layout.length?layout[0].id:null;
      palette();draw();notice();state(hasDraft?'Entwurf geladen (noch nicht veröffentlicht)':'');if(!previewUrl)getPreview();else setFrame();
    }catch(e){state(e.message||'Laden fehlgeschlagen',true)}
  }
  async function saveDraft(){
    clearTimeout(timer);if(saving){pending=true;return}saving=true;
    try{state('Speichere Entwurf…');var d=await cmsApi('wp_bk_draft',{layout:layout});hasDraft=true;dirty=false;state('Entwurf gespeichert');setFrame()}
    catch(e){state(e.message||'Speichern fehlgeschlagen',true)}
    saving=false;if(pending){pending=false;saveDraft()}
  }
  async function publish(){
    clearTimeout(timer);
    try{state('Veröffentliche…');var d=await cmsApi('wp_bk_publish',{layout:layout});layout=d.layout||layout;hasDraft=false;dirty=false;active=!!d.active;notice();draw();state('Veröffentlicht');setFrame()}
    catch(e){state(e.message||'Veröffentlichen fehlgeschlagen',true)}
  }
  async function discard(){
    if(!confirm('Den Entwurf verwerfen? Die veröffentlichte Startseite bleibt unverändert.'))return;
    try{var d=await cmsApi('wp_bk_discard',{});layout=d.layout||[];hasDraft=false;dirty=false;sel=layout.length?layout[0].id:null;draw();state('Entwurf verworfen');setFrame()}catch(e){state(e.message,true)}
  }
  async function reset(){
    if(!confirm('Eigenes Layout verwerfen? Die Startseite folgt dann wieder den Positionen im Customizer.'))return;
    try{await cmsApi('wp_bk_discard',{});var d=await cmsApi('wp_bk_save',{reset:true});layout=d.layout||[];hasDraft=false;dirty=false;sel=layout.length?layout[0].id:null;draw();state('Zurückgesetzt');setFrame()}catch(e){state(e.message,true)}
  }
  function move(from,to){if(from<0||to<0||to>=layout.length||from===to)return;layout.splice(to,0,layout.splice(from,1)[0]);changed();draw()}

  function onClick(e){
    var t=e.target;
    var md=t.closest('[data-lbmode]');if(md){mode(md.dataset.lbmode);return}
    var dv=t.closest('[data-lbdev]');if(dv){device(dv.dataset.lbdev);return}
    var tb=t.closest('[data-lbtab]');if(tb){tab=tb.dataset.lbtab;document.querySelectorAll('#panel-livebuilder [data-lbtab]').forEach(function(b){b.classList.toggle('on',b===tb)});drawFields();return}
    var mo=t.closest('[data-lbmore]');if(mo){pop();var a=mo.dataset.lbmore;if(a==='discard')discard();else if(a==='reset')reset();else if(a==='ai')aiLayout();else if(a==='customizer')customizer();return}
    if(t.closest('#lbMore')){var p=$('lbMorePop'),was=p.hidden;pop();p.hidden=!was;e.stopPropagation();return}
    if(t.closest('#lbDraft')){saveDraft();return}
    if(t.closest('#lbPublish')){publish();return}
    if(t.closest('#lbReload')){setFrame();return}
    if(t.closest('#lbAdd')||t.closest('#lbAddTop')){togglePalette();return}
    var ad=t.closest('[data-add]');
    if(ad){var ty=ad.dataset.add,s={id:uid(),type:ty,hidden:false,props:defaults(ty)};var at=sel?find(sel):-1;layout.splice(at>=0?at+1:layout.length,0,s);sel=s.id;tab='content';togglePalette(false);changed();draw();return}
    var a=t.closest('[data-act]');
    if(a){var id=a.dataset.id,i=find(id),act=a.dataset.act;
      if(act==='menu'){var p2=document.querySelector('#lbList [data-pop="'+id+'"]'),w=p2.hidden;pop();p2.hidden=!w;e.stopPropagation();return}
      pop();
      if(act==='up')move(i,i-1);else if(act==='down')move(i,i+1);
      else if(act==='hide'){layout[i].hidden=!layout[i].hidden;changed();draw()}
      else if(act==='dup'){var c=JSON.parse(JSON.stringify(layout[i]));c.id=uid();layout.splice(i+1,0,c);sel=c.id;changed();draw()}
      else if(act==='del'){if(confirm('Bereich löschen?')){layout.splice(i,1);if(sel===id)sel=layout.length?layout[Math.min(i,layout.length-1)].id:null;changed();draw()}}
      return}
    var it=t.closest('.lb-item');if(it){sel=it.dataset.id;drawList();drawFields();if(document.querySelector('.lb-grid').dataset.lbview==='structure')mode('settings');return}
    var pk=t.closest('[data-pick]');if(pk){var k=pk.dataset.pick,i1=find(sel);if(i1>=0&&window.HomeBuilder)HomeBuilder.pickImage(function(u){var j=find(sel);if(j>=0){layout[j].props[k]=u;changed();drawFields()}});return}
    var cl=t.closest('[data-clear]');if(cl){var i4=find(sel);if(i4>=0){layout[i4].props[cl.dataset.clear]='';changed();drawFields()}return}
    var ia=t.closest('[data-itemadd]');if(ia){var i2=find(sel);layout[i2].props[ia.dataset.itemadd].push({title:'',text:''});changed();drawFields();return}
    var idl=t.closest('[data-itemdel]');if(idl){var q=idl.dataset.itemdel.split('|'),i3=find(sel);layout[i3].props[q[0]].splice(+q[1],1);changed();drawFields();return}
    var ac=t.closest('[data-activate]');
    if(ac){e.preventDefault();cmsApi('wp_theme_activate',{slug:'elvado-baukasten'}).then(function(){active=true;notice();state('Theme aktiviert');setFrame()}).catch(function(x){state(x.message,true)});return}
  }
  function onInput(e){
    var el=e.target;
    if(el.dataset&&el.dataset.vis&&el.closest('#lbFields')){
      var i0=find(sel);if(i0<0)return;var s0=layout[i0],v=s0.visibility=Object.assign({devices:['desktop','tablet','mobile'],audience:'all',from:'',until:''},s0.visibility||{});
      if(el.dataset.vis==='dev'){var set=v.devices.filter(function(x){return x!==el.dataset.v});if(el.checked)set.push(el.dataset.v);v.devices=['desktop','tablet','mobile'].filter(function(x){return set.indexOf(x)>=0})}
      else v[el.dataset.vis]=el.dataset.vis==='audience'?el.value:el.value.replace('T',' ');
      changed();return;
    }
    if(el.dataset&&el.dataset.dev&&el.closest('#lbFields')){
      var i1=find(sel);if(i1<0)return;var s1=layout[i1];s1.responsive=s1.responsive||{};var r=s1.responsive[el.dataset.dev]=s1.responsive[el.dataset.dev]||{};
      if(el.value==='')delete r[el.dataset.k];else r[el.dataset.k]=el.type==='number'?parseInt(el.value,10):el.value;
      if(!Object.keys(r).length)delete s1.responsive[el.dataset.dev];changed();return;
    }if(!el.dataset||el.dataset.k===undefined||!el.closest('#lbFields'))return;var i=find(sel);if(i<0)return;var s=layout[i],k=el.dataset.k;
    var v=el.type==='checkbox'?el.checked:el.type==='number'?parseInt(el.value||'0',10):el.value;
    if(el.dataset.i!==undefined)s.props[k][+el.dataset.i][el.dataset.f]=v;else s.props[k]=v;
    if(el.type==='checkbox'){var sp=el.parentNode.querySelector('span');if(sp)sp.textContent=v?'An':'Aus'}
    var sm=document.querySelector('#lbList .lb-item.on .lb-name small');if(sm)sm.textContent=summary(s);
    changed();
  }
  /* Layout aus der KI: nur bekannte Typen und Felder übernehmen (endgültige Bereinigung serverseitig) */
  function applyAi(sections){
    if(!Array.isArray(sections)||!sections.length)return;var fresh=[];
    sections.forEach(function(x){
      if(!x||!schema[x.type])return;var props=defaults(x.type),known={};(schema[x.type].fields||[]).forEach(function(f){known[f.k]=f});
      Object.keys(x.props||{}).forEach(function(k){var f=known[k],v=x.props[k];if(!f)return;
        if(f.type==='items')props[k]=Array.isArray(v)?v.slice(0,f.max||6).map(function(i){return {title:String((i&&i.title)||''),text:String((i&&i.text)||'')}}):[];
        else if(f.type==='checkbox')props[k]=!!v;else if(f.type==='number')props[k]=parseInt(v,10)||0;
        else if(f.type==='select')props[k]=f.options&&f.options[String(v)]!==undefined?String(v):props[k];
        else if(f.type==='image'||f.type==='url')props[k]=/^(https:\/\/|\/)/.test(String(v))?String(v):'';
        else props[k]=String(v==null?'':v)});
      fresh.push({id:uid(),type:x.type,hidden:false,props:props});
    });
    if(!fresh.length){state('Die KI hat keine verwertbaren Bereiche geliefert.',true);return}
    if(layout.length&&confirm('Das bestehende Layout durch den KI-Entwurf ersetzen?\n\nOK = ersetzen, Abbrechen = unten anhängen.'))layout=fresh;else layout=layout.concat(fresh);
    sel=fresh[0].id;changed();draw();
  }
  function aiLayout(){if(!window.EpAi)return state('KI-Oberfläche nicht verfügbar',true);EpAi.layoutAssistant(applyAi)}
  function mode(m){document.querySelector('#panel-livebuilder .lb-grid').dataset.lbview=m;document.querySelectorAll('#panel-livebuilder [data-lbmode]').forEach(function(b){b.classList.toggle('on',b.dataset.lbmode===m)})}
  function device(d){var f=$('lbFrame');f.dataset.dev=d;document.querySelectorAll('#panel-livebuilder [data-lbdev]').forEach(function(b){b.classList.toggle('on',b.dataset.lbdev===d)})}
  function customizer(){
    var go=function(){if(window.cmsTab)cmsTab('themes',document.querySelector('.tab[data-tab="themes"]'));if(window.DesignHub&&DesignHub.load)DesignHub.load();
      Promise.resolve(window.WpThemes&&WpThemes.load&&WpThemes.load()).then(function(){if(WpThemes.customizeSlug)WpThemes.customizeSlug('elvado-baukasten')}).catch(function(){})};
    if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;go();
  }
  function bind(){
    var root=$('panel-livebuilder');if(!root||root.dataset.bound)return;root.dataset.bound='1';
    root.addEventListener('click',onClick);root.addEventListener('input',onInput);root.addEventListener('change',onInput);
    document.addEventListener('click',function(e){if(!e.target.closest('.lb-more'))pop()});
    var host=$('lbList');
    host.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.classList.contains('lb-item')){e.preventDefault();sel=e.target.dataset.id;drawList();drawFields()}});
    host.addEventListener('dragstart',function(e){var s=e.target.closest&&e.target.closest('.lb-item');if(!s)return;dragId=s.dataset.id;s.classList.add('dragging');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',dragId)}catch(x){}});
    host.addEventListener('dragend',function(){dragId=null;host.querySelectorAll('.dragging,.drag-over').forEach(function(x){x.classList.remove('dragging','drag-over')})});
    host.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();var s=e.target.closest('.lb-item');host.querySelectorAll('.drag-over').forEach(function(x){x.classList.remove('drag-over')});if(s&&s.dataset.id!==dragId)s.classList.add('drag-over')});
    host.addEventListener('drop',function(e){if(!dragId)return;e.preventDefault();var s=e.target.closest('.lb-item');if(s&&s.dataset.id!==dragId)move(find(dragId),find(s.dataset.id));dragId=null});
    window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue=''}});
  }
  window.LiveBuilder={load:function(){bind();load()},publish:publish,saveDraft:saveDraft};
})();
