/* Vorschau-Brücke (Preview Bridge): läuft nur in der geprüften Vorschau (signierter Schlüssel) innerhalb des Live Builders / Customizers.
   Macht Komponenten anklickbar (Hervorheben, Auswählen, Werkzeugleiste: nach oben/unten, Kopie, ausblenden, löschen) und meldet Auswahl und Scroll-Position an den Builder.
   Sicherheit: Nachrichten gelten nur vom Eltern-Fenster (event.source) UND derselben Herkunft (event.origin); Befehle zusätzlich nur mit dem Sitzungsschlüssel, den der Builder beim Handshake („init“) vergibt.
   Der Builder schickt Regionen als Selektoren (z. B. Header/Footer des Themes); sie stammen nur aus dem vertrauenswürdigen Eltern-Fenster. */
(function(){
  'use strict';
  if(window.top===window||window.__epBridge)return;window.__epBridge=1;
  var ORIGIN=location.origin,PARENT=window.parent,token='',edit=true,hlOn=false,selected='',regions=[],labels={},hoverEl=null,selEl=null,timer=null,lastY=0,scrollT=0;
  function post(m){m.ep=1;m.token=token;try{PARENT.postMessage(m,ORIGIN)}catch(e){}}

  /* Überlagerung in einem geschlossenen Shadow-DOM: kollidiert nicht mit den Styles des Themes */
  var host=document.createElement('div');host.setAttribute('aria-hidden','true');host.style.cssText='all:initial;position:fixed;left:0;top:0;width:0;height:0;z-index:2147483646';
  var root=host.attachShadow({mode:'closed'});
  root.innerHTML='<style>.b{position:fixed;pointer-events:none;box-sizing:border-box;display:none;border-radius:2px}.h{border:2px dashed rgba(47,156,255,.85);background:rgba(47,156,255,.07)}.s{border:2px solid #2f9cff;box-shadow:0 0 0 9999px rgba(0,0,0,0)}'
    +'.t{position:fixed;display:none;pointer-events:auto;font:600 12px/1 system-ui,sans-serif;background:#1a6fe0;color:#fff;border-radius:6px 6px 0 0;padding:0;white-space:nowrap;box-shadow:0 4px 14px rgba(0,0,0,.35)}'
    +'.t span{display:inline-block;padding:7px 10px}.t button{all:unset;cursor:pointer;padding:7px 9px;font:700 12px/1 system-ui,sans-serif}.t button:hover,.t button:focus-visible{background:rgba(255,255,255,.22)}.t button.d:hover{background:#d13b4b}.e{position:fixed;display:none;pointer-events:auto;background:#0b1020;border:2px solid #2f9cff;border-radius:8px;box-shadow:0 12px 36px rgba(0,0,0,.5);padding:6px}.e textarea{display:block;box-sizing:border-box;width:100%;min-height:64px;resize:vertical;background:#101830;color:#fff;border:1px solid #2a3a66;border-radius:6px;padding:8px;font:14px/1.4 system-ui,sans-serif}.e small{display:block;color:#9fb4e6;font:11px system-ui,sans-serif;margin-top:4px}.e .r{display:flex;gap:6px;justify-content:flex-end;margin-top:6px}.e button{all:unset;cursor:pointer;padding:5px 10px;border-radius:6px;font:600 12px system-ui,sans-serif;background:#1a6fe0;color:#fff}.e button.c{background:#2a3350}.ln{position:fixed;height:4px;background:#34d399;border-radius:2px;display:none;pointer-events:none;box-shadow:0 0 8px #34d399}.t button.g{cursor:grab;letter-spacing:-2px}</style>'
    +'<div class="b h" id="h"></div><div class="b s" id="s"></div><div class="t" id="t" role="toolbar" aria-label="Komponente bearbeiten"><span id="l"></span><button class="g" data-op="drag" title="Ziehen zum Verschieben" aria-label="Verschieben">⠿</button><button data-op="up" title="Nach oben" aria-label="Nach oben">↑</button><button data-op="down" title="Nach unten" aria-label="Nach unten">↓</button><button data-op="dup" title="Duplizieren" aria-label="Duplizieren">⧉</button><button data-op="hide" title="Ausblenden" aria-label="Ausblenden">Aus</button><button class="d" data-op="del" title="Löschen" aria-label="Löschen">✕</button></div><div class="e" id="e" role="dialog" aria-label="Text ändern"><textarea id="et" maxlength="4000"></textarea><small>Enter = übernehmen · Umschalt+Enter = neue Zeile · Esc = abbrechen</small><div class="r"><button class="c" id="ec">Abbrechen</button><button id="eo">Übernehmen</button></div></div><div class="ln" id="ln"></div>';
  var H=root.getElementById('h'),S=root.getElementById('s'),T=root.getElementById('t'),L=root.getElementById('l'),E=root.getElementById('e'),ET=root.getElementById('et'),LN=root.getElementById('ln');
  function mount(){if(!host.parentNode)(document.body||document.documentElement).appendChild(host)}

  function info(el){
    if(!el)return null;
    var c=el.closest&&el.closest('[data-ep-id],[data-bk]');
    if(c){var id=c.getAttribute('data-ep-id')||c.getAttribute('data-bk'),ty=c.getAttribute('data-ep-type');
      if(!ty){(c.className||'').split(/\s+/).forEach(function(k){var m=/^bk-([a-z_]+)$/.exec(k);if(m&&m[1]!=='section'&&m[1]!=='wrap'&&!ty)ty=m[1]})}
      return {el:c,id:id,kind:'section',label:labels[ty]||ty||'Bereich'}}
    for(var i=0;i<regions.length;i++){var r=regions[i];try{var m=el.closest(r.selector);if(m)return {el:m,id:r.id,kind:'region',label:r.label||r.id}}catch(e){}}
    return detectedFor(el);
  }
  function find(id){
    var els=document.querySelectorAll('[data-ep-id],[data-bk]');for(var i=0;i<els.length;i++){if((els[i].getAttribute('data-ep-id')||els[i].getAttribute('data-bk'))===id)return els[i]}
    for(var j=0;j<regions.length;j++)if(regions[j].id===id){try{return document.querySelector(regions[j].selector)}catch(e){}}
    if(SEL_RE.test(id)){try{return document.querySelector(id)}catch(e){}}
    return null;
  }
  function box(b,el){
    if(!el||(!edit&&!hlOn)){b.style.display='none';return}
    var r=el.getBoundingClientRect();if(r.width<2||r.height<2){b.style.display='none';return}
    b.style.display='block';b.style.left=r.left+'px';b.style.top=r.top+'px';b.style.width=r.width+'px';b.style.height=r.height+'px';
  }
  function draw(){
    box(H,hoverEl&&hoverEl!==selEl?hoverEl:null);box(S,selEl);
    if(selEl&&edit){   /* Werkzeugleiste nur im Bearbeiten-Modus */
      var r=selEl.getBoundingClientRect(),i=info(selEl);T.style.display='block';
      L.textContent=i?i.label:'';var isSec=i&&i.kind==='section',canMove=i&&i.kind==='detected';[].forEach.call(T.querySelectorAll('button'),function(b){b.style.display=b.getAttribute('data-op')==='drag'?(canMove?'':'none'):(isSec?'':'none')});
      var tw=T.offsetWidth,x=Math.min(Math.max(4,r.left),Math.max(4,window.innerWidth-tw-4)),y=r.top-T.offsetHeight;if(y<0)y=Math.min(r.top+4,window.innerHeight-T.offsetHeight-4);
      T.style.left=x+'px';T.style.top=y+'px';
    }else T.style.display='none';
  }
  function choose(el,silent){selEl=el;var i=info(el);selected=i?i.id:'';draw();if(el&&!silent)post({type:'select',id:i.id,kind:i.kind});}
  function setSelected(id,scroll){
    selected=id;selEl=id?find(id):null;draw();
    if(selEl&&scroll){var r=selEl.getBoundingClientRect();if(r.top<0||r.bottom>window.innerHeight)selEl.scrollIntoView({behavior:'smooth',block:'center'})}
  }
  function ready(){mount();setSelected(selected,false);if(!timer)timer=setInterval(function(){if(selEl)draw()},400)}


  /* ───────── Seitenstruktur erkennen (Scanner) ─────────
     Liest die ausgelieferte Seite und liefert eine flache Liste erkannter Bereiche (Gliederungs-Elemente, Elemente mit Kennung, eindeutige Klassen)
     mit Einrückung (d) und Eltern (p). Nur Bereiche mit stabilem, eindeutigem Selektor (#kennung, .klasse oder Gliederungs-Element) – diese lassen sich gestalten. */
  var SEL_RE=/^([#.][a-z][a-z0-9_-]{0,60}|header|footer|nav|main|aside|section|article)$/i;
  var LAND={HEADER:1,FOOTER:1,NAV:1,MAIN:1,ASIDE:1,SECTION:1,ARTICLE:1};
  var SKIP_TAG={SCRIPT:1,STYLE:1,LINK:1,META:1,NOSCRIPT:1,TEMPLATE:1,SVG:1,PATH:1,IMG:1,IFRAME:1,BR:1,HR:1,INPUT:1,BUTTON:1,A:1,SPAN:1,I:1,B:1,P:1,H1:1,H2:1,H3:1,H4:1,LI:1,UL:1,OL:1,TABLE:1,TR:1,TD:1,TH:1};
  var SKIP_CLS=/^(col|row|container|d-|p[xytblr]?-|m[xytblr]?-|text-|bg-|flex|w-|h-|justify|align|g-|gap|border|rounded|shadow|fa|fas|far|fab|active|show|hidden|hide|is-|has-|js-|ep-|wp-|on$|off$)/i;
  var SECT_CLS=/(^|[-_])(section|hero|banner|wrapper|card|grid|widget|sidebar|footer|header|nav|menu|player|panel|block|teaser|slider|gallery|list|bar|area|box|top|bottom|main|content|wrap)([-_]|$)/i;
  function uniq(sel){try{return document.querySelectorAll(sel).length===1}catch(e){return false}}
  function clsList(el){var c=el.className;return (typeof c==='string'?c:'').split(/\s+/).filter(function(x){return /^[a-z][a-z0-9_-]{0,60}$/i.test(x)})}
  function selFor(el){
    var id=el.id;if(id&&/^[a-z][a-z0-9_-]{0,60}$/i.test(id)&&!/^(elvado-|ep-|cms-|wp-)/i.test(id)&&!/[a-f0-9]{10,}/i.test(id)){var s='#'+id;if(uniq(s))return s}
    var cl=clsList(el).filter(function(c){return !SKIP_CLS.test(c)});
    for(var i=0;i<cl.length;i++){var k='.'+cl[i];if(uniq(k))return k}
    var t=el.tagName.toLowerCase();if(LAND[el.tagName]&&uniq(t))return t;
    return '';
  }
  function labelFor(el,sel){
    var a=el.getAttribute('aria-label');if(a)return a.trim().slice(0,40);
    var tag=el.tagName;
    if(tag==='SECTION'||tag==='ARTICLE'||tag==='ASIDE'){var h=el.querySelector('h1,h2,h3,h4');var ht=h?(h.textContent||'').replace(/\s+/g,' ').trim():'';if(ht)return ht.slice(0,40)}
    var base=sel.replace(/^[#.]/,'').replace(/[-_]+/g,' ').trim();return base?base.charAt(0).toUpperCase()+base.slice(1):tag.toLowerCase();
  }
  function isCand(el){
    if(el.nodeType!==1||SKIP_TAG[el.tagName]||host.contains(el)||el===host)return false;
    var cls=clsList(el),sect=cls.some(function(c){return !SKIP_CLS.test(c)&&SECT_CLS.test(c)});
    if(!LAND[el.tagName]&&!el.id&&!sect)return false;
    var r=el.getBoundingClientRect();if(r.width<120||r.height<40)return false;
    var cs=getComputedStyle(el);return cs.display!=='none'&&cs.visibility!=='hidden';
  }
  function scan(){
    var out=[],map=new Map(),all=(document.body||document.documentElement).querySelectorAll('*'),n=Math.min(all.length,3000),sy=window.pageYOffset;
    for(var i=0;i<n&&out.length<90;i++){
      var el=all[i];if(!isCand(el))continue;var sel=selFor(el);if(!sel||!SEL_RE.test(sel))continue;
      var par=el.parentElement,pn=null;while(par&&!pn){pn=map.get(par)||null;par=par.parentElement}
      var r=el.getBoundingClientRect();
      if(pn&&!el.id&&!LAND[el.tagName]&&Math.abs(pn.h-r.height)<8&&Math.abs(pn.w-r.width)<8)continue;   /* Hülle ohne eigenen Inhalt */
      var d=pn?pn.d+1:0;if(d>4)continue;
      var node={s:sel,t:el.tagName.toLowerCase(),l:labelFor(el,sel),d:d,p:pn?pn.s:'',y:Math.round(r.top+sy),h:Math.round(r.height),w:Math.round(r.width)};
      map.set(el,node);out.push(node);
    }
    return out;
  }
  function detectedFor(el){
    for(var k=0,e=el;e&&e!==document.documentElement&&k<10;k++,e=e.parentElement){if(isCand(e)){var s=selFor(e);if(s&&SEL_RE.test(s))return {el:e,id:s,kind:'detected',label:labelFor(e,s)}}}
    return null;
  }
  var scanT=null,lastJson='';
  function sendStructure(force){
    if(!token)return;var nodes;try{nodes=scan()}catch(e){return}
    var j=JSON.stringify(nodes.map(function(x){return [x.s,x.d,x.h]}));if(!force&&j===lastJson)return;lastJson=j;post({type:'structure',nodes:nodes});
  }
  function scheduleScan(){clearTimeout(scanT);scanT=setTimeout(function(){sendStructure(false)},900)}
  try{new MutationObserver(function(){if(token)scheduleScan()}).observe(document.documentElement,{childList:true,subtree:true,attributes:true,attributeFilter:['class','style','hidden']})}catch(e){}
  window.addEventListener('load',function(){if(token)scheduleScan()});


  /* ───────── Inhalte direkt ändern: Text per Doppelklick, Reihenfolge per Ziehen (themeunabhängig) ─────────
     Änderungen werden sofort in der Vorschau angezeigt und dem Builder gemeldet; er speichert sie als Elemente der Seite (Entwurf, Veröffentlichen, Verlauf). */
  var BADTAG={SCRIPT:1,STYLE:1,TEXTAREA:1,INPUT:1,SELECT:1,IFRAME:1,OBJECT:1,NOSCRIPT:1,TEMPLATE:1,SVG:1};
  var PATH_RE=/^(#[a-z][a-z0-9_-]{0,60}|body)((?: > (?:[a-z][a-z0-9]{0,9}(?::nth-of-type\([0-9]{1,3}\))?|\.[a-z][a-z0-9_-]{0,40})){0,8})$/i;
  function nthOfType(el){var n=1,p=el;while((p=p.previousElementSibling))if(p.tagName===el.tagName)n++;return n}
  /* Pfad zu einem Element: ab dem nächsten Vorfahren mit eindeutiger Kennung (sonst body), Schritt für Schritt „tag:nth-of-type(n)“; prüft, dass er das Element wirklich trifft */
  function pathFor(el){
    var segs=[],cur=el,top='body';
    while(cur&&cur!==document.body&&cur!==document.documentElement){
      if(cur.id&&/^[a-z][a-z0-9_-]{0,60}$/i.test(cur.id)&&!/^(elvado-|ep-|cms-|wp-)/i.test(cur.id)&&!/[a-f0-9]{10,}/i.test(cur.id)&&uniq('#'+cur.id)){top='#'+cur.id;break}
      segs.unshift(cur.tagName.toLowerCase()+':nth-of-type('+nthOfType(cur)+')');cur=cur.parentElement;
    }
    if(segs.length>8)return '';
    var p=top+(segs.length?' > '+segs.join(' > '):'');
    if(!PATH_RE.test(p)||p.length>400)return '';
    try{return document.querySelector(p)===el?p:''}catch(e){return ''}
  }
  function textNodeAt(x,y){
    var n=null;
    try{if(document.caretPositionFromPoint){var p=document.caretPositionFromPoint(x,y);if(p&&p.offsetNode&&p.offsetNode.nodeType===3)n=p.offsetNode}
    else if(document.caretRangeFromPoint){var r=document.caretRangeFromPoint(x,y);if(r&&r.startContainer&&r.startContainer.nodeType===3)n=r.startContainer}}catch(e){}
    if(!n||!n.parentElement||BADTAG[n.parentElement.tagName]||host.contains(n.parentElement))return null;
    return (n.nodeValue||'').trim()?n:null;
  }
  var editNode=null,editOrig='';
  function closeEdit(){editNode=null;E.style.display='none'}
  function openEdit(n,x,y){
    var par=n.parentElement,k=[].indexOf.call(par.childNodes,n),sel=pathFor(par);
    if(!sel||k<0){post({type:'notice',msg:'Dieser Text liegt zu tief verschachtelt und lässt sich nicht sicher ansprechen.'});return}
    editNode=n;editOrig=n.nodeValue;ET.value=n.nodeValue;
    var rg=document.createRange();rg.selectNodeContents(n);var r=rg.getBoundingClientRect();
    var w=Math.min(Math.max(300,r.width+24),Math.min(560,window.innerWidth-16));
    E.style.width=w+'px';E.style.display='block';E.style.left=Math.max(8,Math.min(window.innerWidth-w-8,r.left-6))+'px';E.style.top=Math.max(8,Math.min(window.innerHeight-170,r.bottom+8))+'px';
    E.dataset.sel=sel;E.dataset.k=String(k);ET.focus();ET.select();
  }
  function commitEdit(){
    if(!editNode)return;var t=ET.value,sel=E.dataset.sel,k=+E.dataset.k,n=editNode,orig=editOrig;closeEdit();
    if(t===orig)return;
    n.nodeValue=t;post({type:'text-edit',s:sel,k:k,t:t,o:orig});draw();
  }
  ET.addEventListener('keydown',function(e){e.stopPropagation();if(e.key==='Escape'){e.preventDefault();closeEdit()}else if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();commitEdit()}});
  root.getElementById('eo').addEventListener('click',commitEdit);root.getElementById('ec').addEventListener('click',closeEdit);
  document.addEventListener('dblclick',function(e){
    if(!token||!edit||(e.target&&host.contains(e.target)))return;
    var n=textNodeAt(e.clientX,e.clientY);if(!n)return;e.preventDefault();e.stopPropagation();openEdit(n,e.clientX,e.clientY);
  },true);

  /* Ziehen: ausgewähltes Element (Bereich) innerhalb seines Containers verschieben. Gemeldet werden nur Geschwister mit stabilem Selektor (Kennung oder eindeutige Klasse),
     denn Positionspfade ändern sich beim Verschieben. */
  var drag=null;
  function stableSel(el){var s=selFor(el);return s&&SEL_RE.test(s)&&s!=='main'?s:''}
  function siblingTarget(x,y,el){
    var hit=document.elementFromPoint(x,y);if(!hit||host.contains(hit))return null;
    while(hit&&hit.parentElement!==el.parentElement)hit=hit.parentElement;
    return hit&&hit!==el?hit:null;
  }
  function dragStart(e){
    var si=selEl?info(selEl):null;if(!selEl||!edit||!si||si.kind!=='detected')return;var par=selEl.parentElement;if(!par)return;
    var sibs=[].filter.call(par.children,function(c){return c!==host&&!BADTAG[c.tagName]});
    if(sibs.length<2)return;
    drag={el:selEl,par:par,tgt:null,after:false};e.preventDefault();
    window.addEventListener('pointermove',dragMove,true);window.addEventListener('pointerup',dragEnd,true);
  }
  function dragMove(e){
    if(!drag)return;var t=siblingTarget(e.clientX,e.clientY,drag.el);drag.tgt=t;
    if(!t){LN.style.display='none';return}
    var r=t.getBoundingClientRect();drag.after=e.clientY>r.top+r.height/2;
    LN.style.display='block';LN.style.left=r.left+'px';LN.style.width=r.width+'px';LN.style.top=(drag.after?r.bottom-2:r.top-2)+'px';
  }
  function dragEnd(){
    window.removeEventListener('pointermove',dragMove,true);window.removeEventListener('pointerup',dragEnd,true);LN.style.display='none';
    var d=drag;drag=null;if(!d||!d.tgt)return;
    d.par.insertBefore(d.el,d.after?d.tgt.nextSibling:d.tgt);
    var items=[].map.call(d.par.children,stableSel).filter(Boolean),c=pathFor(d.par);
    if(!c||items.length<2){post({type:'notice',msg:'Die neue Reihenfolge lässt sich hier nicht sicher speichern (Bereiche ohne eindeutige Kennung).'});return}
    if(!stableSel(d.el)){post({type:'notice',msg:'Dieser Bereich hat keine eindeutige Kennung und kann nicht verschoben werden.'});return}
    post({type:'reorder',c:c,items:items});draw();
  }
  T.addEventListener('pointerdown',function(e){var b=e.target.closest&&e.target.closest('button[data-op="drag"]');if(b)dragStart(e)});

  /* Anklicken und Hervorheben (nur im Bearbeiten-Modus; Links/Formulare werden dabei nicht ausgeführt) */
  document.addEventListener('mouseover',function(e){if(!token||!edit)return;var i=info(e.target);var n=i?i.el:null;if(n!==hoverEl){hoverEl=n;draw()}},true);
  document.addEventListener('mouseleave',function(){hoverEl=null;draw()},true);
  document.addEventListener('click',function(e){
    if(!token||!edit)return;
    if(e.target&&e.target.closest&&host.contains(e.target))return;
    var i=info(e.target);
    if(i){e.preventDefault();e.stopPropagation();choose(i.el)}
    else{var a=e.target.closest&&e.target.closest('a[href],form,button');if(a){e.preventDefault();e.stopPropagation()}else choose(null,true)}
  },true);
  document.addEventListener('submit',function(e){if(token&&edit)e.preventDefault()},true);
  document.addEventListener('keydown',function(e){if(token&&e.key==='Escape'&&selEl){choose(null,true);post({type:'select',id:'',kind:'section'})}});
  T.addEventListener('click',function(e){var b=e.target.closest&&e.target.closest('button[data-op]');if(b&&b.getAttribute('data-op')!=='drag'&&selected)post({type:'action',op:b.getAttribute('data-op'),id:selected})});
  window.addEventListener('scroll',function(){draw();var now=Date.now();if(now-scrollT>200){scrollT=now;lastY=window.pageYOffset;post({type:'scroll',y:lastY})}},{passive:true});
  window.addEventListener('resize',draw);
  window.addEventListener('beforeunload',function(){post({type:'scroll',y:window.pageYOffset})});

  /* Handshake und Befehle */
  window.addEventListener('message',function(e){
    if(e.source!==PARENT||e.origin!==ORIGIN)return;var d=e.data;if(!d||d.ep!==1||typeof d.type!=='string')return;
    if(d.type==='init'){
      if(typeof d.token!=='string'||d.token.length<16||(token&&token!==d.token))return;
      token=d.token;edit=d.edit!==false;regions=(Array.isArray(d.regions)?d.regions:[]).slice(0,20).filter(function(r){return r&&typeof r.id==='string'&&typeof r.selector==='string'&&r.selector.length<200});
      labels=d.labels&&typeof d.labels==='object'?d.labels:{};selected=String(d.selected||'');ready();if(d.scroll>0)window.scrollTo(0,+d.scroll);scheduleScan();return;
    }
    if(!token||d.token!==token)return;
    if(d.type==='select'){hlOn=true;setSelected(String(d.id||''),!!d.scroll)}
    else if(d.type==='mode'){edit=d.edit!==false;if(!edit){hoverEl=null}draw()}
    else if(d.type==='scan')sendStructure(true);
    else if(d.type==='hl'){var q=String(d.sel||'');var he=null;if(q&&SEL_RE.test(q)){try{he=document.querySelector(q)}catch(x){}}hlOn=!!he||hlOn;hoverEl=he;draw()}
  });
  function hello(){post({type:'hello',url:location.href,title:document.title})}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',hello);else hello();
})();
