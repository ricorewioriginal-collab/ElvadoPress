/* Live Builder (Website → Live Builder): Seitenstruktur (mit verschachtelten Komponenten), Einstellungen des gewählten Bereichs, Live-Vorschau mit Vorschau-Brücke (Anklicken, Werkzeugleiste).
   Server: wp_bk_get / wp_bk_draft / wp_bk_publish / wp_bk_discard / wp_bk_rollback / wp_bk_save (cms/api.php); Komponenten-Katalog: components-api.php.
   Änderungen gehen zuerst in einen Entwurf (nur in der geprüften Vorschau sichtbar); „Veröffentlichen“ macht ihn öffentlich; Fassungen lassen sich zurückholen, die Veröffentlichung lässt sich planen.
   Vorschau-Brücke: postMessage nur zwischen Builder und Vorschau-Frame, Herkunft und Frame werden geprüft, Befehle tragen einen Sitzungsschlüssel (siehe preview-bridge.js). */
(function(){
  'use strict';
  var layout=[],schema={},sel=null,tab='content',active=false,hasDraft=false,publishAt='',revisions=[],dirty=false,saving=false,pending=false,dragId=null,timer=null,previewUrl='';
  var target=null,targets=[];   /* target: Bearbeitungsziel eines Pakets (bestehende Bereiche der Website, nur Gestaltung/Sichtbarkeit); null = Startseite des Themes */
  var cat={},catCats={},bridge={token:'',scroll:0,ready:false,edit:true};
  var REGIONS=[{id:'header',selector:'header.site-header,#masthead,.site-header',label:'Header'},{id:'footer',selector:'footer.site-footer,#colophon,.site-footer',label:'Footer'},{id:'menu',selector:'nav.main-navigation,.main-navigation,#site-navigation',label:'Navigation'}];
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function uid(){return 's'+Math.random().toString(36).slice(2,10)}
  function rnd(){var a=new Uint8Array(18);(window.crypto||window.msCrypto).getRandomValues(a);return Array.prototype.map.call(a,function(x){return ('0'+x.toString(16)).slice(-2)}).join('')}
  function state(t,bad){var e=$('lbState');if(e){e.textContent=t||'';e.className='lb-state'+(bad?' bad':'')}}
  function fdef(type,k){var c=cat[type];if(!c)return null;for(var i=0;i<c.fields.length;i++)if(c.fields[i].k===k)return c.fields[i];return null}
  function grp(type,k){var f=fdef(type,k);return f?f.group:'content'}
  function rules(type){return (cat[type]||{}).rules||{}}
  async function loadCatalog(){
    try{var tk=sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
      var r=await fetch('/cms/components-api.php?action=components_catalog',{headers:{'X-AnMaCha-Token':tk}}),d=await r.json();
      if(d.status==='ok'){cat={};(d.components||[]).forEach(function(c){cat[c.id]=c});catCats=d.categories||{};targets=d.targets||[];targetUi()}}catch(e){}
  }

  /* ───────── Bearbeitungsziel (Paket): bestehende Bereiche der Website ───────── */
  async function capi(action,body){
    var url='/cms/components-api.php?action='+action+'&scope='+encodeURIComponent(target.scope)+'&_='+Date.now();
    var opts=body===undefined?{headers:cmsHeaders(false)}:{method:'POST',headers:cmsHeaders(true),body:JSON.stringify(Object.assign({scope:target.scope},body))};
    var r=await fetch(url,opts),d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(d.status!=='ok')throw new Error(d.message||'Fehler');return d;
  }
  function targetUi(){
    var sl=$('lbTarget');if(!sl)return;
    sl.hidden=!targets.length;
    var cb=window.CMS_BRAND||'';var vis=targets.filter(function(t){return !t.brand||!cb||t.brand===cb||(target&&target.id===t.id)});
    sl.innerHTML='<option value="">Startseite (Theme)</option>'+vis.map(function(t){return '<option value="'+esc(t.id)+'"'+(target&&target.id===t.id?' selected':'')+'>'+esc(t.label)+'</option>'}).join('');
    ['lbAdd','lbAddTop'].forEach(function(i){var b=$(i);if(b)b.hidden=!!target});
    ['reset','ai','customizer'].forEach(function(k){var b=document.querySelector('#lbMorePop [data-lbmore="'+k+'"]');if(b)b.hidden=!!target});
  }
  function switchTarget(id){
    if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?')){targetUi();return}
    target=targets.filter(function(t){return t.id===id})[0]||null;previewUrl='';sel=null;layout=[];targetUi();regionNote('');load();
  }
  window.addEventListener('cms:brand',function(e){
    var id=e.detail&&e.detail.id;if(!id||!targets.length)return;
    var t=targets.filter(function(x){return x.brand===id})[0];
    if(t){if(!target||target.id!==t.id)switchTarget(t.id)}else if(target&&target.brand&&target.brand!==id)switchTarget('');else targetUi();
  });
  function targetSchema(){
    schema={};Object.keys(cat).forEach(function(k){var c=cat[k];if(c.bind&&(c.source===target.source||k==='ep_area'))schema[k]={label:c.name,icon:'fa-'+c.icon,fields:c.fields}});
  }
  function targetDefaults(){
    return Object.keys(schema).filter(function(t){return t!=='ep_area'}).map(function(t){return {id:uid(),type:t,hidden:false,props:defaults(t)}});
  }
  async function loadTarget(){
    targetSchema();
    var d=await capi('layout_get'),src=d.draft||d.published;
    layout=src&&Array.isArray(src.layout)?src.layout:[];
    var have={};layout.forEach(function(x){have[x.type]=1});
    Object.keys(schema).forEach(function(t){if(!have[t]&&t!=='ep_area')layout.push({id:uid(),type:t,hidden:false,props:defaults(t)})});   // neue Bereiche des Pakets ergänzen
    layout=layout.filter(function(x){return schema[x.type]});
    var ord=function(x){var o=((cat[x.type]||{}).data||{}).order;return typeof o==='number'?o:999};
    layout.sort(function(a,b){return ord(a)-ord(b)});   /* Reihenfolge der Website, nicht der Kategorien */
    active=true;hasDraft=!!d.draft;publishAt=(d.draft&&d.draft.publish_at)||'';revisions=d.revisions||[];dirty=false;
    if(!sel||!locate(sel))sel=layout.length?layout[0].id:null;
    palette();draw();notice();badge();state(hasDraft?'Entwurf geladen (noch nicht veröffentlicht)':'');
    if(!previewUrl)getPreview();else setFrame();
  }

  /* ───────── Baum ───────── */
  function locate(id,list,parent){
    list=list||layout;
    for(var i=0;i<list.length;i++){
      if(list[i].id===id)return {list:list,idx:i,node:list[i],parent:parent||null};
      if(list[i].children){var r=locate(id,list[i].children,list[i]);if(r)return r}
    }
    return null;
  }
  function inside(node,id){if(node.id===id)return true;return (node.children||[]).some(function(c){return inside(c,id)})}
  function cloneNew(n){var c=JSON.parse(JSON.stringify(n));(function re(x){x.id=uid();(x.children||[]).forEach(re)})(c);return c}
  function canAccept(parent,type){
    if(!parent)return !(rules(type).parents&&rules(type).parents.length&&rules(type).parents.indexOf('root')<0);
    var pr=rules(parent.type);if(!pr.droppable)return false;
    var acc=pr.accepts||[];if(acc.length&&acc.indexOf(type)<0&&acc.indexOf('category:'+(cat[type]||{}).category)<0)return false;
    var par=rules(type).parents||[];return !par.length||par.indexOf(parent.type)>=0;
  }
  function defaults(type){var o={};((schema[type]||{}).fields||[]).forEach(function(f){o[f.k]=f.default!==undefined?f.default:(f.type==='checkbox'?false:f.type==='items'?[]:f.type==='number'?0:'')});
    if(type==='features')o.items=[{title:'Schnell',text:'Kurze Ladezeiten ohne Ballast.'},{title:'Flexibel',text:'Alles lässt sich anpassen.'},{title:'Eigenständig',text:'Deine Inhalte, dein Design.'}];return o}
  function summary(s){var p=s.props||{};return String(p.title||p.label||p.text||p.body||p.code||p.shortcode||'').replace(/<[^>]*>/g,'').slice(0,60)}
  function changed(){dirty=true;state(publishAt?'Ungespeicherte Änderungen (Veröffentlichung geplant)':'Ungespeicherte Änderungen');clearTimeout(timer);timer=setTimeout(saveDraft,900)}

  function itemHtml(s,depth,i,len){
    var sc=schema[s.type]||{label:s.type,icon:'fa-square'},fixed=rules(s.type).locked;if(s.type==='ep_area')sc={label:(s.props&&s.props.label)||sc.label,icon:sc.icon};
    return '<div class="lb-item'+(s.id===sel?' on':'')+(s.hidden?' off':'')+'" style="margin-left:'+(depth*14)+'px" draggable="'+(fixed?'false':'true')+'" data-id="'+s.id+'" tabindex="0" role="button" aria-label="'+esc(sc.label)+' bearbeiten">'
      +'<span class="lb-grip" title="Ziehen zum Sortieren"><i class="fas fa-grip-vertical"></i></span><i class="fas '+sc.icon+' lb-ico"></i>'
      +'<span class="lb-name">'+esc(sc.label)+'<small>'+esc(summary(s))+'</small></span>'
      +'<button type="button" class="lb-ic" data-act="hide" data-id="'+s.id+'" aria-label="'+(s.hidden?'Einblenden':'Ausblenden')+'"><i class="fas '+(s.hidden?'fa-eye-slash':'fa-eye')+'"></i></button>'
      +'<span class="lb-more"><button type="button" class="lb-ic" data-act="menu" data-id="'+s.id+'" aria-label="Mehr"><i class="fas fa-ellipsis-vertical"></i></button>'
      +'<div class="lb-pop" data-pop="'+s.id+'" hidden><button type="button" data-act="up" data-id="'+s.id+'"'+(i===0?' disabled':'')+'><i class="fas fa-arrow-up"></i>Nach oben</button>'
      +'<button type="button" data-act="down" data-id="'+s.id+'"'+(i===len-1?' disabled':'')+'><i class="fas fa-arrow-down"></i>Nach unten</button>'
      +'<button type="button" data-act="dup" data-id="'+s.id+'"><i class="fas fa-copy"></i>Duplizieren</button>'
      +'<button type="button" data-act="del" data-id="'+s.id+'"><i class="fas fa-trash"></i>Löschen</button></div></span></div>';
  }
  function listHtml(list,depth){
    return list.map(function(s,i){
      return itemHtml(s,depth,i,list.length)+(s.children?listHtml(s.children,depth+1)+(rules(s.type).droppable?'<button type="button" class="lb-inner" style="margin-left:'+((depth+1)*14)+'px" data-addin="'+s.id+'"><i class="fas fa-plus"></i> Hier hinein</button>':''):'');
    }).join('');
  }
  function drawList(){
    var h=$('lbList');if(!h)return;
    h.innerHTML=layout.length?listHtml(layout,0):'<div class="hint" style="padding:10px">Noch keine Bereiche. Füge unten einen hinzu.</div>';
  }
  function field(s,f){
    var v=(s.props||{})[f.k],id='lbf_'+f.k,at=' data-k="'+f.k+'" id="'+id+'"',h='<div class="lb-field"><label for="'+id+'">'+esc(f.label)+'</label>';
    if(f.type==='text')h+='<input class="fc" maxlength="300" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='url')h+='<input class="fc" placeholder="https://… oder /seite" value="'+esc(v)+'"'+at+'>';
    else if(f.type==='textarea')h+='<textarea class="fc" rows="5"'+at+'>'+esc(v)+'</textarea>';
    else if(f.type==='number'){var un=(f.css&&f.css.unit)||'',rg=(f.max-f.min)<=1000&&f.max>f.min;
      h+=rg?'<div class="lb-range"><input type="range" min="'+f.min+'" max="'+f.max+'" value="'+esc(v)+'" data-k="'+f.k+'" data-slider="1" aria-label="'+esc(f.label)+'"><div class="lb-num"><input type="number" min="'+f.min+'" max="'+f.max+'" value="'+esc(v)+'"'+at+'>'+(un?'<span>'+esc(un)+'</span>':'')+'</div></div>'
        :'<input class="fc" type="number" min="'+f.min+'" max="'+f.max+'" value="'+esc(v)+'"'+at+'>'}
    else if(f.type==='color')h+='<div class="lb-color"><input type="color" value="'+(/^#[0-9a-f]{6}$/i.test(v)?esc(v):'#000000')+'" data-colorfor="'+f.k+'" aria-label="Farbe wählen"><input class="fc" placeholder="#rrggbb (leer = Standard)" maxlength="7" value="'+esc(v)+'"'+at+'></div>';
    else if(f.type==='checkbox')return '<div class="lb-field lb-row"><label for="'+id+'">'+esc(f.label)+'</label><label class="lb-tg"><input type="checkbox"'+(v?' checked':'')+at+'><i></i></label></div>';
    else if(f.type==='select')h+='<select class="fc"'+at+'>'+Object.keys(f.options).map(function(k){return '<option value="'+esc(k)+'"'+(String(v)===k?' selected':'')+'>'+esc(f.options[k])+'</option>'}).join('')+'</select>';
    else if(f.type==='image')h+='<div class="lb-img">'+(v?'<img src="'+esc(v)+'" alt="">':'')+'<input class="fc" placeholder="Adresse (https://… oder /…)" value="'+esc(v)+'"'+at+'><div class="lb-imgbtn"><button type="button" class="btn-g" data-pick="'+f.k+'">Bild wählen</button>'+(v?'<button type="button" class="btn-g" data-clear="'+f.k+'">Entfernen</button>':'')+'</div></div>';
    else if(f.type==='items'){
      var it=Array.isArray(v)?v:[],sub=(f.item&&f.item.length)?f.item:[{k:'title',label:'Titel',type:'text'},{k:'text',label:'Text',type:'textarea'}];
      h+='<div class="lb-items">'+it.map(function(x,i){
        return '<div class="lb-card">'+sub.map(function(sf){var val=x[sf.k];var ph=esc(sf.label);
          return sf.type==='textarea'?'<textarea class="fc" rows="2" placeholder="'+ph+'" data-k="'+f.k+'" data-i="'+i+'" data-f="'+sf.k+'">'+esc(val)+'</textarea>':'<input class="fc" placeholder="'+ph+'" maxlength="300" value="'+esc(val)+'" data-k="'+f.k+'" data-i="'+i+'" data-f="'+sf.k+'">'}).join('')
          +'<button type="button" class="btn-g" data-itemdel="'+f.k+'|'+i+'" aria-label="Eintrag löschen"><i class="fas fa-trash"></i></button></div>'}).join('')
        +(it.length<(f.max||6)?'<button type="button" class="btn-g" data-itemadd="'+f.k+'"><i class="fas fa-plus"></i> Eintrag</button>':'')+'</div>';}
    var cdef=fdef(s.type,f.k);
    if(cdef&&cdef.responsive&&(f.type==='number'||f.type==='select')&&tab==='design'){
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
    var h='<div class="lb-field"><label>Auf diesen Geräten zeigen</label><div class="lb-devs">'+Object.keys(dv).map(function(k){return '<div class="lb-row"><label>'+dv[k]+'</label><label class="lb-tg"><input type="checkbox" data-vis="dev" data-v="'+k+'"'+(v.devices.indexOf(k)>=0?' checked':'')+'><i></i></label></div>'}).join('')+'</div></div>';
    h+='<div class="lb-field"><label for="lbvAud">Wer sieht den Bereich?</label><select class="fc" id="lbvAud" data-vis="audience"><option value="all"'+(v.audience==='all'?' selected':'')+'>Alle</option><option value="guests"'+(v.audience==='guests'?' selected':'')+'>Nur Besucher (nicht angemeldet)</option><option value="members"'+(v.audience==='members'?' selected':'')+'>Nur Angemeldete</option></select></div>';
    h+='<div class="lb-field"><label for="lbvFrom">Sichtbar ab</label><input class="fc" type="datetime-local" id="lbvFrom" data-vis="from" value="'+esc((v.from||'').replace(' ','T'))+'"></div>';
    h+='<div class="lb-field"><label for="lbvUntil">Sichtbar bis</label><input class="fc" type="datetime-local" id="lbvUntil" data-vis="until" value="'+esc((v.until||'').replace(' ','T'))+'"></div>';
    return h+'<p class="hint">Ohne Angaben ist der Bereich immer sichtbar. Geräteabhängige Werte (Höhe, Spalten) stehen im Reiter „Design“.</p>';
  }
  function drawFields(){
    var h=$('lbFields'),t=$('lbSetTitle'),loc=sel?locate(sel):null;if(!h)return;
    if(!loc){t.textContent='Bereich bearbeiten';h.innerHTML='<div class="hint" style="padding:12px">Wähle links oder in der Vorschau einen Bereich, um ihn zu bearbeiten.</div>';return}
    var s=loc.node,sc=schema[s.type]||{label:s.type,fields:[]};t.textContent=sc.label+' bearbeiten';
    var fs=(sc.fields||[]).filter(function(f){var g=grp(s.type,f.k);return tab==='design'?g==='design':tab==='visibility'?g==='behavior':g!=='design'&&g!=='behavior'});
    var sec='',body=fs.map(function(f){var hd='';if(f.section&&f.section!==sec){sec=f.section;hd='<div class="lb-sec">'+esc(sec)+'</div>'}return hd+field(s,f)}).join('');
    if(tab==='visibility'){h.innerHTML=body+(body?'<div class="lb-sec">Sichtbarkeit</div>':'')+visPanel(s);return}
    if(!fs.length&&tab==='design'){h.innerHTML='<div class="hint" style="padding:12px">Für diesen Bereich gibt es keine Design-Einstellungen.</div>';return}
    if(!fs.length&&tab==='content'){h.innerHTML='<div class="hint" style="padding:12px">'+(target?'Texte und Inhalte dieses Bereichs pflegst du wie bisher in der jeweiligen Verwaltung. Hier gestaltest du ihn im Reiter „Design“ und steuerst, was angezeigt wird, unter „Verhalten“.':'Dieser Bereich hat keine Inhaltsfelder.')+'</div>';return}
    h.innerHTML=body;
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
  function regionNote(id){
    var n=$('lbRegion');if(!n)return;
    if(!id){n.hidden=true;return}
    var r=REGIONS.filter(function(x){return x.id===id})[0],label=r?r.label:id;
    n.hidden=false;n.innerHTML='<b>'+esc(label)+'</b> gehört zum Theme. Logo, Menüs und Farben stellst du im Customizer ein. <button type="button" class="btn-g" data-lbmore="customizer">Customizer öffnen</button>';
  }

  /* ───────── Vorschau und Brücke ───────── */
  function frame(){return $('lbFrame')}
  function send(m){var f=frame();if(!f||!f.contentWindow||!bridge.token)return;m.ep=1;m.token=bridge.token;try{f.contentWindow.postMessage(m,location.origin)}catch(e){}}
  function typeLabels(){var o={};Object.keys(schema).forEach(function(t){o[t]=schema[t].label});return o}
  function bridgeRegions(){if(!target)return REGIONS;return layout.filter(function(x){return cat[x.type]&&cat[x.type].bind&&regionSel(x)}).map(function(x){return {id:x.id,selector:regionSel(x),label:x.type==='ep_area'?((x.props||{}).label||(schema[x.type]||{}).label):((schema[x.type]||{}).label||x.type)}})}

  /* ───────── Erkannte Seitenstruktur (Scanner der Vorschau) ───────── */
  var SELRE=/^([#.][a-z][a-z0-9_-]{0,60}|header|footer|nav|main|aside|section|article)$/i,detected=[],detSel='';
  function regionSel(x){var c=cat[x.type];if(!c||!c.bind)return '';var q=c.bind==='*'?String((x.props||{}).selector||''):c.bind;return SELRE.test(q)?q:''}
  function coveredBy(selector){var hit=null;(function w(l){l.forEach(function(x){if(!hit&&regionSel(x)===selector)hit=x;if(x.children)w(x.children)})})(layout);return hit}
  function toFrame(m){var f=frame();if(!f||!f.contentWindow||!bridge.token)return;m.ep=1;m.token=bridge.token;f.contentWindow.postMessage(m,location.origin)}
  function drawDetected(){
    var box=$('lbDet');if(!box)return;
    box.hidden=!detected.length;if(!detected.length)return;
    $('lbDetN').textContent=detected.length+' Bereiche';
    $('lbDetHint').textContent=target?'Live aus der Vorschau gelesen. „Gestalten“ nimmt einen Bereich in die Seitenstruktur auf (Farben, Abstände, Höhe, Sichtbarkeit).':'Live aus der Vorschau gelesen: Klick markiert den Bereich in der Vorschau.';
    $('lbDetList').innerHTML=detected.map(function(n,i){
      var cov=coveredBy(n.s),on=n.s===detSel;
      return '<div class="lb-detrow'+(on?' on':'')+'" role="listitem" data-di="'+i+'" tabindex="0" style="margin-left:'+(Math.min(4,n.d)*12)+'px"><span class="lb-detname">'+esc(n.l)+'<small>'+esc(n.s)+' · '+n.h+' px</small></span>'
        +(target?(cov?'<span class="lb-detok" title="Schon in der Seitenstruktur"><i class="fas fa-check"></i></span>':'<button type="button" class="lb-detbtn" data-dadd="'+i+'">Gestalten</button>'):'')+'</div>';
    }).join('');
  }
  function addDetected(i){
    var n=detected[i];if(!n||!SELRE.test(n.s)||!cat.ep_area||!target)return;
    var hit=coveredBy(n.s);if(hit){sel=hit.id;draw();return}
    var s={id:uid(),type:'ep_area',hidden:false,props:defaults('ep_area')};s.props.selector=n.s;s.props.label=String(n.l||n.s).slice(0,60);
    layout.push(s);sel=s.id;tab='design';changed();draw();init();drawDetected();
    if(document.querySelector('.lb-grid').dataset.lbview==='structure')mode('settings');
  }
  function onDetected(nodes){
    detected=(Array.isArray(nodes)?nodes:[]).slice(0,90).filter(function(n){return n&&typeof n.s==='string'&&SELRE.test(n.s)}).map(function(n){return {s:n.s,t:String(n.t||'').slice(0,12),l:String(n.l||n.s).slice(0,60),d:Math.max(0,Math.min(4,+n.d||0)),p:String(n.p||''),y:+n.y||0,h:+n.h||0}});
    drawDetected();
  }
  function init(){var f=frame();if(!f||!f.contentWindow)return;f.contentWindow.postMessage({ep:1,type:'init',token:bridge.token,edit:bridge.edit,regions:bridgeRegions(),labels:typeLabels(),selected:sel||'',scroll:bridge.scroll},location.origin)}
  function onMessage(e){
    var f=frame();if(!f||e.source!==f.contentWindow||e.origin!==location.origin)return;
    var d=e.data;if(!d||d.ep!==1||typeof d.type!=='string')return;
    if(d.type==='hello'){bridge.ready=true;init();return}
    if(d.token!==bridge.token)return;
    if(d.type==='scroll'){bridge.scroll=Math.max(0,Math.min(1e6,+d.y||0));return}
    if(d.type==='structure'){onDetected(d.nodes);return}
    if(d.type==='select'){
      var id=String(d.id||'');
      if(d.kind==='detected'){var cv=SELRE.test(id)?coveredBy(id):null;detSel=SELRE.test(id)?id:'';if(cv){sel=cv.id;draw()}drawDetected();return}
      detSel='';
      if(d.kind==='region'){if(target&&locate(id)){regionNote('');sel=id;draw();if(document.querySelector('.lb-grid').dataset.lbview==='structure')mode('settings');return}sel=null;draw();regionNote(id);return}
      regionNote('');sel=id&&locate(id)?id:null;draw();if(sel&&document.querySelector('.lb-grid').dataset.lbview==='structure')mode('settings');return;
    }
    if(d.type==='action'&&typeof d.id==='string'&&locate(d.id)){act(String(d.op),d.id)}
  }
  function setFrame(){
    var f=frame();if(!f||!previewUrl)return;bridge.ready=false;
    f.src=previewUrl+(previewUrl.indexOf('?')<0?'?':'&')+'rrw_bk_t='+Date.now();$('lbUrl').textContent=location.origin+'/';
  }
  async function getPreview(){var tgt=target;   /* späte Antwort für ein inzwischen gewechseltes Ziel verwerfen */
    if(tgt){try{var pd=await capi('layout_preview');if(tgt===target){previewUrl=pd.url;setFrame()}}catch(e){state(e.message||'Vorschau nicht verfügbar',true)}return}
    try{var d=await cmsApi('wp_theme_preview',{slug:'elvado-baukasten'});if(d.url&&tgt===target){previewUrl=d.url;setFrame()}}catch(e){state(e.message||'Vorschau nicht verfügbar',true)}}
  function notice(){var n=$('lbNotice');if(target){n.style.display='';n.innerHTML='Hier gestaltest du die <b>bestehenden Bereiche</b> deiner Website (Abstände, Farben, Höhe, Sichtbarkeit). Die Website rendert weiter selbst – ihre Texte und Inhalte pflegst du wie bisher in den jeweiligen Verwaltungsbereichen.';return}if(active){n.style.display='none';return}n.style.display='';n.innerHTML='Das Theme „ElvadoPress Baukasten“ ist noch nicht aktiv. Du kannst hier bauen und die Vorschau nutzen; zum Veröffentlichen <a href="#" data-activate="1">Theme aktivieren</a>.'}
  function badge(){var b=$('lbSched');if(!b)return;b.hidden=!publishAt;b.textContent=publishAt?'Geplant: '+publishAt.replace('T',' '):''}

  async function load(){
    try{
      if(!Object.keys(cat).length)await loadCatalog();
      if(target){await loadTarget();return}
      var d=await cmsApi('wp_bk_get');schema=d.schema||{};layout=d.layout||[];active=!!d.active;hasDraft=!!d.draft;publishAt=d.publish_at||'';revisions=d.revisions||[];dirty=false;
      if(!sel||!locate(sel))sel=layout.length?layout[0].id:null;
      palette();draw();notice();badge();state(hasDraft?'Entwurf geladen (noch nicht veröffentlicht)':'');if(!previewUrl)getPreview();else setFrame();
    }catch(e){state(e.message||'Laden fehlgeschlagen',true)}
  }
  async function saveDraft(){
    clearTimeout(timer);if(saving){pending=true;return}saving=true;
    try{state('Speichere Entwurf…');var d=target?await capi('layout_save_draft',{layout:layout,publish_at:publishAt}):await cmsApi('wp_bk_draft',{layout:layout,publish_at:publishAt});hasDraft=true;dirty=false;revisions=d.revisions||revisions;state(publishAt?'Entwurf gespeichert · geplant für '+publishAt.replace('T',' '):'Entwurf gespeichert');setFrame()}
    catch(e){state(e.message||'Speichern fehlgeschlagen',true)}
    saving=false;if(pending){pending=false;saveDraft()}
  }
  async function publish(){
    clearTimeout(timer);publishAt='';badge();
    try{state('Veröffentliche…');if(target){await capi('layout_save_draft',{layout:layout,publish_at:''});var pd=await capi('layout_publish',{});await loadTarget();state('Veröffentlicht');setFrame();return}var d=await cmsApi('wp_bk_publish',{layout:layout});layout=d.layout||layout;hasDraft=false;dirty=false;active=!!d.active;revisions=d.revisions||revisions;notice();draw();state('Veröffentlicht');setFrame()}
    catch(e){state(e.message||'Veröffentlichen fehlgeschlagen',true)}
  }
  async function discard(){
    if(!confirm('Den Entwurf verwerfen? Die veröffentlichte Startseite bleibt unverändert.'))return;
    if(target){try{await capi('layout_discard',{});publishAt='';await loadTarget();state('Entwurf verworfen');setFrame()}catch(e){state(e.message,true)}return}
    try{var d=await cmsApi('wp_bk_discard',{});layout=d.layout||[];hasDraft=false;dirty=false;publishAt='';badge();sel=layout.length?layout[0].id:null;draw();state('Entwurf verworfen');setFrame()}catch(e){state(e.message,true)}
  }
  async function reset(){
    if(!confirm('Eigenes Layout verwerfen? Die Startseite folgt dann wieder den Positionen im Customizer.'))return;
    try{await cmsApi('wp_bk_discard',{});var d=await cmsApi('wp_bk_save',{reset:true});layout=d.layout||[];hasDraft=false;dirty=false;publishAt='';badge();revisions=d.revisions||revisions;sel=layout.length?layout[0].id:null;draw();state('Zurückgesetzt');setFrame()}catch(e){state(e.message,true)}
  }
  async function rollback(n){
    if(!confirm('Fassung '+n+' wieder veröffentlichen? Die aktuelle Fassung bleibt im Verlauf erhalten.'))return;
    if(target){try{await capi('layout_rollback',{n:n});publishAt='';await loadTarget();modal('');state('Fassung '+n+' veröffentlicht');setFrame()}catch(e){state(e.message,true)}return}
    try{var d=await cmsApi('wp_bk_rollback',{n:n});layout=d.layout||layout;hasDraft=false;dirty=false;publishAt='';badge();revisions=d.revisions||revisions;sel=layout.length?layout[0].id:null;draw();modal('');state('Fassung '+n+' veröffentlicht');setFrame()}catch(e){state(e.message,true)}
  }

  /* ───────── Dialoge: Verlauf, Planen ───────── */
  function modal(kind){
    var m=$('lbModal');if(!m)return;if(!kind){m.hidden=true;m.innerHTML='';return}
    m.hidden=false;
    if(kind==='history'){
      m.innerHTML='<div class="lb-modal-box" role="dialog" aria-label="Verlauf"><div class="lb-h"><b>Verlauf der Startseite</b><button type="button" class="lb-ic" data-modal="close" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div>'
        +(revisions.length?'<div class="lb-rev">'+revisions.map(function(r,i){return '<div class="lb-revrow"><div><b>Fassung '+r.n+'</b>'+(i===0?' <span class="hint">(aktuell)</span>':'')+'<div class="hint">'+esc(String(r.at).replace('T',' ').slice(0,16))+' · '+esc(r.by||'?')+' · '+r.count+' Bereiche'+(r.label?' · '+esc(r.label):'')+'</div></div>'+(i===0?'':'<button type="button" class="btn-g" data-rollback="'+r.n+'">Wiederherstellen</button>')+'</div>'}).join('')+'</div>':'<p class="hint">Noch keine veröffentlichten Fassungen.</p>')+'</div>';
    }else if(kind==='schedule'){
      m.innerHTML='<div class="lb-modal-box" role="dialog" aria-label="Veröffentlichung planen"><div class="lb-h"><b>Veröffentlichung planen</b><button type="button" class="lb-ic" data-modal="close" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div>'
        +'<p class="hint">Der Entwurf wird zum gewählten Zeitpunkt automatisch veröffentlicht (beim nächsten Aufruf der Website).</p><div class="lb-field"><label for="lbSchedAt">Zeitpunkt</label><input class="fc" type="datetime-local" id="lbSchedAt" value="'+esc(publishAt.replace(' ','T'))+'"></div>'
        +'<div style="display:flex;gap:8px;margin-top:10px"><button type="button" class="btn-a" data-modal="schedule-ok">Planen</button>'+(publishAt?'<button type="button" class="btn-g" data-modal="schedule-off">Planung entfernen</button>':'')+'</div></div>';
    }
  }

  /* ───────── Aktionen auf dem Baum ───────── */
  function move(loc,to){if(target)return;if(to<0||to>=loc.list.length||to===loc.idx)return;loc.list.splice(to,0,loc.list.splice(loc.idx,1)[0]);changed();draw()}
  function act(op,id){
    var loc=locate(id);if(!loc)return;
    if(target&&op!=='hide'){state('Bereiche der bestehenden Website lassen sich nur ausblenden und gestalten.',true);return}
    if(op==='up')move(loc,loc.idx-1);else if(op==='down')move(loc,loc.idx+1);
    else if(op==='hide'){loc.node.hidden=!loc.node.hidden;changed();draw()}
    else if(op==='dup'){if(rules(loc.node.type).repeatable===false){state('Diese Komponente darf nur einmal vorkommen.',true);return}var c=cloneNew(loc.node);loc.list.splice(loc.idx+1,0,c);sel=c.id;changed();draw()}
    else if(op==='del'){if(rules(loc.node.type).locked){state('Diese Komponente ist fest verankert.',true);return}if(confirm('Bereich löschen?')){loc.list.splice(loc.idx,1);if(sel===id||(loc.node&&sel&&inside(loc.node,sel)))sel=loc.list.length?loc.list[Math.min(loc.idx,loc.list.length-1)].id:null;changed();draw()}}
    if(bridge.ready&&op!=='del')send({type:'select',id:sel||''});
  }
  function addType(ty){
    if(rules(ty).repeatable===false||rules(ty).max_instances>0){var n=0;(function c(l){l.forEach(function(x){if(x.type===ty)n++;c(x.children||[])})})(layout);if((rules(ty).repeatable===false&&n>=1)||(rules(ty).max_instances>0&&n>=rules(ty).max_instances)){state('Von dieser Komponente ist nur '+(rules(ty).repeatable===false?'eine':rules(ty).max_instances)+' erlaubt.',true);return}}
    var s={id:uid(),type:ty,hidden:false,props:defaults(ty)};if(rules(ty).droppable)s.children=[];
    var loc=sel?locate(sel):null;
    if(loc&&rules(loc.node.type).droppable&&canAccept(loc.node,ty)){loc.node.children=loc.node.children||[];loc.node.children.push(s)}
    else if(loc&&canAccept(loc.parent,ty))loc.list.splice(loc.idx+1,0,s);
    else layout.push(s);
    sel=s.id;tab='content';togglePalette(false);changed();draw();
  }

  function onClick(e){
    var t=e.target;
    var md=t.closest('[data-lbmode]');if(md){mode(md.dataset.lbmode);return}
    var dv=t.closest('[data-lbdev]');if(dv){device(dv.dataset.lbdev);return}
    var tb=t.closest('[data-lbtab]');if(tb){tab=tb.dataset.lbtab;document.querySelectorAll('#panel-livebuilder [data-lbtab]').forEach(function(b){b.classList.toggle('on',b===tb)});drawFields();return}
    var mo=t.closest('[data-lbmore]');if(mo){pop();var a=mo.dataset.lbmore;if(a==='discard')discard();else if(a==='reset')reset();else if(a==='ai')aiLayout();else if(a==='customizer')customizer();else if(a==='history')modal('history');else if(a==='schedule')modal('schedule');return}
    var mm=t.closest('[data-modal]');if(mm){var k=mm.dataset.modal;if(k==='close')modal('');else if(k==='schedule-ok'){var v=($('lbSchedAt')||{}).value||'';if(!v){state('Bitte einen Zeitpunkt wählen.',true);return}publishAt=v.replace('T',' ');modal('');badge();saveDraft()}else if(k==='schedule-off'){publishAt='';modal('');badge();saveDraft()}return}
    var rb=t.closest('[data-rollback]');if(rb){rollback(+rb.dataset.rollback);return}
    if(t.closest('#lbMore')||t.closest('#lbPubMore')){var pp=$(t.closest('#lbPubMore')?'lbPubPop':'lbMorePop'),was=pp.hidden;pop();pp.hidden=!was;e.stopPropagation();return}
    if(t.closest('#lbDraft')){saveDraft();return}
    if(t.closest('#lbPublish')){publish();return}
    if(t.closest('#lbReload')){setFrame();return}
    if(t.closest('#lbAdd')||t.closest('#lbAddTop')){togglePalette();return}
    var da=t.closest('[data-dadd]');if(da){addDetected(+da.dataset.dadd);return}
    var dr=t.closest('[data-di]');if(dr){var dn=detected[+dr.dataset.di];if(dn){detSel=dn.s;var cv2=coveredBy(dn.s);if(cv2){sel=cv2.id;draw()}toFrame({type:'select',sel:dn.s,id:cv2?cv2.id:dn.s,scroll:true});drawDetected()}return}
    if(t.closest('#lbDetScan')){toFrame({type:'scan'});return}
    var ai=t.closest('[data-addin]');if(ai){sel=ai.dataset.addin;togglePalette(true);return}
    var ad=t.closest('[data-add]');if(ad){addType(ad.dataset.add);return}
    var a=t.closest('[data-act]');
    if(a){var id=a.dataset.id,ac=a.dataset.act;
      if(ac==='menu'){var p2=document.querySelector('#lbList [data-pop="'+id+'"]'),w=p2.hidden;pop();p2.hidden=!w;e.stopPropagation();return}
      pop();act(ac,id);return}
    var it=t.closest('.lb-item');if(it){sel=it.dataset.id;regionNote('');drawList();drawFields();send({type:'select',id:sel,scroll:true});if(document.querySelector('.lb-grid').dataset.lbview==='structure')mode('settings');return}
    var pk=t.closest('[data-pick]');if(pk){var k2=pk.dataset.pick;if(window.HomeBuilder)HomeBuilder.pickImage(function(u){var l=locate(sel);if(l){l.node.props[k2]=u;changed();drawFields()}});return}
    var cl=t.closest('[data-clear]');if(cl){var l2=locate(sel);if(l2){l2.node.props[cl.dataset.clear]='';changed();drawFields()}return}
    var ia=t.closest('[data-itemadd]');if(ia){var l3=locate(sel),sub=(fdef(l3.node.type,ia.dataset.itemadd)||{}).item||[{k:'title'},{k:'text'}],row={};sub.forEach(function(x){row[x.k]=''});l3.node.props[ia.dataset.itemadd].push(row);changed();drawFields();return}
    var idl=t.closest('[data-itemdel]');if(idl){var q=idl.dataset.itemdel.split('|'),l4=locate(sel);l4.node.props[q[0]].splice(+q[1],1);changed();drawFields();return}
    var ac2=t.closest('[data-activate]');
    if(ac2){e.preventDefault();cmsApi('wp_theme_activate',{slug:'elvado-baukasten'}).then(function(){active=true;notice();state('Theme aktiviert');setFrame()}).catch(function(x){state(x.message,true)});return}
  }
  function onInput(e){
    var el=e.target;
    if(el.id==='lbTarget'){switchTarget(el.value);return}
    if(el.dataset&&el.dataset.colorfor&&el.closest('#lbFields')){var ti=document.querySelector('#lbFields input.fc[data-k="'+el.dataset.colorfor+'"]');if(ti){ti.value=el.value;ti.dispatchEvent(new Event('input',{bubbles:true}))}return}
    if(el.dataset&&el.dataset.vis&&el.closest('#lbFields')){
      var l0=locate(sel);if(!l0)return;var s0=l0.node,v=s0.visibility=Object.assign({devices:['desktop','tablet','mobile'],audience:'all',from:'',until:''},s0.visibility||{});
      if(el.dataset.vis==='dev'){var set=v.devices.filter(function(x){return x!==el.dataset.v});if(el.checked)set.push(el.dataset.v);v.devices=['desktop','tablet','mobile'].filter(function(x){return set.indexOf(x)>=0})}
      else v[el.dataset.vis]=el.dataset.vis==='audience'?el.value:el.value.replace('T',' ');
      changed();return;
    }
    if(el.dataset&&el.dataset.dev&&el.closest('#lbFields')){
      var l1=locate(sel);if(!l1)return;var s1=l1.node;s1.responsive=s1.responsive||{};var r=s1.responsive[el.dataset.dev]=s1.responsive[el.dataset.dev]||{};
      if(el.value==='')delete r[el.dataset.k];else r[el.dataset.k]=el.type==='number'?parseInt(el.value,10):el.value;
      if(!Object.keys(r).length)delete s1.responsive[el.dataset.dev];changed();return;
    }
    if(el.dataset&&el.dataset.slider&&el.closest('#lbFields')){var nb=el.parentNode.querySelector('input[type=number]');if(nb)nb.value=el.value}
    else if(el.type==='number'&&el.closest&&el.closest('.lb-num')){var rgi=el.closest('.lb-range').querySelector('input[type=range]');if(rgi)rgi.value=el.value}
    if(!el.dataset||el.dataset.k===undefined||!el.closest('#lbFields'))return;var l=locate(sel);if(!l)return;var s=l.node,k=el.dataset.k;
    var v2=el.type==='checkbox'?el.checked:(el.type==='number'||el.type==='range')?parseInt(el.value||'0',10):el.value;
    if(el.dataset.i!==undefined)s.props[k][+el.dataset.i][el.dataset.f]=v2;else s.props[k]=v2;
    if(el.type==='checkbox'){var sp=el.parentNode.querySelector('span');if(sp)sp.textContent=v2?'An':'Aus'}
    var sm=document.querySelector('#lbList .lb-item.on .lb-name small');if(sm)sm.textContent=summary(s);
    changed();
  }
  function mode(m){
    document.querySelector('#panel-livebuilder .lb-grid').dataset.lbview=m;
    document.querySelectorAll('#panel-livebuilder [data-lbmode]').forEach(function(b){b.classList.toggle('on',b.dataset.lbmode===m)});
    var ed=m!=='preview';if(ed!==bridge.edit){bridge.edit=ed;send({type:'mode',edit:ed})}   // „Vorschau“: Links funktionieren, keine Auswahl
  }
  function device(d){var f=frame();f.dataset.dev=d;try{document.dispatchEvent(new CustomEvent('ep-device',{detail:d}))}catch(e){}document.querySelectorAll('#panel-livebuilder [data-lbdev]').forEach(function(b){b.classList.toggle('on',b.dataset.lbdev===d)})}
  function customizer(){
    var go=function(){if(window.cmsTab)cmsTab('themes',document.querySelector('.tab[data-tab="themes"]'));if(window.DesignHub&&DesignHub.load)DesignHub.load();
      Promise.resolve(window.WpThemes&&WpThemes.load&&WpThemes.load()).then(function(){if(WpThemes.customizeSlug)WpThemes.customizeSlug('elvado-baukasten')}).catch(function(){})};
    if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;go();
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
  function detHover(e){var r=e.target.closest&&e.target.closest('[data-di]');var n=r?detected[+r.dataset.di]:null;toFrame({type:'hl',sel:n?n.s:''})}
  function bind(){
    var root=$('panel-livebuilder');if(!root||root.dataset.bound)return;root.dataset.bound='1';bridge.token=rnd();
    root.addEventListener('click',onClick);root.addEventListener('input',onInput);root.addEventListener('change',onInput);
    window.addEventListener('message',onMessage);
    var dl=$('lbDetList');if(dl){dl.addEventListener('mouseover',detHover);dl.addEventListener('mouseleave',function(){toFrame({type:'hl',sel:''})})}
    var f=frame();if(f)f.addEventListener('load',function(){setTimeout(function(){if(!bridge.ready)init()},200)});   // falls „hello“ vor dem Zuhören kam
    document.addEventListener('click',function(e){if(!e.target.closest('.lb-more'))pop()});
    var host=$('lbList');
    host.addEventListener('keydown',function(e){if((e.key==='Enter'||e.key===' ')&&e.target.classList.contains('lb-item')){e.preventDefault();sel=e.target.dataset.id;drawList();drawFields();send({type:'select',id:sel,scroll:true})}});
    host.addEventListener('dragstart',function(e){var s=e.target.closest&&e.target.closest('.lb-item');if(!s)return;dragId=s.dataset.id;s.classList.add('dragging');e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain',dragId)}catch(x){}});
    host.addEventListener('dragend',function(){dragId=null;host.querySelectorAll('.dragging,.drag-over').forEach(function(x){x.classList.remove('dragging','drag-over')})});
    host.addEventListener('dragover',function(e){if(!dragId)return;e.preventDefault();var s=e.target.closest('.lb-item');host.querySelectorAll('.drag-over').forEach(function(x){x.classList.remove('drag-over')});if(s&&s.dataset.id!==dragId)s.classList.add('drag-over')});
    host.addEventListener('drop',function(e){
      if(!dragId||target)return;e.preventDefault();var s=e.target.closest('.lb-item');if(!s||s.dataset.id===dragId)return;
      var from=locate(dragId),to=locate(s.dataset.id);dragId=null;if(!from||!to||inside(from.node,to.node.id))return;
      if(!canAccept(to.parent,from.node.type)){state('Dort darf diese Komponente nicht stehen.',true);return}
      from.list.splice(from.idx,1);var tl=locate(s.dataset.id);tl.list.splice(tl.idx,0,from.node);changed();draw();
    });
    window.addEventListener('beforeunload',function(e){if(dirty){e.preventDefault();e.returnValue=''}});
  }
  window.LiveBuilder={device:function(d){device(d)},load:function(){bind();load()},publish:publish,saveDraft:saveDraft};
})();
