'use strict';
/* ElvadoPress Block-Editor – Blocktypen und Einstellungs-Bausteine (Inspector). Siehe blocks/core.js für das Speicherformat. */
(function(E){
  const {esc,h,icon,css,num,styleOf,attrsOf,SIZES}=E.util,dom=E.dom,nb=E.newBlock;
  const A=attrsOf;

  /* ───────── Bausteine der Einstellungsleiste ───────── */
  const PALETTE=['#000000','#ffffff','#2f7bff','#8b3dff','#00b4d8','#16a34a','#f59e0b','#ef4444','#ec4899','#64748b','#f1f5f9','#0f172a'];
  const C=E.ctl={
    section(title,kids,open){const d=h('details',{class:'epb-sec',open:open!==false},h('summary',null,title),h('div',{class:'epb-sec-body'},kids));return d},
    field(label,ctl){return h('div',{class:'epb-field'},label?h('label',null,label):null,ctl)},
    note(t){return h('div',{class:'epb-note',html:t})},
    select(label,val,opts,cb){const s=h('select',{class:'fc',onchange:()=>cb(s.value)},opts.map(o=>h('option',{value:o[0],selected:String(o[0])===String(val)},o[1])));return C.field(label,s)},
    text(label,val,cb,o){o=o||{};const i=h('input',{class:'fc',type:o.type||'text',value:val||'',placeholder:o.ph||'',oninput:()=>cb(i.value)});return C.field(label,i)},
    textarea(label,val,cb,o){o=o||{};const i=h('textarea',{class:'fc',rows:o.rows||3,placeholder:o.ph||'',oninput:()=>cb(i.value)});i.value=val||'';return C.field(label,i)},
    number(label,val,cb,o){o=o||{};const i=h('input',{class:'fc',type:'number',min:o.min,max:o.max,step:o.step||1,value:val===undefined||val===null?'':val,oninput:()=>cb(i.value===''?'':+i.value)});return C.field(label,i)},
    range(label,val,cb,o){o=o||{};const v=h('span',{class:'epb-rv'},String(val??o.def??0));const i=h('input',{type:'range',min:o.min,max:o.max,step:o.step||1,value:val??o.def??0,oninput:()=>{v.textContent=i.value;cb(+i.value)}});return C.field(label,h('div',{class:'epb-range'},i,v))},
    toggle(label,val,cb){const i=h('input',{type:'checkbox',checked:!!val,onchange:()=>cb(i.checked)});return h('label',{class:'epb-toggle'},i,h('span',null,label))},
    seg(label,val,opts,cb){return C.field(label,h('div',{class:'epb-seg'},opts.map(o=>h('button',{type:'button',class:String(o[0])===String(val)?'on':'',title:o[2]||o[1],onclick:e=>{e.preventDefault();cb(o[0])}},o[1].startsWith('fa-')?icon(o[1]):o[1]))))},
    color(label,val,cb){
      const sw=PALETTE.map(c=>h('button',{type:'button',class:'epb-sw'+(val===c?' on':''),style:'background:'+c,title:c,onclick:e=>{e.preventDefault();cb(c)}}));
      const pick=h('input',{type:'color',class:'epb-cp',value:/^#[0-9a-f]{6}$/i.test(val||'')?val:'#2f7bff',oninput:()=>cb(pick.value)});
      return C.field(label,h('div',{class:'epb-colors'},sw,pick,h('button',{type:'button',class:'epb-clr',title:'Zurücksetzen',onclick:e=>{e.preventDefault();cb('')}},icon('fa-ban'))))
    },
  };
  /* Gemeinsame Gruppen: Typografie, Farben, Abstand, Rahmen, Erweitert */
  function groups(b,ctx,o){
    o=o||{};const set=(k,v)=>ctx.set(b,{[k]:v}),out=[];
    if(o.text)out.push(C.section('Typografie',[
      C.seg('Ausrichtung',b.align||'left',[['left','fa-align-left','Links'],['center','fa-align-center','Zentriert'],['right','fa-align-right','Rechts'],['justify','fa-align-justify','Blocksatz']],v=>set('align',v)),
      C.select('Schriftgröße',b.size||'',[['','Standard'],['s','Klein'],['m','Normal'],['l','Groß'],['xl','Sehr groß'],['xxl','Riesig']],v=>set('size',v)),
      C.select('Schriftstärke',b.weight||'',[['','Standard'],['400','Normal'],['600','Halbfett'],['700','Fett'],['800','Extrafett']],v=>set('weight',v)),
      C.range('Zeilenhöhe',b.lineHeight||'',v=>set('lineHeight',v),{min:1,max:2.5,step:.1,def:1.6}),
    ]));
    if(o.colors!==false)out.push(C.section('Farben',[C.color('Textfarbe',b.color,v=>set('color',v)),C.color('Hintergrund',b.bg,v=>set('bg',v))],!!b.color||!!b.bg));
    if(o.spacing!==false)out.push(C.section('Abstand',[
      C.seg('Innenabstand',b.padding||'',[['','–'],['none','0'],['s','S'],['m','M'],['l','L']],v=>set('padding',v)),
      C.number('Abstand oben (px)',b.mt,v=>set('mt',v),{min:0,max:200}),C.number('Abstand unten (px)',b.mb,v=>set('mb',v),{min:0,max:200})],false));
    if(o.border!==false)out.push(C.section('Rahmen',[C.range('Rundung (px)',b.radius||0,v=>set('radius',v),{min:0,max:60}),C.range('Rahmenstärke (px)',b.borderW||0,v=>set('borderW',v),{min:0,max:12}),C.color('Rahmenfarbe',b.borderC,v=>set('borderC',v))],false));
    out.push(C.section('Erweitert',[C.text('CSS-Klasse(n)',b.className,v=>ctx.set(b,{className:v},true),{ph:'z. B. mein-block'}),C.text('Anker (HTML-ID)',b.anchor,v=>ctx.set(b,{anchor:v},true),{ph:'z. B. kontakt'}),C.note('Mit dem Anker lässt sich der Block per Link <code>#anker</code> ansteuern.')],false));
    return out;
  }

  /* ───────── Text ───────── */
  const alignBtns=(b,ctx)=>[['left','fa-align-left','Links'],['center','fa-align-center','Zentriert'],['right','fa-align-right','Rechts']].map(a=>({icon:a[1],title:a[2],active:(b.align||'left')===a[0],run:()=>ctx.set(b,{align:a[0]})}));
  E.reg('paragraph',{label:'Absatz',icon:'fa-paragraph',cat:'text',kw:'text absatz paragraph',content:['html'],defaults:()=>({html:'',align:'left'}),
    serialize:b=>'<p'+A(b)+'>'+(b.html||'')+'</p>',
    parse:(inner,attrs)=>{const p=dom(inner).querySelector('p');return Object.assign(nb('paragraph'),attrs,{html:p?p.innerHTML:inner.trim()})},
    render:(b,ctx)=>{const p=h('div',{class:'epb-text epb-p',style:styleOf(b)});ctx.rich(p,b,'html',{ph:'Tippe / für Blöcke oder schreibe einfach los …',enter:true});return p},
    toolbar:alignBtns,inspector:(b,ctx)=>groups(b,ctx,{text:true})});
  E.reg('heading',{label:'Überschrift',icon:'fa-heading',cat:'text',kw:'überschrift titel heading h1 h2 h3',content:['html'],defaults:()=>({html:'',level:2,align:'left'}),
    serialize:b=>{const l=Math.max(1,Math.min(6,+b.level||2));return '<h'+l+A(b)+'>'+(b.html||'')+'</h'+l+'>'},
    parse:(inner,attrs)=>{const e=dom(inner).querySelector('h1,h2,h3,h4,h5,h6');return Object.assign(nb('heading'),attrs,{html:e?e.innerHTML:inner.trim(),level:e?+e.tagName[1]:(attrs.level||2)})},
    render:(b,ctx)=>{const e=h('div',{class:'epb-text epb-h epb-h'+b.level,style:styleOf(b)});ctx.rich(e,b,'html',{ph:'Überschrift',enter:true,single:true});return e},
    toolbar:(b,ctx)=>[1,2,3,4].map(l=>({text:'H'+l,title:'Ebene '+l,active:+b.level===l,run:()=>ctx.set(b,{level:l})})).concat(alignBtns(b,ctx)),
    inspector:(b,ctx)=>[C.section('Überschrift',[C.select('Ebene',b.level,[[1,'H1 – Haupttitel'],[2,'H2'],[3,'H3'],[4,'H4'],[5,'H5'],[6,'H6']],v=>ctx.set(b,{level:+v}))])].concat(groups(b,ctx,{text:true}))});
  E.reg('list',{label:'Liste',icon:'fa-list-ul',cat:'text',kw:'liste aufzählung list ul ol',content:['html'],defaults:()=>({html:'<li></li>',ordered:false}),
    serialize:b=>{const t=b.ordered?'ol':'ul';return '<'+t+A(b)+(b.ordered&&b.start>1?' start="'+(+b.start)+'"':'')+'>'+(b.html||'<li></li>')+'</'+t+'>'},
    parse:(inner,attrs)=>{const e=dom(inner).querySelector('ul,ol');return Object.assign(nb('list'),attrs,{html:e?e.innerHTML:'<li>'+inner.trim()+'</li>',ordered:e?e.tagName==='OL':!!attrs.ordered})},
    render:(b,ctx)=>{const e=h(b.ordered?'ol':'ul',{class:'epb-text epb-ul',style:styleOf(b)});ctx.rich(e,b,'html',{ph:'',list:true});if(!e.innerHTML.trim())e.innerHTML='<li><br></li>';return e},
    toolbar:(b,ctx)=>[{icon:'fa-list-ul',title:'Aufzählung',active:!b.ordered,run:()=>ctx.set(b,{ordered:false})},{icon:'fa-list-ol',title:'Nummerierung',active:!!b.ordered,run:()=>ctx.set(b,{ordered:true})}],
    inspector:(b,ctx)=>[C.section('Liste',[C.seg('Art',b.ordered?'1':'0',[['0','fa-list-ul','Aufzählung'],['1','fa-list-ol','Nummeriert']],v=>ctx.set(b,{ordered:v==='1'})),b.ordered?C.number('Beginnt bei',b.start||1,v=>ctx.set(b,{start:v},true),{min:1,max:9999}):null].filter(Boolean))].concat(groups(b,ctx,{text:true}))});
  E.reg('quote',{label:'Zitat',icon:'fa-quote-left',cat:'text',kw:'zitat quote',content:['html','cite'],defaults:()=>({html:'',cite:'',align:'left'}),
    serialize:b=>'<blockquote'+A(b,'border-left:4px solid '+(css(b.borderC)||'#2f7bff')+';margin-left:0;padding:8px 0 8px 18px'+(b.borderW?'':''))+'><p>'+(b.html||'')+'</p>'+(b.cite?'<cite>'+b.cite+'</cite>':'')+'</blockquote>',
    parse:(inner,attrs)=>{const d=dom(inner),p=d.querySelector('p'),c=d.querySelector('cite');return Object.assign(nb('quote'),attrs,{html:p?p.innerHTML:(d.querySelector('blockquote')||d).innerHTML,cite:c?c.innerHTML:''})},
    render:(b,ctx)=>{const w=h('div',{class:'epb-quote',style:styleOf(b)}),t=h('div',{class:'epb-text'}),c=h('div',{class:'epb-text epb-cite'});ctx.rich(t,b,'html',{ph:'Zitat …',single:false});ctx.rich(c,b,'cite',{ph:'Quelle / Urheber (optional)',single:true});w.append(t,c);return w},
    toolbar:alignBtns,inspector:(b,ctx)=>groups(b,ctx,{text:true})});
  E.reg('code',{label:'Code',icon:'fa-code',cat:'text',kw:'code quelltext pre',content:['html'],defaults:()=>({html:'',lang:''}),
    serialize:b=>'<pre'+A(b)+'><code'+(b.lang?' class="language-'+esc(String(b.lang).replace(/[^a-z0-9+#-]/gi,''))+'"':'')+'>'+(b.html||'')+'</code></pre>',
    parse:(inner,attrs)=>{const d=dom(inner),c=d.querySelector('code')||d.querySelector('pre');return Object.assign(nb('code'),attrs,{html:esc(c?c.textContent:inner)})},
    render:(b,ctx)=>{const t=h('textarea',{class:'epb-code-area',spellcheck:'false',placeholder:'Code …',rows:3});t.value=(function(){const d=document.createElement('div');d.innerHTML=b.html||'';return d.textContent})();
      const fit=()=>{t.style.height='auto';t.style.height=Math.max(70,t.scrollHeight)+'px'};t.addEventListener('input',()=>{b.html=esc(t.value);fit();ctx.changed(b)});setTimeout(fit,0);t.addEventListener('keydown',ev=>{if(ev.key==='Tab'){ev.preventDefault();const s=t.selectionStart;t.value=t.value.slice(0,s)+'  '+t.value.slice(t.selectionEnd);t.selectionStart=t.selectionEnd=s+2;t.dispatchEvent(new Event('input'))}});return h('div',{class:'epb-code'},h('div',{class:'epb-code-lang'},b.lang||'Code'),t)},
    focus:el=>{const t=el.querySelector('textarea');t&&t.focus()},
    inspector:(b,ctx)=>[C.section('Code',[C.text('Sprache (Hinweis)',b.lang,v=>ctx.set(b,{lang:v},true),{ph:'z. B. php, js, html'})])].concat(groups(b,ctx,{colors:true,spacing:true,border:false}))});

  /* ───────── Medien ───────── */
  const sizeW={s:'25%',m:'50%',l:'75%',f:'100%'};
  E.reg('image',{label:'Bild',icon:'fa-image',cat:'media',kw:'bild foto image grafik',content:['html'],defaults:()=>({url:'',alt:'',html:'',size:'f',align:'center',link:'',newTab:false}),
    serialize:b=>{
      const w=b.widthPx?num(b.widthPx,20,2000,0)+'px':(sizeW[b.size]||'100%');
      const img='<img src="'+esc(b.url||'')+'" alt="'+esc(b.alt||'')+'" style="max-width:100%;height:auto;width:'+w+';'+(b.radius?'border-radius:'+num(b.radius,0,60,0)+'px;':'')+(b.shadow?'box-shadow:0 8px 24px rgba(0,0,0,.25);':'')+'" loading="lazy">';
      const inner=b.link?'<a href="'+esc(b.link)+'"'+(b.newTab?' target="_blank" rel="noopener noreferrer"':'')+'>'+img+'</a>':img;
      return '<figure'+A({anchor:b.anchor,className:b.className,align:b.align,mt:b.mt,mb:b.mb,padding:b.padding,bg:b.bg},'margin-left:0;margin-right:0')+'>'+inner+(b.html?'<figcaption>'+b.html+'</figcaption>':'')+'</figure>';
    },
    parse:(inner,attrs)=>{const d=dom(inner),i=d.querySelector('img'),c=d.querySelector('figcaption'),a=d.querySelector('a');return Object.assign(nb('image'),{url:i?i.getAttribute('src')||'':'',alt:i?i.getAttribute('alt')||'':'',link:a?a.getAttribute('href')||'':''},attrs,{html:c?c.innerHTML:''})},
    render:(b,ctx)=>{
      const w=h('div',{class:'epb-image',style:'text-align:'+(b.align||'center')});
      if(!b.url){
        w.append(h('div',{class:'epb-ph-media'},icon('fa-image'),h('div',null,'Bild auswählen'),h('div',{class:'epb-ph-btns'},
          h('button',{type:'button',class:'btn-a',onclick:()=>ctx.pickImage(r=>ctx.set(b,{url:r.url,alt:r.alt||b.alt,html:r.caption||b.html}))},icon('fa-photo-film'),' Mediathek / Freie Bilder'),
          h('button',{type:'button',class:'btn-g',onclick:()=>{const u=prompt('Bild-Adresse (https://…)');if(u)ctx.set(b,{url:u.trim()})}},icon('fa-link'),' Adresse'),
          ctx.upload?h('button',{type:'button',class:'btn-g',onclick:()=>ctx.upload(r=>ctx.set(b,{url:r.url,alt:r.alt||b.alt}))},icon('fa-upload'),' Hochladen'):null,
          ctx.aiImage?h('button',{type:'button',class:'btn-g',onclick:()=>ctx.aiImage(r=>ctx.set(b,{url:r.url,alt:r.alt||b.alt}))},icon('fa-wand-magic-sparkles'),' Mit KI erzeugen'):null)));
        return w;
      }
      const img=h('img',{src:b.url,alt:b.alt||'',style:'max-width:100%;height:auto;width:'+(b.widthPx?num(b.widthPx,20,2000,0)+'px':(sizeW[b.size]||'100%'))+(b.radius?';border-radius:'+num(b.radius,0,60,0)+'px':'')+(b.shadow?';box-shadow:0 8px 24px rgba(0,0,0,.25)':'')});
      const cap=h('div',{class:'epb-text epb-caption'});ctx.rich(cap,b,'html',{ph:'Bildunterschrift (optional)',single:true});
      w.append(img,cap);return w;
    },
    toolbar:(b,ctx)=>[{icon:'fa-photo-film',title:'Bild ersetzen',run:()=>ctx.pickImage(r=>ctx.set(b,{url:r.url,alt:r.alt||b.alt}))}].concat(alignBtns(b,ctx)),
    inspector:(b,ctx)=>[C.section('Bild',[
      C.text('Adresse (URL)',b.url,v=>ctx.set(b,{url:v},true)),C.text('Alternativtext (wichtig für Barrierefreiheit & SEO)',b.alt,v=>ctx.set(b,{alt:v},true)),
      C.seg('Breite',b.widthPx?'':(b.size||'f'),[['s','25%'],['m','50%'],['l','75%'],['f','100%']],v=>ctx.set(b,{size:v,widthPx:''})),
      C.number('Eigene Breite (px, leer = Preset)',b.widthPx,v=>ctx.set(b,{widthPx:v})),
      C.seg('Ausrichtung',b.align||'center',[['left','fa-align-left','Links'],['center','fa-align-center','Mitte'],['right','fa-align-right','Rechts']],v=>ctx.set(b,{align:v})),
      C.range('Rundung (px)',b.radius||0,v=>ctx.set(b,{radius:v}),{min:0,max:60}),C.toggle('Schatten',b.shadow,v=>ctx.set(b,{shadow:v})),
    ]),C.section('Link',[C.text('Bild verlinken (URL)',b.link,v=>ctx.set(b,{link:v},true),{ph:'https://…'}),C.toggle('In neuem Tab öffnen',b.newTab,v=>ctx.set(b,{newTab:v},true))],!!b.link)].concat(groups(b,ctx,{colors:false,border:false}))});
  E.reg('gallery',{label:'Galerie',icon:'fa-images',cat:'media',kw:'galerie bilder gallery',defaults:()=>({images:[],columns:3,gap:12,crop:true}),
    serialize:b=>'<div'+A({anchor:b.anchor,className:b.className,mt:b.mt,mb:b.mb},'display:grid;grid-template-columns:repeat('+num(b.columns,1,6,3)+',minmax(0,1fr));gap:'+num(b.gap,0,60,12)+'px')+'>'+(b.images||[]).map(i=>'<figure style="margin:0"><img src="'+esc(i.url)+'" alt="'+esc(i.alt||'')+'" style="width:100%;'+(b.crop?'aspect-ratio:1/1;object-fit:cover;':'height:auto;')+'" loading="lazy"></figure>').join('')+'</div>',
    parse:(inner,attrs)=>{const imgs=[...dom(inner).querySelectorAll('img')].map(i=>({url:i.getAttribute('src')||'',alt:i.getAttribute('alt')||''}));return Object.assign(nb('gallery'),attrs,{images:Array.isArray(attrs.images)&&attrs.images.length?attrs.images:imgs})},
    render:(b,ctx)=>{const w=h('div',{class:'epb-gallery'});
      const g=h('div',{class:'epb-gal-grid',style:'grid-template-columns:repeat('+num(b.columns,1,6,3)+',minmax(0,1fr));gap:'+num(b.gap,0,60,12)+'px'},(b.images||[]).map((i,ix)=>h('div',{class:'epb-gal-item'},h('img',{src:i.url,alt:i.alt||'',style:b.crop?'aspect-ratio:1/1;object-fit:cover;width:100%':'width:100%'}),h('button',{type:'button',class:'epb-gal-x',title:'Entfernen',onclick:e=>{e.stopPropagation();b.images.splice(ix,1);ctx.set(b,{images:b.images})}},icon('fa-xmark')))));
      w.append(g,h('div',{class:'epb-ph-btns'},h('button',{type:'button',class:'btn-a',onclick:()=>ctx.pickImage(r=>{b.images=(b.images||[]).concat([{url:r.url,alt:r.alt||''}]);ctx.set(b,{images:b.images})})},icon('fa-plus'),' Bild hinzufügen')));return w},
    inspector:(b,ctx)=>[C.section('Galerie',[C.range('Spalten',b.columns,v=>ctx.set(b,{columns:v}),{min:1,max:6}),C.range('Abstand (px)',b.gap,v=>ctx.set(b,{gap:v}),{min:0,max:60}),C.toggle('Quadratisch zuschneiden',b.crop,v=>ctx.set(b,{crop:v}))])].concat(groups(b,ctx,{colors:false,border:false}))});
  E.reg('video',{label:'Video-Datei',icon:'fa-film',cat:'media',kw:'video film mp4',defaults:()=>({url:'',poster:'',controls:true,loop:false,muted:false}),
    serialize:b=>'<video'+A(b,'width:100%;max-width:100%')+(b.controls?' controls':'')+(b.loop?' loop':'')+(b.muted?' muted':'')+' preload="metadata" src="'+esc(b.url||'')+'"'+(b.poster?' poster="'+esc(b.poster)+'"':'')+'></video>',
    parse:(inner,attrs)=>{const v=dom(inner).querySelector('video');return Object.assign(nb('video'),{url:v?v.getAttribute('src')||'':'',poster:v?v.getAttribute('poster')||'':''},attrs)},
    render:(b,ctx)=>b.url?h('video',{src:b.url,controls:true,style:'width:100%',poster:b.poster||null}):h('div',{class:'epb-ph-media'},icon('fa-film'),h('div',null,'Video-Datei (mp4/webm) – Adresse in den Einstellungen eintragen'),h('button',{type:'button',class:'btn-g',onclick:()=>{const u=prompt('Video-Adresse (https://… .mp4)');if(u)ctx.set(b,{url:u.trim()})}},'Adresse eingeben')),
    inspector:(b,ctx)=>[C.section('Video',[C.text('Adresse (URL)',b.url,v=>ctx.set(b,{url:v},true)),C.text('Vorschaubild (URL)',b.poster,v=>ctx.set(b,{poster:v},true)),C.toggle('Steuerung anzeigen',b.controls,v=>ctx.set(b,{controls:v},true)),C.toggle('Wiederholen',b.loop,v=>ctx.set(b,{loop:v},true)),C.toggle('Stumm',b.muted,v=>ctx.set(b,{muted:v},true))])].concat(groups(b,ctx,{colors:false,border:false}))});
  E.reg('audio',{label:'Audio-Datei',icon:'fa-music',cat:'media',kw:'audio musik mp3 podcast',defaults:()=>({url:'',controls:true,loop:false}),
    serialize:b=>'<audio'+A(b,'width:100%')+' controls'+(b.loop?' loop':'')+' preload="none" src="'+esc(b.url||'')+'"></audio>',
    parse:(inner,attrs)=>{const v=dom(inner).querySelector('audio');return Object.assign(nb('audio'),{url:v?v.getAttribute('src')||'':''},attrs)},
    render:(b,ctx)=>b.url?h('audio',{src:b.url,controls:true,style:'width:100%'}):h('div',{class:'epb-ph-media'},icon('fa-music'),h('div',null,'Audio-Datei (mp3/ogg)'),h('button',{type:'button',class:'btn-g',onclick:()=>{const u=prompt('Audio-Adresse (https://… .mp3)');if(u)ctx.set(b,{url:u.trim()})}},'Adresse eingeben')),
    inspector:(b,ctx)=>[C.section('Audio',[C.text('Adresse (URL)',b.url,v=>ctx.set(b,{url:v},true)),C.toggle('Wiederholen',b.loop,v=>ctx.set(b,{loop:v},true))])].concat(groups(b,ctx,{colors:false,border:false}))});

  /* ───────── Einbettungen ───────── */
  function embedFrom(u){
    u=String(u||'').trim();let m;if(!u)return null;
    if((m=u.match(/^(?:https?:\/\/)?(?:www\.|m\.)?(?:youtube\.com\/(?:watch\?(?:.*&)?v=|embed\/|shorts\/|live\/)|youtu\.be\/)([A-Za-z0-9_-]{6,15})/i)))return {provider:'YouTube',src:'https://www.youtube-nocookie.com/embed/'+m[1],ratio:'16/9'};
    if((m=u.match(/^(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(?:video\/)?(\d+)/i)))return {provider:'Vimeo',src:'https://player.vimeo.com/video/'+m[1],ratio:'16/9'};
    if((m=u.match(/^(?:https?:\/\/)?open\.spotify\.com\/(?:intl-[a-z]+\/)?(track|album|playlist|episode|show|artist)\/([A-Za-z0-9]+)/i)))return {provider:'Spotify',src:'https://open.spotify.com/embed/'+m[1]+'/'+m[2],height:/track|episode/.test(m[1])?152:352};
    if(/^(?:https?:\/\/)?(?:www\.)?soundcloud\.com\//i.test(u))return {provider:'SoundCloud',src:'https://w.soundcloud.com/player/?url='+encodeURIComponent(u.replace(/^(?!https?:)/,'https://'))+'&color=%232f7bff',height:166};
    if((m=u.match(/^(?:https?:\/\/)?(?:www\.)?mixcloud\.com(\/[^?#]+)/i)))return {provider:'Mixcloud',src:'https://www.mixcloud.com/widget/iframe/?feed='+encodeURIComponent(m[1]),height:120};
    if(/^https:\/\/(?:www\.)?google\.com\/maps\/embed/i.test(u)||/^https:\/\/(?:www\.)?openstreetmap\.org\/export\/embed\.html/i.test(u))return {provider:'Karte',src:u,height:380};
    if((m=u.match(/^(?:https?:\/\/)?(?:www\.)?google\.[a-z.]+\/maps\/?(?:.*[?&]q=|place\/|search\/)([^&/#]+)/i)))return {provider:'Google Maps',src:'https://maps.google.com/maps?q='+encodeURIComponent(decodeURIComponent(m[1].replace(/\+/g,' ')))+'&output=embed',height:380};
    if(/^https:\/\/embed\.(?:music|podcasts)\.apple\.com\//i.test(u))return {provider:'Apple',src:u,height:175};
    return null;
  }
  E.embedFrom=embedFrom;
  E.reg('embed',{label:'Einbettung (Video, Musik, Karte)',icon:'fa-circle-play',cat:'embed',kw:'embed youtube vimeo spotify soundcloud karte maps einbetten video',defaults:()=>({url:'',src:'',provider:'',ratio:'16/9',height:0,caption:'',html:''}),content:['html'],
    serialize:b=>{if(!b.src)return '<p>'+esc(b.url||'')+'</p>';
      const st='width:100%;border:0;'+(b.height?'height:'+num(b.height,60,900,360)+'px':'aspect-ratio:'+css(b.ratio||'16/9'));
      return '<figure'+A({anchor:b.anchor,className:b.className,mt:b.mt,mb:b.mb},'margin-left:0;margin-right:0')+'><iframe src="'+esc(b.src)+'" style="'+st+'" loading="lazy" allowfullscreen title="'+esc(b.provider||'Einbettung')+'"></iframe>'+(b.html?'<figcaption>'+b.html+'</figcaption>':'')+'</figure>'},
    parse:(inner,attrs)=>{const d=dom(inner),f=d.querySelector('iframe'),c=d.querySelector('figcaption');const src=f?f.getAttribute('src')||'':'';return Object.assign(nb('embed'),{src,url:attrs.url||src},attrs,{html:c?c.innerHTML:''})},
    render:(b,ctx)=>{const w=h('div',{class:'epb-embed'});
      if(b.src){w.append(h('div',{class:'epb-embed-frame',style:b.height?'height:'+num(b.height,60,900,360)+'px':'aspect-ratio:'+css(b.ratio||'16/9')},h('iframe',{src:b.src,loading:'lazy',style:'width:100%;height:100%;border:0;pointer-events:none'}),h('div',{class:'epb-embed-shield'},icon('fa-circle-play'),' '+(b.provider||'Einbettung'))));const cap=h('div',{class:'epb-text epb-caption'});ctx.rich(cap,b,'html',{ph:'Beschriftung (optional)',single:true});w.append(cap)}
      else{const i=h('input',{class:'fc',placeholder:'Adresse einfügen: YouTube, Vimeo, Spotify, SoundCloud, Mixcloud, Google Maps …',value:b.url||''});
        const go=()=>{const r=embedFrom(i.value);if(!r){alert('Diese Adresse wird nicht unterstützt. Erlaubt: YouTube, Vimeo, Spotify, SoundCloud, Mixcloud, Apple Music/Podcasts, Google Maps/OpenStreetMap-Einbettungen. Für anderes den Block „HTML“ verwenden.');return}ctx.set(b,{url:i.value.trim(),src:r.src,provider:r.provider,ratio:r.ratio||'16/9',height:r.height||0})};
        i.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();go()}});w.append(h('div',{class:'epb-ph-media'},icon('fa-circle-play'),h('div',null,'Einbetten'),i,h('button',{type:'button',class:'btn-a',onclick:go},'Einbetten')))}
      return w},
    inspector:(b,ctx)=>[C.section('Einbettung',[C.text('Adresse',b.url,v=>{const r=embedFrom(v);ctx.set(b,Object.assign({url:v},r?{src:r.src,provider:r.provider,ratio:r.ratio||'16/9',height:r.height||0}:{}),true)}),C.select('Seitenverhältnis',b.height?'':(b.ratio||'16/9'),[['16/9','16:9'],['4/3','4:3'],['1/1','1:1'],['21/9','21:9']],v=>ctx.set(b,{ratio:v,height:0})),C.number('Feste Höhe (px, überschreibt)',b.height||'',v=>ctx.set(b,{height:v||0}),{min:60,max:900}),C.note('Unterstützt: YouTube, Vimeo, Spotify, SoundCloud, Mixcloud, Apple Music/Podcasts, Google Maps. Alles andere über den Block „HTML“.')])].concat(groups(b,ctx,{colors:false,border:false,text:false}))});

  /* ───────── Design ───────── */
  E.reg('button',{label:'Button',icon:'fa-square-up-right',cat:'design',kw:'button knopf link cta',defaults:()=>({label:'Mehr erfahren',url:'',newTab:false,variant:'fill',color:'#2f7bff',textColor:'#ffffff',radius:8,width:'auto',align:'left',size:'m'}),
    serialize:b=>{const col=css(b.color)||'#2f7bff',tc=css(b.textColor)||'#ffffff',fill=b.variant!=='outline';
      const st='display:inline-block;padding:12px 26px;text-decoration:none;font-weight:600;border-radius:'+num(b.radius,0,60,8)+'px;'+(fill?'background-color:'+col+';color:'+tc+';border:2px solid '+col:'color:'+col+';border:2px solid '+col)+';'+(b.width==='full'?'display:block;text-align:center;':'')+(b.size&&SIZES[b.size]?'font-size:'+SIZES[b.size]+';':'');
      return '<div'+A({anchor:b.anchor,className:b.className,align:b.align,mt:b.mt,mb:b.mb})+'><a href="'+esc(b.url||'#')+'"'+(b.newTab?' target="_blank" rel="noopener noreferrer"':'')+' style="'+esc(st)+'">'+esc(b.label||'')+'</a></div>'},
    parse:(inner,attrs)=>{const a=dom(inner).querySelector('a');return Object.assign(nb('button'),{label:a?a.textContent:'',url:a?a.getAttribute('href')||'':''},attrs)},
    render:(b,ctx)=>{const col=css(b.color)||'#2f7bff',tc=css(b.textColor)||'#ffffff',fill=b.variant!=='outline';
      const lab=h('span',{class:'epb-btn-label',contenteditable:'true','data-ph':'Beschriftung',spellcheck:'false',style:'display:inline-block;padding:12px 26px;font-weight:600;border-radius:'+num(b.radius,0,60,8)+'px;border:2px solid '+col+';'+(fill?'background:'+col+';color:'+tc:'color:'+col)+';'+(b.width==='full'?'display:block;text-align:center':'')});
      lab.textContent=b.label||'';lab.addEventListener('input',()=>{b.label=lab.textContent;ctx.changed(b)});lab.addEventListener('keydown',e=>{if(e.key==='Enter')e.preventDefault()});
      return h('div',{style:'text-align:'+(b.align||'left')},lab)},
    focus:el=>{const l=el.querySelector('.epb-btn-label');l&&l.focus()},
    toolbar:alignBtns,
    inspector:(b,ctx)=>[C.section('Button',[C.text('Ziel-Adresse (URL)',b.url,v=>ctx.set(b,{url:v},true),{ph:'https://… oder /seite/'}),C.toggle('In neuem Tab öffnen',b.newTab,v=>ctx.set(b,{newTab:v},true)),
      C.seg('Stil',b.variant||'fill',[['fill','Gefüllt'],['outline','Umriss']],v=>ctx.set(b,{variant:v})),C.color('Farbe',b.color,v=>ctx.set(b,{color:v||'#2f7bff'})),b.variant!=='outline'?C.color('Textfarbe',b.textColor,v=>ctx.set(b,{textColor:v||'#ffffff'})):null,
      C.range('Rundung (px)',b.radius,v=>ctx.set(b,{radius:v}),{min:0,max:40}),C.seg('Breite',b.width||'auto',[['auto','Auto'],['full','Volle Breite']],v=>ctx.set(b,{width:v})),C.select('Schriftgröße',b.size||'m',[['s','Klein'],['m','Normal'],['l','Groß'],['xl','Sehr groß']],v=>ctx.set(b,{size:v})),
      C.seg('Ausrichtung',b.align||'left',[['left','fa-align-left','Links'],['center','fa-align-center','Mitte'],['right','fa-align-right','Rechts']],v=>ctx.set(b,{align:v}))].filter(Boolean))].concat(groups(b,ctx,{colors:false,border:false,spacing:true}))});
  E.reg('divider',{label:'Trennlinie',icon:'fa-minus',cat:'design',kw:'trennlinie linie hr divider',defaults:()=>({style:'solid',thickness:2,width:100,color:'#94a3b8'}),
    serialize:b=>'<hr'+A({anchor:b.anchor,className:b.className,mt:b.mt,mb:b.mb},'border:0;border-top:'+num(b.thickness,1,12,2)+'px '+(['solid','dashed','dotted'].includes(b.style)?b.style:'solid')+' '+(css(b.color)||'#94a3b8')+';width:'+num(b.width,10,100,100)+'%')+'>',
    parse:(inner,attrs)=>Object.assign(nb('divider'),attrs),
    render:(b)=>h('div',{class:'epb-divider'},h('hr',{style:'border:0;border-top:'+num(b.thickness,1,12,2)+'px '+(['solid','dashed','dotted'].includes(b.style)?b.style:'solid')+' '+(css(b.color)||'#94a3b8')+';width:'+num(b.width,10,100,100)+'%'})),
    inspector:(b,ctx)=>[C.section('Linie',[C.select('Stil',b.style,[['solid','Durchgezogen'],['dashed','Gestrichelt'],['dotted','Gepunktet']],v=>ctx.set(b,{style:v})),C.range('Stärke (px)',b.thickness,v=>ctx.set(b,{thickness:v}),{min:1,max:12}),C.range('Breite (%)',b.width,v=>ctx.set(b,{width:v}),{min:10,max:100}),C.color('Farbe',b.color,v=>ctx.set(b,{color:v||'#94a3b8'}))]),C.section('Abstand',[C.number('Abstand oben (px)',b.mt,v=>ctx.set(b,{mt:v}),{min:0,max:200}),C.number('Abstand unten (px)',b.mb,v=>ctx.set(b,{mb:v}),{min:0,max:200})],false)]});
  E.reg('spacer',{label:'Abstand',icon:'fa-arrows-up-down',cat:'design',kw:'abstand platz spacer leerraum',defaults:()=>({height:40}),
    serialize:b=>'<div style="height:'+num(b.height,0,400,40)+'px" aria-hidden="true"></div>',parse:(inner,attrs)=>Object.assign(nb('spacer'),attrs),
    render:(b)=>h('div',{class:'epb-spacer',style:'height:'+num(b.height,0,400,40)+'px'},h('span',null,num(b.height,0,400,40)+' px')),
    inspector:(b,ctx)=>[C.section('Abstand',[C.range('Höhe (px)',b.height,v=>ctx.set(b,{height:v}),{min:0,max:400})])]});
  const CONT_MAX=4;
  E.reg('columns',{label:'Spalten',icon:'fa-table-columns',cat:'design',kw:'spalten columns layout',container:true,defaults:()=>({gap:24,children:[nb('column',{children:[nb('paragraph')]}),nb('column',{children:[nb('paragraph')]})]}),
    afterParse:(attrs,kids)=>({children:kids.filter(k=>k.name==='column').length?kids.filter(k=>k.name==='column'):[nb('column',{children:kids})]}),
    serialize:(b,sl)=>'<div'+A({anchor:b.anchor,className:b.className,mt:b.mt,mb:b.mb,bg:b.bg,padding:b.padding,radius:b.radius},'display:flex;flex-wrap:wrap;gap:'+num(b.gap,0,80,24)+'px;align-items:'+(b.valign==='center'?'center':b.valign==='end'?'flex-end':'flex-start'))+'>'+sl(b.children||[])+'</div>',
    render:(b,ctx)=>h('div',{class:'epb-columns',style:'gap:'+num(b.gap,0,80,24)+'px;align-items:'+(b.valign==='center'?'center':b.valign==='end'?'flex-end':'flex-start')},(b.children||[]).map(c=>ctx.renderChild(c,b))),
    inspector:(b,ctx)=>[C.section('Spalten',[C.seg('Anzahl',String((b.children||[]).length),[['1','1'],['2','2'],['3','3'],['4','4']],v=>{
      const want=Math.max(1,Math.min(CONT_MAX,+v)),cur=b.children||[];
      while(cur.length<want)cur.push(nb('column',{children:[nb('paragraph')]}));
      while(cur.length>want){const last=cur.pop(),prev=cur[cur.length-1];prev.children=(prev.children||[]).concat((last.children||[]).filter(x=>!(x.name==='paragraph'&&!(x.html||'').trim())))}
      ctx.set(b,{children:cur})}),
      C.range('Abstand (px)',b.gap,v=>ctx.set(b,{gap:v}),{min:0,max:80}),C.seg('Vertikal',b.valign||'start',[['start','Oben'],['center','Mitte'],['end','Unten']],v=>ctx.set(b,{valign:v}))])].concat(groups(b,ctx,{text:false,border:true}))});
  E.reg('column',{label:'Spalte',icon:'fa-table-columns',cat:'hidden',kw:'',container:true,defaults:()=>({children:[]}),
    afterParse:(attrs,kids)=>({children:kids}),
    serialize:(b,sl)=>'<div'+A({bg:b.bg,padding:b.padding,radius:b.radius},'flex:'+(b.basis?'0 0 '+num(b.basis,10,100,50)+'%':'1 1 220px')+';min-width:0')+'>'+sl(b.children||[])+'</div>',
    render:(b,ctx)=>ctx.renderList(b),inspector:(b,ctx)=>[C.section('Spalte',[C.range('Breite (%, 0 = automatisch)',b.basis||0,v=>ctx.set(b,{basis:v}),{min:0,max:100,step:5})])].concat(groups(b,ctx,{text:false,colors:true,border:true}))});
  E.reg('group',{label:'Gruppe (Container)',icon:'fa-object-group',cat:'design',kw:'gruppe container box abschnitt group',container:true,defaults:()=>({bg:'#f1f5f9',padding:'m',radius:12,children:[nb('paragraph')]}),
    afterParse:(attrs,kids)=>({children:kids}),
    serialize:(b,sl)=>'<div'+A(b,b.maxWidth?'max-width:'+num(b.maxWidth,200,1600,800)+'px;margin-left:auto;margin-right:auto':'')+'>'+sl(b.children||[])+'</div>',
    render:(b,ctx)=>h('div',{class:'epb-group',style:styleOf(b,b.maxWidth?'max-width:'+num(b.maxWidth,200,1600,800)+'px;margin-left:auto;margin-right:auto':'')},ctx.renderList(b)),
    inspector:(b,ctx)=>[C.section('Gruppe',[C.number('Maximale Breite (px, leer = voll)',b.maxWidth,v=>ctx.set(b,{maxWidth:v}),{min:200,max:1600})])].concat(groups(b,ctx,{text:true}))});
  E.reg('details',{label:'Aufklapp-Bereich (FAQ)',icon:'fa-caret-down',cat:'design',kw:'aufklappen details faq akkordeon summary',content:['summary','html'],defaults:()=>({summary:'Frage oder Titel',html:'',open:false}),
    serialize:b=>'<details'+A(b)+(b.open?' open':'')+'><summary>'+(b.summary||'')+'</summary><div>'+(b.html||'')+'</div></details>',
    parse:(inner,attrs)=>{const d=dom(inner),s=d.querySelector('summary'),b=d.querySelector('details>div');return Object.assign(nb('details'),attrs,{summary:s?s.innerHTML:'',html:b?b.innerHTML:''})},
    render:(b,ctx)=>{const w=h('div',{class:'epb-details',style:styleOf(b)}),s=h('div',{class:'epb-text epb-summary'}),t=h('div',{class:'epb-text'});ctx.rich(s,b,'summary',{ph:'Titel',single:true});ctx.rich(t,b,'html',{ph:'Inhalt …'});w.append(h('div',{class:'epb-details-h'},icon('fa-caret-down'),s),t);return w},
    inspector:(b,ctx)=>[C.section('Aufklapp-Bereich',[C.toggle('Standardmäßig geöffnet',b.open,v=>ctx.set(b,{open:v},true))])].concat(groups(b,ctx,{text:true}))});
  E.reg('table',{label:'Tabelle',icon:'fa-table',cat:'design',kw:'tabelle table',defaults:()=>({rows:[['',''],['','']],header:true,striped:false,full:true,borders:true}),
    serialize:b=>{const rows=b.rows||[],cell=(t,c)=>'<'+t+(b.borders?' style="border:1px solid #cbd5e1;padding:8px 10px;text-align:left"':' style="padding:8px 10px;text-align:left"')+'>'+(c||'')+'</'+t+'>';
      const body=rows.slice(b.header?1:0).map((r,i)=>'<tr'+(b.striped&&i%2?' style="background-color:#f1f5f9"':'')+'>'+r.map(c=>cell('td',c)).join('')+'</tr>').join('');
      return '<table'+A({anchor:b.anchor,className:b.className,mt:b.mt,mb:b.mb},'border-collapse:collapse;'+(b.full?'width:100%':''))+'>'+(b.header&&rows[0]?'<thead><tr>'+rows[0].map(c=>cell('th',c)).join('')+'</tr></thead>':'')+'<tbody>'+body+'</tbody></table>'},
    parse:(inner,attrs)=>{const t=dom(inner).querySelector('table');const rows=t?[...t.querySelectorAll('tr')].map(tr=>[...tr.children].map(c=>c.innerHTML)):[['','']];return Object.assign(nb('table'),{rows,header:!!(t&&t.querySelector('thead')),full:true,borders:true},attrs,{rows})},
    render:(b,ctx)=>{const w=h('div',{class:'epb-table-wrap'}),tb=h('table',{class:'epb-table'+(b.borders?' bordered':''),style:b.full?'width:100%':''});
      (b.rows||[]).forEach((r,ri)=>{const tr=h('tr',{style:b.striped&&ri%2&&!(b.header&&ri===0)?'background:rgba(127,127,127,.12)':''});r.forEach((c,ci)=>{const td=h(b.header&&ri===0?'th':'td',{contenteditable:'true',spellcheck:'true'});td.innerHTML=c||'';td.addEventListener('input',()=>{b.rows[ri][ci]=td.innerHTML;ctx.changed(b)});td.addEventListener('keydown',e=>{if(e.key==='Enter')e.preventDefault()});tr.append(td)});tb.append(tr)});
      w.append(tb,h('div',{class:'epb-ph-btns'},h('button',{type:'button',class:'btn-g',onclick:()=>{b.rows.push(new Array(b.rows[0].length).fill(''));ctx.set(b,{rows:b.rows})}},icon('fa-plus'),' Zeile'),h('button',{type:'button',class:'btn-g',onclick:()=>{b.rows.forEach(r=>r.push(''));ctx.set(b,{rows:b.rows})}},icon('fa-plus'),' Spalte')));return w},
    inspector:(b,ctx)=>[C.section('Tabelle',[C.toggle('Kopfzeile',b.header,v=>ctx.set(b,{header:v})),C.toggle('Zebra-Streifen',b.striped,v=>ctx.set(b,{striped:v})),C.toggle('Rahmen',b.borders,v=>ctx.set(b,{borders:v})),C.toggle('Volle Breite',b.full,v=>ctx.set(b,{full:v})),
      C.field('Zeilen / Spalten',h('div',{class:'epb-ph-btns'},h('button',{type:'button',class:'btn-g',onclick:()=>{if(b.rows.length>1){b.rows.pop();ctx.set(b,{rows:b.rows})}}},'− Zeile'),h('button',{type:'button',class:'btn-g',onclick:()=>{if(b.rows[0].length>1){b.rows.forEach(r=>r.pop());ctx.set(b,{rows:b.rows})}}},'− Spalte')))])].concat(groups(b,ctx,{colors:false,border:false,text:false}))});

  /* ───────── Erweitert ───────── */
  E.reg('html',{label:'HTML',icon:'fa-file-code',cat:'advanced',kw:'html code eigener quelltext embed script',defaults:()=>({html:'',preview:false}),
    serialize:b=>b.html||'',
    parse:(inner,attrs)=>Object.assign(nb('html'),attrs,{html:inner.replace(/^\s*\n/,'').replace(/\n\s*$/,'')}),
    render:(b,ctx)=>{const w=h('div',{class:'epb-html'});
      const t=h('textarea',{class:'epb-code-area',spellcheck:'false',placeholder:'<div>Eigenes HTML …</div>',rows:5});t.value=b.html||'';
      const fit=()=>{t.style.height='auto';t.style.height=Math.max(110,t.scrollHeight)+'px'};t.addEventListener('input',()=>{b.html=t.value;fit();ctx.changed(b)});setTimeout(fit,0);
      const pv=h('div',{class:'epb-html-preview'});
      const draw=()=>{pv.innerHTML='';if(!b.preview)return;pv.append(h('iframe',{sandbox:'',srcdoc:'<base target="_blank"><style>body{font-family:system-ui,sans-serif;margin:8px}</style>'+(b.html||''),style:'width:100%;min-height:120px;border:1px solid var(--border);border-radius:8px;background:#fff'}))};
      const tabs=h('div',{class:'epb-html-tabs'},h('button',{type:'button',class:b.preview?'':'on',onclick:()=>{b.preview=false;ctx.rerender(b)}},icon('fa-code'),' HTML'),h('button',{type:'button',class:b.preview?'on':'',onclick:()=>{b.preview=true;ctx.rerender(b)}},icon('fa-eye'),' Vorschau'));
      w.append(tabs);if(b.preview){draw();w.append(pv)}else w.append(t);
      w.append(h('div',{class:'epb-note'},ctx.canRaw?'Als Administrator wird dieser HTML-Code <b>unverändert</b> gespeichert (auch Skripte, iframes, Formulare). Nur Code einfügen, dem du vertraust.':'Dieser HTML-Code wird beim Speichern bereinigt: Skripte, Formulare und fremde iframes werden entfernt. Erlaubt sind u. a. Tabellen, Bilder, Video/Audio und Player bekannter Anbieter.'));
      return w},
    focus:el=>{const t=el.querySelector('textarea');t&&t.focus()},
    inspector:(b,ctx)=>[C.section('HTML',[C.toggle('Vorschau anzeigen',b.preview,v=>ctx.set(b,{preview:v})),C.note(ctx.canRaw?'<b>Administrator:</b> ungefiltert (Skripte erlaubt).':'Wird bereinigt (kein Skript).')])]});
  E.reg('shortcode',{label:'Shortcode',icon:'fa-terminal',cat:'advanced',kw:'shortcode plugin widget',defaults:()=>({html:''}),
    serialize:b=>String(b.html||'').replace(/</g,'&lt;').replace(/>/g,'&gt;'),
    parse:(inner,attrs)=>Object.assign(nb('shortcode'),attrs,{html:inner.replace(/&lt;/g,'<').replace(/&gt;/g,'>').trim()}),
    render:(b,ctx)=>{const i=h('input',{class:'fc epb-mono',placeholder:'[mein_shortcode option="…"]',value:b.html||''});i.addEventListener('input',()=>{b.html=i.value;ctx.changed(b)});return h('div',{class:'epb-shortcode'},icon('fa-terminal'),i)},
    focus:el=>{const t=el.querySelector('input');t&&t.focus()},
    inspector:()=>[C.section('Shortcode',[C.note('Shortcodes von Plugins und Themes, z. B. <code>[radio_player]</code>, <code>[lovable widget="…"]</code>. Sie werden beim Anzeigen der Seite ausgeführt.')])]});
  E.reg('unknown',{label:'Unbekannter Block',icon:'fa-triangle-exclamation',cat:'hidden',kw:'',defaults:()=>({html:''}),
    serialize:b=>b.html||'',parse:(i,a)=>Object.assign(nb('unknown'),{html:i}),
    render:(b,ctx)=>{const t=h('textarea',{class:'epb-code-area',rows:4,spellcheck:'false'});t.value=b.html||'';t.addEventListener('input',()=>{b.html=t.value;ctx.changed(b)});return h('div',{class:'epb-html'},h('div',{class:'epb-note'},'Dieser Block stammt aus einem anderen Editor und wird unverändert erhalten. Du kannst den HTML-Code hier bearbeiten.'),t)},
    inspector:()=>[]});
})(window.EPB);
