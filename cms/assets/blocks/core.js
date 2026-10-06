'use strict';
/* ElvadoPress Block-Editor – Kern: Hilfsfunktionen, Stil-Aufbau, Block-Register, Lesen/Schreiben der Block-Kommentare.
   Gespeichert wird normales HTML, jeder Block steht zwischen Kommentaren: <!-- ep:paragraph {"align":"center"} --><p …>…</p><!-- /ep:paragraph -->.
   Die Kommentare sind für Browser unsichtbar; Aussehen steckt in Inline-Stilen, damit jedes Theme den Inhalt richtig darstellt.
   Der Server (cms/lib/htmlsafe.php) lässt genau diese Kommentare und Stile durch. Dateien: blocks/core.js, blocks/types.js, blocks/editor.js, block-editor.css. */
window.EPB=window.EPB||{};
(function(E){
  const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  let n=0;const uid=()=>'b'+Date.now().toString(36)+(n++).toString(36)+Math.random().toString(36).slice(2,5);
  function h(tag,attrs,...kids){
    const e=document.createElement(tag);
    for(const k in(attrs||{})){const v=attrs[k];if(v==null||v===false)continue;
      if(k==='class')e.className=v;else if(k==='style')e.style.cssText=v;else if(k==='html')e.innerHTML=v;else if(k.startsWith('on')&&typeof v==='function')e.addEventListener(k.slice(2),v);
      else if(v===true)e.setAttribute(k,'');else e.setAttribute(k,v);}
    kids.flat().forEach(c=>{if(c==null||c===false)return;e.appendChild(typeof c==='string'||typeof c==='number'?document.createTextNode(String(c)):c)});
    return e;
  }
  const icon=name=>h('i',{class:'fas '+name});
  /* Nur unbedenkliche CSS-Werte (kommen aus Farbwählern/Zahlenfeldern, aber auch aus gespeicherten Attributen) */
  const css=v=>/^[#a-zA-Z0-9%.,()\s\-+\/]*$/.test(String(v))?String(v):'';
  const num=(v,min,max,def)=>{v=parseFloat(v);if(!isFinite(v))return def;return Math.max(min,Math.min(max,v))};
  const SIZES={s:'0.875rem',m:'1rem',l:'1.25rem',xl:'1.75rem',xxl:'2.5rem'};
  const PAD={none:'0',s:'12px',m:'24px',l:'40px'};
  const FAMILIES={'':'',serif:'Georgia,serif',mono:'ui-monospace,monospace'};

  /* Gemeinsame Stil-Angaben eines Blocks (Ausrichtung, Größe, Farben, Abstand, Rahmen) → style-Zeichenkette */
  function styleOf(b,extra){
    const s=[];
    if(b.align&&b.align!=='left'&&b.align!=='none')s.push('text-align:'+css(b.align));
    if(b.size&&SIZES[b.size])s.push('font-size:'+SIZES[b.size]);else if(b.sizePx)s.push('font-size:'+num(b.sizePx,8,120,16)+'px');
    if(b.weight)s.push('font-weight:'+css(b.weight));
    if(b.lineHeight)s.push('line-height:'+num(b.lineHeight,1,3,1.6));
    if(b.color)s.push('color:'+css(b.color));
    if(b.bg)s.push('background-color:'+css(b.bg));
    if(b.padding&&PAD[b.padding]!==undefined)s.push('padding:'+PAD[b.padding]);
    if(b.mt!==undefined&&b.mt!==''&&b.mt!==null)s.push('margin-top:'+num(b.mt,0,200,0)+'px');
    if(b.mb!==undefined&&b.mb!==''&&b.mb!==null)s.push('margin-bottom:'+num(b.mb,0,200,0)+'px');
    if(b.radius)s.push('border-radius:'+num(b.radius,0,60,0)+'px');
    if(b.borderW){s.push('border:'+num(b.borderW,0,12,0)+'px solid '+(css(b.borderC)||'currentColor'))}
    if(extra)s.push(extra);
    return s.filter(Boolean).join(';');
  }
  function attrsOf(b,extraStyle,extraClass){
    const st=styleOf(b,extraStyle),cl=[extraClass,b.className&&String(b.className).replace(/[^A-Za-z0-9_\- ]/g,'')].filter(Boolean).join(' ');
    return (b.anchor?' id="'+esc(String(b.anchor).replace(/[^A-Za-z0-9_\-]/g,''))+'"':'')+(cl?' class="'+esc(cl)+'"':'')+(st?' style="'+esc(st)+'"':'');
  }

  /* Block-Register: E.types[name] = {label,icon,cat,kw,defaults(),content:[Schlüssel mit HTML-Inhalt],serialize(b,ser),parse(inner,attrs,parseList),render(b,ctx),inspector(b,ctx)} */
  E.types={};E.cats=[['text','Text'],['media','Medien'],['design','Design'],['embed','Einbettungen'],['advanced','Erweitert']];
  E.reg=(name,def)=>{E.types[name]=Object.assign({name,content:[],container:false},def)};
  E.util={esc,h,icon,css,num,uid,styleOf,attrsOf,SIZES,PAD,FAMILIES};

  const dom=html=>{const d=document.createElement('div');d.innerHTML=html;return d};
  E.dom=dom;
  const block=(name,attrs)=>Object.assign({id:uid(),name},E.types[name]?E.types[name].defaults():{},attrs||{});
  E.newBlock=block;

  /* ───────── Schreiben ───────── */
  function jsonAttrs(b){
    const t=E.types[b.name],skip=new Set(['id','name','children'].concat(t?t.content:[]));const o={};
    for(const k in b){if(skip.has(k))continue;const v=b[k];if(v===''||v===null||v===undefined||v===false)continue;o[k]=v}
    const keys=Object.keys(o);if(!keys.length)return '';
    return ' '+JSON.stringify(o).replace(/</g,'\\u003c').replace(/>/g,'\\u003e').replace(/--/g,'\\u002d\\u002d');
  }
  function ser(b){
    if(b.name==='unknown')return b.html||'';
    const t=E.types[b.name];if(!t)return b.html||'';
    const inner=t.serialize(b,serList);
    return '<!-- ep:'+b.name+jsonAttrs(b)+' -->'+inner+'<!-- /ep:'+b.name+' -->';
  }
  const serList=list=>list.map(ser).join('\n');
  E.serialize=serList;

  /* ───────── Lesen ───────── */
  const TOK=/<!--\s*(\/)?(ep|wp):([a-z0-9][a-z0-9_\/\-]*)(?:\s+(\{[\s\S]*?\}))?(?=\s*-->)\s*-->/gi;
  function tokens(html){
    const out=[];let m;TOK.lastIndex=0;
    while((m=TOK.exec(html))){out.push({close:!!m[1],ns:m[2].toLowerCase(),name:m[3].toLowerCase(),json:m[4]||'',start:m.index,end:m.index+m[0].length})}
    return out;
  }
  function jsonOf(s){if(!s)return {};try{const o=JSON.parse(s);return o&&typeof o==='object'&&!Array.isArray(o)?o:{}}catch(e){return {}}}
  /* Alte/fremde Inhalte ohne Block-Kommentare ("klassisch") in Blöcke umwandeln */
  function classic(html){
    const out=[],root=dom(html);
    const push=(name,attrs)=>out.push(block(name,attrs));
    const alignOf=el=>{const a=(el.style&&el.style.textAlign)||'';return ['left','center','right','justify'].includes(a)?a:'left'};
    root.childNodes.forEach(nd=>{
      if(nd.nodeType===3){const t=nd.textContent.trim();if(t)push('paragraph',{html:esc(t)});return}
      if(nd.nodeType!==1)return;const tag=nd.tagName.toLowerCase();
      if(tag==='p'){if(nd.textContent.trim()===''&&!nd.querySelector('img'))return;push('paragraph',{html:nd.innerHTML,align:alignOf(nd)})}
      else if(/^h[1-6]$/.test(tag))push('heading',{html:nd.innerHTML,level:+tag[1],align:alignOf(nd)});
      else if(tag==='ul'||tag==='ol')push('list',{html:nd.innerHTML,ordered:tag==='ol'});
      else if(tag==='blockquote'){const p=nd.querySelector('p'),c=nd.querySelector('cite');const cite=c?c.innerHTML:'';if(c)c.remove();push('quote',{html:p?p.innerHTML:nd.innerHTML,cite})}
      else if(tag==='hr')push('divider',{});
      else if(tag==='pre')push('code',{html:esc(nd.textContent)});
      else if(tag==='img')push('image',{url:nd.getAttribute('src')||'',alt:nd.getAttribute('alt')||''});
      else if(tag==='figure'&&nd.querySelector('img')){const i=nd.querySelector('img'),c=nd.querySelector('figcaption');push('image',{url:i.getAttribute('src')||'',alt:i.getAttribute('alt')||'',html:c?c.innerHTML:''})}
      else if(tag==='table')out.push(E.types.table.parse(nd.outerHTML,{}));
      else if(tag==='iframe'||tag==='video'||tag==='audio'||tag==='div'||tag==='section'||tag==='details'||tag==='figure'||tag==='aside'||tag==='article'||tag==='header'||tag==='footer'||tag==='main'||tag==='nav')push('html',{html:nd.outerHTML});
      else push('html',{html:nd.outerHTML});
    });
    return out;
  }
  const WP={paragraph:'paragraph',heading:'heading',list:'list',quote:'quote',image:'image',separator:'divider',spacer:'spacer',code:'code',preformatted:'code',html:'html',table:'table'};
  function convertWp(name,attrs,inner){
    const d=dom(inner);
    switch(name){
      case 'paragraph':{const p=d.querySelector('p');return block('paragraph',{html:p?p.innerHTML:inner,align:attrs.align||'left'})}
      case 'heading':{const e=d.querySelector('h1,h2,h3,h4,h5,h6');return block('heading',{html:e?e.innerHTML:inner,level:e?+e.tagName[1]:(attrs.level||2),align:attrs.textAlign||'left'})}
      case 'list':{const e=d.querySelector('ul,ol');return block('list',{html:e?e.innerHTML:inner,ordered:e?e.tagName==='OL':!!attrs.ordered})}
      case 'quote':{const p=d.querySelector('p'),c=d.querySelector('cite');return block('quote',{html:p?p.innerHTML:inner,cite:c?c.innerHTML:''})}
      case 'image':{const i=d.querySelector('img'),c=d.querySelector('figcaption');return block('image',{url:i?i.getAttribute('src'):'',alt:i?i.getAttribute('alt')||'':'',html:c?c.innerHTML:''})}
      case 'separator':return block('divider',{});
      case 'spacer':return block('spacer',{height:attrs.height?parseInt(attrs.height,10)||40:40});
      case 'code':case 'preformatted':return block('code',{html:esc(d.textContent)});
      case 'html':return block('html',{html:inner.trim()});
      case 'table':return E.types.table.parse(inner,{});
    }
    return null;
  }
  /* Wurzel: Liste von Blöcken aus HTML (rekursiv für Container) */
  const classicList=txt=>{txt=String(txt||'');return txt.replace(/<!--[\s\S]*?-->/g,'').trim()===''?[]:classic(txt)};
  E.parse=function(html){
    html=String(html||'').trim();if(html==='')return [];
    const toks=tokens(html);if(!toks.length)return classicList(html);
    const out=[];let p=0;
    (function run(from,to,list,startPos,endPos,inContainer){
      let pos=startPos;
      for(let k=from;k<to;){
        const t=toks[k];if(t.close){k++;continue}
        let depth=1,c=k+1;
        for(;c<to;c++){const u=toks[c];if(u.name!==t.name||u.ns!==t.ns)continue;depth+=u.close?-1:1;if(depth===0)break}
        if(!inContainer)list.push(...classicList(html.slice(pos,t.start)));   // lose Inhalte zwischen Blöcken → klassische Blöcke (in Containern ist es nur die Hülle)
        if(c>=to){list.push(block('unknown',{html:html.slice(t.start,endPos)}));pos=endPos;k=to;break}
        const cl=toks[c],attrs=jsonOf(t.json);let b=null;
        if(t.ns==='ep'&&E.types[t.name]){
          const T=E.types[t.name];
          if(T.container){const kids=[];run(k+1,c,kids,t.end,cl.start,true);b=Object.assign(block(t.name,attrs),T.afterParse?T.afterParse(attrs,kids):{children:kids})}
          else b=T.parse(html.slice(t.end,cl.start),attrs);
        }else if(t.ns==='wp'&&WP[t.name])b=convertWp(t.name,attrs,html.slice(t.end,cl.start));
        if(!b)b=block('unknown',{html:html.slice(t.start,cl.end)});
        list.push(b);pos=cl.end;k=c+1;
      }
      if(!inContainer)list.push(...classicList(html.slice(pos,endPos)));
    })(0,toks.length,out,0,html.length,false);
    return out;
  };
  E.serializeBlock=ser;
})(window.EPB);
