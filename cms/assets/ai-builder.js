/* KI-Website-Generator: Idee beschreiben → die KI entwirft Titel, Farben, Startseite (Homepage-Baukasten), Seiten und Beiträge → Vorschau → Übernehmen (rückgängig machbar).
   Server: ai_site_plan (Entwurf, wird nicht gespeichert). Übernehmen nutzt die vorhandenen Speicher-Aktionen: wp_theme_activate, wp_theme_customize_save, wp_bk_save, save(pages|menus), news_save. */
(function(){
  'use strict';
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})};
  var api=function(a,b){return window.cmsApi(a,b)};
  var toast=function(m,bad){try{window.cmsToast(m,!!bad)}catch(e){}};
  var BK='elvado-baukasten',UNDO_KEY='ep_builder_undo';
  var EXAMPLES=[
    ['Schreinerei','Schreinerei Weller: Handwerk, Qualität und individuelle Lösungen aus Holz. Wir bauen Möbel nach Maß, Innenausbau und kreative Einzelstücke – im Mittelpunkt steht die Verbindung aus handwerklichem Können und persönlicher Beratung.'],
    ['Café','Café Sonnenschein: gemütliches Café mit selbstgebackenem Kuchen, fairem Kaffee und Frühstück. Wir sind Treffpunkt im Viertel und veranstalten monatlich kleine Lesungen und Konzerte.'],
    ['Podcast','Der Podcast „Zwischen den Zeilen“: Gespräche über Bücher, Schreiben und Kreativität. Alle zwei Wochen eine neue Folge mit Gästen aus der Literaturszene.'],
    ['Fotografin','Lena Kraft Fotografie: Hochzeiten, Porträts und Familienshootings in natürlichem Licht. Persönlich, ehrlich und mit Blick für den echten Moment.'],
    ['Verein','Der Sportverein TSV Neustadt: Fußball, Turnen und Laufgruppe für alle Altersstufen. Wir suchen neue Mitglieder, Trainer und Helfer.']
  ];
  var s={step:'input',busy:false,status:null,plan:null,err:'',input:{description:'',name:'',tone:'modern',parts:{home:true,pages:true,posts:true}},opts:{activate:true,postsDraft:true,menu:true,images:true},applied:null,log:[]};
  function host(){return document.getElementById('aiBuilder')}

  function loadStatus(){
    return api('ai_status').then(function(d){s.status=d}).catch(function(){s.status={providers:[]}});
  }
  function open(){
    if(!s.status)loadStatus().then(draw);else draw();
    try{var u=JSON.parse(localStorage.getItem(UNDO_KEY)||'null');if(u&&!s.applied)s.applied=u}catch(e){}
    draw();
  }

  // ------------------------------------------------------------------ Zeichnen
  function draw(){
    var h=host();if(!h)return;
    h.innerHTML=({input:drawInput,plan:drawPlan,done:drawDone}[s.step]||drawInput)();
  }
  function drawInput(){
    var ok=s.status&&(s.status.providers||[]).length>0;
    var i=s.input;
    return '<div class="aib-hero"><h1>Deine neue Website, in Sekunden fertig.</h1><p>Beschreibe deine Idee – die KI entwirft Startseite, Seiten und Beiträge. Du siehst alles vorab und kannst es vor dem Übernehmen prüfen.</p></div>'
      +(s.status&&!ok?'<div class="card aib-warn"><b>Noch kein KI-Anbieter eingerichtet.</b> Trage in der <a href="#" onclick="cmsTab(\'aicenter\',document.querySelector(\'[data-tab=aicenter]\'));AiCenter.open();return false">KI-Zentrale</a> einen Schlüssel ein – oder schalte dort einen kostenlosen Dienst ohne Schlüssel ein (Pollinations, LLM7; zum Ausprobieren, Eingaben gehen an Dritte).</div>':'')
      +(s.applied?'<div class="card aib-note"><i class="fas fa-rotate-left"></i> Der zuletzt übernommene Entwurf kann rückgängig gemacht werden. <button class="btn-g" onclick="AiBuilder.undo()">Rückgängig machen</button></div>':'')
      +'<div class="aib-box"><textarea id="aibDesc" class="aib-text" rows="6" placeholder="z. B. Schreinerei Weller: Handwerk, Qualität und individuelle Lösungen aus Holz …" oninput="AiBuilder.set(\'description\',this.value)">'+esc(i.description)+'</textarea>'
      +'<div class="aib-chips">'+EXAMPLES.map(function(e,n){return '<button type="button" class="aib-chip" onclick="AiBuilder.example('+n+')">'+esc(e[0])+'</button>'}).join('')+'</div>'
      +'<div class="aib-opts"><div><label class="news-lbl">Name (optional)</label><input class="fc w-100" value="'+esc(i.name)+'" oninput="AiBuilder.set(\'name\',this.value)" placeholder="Firmen- oder Projektname"></div>'
      +'<div><label class="news-lbl">Ton</label><select class="fc w-100" onchange="AiBuilder.set(\'tone\',this.value)">'+[['modern','Modern & klar'],['klassisch','Klassisch & seriös'],['freundlich','Freundlich & nahbar'],['minimal','Minimalistisch'],['kreativ','Kreativ & verspielt']].map(function(t){return '<option value="'+t[0]+'" '+(i.tone===t[0]?'selected':'')+'>'+t[1]+'</option>'}).join('')+'</select></div>'
      +'<div class="aib-parts"><label class="news-lbl">Was soll entstehen?</label>'+[['home','Startseite'],['pages','Seiten'],['posts','Beiträge']].map(function(p){return '<label class="wm-check"><input type="checkbox" '+(i.parts[p[0]]?'checked':'')+' onchange="AiBuilder.part(\''+p[0]+'\',this.checked)"> '+p[1]+'</label>'}).join('')+'</div></div>'
      +(s.err?'<div class="aib-err"><i class="fas fa-circle-exclamation"></i> '+esc(s.err)+'</div>':'')
      +'<div class="aib-go"><button class="aib-btn" '+(s.busy||!ok?'disabled':'')+' onclick="AiBuilder.plan()">'+(s.busy?'<i class="fas fa-spinner fa-spin"></i> Die KI entwirft deine Website … (das kann bis zu einer Minute dauern)':'<i class="fas fa-wand-magic-sparkles"></i> Website erstellen')+'</button></div></div>'
      +'<p class="hint aib-foot">Die Beschreibung wird zur Erstellung an den in der KI-Zentrale gewählten KI-Anbieter übertragen. Bitte keine vertraulichen Daten eingeben. Impressum und Datenschutz legst du unter „Rechtliches“ an – sie werden nicht von der KI erfunden.</p>';
  }
  function swatches(p){return ['accent','bg','card','text','hero_bg','hero_text'].map(function(k){return '<span class="aib-sw" title="'+k+' '+esc(p[k])+'" style="background:'+esc(p[k])+'"></span>'}).join('')}
  function sec(sec,p){
    var x=sec.props||{},bg={default:p.bg,alt:p.card,accent:p.accent,dark:p.hero_bg}[x.bg||'default']||p.bg;
    var fg=(x.bg==='accent'||x.bg==='dark')?'#fff':p.text;
    var btn=function(l){return l?'<span class="aib-btn-s" style="background:'+esc(p.accent)+'">'+esc(l)+'</span>':''};
    var wrap=function(inner,style){return '<div class="aib-sec" style="background:'+esc(bg)+';color:'+fg+';'+(style||'')+'">'+inner+'</div>'};
    switch(sec.type){
      case 'hero':return '<div class="aib-sec aib-hero-s" style="background:'+esc(p.hero_bg)+';color:'+esc(p.hero_text)+'"><b>'+esc(x.title)+'</b><div>'+esc(x.text)+'</div>'+btn(x.btn_label)+(x.image_query?'<div class="aib-q"><i class="fas fa-image"></i> Bild: '+esc(x.image_query)+'</div>':'')+'</div>';
      case 'features':return wrap((x.title?'<b>'+esc(x.title)+'</b>':'')+'<div class="aib-cols">'+(x.items||[]).map(function(i){return '<div class="aib-colbox" style="background:'+esc(p.card)+';color:'+esc(p.text)+'"><b>'+esc(i.title)+'</b><div>'+esc(i.text)+'</div></div>'}).join('')+'</div>');
      case 'text':return wrap((x.title?'<b>'+esc(x.title)+'</b>':'')+'<div>'+String(x.body||'').replace(/<[^>]+>/g,' ').slice(0,260)+'</div>',x.align==='center'?'text-align:center':'');
      case 'image_text':return wrap('<div class="aib-img"></div><div>'+(x.title?'<b>'+esc(x.title)+'</b>':'')+'<div>'+esc(x.text)+'</div>'+btn(x.btn_label)+(x.image_query?'<div class="aib-q"><i class="fas fa-image"></i> Bild: '+esc(x.image_query)+'</div>':'')+'</div>','display:flex;gap:12px;align-items:center'+(x.reverse?';flex-direction:row-reverse':''));
      case 'posts':return wrap('<b>'+esc(x.title)+'</b><div class="aib-cols">'+Array.from({length:Math.min(3,x.count||3)}).map(function(){return '<div class="aib-colbox" style="background:'+esc(p.card)+'"><div class="aib-img" style="height:34px"></div><i>Beitrag</i></div>'}).join('')+'</div>');
      case 'cta':return wrap('<b>'+esc(x.title)+'</b><div>'+esc(x.text)+'</div>'+btn(x.btn_label),'text-align:center');
    }
    return '';
  }
  function drawPlan(){
    var pl=s.plan,p=pl.palette;
    var pageHtml=pl.pages.map(function(pg,i){return '<details class="aib-item"><summary><label class="wm-check" onclick="event.stopPropagation()"><input type="checkbox" '+(pg.off?'':'checked')+' onchange="AiBuilder.toggle(\'pages\','+i+',this.checked)"> <b>'+esc(pg.title)+'</b></label> <span class="hint">/'+esc(pg.slug)+'.html</span></summary>'
      +'<div class="aib-pagebody">'+pg.blocks.map(function(b){return b.type==='heading'?'<h4>'+esc(b.text)+'</h4>':(b.type==='button'?'<span class="aib-btn-s" style="background:'+esc(p.accent)+'">'+esc(b.label)+'</span>':(b.type==='html'?b.html:'<p>'+esc(b.text).replace(/\n\n/g,'</p><p>')+'</p>'))}).join('')+'</div></details>'}).join('');
    var postHtml=pl.posts.map(function(po,i){return '<details class="aib-item"><summary><label class="wm-check" onclick="event.stopPropagation()"><input type="checkbox" '+(po.off?'':'checked')+' onchange="AiBuilder.toggle(\'posts\','+i+',this.checked)"> <b>'+esc(po.title)+'</b></label></summary><div class="aib-pagebody">'+po.body_html+'</div></details>'}).join('');
    return '<div class="card"><div class="th"><div><div class="tt"><i class="fas fa-eye"></i>Vorschau deines Entwurfs</div><div class="hint">KI-Modell: '+esc((pl.meta||{}).provider)+' · '+esc((pl.meta||{}).model)+'. Nichts ist gespeichert, bis du „Übernehmen“ wählst.</div></div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn-g" onclick="AiBuilder.back()"><i class="fas fa-arrow-left"></i> Zurück</button><button class="btn-g" '+(s.busy?'disabled':'')+' onclick="AiBuilder.plan()"><i class="fas fa-rotate"></i> Anders versuchen</button><button class="btn-a" '+(s.busy?'disabled':'')+' onclick="AiBuilder.apply()"><i class="fas fa-check"></i> Übernehmen</button></div></div>'
      +(s.err?'<div class="aib-err">'+esc(s.err)+'</div>':'')
      +'<div class="aib-grid"><div><label class="news-lbl">Titel der Website</label><input class="fc w-100" value="'+esc(pl.site.title)+'" oninput="AiBuilder.site(\'title\',this.value)"><label class="news-lbl" style="margin-top:8px">Untertitel</label><input class="fc w-100" value="'+esc(pl.site.tagline)+'" oninput="AiBuilder.site(\'tagline\',this.value)"></div>'
      +'<div><label class="news-lbl">Farbwelt</label><div class="aib-sws">'+swatches(p)+'</div><div class="hint">Wird im Theme „ElvadoPress Baukasten“ als Farben gesetzt.</div></div></div>'
      +'<div class="aib-opts2"><label class="wm-check"><input type="checkbox" '+(s.opts.activate?'checked':'')+' onchange="AiBuilder.opt(\'activate\',this.checked)"> Theme „ElvadoPress Baukasten“ aktivieren (nötig für Startseite und Farben)</label>'
      +'<label class="wm-check"><input type="checkbox" '+(s.opts.menu?'checked':'')+' onchange="AiBuilder.opt(\'menu\',this.checked)"> Neue Seiten ins Hauptmenü aufnehmen</label>'
      +'<label class="wm-check"><input type="checkbox" '+(s.opts.postsDraft?'checked':'')+' onchange="AiBuilder.opt(\'postsDraft\',this.checked)"> Beiträge als Entwurf anlegen (nicht sofort veröffentlichen)</label>'
      +(imageQueries(pl,pl.posts.filter(function(x){return !x.off})).length?'<label class="wm-check"><input type="checkbox" '+(s.opts.images?'checked':'')+' onchange="AiBuilder.opt(\'images\',this.checked)"> Passende freie Bilder laden und mit Alt-Text versehen ('+imageQueries(pl,pl.posts.filter(function(x){return !x.off})).length+' Bilder; Quellen laut Menü „Medien“, Bildnachweise werden bei Bedarf als Seite angelegt)</label>':'')+'</div></div>'
      +(pl.home.length?'<div class="card"><div class="th"><div class="tt"><i class="fas fa-house"></i>Startseite ('+pl.home.length+' Abschnitte)</div></div><div class="aib-prev" style="background:'+esc(p.bg)+'">'+pl.home.map(function(x){return sec(x,p)}).join('')+'</div><div class="hint">Bilder wählst du nach dem Übernehmen im Homepage-Baukasten (Mediathek oder freie Bildquellen).</div></div>':'')
      +(pl.pages.length?'<div class="card"><div class="th"><div class="tt"><i class="fas fa-file-lines"></i>Seiten ('+pl.pages.length+')</div></div>'+pageHtml+'</div>':'')
      +(pl.posts.length?'<div class="card"><div class="th"><div class="tt"><i class="fas fa-newspaper"></i>Beiträge ('+pl.posts.length+')</div></div>'+postHtml+'</div>':'');
  }
  function drawDone(){
    return '<div class="card"><div class="th"><div class="tt"><i class="fas fa-circle-check" style="color:var(--good)"></i>Fertig – deine Website ist angelegt</div></div>'
      +'<div class="aib-log">'+s.log.map(function(l){return '<div class="'+(l.ok?'aic-ok':'aic-bad')+'"><i class="fas fa-'+(l.ok?'check':'xmark')+'"></i> '+esc(l.text)+'</div>'}).join('')+'</div>'
      +'<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><a class="btn-a" href="/" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Website ansehen</a>'
      +'<button class="btn-g" onclick="cmsTab(\'baukasten\',document.querySelector(\'[data-tab=baukasten]\'));window.HomeBuilder&&HomeBuilder.load()"><i class="fas fa-table-columns"></i> Startseite im Baukasten verfeinern</button>'
      +'<button class="btn-g" onclick="AiBuilder.undo()"><i class="fas fa-rotate-left"></i> Alles rückgängig machen</button><button class="btn-g" onclick="AiBuilder.reset()">Neuen Entwurf starten</button></div></div>';
  }

  // ------------------------------------------------------------------ Eingaben
  function set(k,v){s.input[k]=v}
  function part(k,v){s.input.parts[k]=v}
  function example(n){s.input.description=EXAMPLES[n][1];draw()}
  function opt(k,v){s.opts[k]=v}
  function site(k,v){s.plan.site[k]=v}
  function toggle(kind,i,on){s.plan[kind][i].off=!on}
  function back(){s.step='input';s.err='';draw()}
  function reset(){s.step='input';s.plan=null;s.err='';draw()}

  // ------------------------------------------------------------------ Entwurf
  function plan(){
    if(s.busy)return;
    if((s.input.description||'').trim().length<12){s.err='Bitte beschreibe dein Vorhaben etwas genauer (ein bis zwei Sätze).';s.step='input';return draw()}
    s.busy=true;s.err='';draw();
    api('ai_site_plan',{description:s.input.description,name:s.input.name,tone:s.input.tone,parts:s.input.parts}).then(function(d){
      s.plan=d.plan;s.step='plan';s.busy=false;draw();window.scrollTo({top:0,behavior:'smooth'});
    }).catch(function(e){s.busy=false;s.err=e.message||'Der Entwurf konnte nicht erstellt werden.';draw()});
  }

  // ------------------------------------------------------------------ Übernehmen
  function ctlValues(d,ids){
    var out={};(d.sections||[]).forEach(function(sc){(sc.controls||[]).forEach(function(c){if(ids.indexOf(c.id)>=0)out[c.id]=c.value})});return out;
  }
  var COLOR_IDS={accent:'color_accent',bg:'color_bg',card:'color_card',text:'color_text',hero_bg:'color_hero_bg',hero_text:'color_hero_text'};
  function step(text,fn){
    return Promise.resolve().then(fn).then(function(){s.log.push({ok:true,text:text})}).catch(function(e){s.log.push({ok:false,text:text+' – '+(e.message||'Fehler')});throw e});
  }
  function imageQueries(pl,postsNew){
    var q=[];
    (pl.home||[]).forEach(function(sec,i){var pr=sec.props||{};if(pr.image_query&&(sec.type==='hero'||sec.type==='image_text'))q.push({key:'home:'+i,q:pr.image_query,orient:'landscape',width:sec.type==='hero'?1600:1024})});
    (postsNew||[]).forEach(function(po,j){if(po.image_query)q.push({key:'post:'+j,q:po.image_query,orient:'landscape',width:1024})});
    return q.slice(0,8);
  }
  /* Freie Bilder laden und in den Entwurf eintragen; Fehler brechen das Übernehmen nicht ab */
  function loadImages(pl,postsNew,credits){
    var q=imageQueries(pl,postsNew);if(!q.length)return Promise.resolve('');
    return api('ai_site_images',{queries:q}).then(function(d){
      if(!d.usable)return 'Keine Bildquelle eingerichtet – ohne Bilder (Menü „Medien“ → Freie Bilder)';
      var ok=0;(d.images||[]).forEach(function(im){
        if(!im.ok)return;var m=/^(home|post):(\d+)$/.exec(im.key);if(!m)return;ok++;
        if(m[1]==='home'){var pr=pl.home[+m[2]].props;pr.image=im.url;pr.image_alt=im.alt}else if(postsNew[+m[2]])postsNew[+m[2]].image_url=im.url;
        if(im.attribution_required&&im.credit)credits.push(im.credit);
      });
      return ok+' von '+q.length+' Bildern geladen und mit Alt-Text versehen';
    });
  }
  function freeSlug(base){var have={};(CMS.pages||[]).forEach(function(p){have[p.slug]=1});var s0=base,n=2;while(have[s0])s0=base+'-'+(n++);return s0}
  function apply(){
    if(s.busy)return;var pl=s.plan;
    if(!confirm('Entwurf übernehmen? Titel, Farben und Startseite werden ersetzt, neue Seiten und Beiträge werden hinzugefügt. Das lässt sich anschließend rückgängig machen.'))return;
    s.busy=true;s.log=[];s.err='';
    var ids=['blogname','blogdescription'].concat(Object.keys(COLOR_IDS).map(function(k){return COLOR_IDS[k]}));
    var snap={time:Date.now(),theme:null,values:{},layout:null,pages:null,menus:null,posts:[],applied:{}};
    var pagesNew=pl.pages.filter(function(p){return !p.off}),postsNew=pl.posts.filter(function(p){return !p.off});
    var p0=Promise.resolve();
    // Zustand sichern
    p0=p0.then(function(){return api('wp_themes').then(function(d){snap.theme=(d.themes||[]).filter(function(t){return t.active})[0]||null;snap.theme=snap.theme?snap.theme.slug:'';snap.front=!!d.front;snap.hasBk=(d.themes||[]).some(function(t){return t.slug===BK})})});
    p0=p0.then(function(){if(!snap.hasBk)throw new Error('Das Theme „ElvadoPress Baukasten“ ist nicht installiert.')});
    p0=p0.then(function(){return api('wp_theme_customize',{slug:BK}).then(function(d){snap.values=ctlValues(d,ids)})});
    p0=p0.then(function(){return api('wp_bk_get',{}).then(function(d){snap.layout={custom:!!d.custom,layout:d.layout}})});
    p0=p0.then(function(){snap.pages=JSON.parse(JSON.stringify(CMS.pages||[]));snap.menus=JSON.parse(JSON.stringify(CMS.menus||{top:[],bottom:[]}))});
    p0=p0.then(function(){try{localStorage.setItem(UNDO_KEY,JSON.stringify(snap))}catch(e){}s.applied=snap});
    // Schreiben
    if(s.opts.activate)p0=p0.then(function(){if(snap.theme===BK)return;return step('Theme „ElvadoPress Baukasten“ aktiviert',function(){return api('wp_theme_activate',{slug:BK})})});
    var credits=[];
    if(s.opts.images)p0=p0.then(function(){return loadImages(pl,postsNew,credits).then(function(msg){if(msg)s.log.push({ok:true,text:msg})}).catch(function(e){s.log.push({ok:false,text:'Bilder: '+(e.message||'Fehler')+' – weiter ohne Bilder'})})});
    p0=p0.then(function(){
      if(credits.length){var ul='<ul>'+credits.filter(function(c,i){return credits.indexOf(c)===i}).map(function(c){return '<li>'+esc(c)+'</li>'}).join('')+'</ul>';
        pagesNew.push({title:'Bildnachweise',slug:freeSlug('bildnachweise'),bottom:true,blocks:[{id:'blk_'+Math.random().toString(36).slice(2,10),type:'heading',enabled:true,text:'Bildnachweise',level:2},{id:'blk_'+Math.random().toString(36).slice(2,10),type:'text',enabled:true,text:'Die Bilder auf dieser Website stammen aus freien Bildquellen. Urheber und Lizenzen:'},{id:'blk_'+Math.random().toString(36).slice(2,10),type:'html',enabled:true,html:ul}]})}
      pl.home.forEach(function(x){if(x.props)delete x.props.image_query});
    });
    p0=p0.then(function(){return step('Titel und Farben gesetzt',function(){
      var v={blogname:pl.site.title,blogdescription:pl.site.tagline};Object.keys(COLOR_IDS).forEach(function(k){v[COLOR_IDS[k]]=pl.palette[k]});
      return api('wp_theme_customize_save',{slug:BK,values:v});
    })});
    if(pl.home.length)p0=p0.then(function(){return step('Startseite mit '+pl.home.length+' Abschnitten gespeichert',function(){return api('wp_bk_save',{layout:pl.home})})});
    if(pagesNew.length)p0=p0.then(function(){return step(pagesNew.length+' Seiten angelegt',function(){
      var all=(CMS.pages||[]).slice();
      pagesNew.forEach(function(pg){all.push({id:'page_'+Math.random().toString(36).slice(2,10),slug:pg.slug,title:pg.title,type:'custom',system_target:'',enabled:true,native_enabled:false,headline:'',intro:'',text_overrides:[],blocks_before:pg.blocks,blocks_after:[],meta_title:'',meta_description:'',noindex:false,publish_at:''})});
      return api('save',{section:'pages',value:all}).then(function(d){CMS.pages=d.value});
    })});
    if(s.opts.menu&&pagesNew.length)p0=p0.then(function(){return step('Menüpunkte ergänzt',function(){
      var m=JSON.parse(JSON.stringify(CMS.menus||{top:[],bottom:[]}));m.top=m.top||[];
      m.bottom=m.bottom||[];pagesNew.forEach(function(pg){(pg.bottom?m.bottom:m.top).push({id:'m_'+Math.random().toString(36).slice(2,8),label:pg.title.slice(0,40),target:'page:'+pg.slug,icon:'fa-file-lines',parent_id:'',enabled:true})});
      return api('save',{section:'menus',value:m}).then(function(d){CMS.menus=d.value});
    })});
    if(postsNew.length)p0=p0.then(function(){return step(postsNew.length+' Beiträge angelegt'+(s.opts.postsDraft?' (Entwurf)':''),function(){
      return postsNew.reduce(function(pr,po){return pr.then(function(){return api('news_save',{title:po.title,excerpt:po.excerpt,body_html:po.body_html,image_url:po.image_url||'',category:'News',status:s.opts.postsDraft?'draft':'published',tags:'',comments:'default'}).then(function(d){if(d&&d.id)snap.posts.push(d.id)})})},Promise.resolve());
    })});
    p0.then(function(){
      snap.applied={pages:pagesNew.map(function(p){return p.slug})};try{localStorage.setItem(UNDO_KEY,JSON.stringify(snap))}catch(e){}
      s.busy=false;s.step='done';draw();window.scrollTo({top:0,behavior:'smooth'});toast('Website übernommen ✓');
    }).catch(function(e){
      try{localStorage.setItem(UNDO_KEY,JSON.stringify(snap))}catch(x){}
      s.busy=false;s.step='done';s.log.push({ok:false,text:'Abgebrochen: '+(e.message||'Fehler')+' – bereits Geändertes lässt sich mit „Alles rückgängig machen“ zurücknehmen.'});draw();toast(e.message||'Fehler',true);
    });
  }

  // ------------------------------------------------------------------ Rückgängig
  function undo(){
    var u=s.applied;if(!u){toast('Nichts zum Rückgängigmachen.',true);return}
    if(!confirm('Alle Änderungen dieses Entwurfs zurücknehmen (Titel, Farben, Startseite, Seiten, Menü, Beiträge)?'))return;
    s.busy=true;s.log=[];
    var p=Promise.resolve();
    p=p.then(function(){return step('Titel und Farben zurückgesetzt',function(){return api('wp_theme_customize_save',{slug:BK,values:u.values})})});
    p=p.then(function(){return step('Startseite zurückgesetzt',function(){return u.layout&&u.layout.custom?api('wp_bk_save',{layout:u.layout.layout}):api('wp_bk_save',{reset:true})})});
    p=p.then(function(){return step('Seiten zurückgesetzt',function(){return api('save',{section:'pages',value:u.pages}).then(function(d){CMS.pages=d.value})})});
    p=p.then(function(){return step('Menüs zurückgesetzt',function(){return api('save',{section:'menus',value:u.menus}).then(function(d){CMS.menus=d.value})})});
    if((u.posts||[]).length)p=p.then(function(){return step(u.posts.length+' Beiträge in den Papierkorb verschoben',function(){return u.posts.reduce(function(pr,id){return pr.then(function(){return api('news_delete',{id:id})})},Promise.resolve())})});
    p=p.then(function(){if(u.theme===BK)return;return step('Vorheriges Theme wiederhergestellt',function(){return u.theme?api('wp_theme_activate',{slug:u.theme}):api('wp_theme_deactivate',{})})});
    p.then(function(){s.applied=null;try{localStorage.removeItem(UNDO_KEY)}catch(e){}s.busy=false;s.step='done';draw();toast('Rückgängig gemacht ✓')})
     .catch(function(e){s.busy=false;s.step='done';draw();toast(e.message||'Fehler',true)});
  }

  window.AiBuilder={open:open,set:set,part:part,example:example,opt:opt,site:site,toggle:toggle,back:back,reset:reset,plan:plan,apply:apply,undo:undo};
})();
