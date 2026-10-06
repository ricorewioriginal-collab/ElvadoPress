'use strict';
/* ElvadoPress Block-Editor – Oberfläche: Arbeitsfläche, Block-Werkzeugleiste, Inserter ("/"), Einstellungen (Inspector), Strukturansicht,
   Code-Editor, Rückgängig/Wiederholen, Drag & Drop. Benutzung: EPB.create(host,{html,onChange,pickImage,upload,canRaw}) → {getHTML,setHTML,…}. */
(function(E){
  const {h,icon,esc}=E.util,C=E.ctl;
  const INLINE_OK=new Set(['B','STRONG','I','EM','U','S','STRIKE','A','CODE','SUP','SUB','MARK','BR','SPAN']);
  const WRAP_NEW=()=>E.newBlock('paragraph');

  E.create=function(host,opts){
    opts=opts||{};
    const st={blocks:E.parse(opts.html||''),sel:null,mode:'visual',tab:'block',sideOpen:opts.side!==false,hist:[],hpos:-1,restoring:false,drag:null,slash:null,lastRich:null,listeners:[]};
    if(!st.blocks.length)st.blocks=[WRAP_NEW()];
    const root=h('div',{class:'epb'+(opts.compact?' epb-compact':'')});
    const top=h('div',{class:'epb-top'}),main=h('div',{class:'epb-main'}),canvas=h('div',{class:'epb-canvas'}),listEl=h('div',{class:'epb-list'}),side=h('div',{class:'epb-side'}),code=h('textarea',{class:'epb-codeview',spellcheck:'false'});
    const stats=h('span',{class:'epb-stats'});
    canvas.append(listEl);main.append(canvas,side);root.append(top,main,code);host.innerHTML='';host.append(root);
    code.style.display='none';
    let pop=null;
    const closePop=()=>{if(pop){pop.remove();pop=null;st.slash=null}};
    document.addEventListener('mousedown',e=>{if(pop&&!pop.contains(e.target)&&!e.target.closest('[data-pop-owner]'))closePop()},true);

    /* ───── Hilfen ───── */
    function find(id,list,parent){list=list||st.blocks;for(let i=0;i<list.length;i++){const b=list[i];if(b.id===id)return {b,list,i,parent:parent||null};if(b.children){const r=find(id,b.children,b);if(r)return r}}return null}
    function each(list,fn,depth){(list||st.blocks).forEach(b=>{fn(b,depth||0);if(b.children)each(b.children,fn,(depth||0)+1)})}
    const blockEl=id=>listEl.querySelector('.epb-block[data-id="'+id+'"]');
    const html=()=>E.serialize(st.blocks);
    function emit(){if(opts.onChange)opts.onChange(api)}
    let ht=null;
    function pushHist(){
      if(st.restoring)return;const s=html();if(st.hist[st.hpos]===s)return;
      st.hist=st.hist.slice(0,st.hpos+1);st.hist.push(s);if(st.hist.length>120)st.hist.shift();st.hpos=st.hist.length-1;updTop();
    }
    function changed(){clearTimeout(ht);ht=setTimeout(()=>{pushHist();updStats()},500);emit()}
    function structural(){clearTimeout(ht);pushHist();updStats();emit()}
    function restore(i){
      if(i<0||i>=st.hist.length)return;st.hpos=i;st.restoring=true;st.blocks=E.parse(st.hist[i]);if(!st.blocks.length)st.blocks=[WRAP_NEW()];st.sel=null;renderAll();st.restoring=false;updTop();emit();
    }
    const undo=()=>{clearTimeout(ht);pushHist();restore(st.hpos-1)},redo=()=>restore(st.hpos+1);

    /* ───── Rendering ───── */
    function renderAll(){
      listEl.innerHTML='';st.blocks.forEach((b,i)=>listEl.append(blockNode(b)));
      listEl.append(appender(st.blocks,st.blocks.length));
      if(st.sel&&!find(st.sel))st.sel=null;
      if(st.sel){const el=blockEl(st.sel);el&&el.classList.add('is-selected');decorate(st.sel)}
      renderSide();updTop();updStats();
    }
    function appender(list,index){
      return h('div',{class:'epb-appender'},h('button',{type:'button','data-pop-owner':'1',title:'Block hinzufügen',onclick:e=>{e.preventDefault();openInserter(e.currentTarget,list,index)}},icon('fa-plus')),
        h('span',{class:'epb-appender-hint'},'Block hinzufügen'));
    }
    function blockNode(b){
      const T=E.types[b.name]||E.types.unknown;
      const wrap=h('div',{class:'epb-block epb-t-'+b.name,'data-id':b.id,'data-type':b.name});
      const body=h('div',{class:'epb-body'});
      try{body.append(T.render(b,ctx))}catch(err){body.append(h('div',{class:'epb-note'},'Block konnte nicht angezeigt werden: '+err.message))}
      wrap.append(h('div',{class:'epb-bar'}),body);
      wrap.addEventListener('mousedown',e=>{if(e.target.closest('.epb-bar'))return;selectBlock(b.id,true)});
      wrap.addEventListener('focusin',()=>selectBlock(b.id,true));
      wrap.addEventListener('dragover',e=>dragOver(e,b,wrap));wrap.addEventListener('dragleave',()=>wrap.classList.remove('drop-before','drop-after'));wrap.addEventListener('drop',e=>dropOn(e,b,wrap));
      return wrap;
    }
    function rerender(b){
      const el=blockEl(b.id);if(!el)return renderAll();
      const n=blockNode(b);el.replaceWith(n);if(st.sel===b.id){n.classList.add('is-selected');decorate(b.id)}
      const f=n.querySelector('[data-focus]');
    }
    function selectBlock(id,fromFocus){
      if(st.sel===id){if(!fromFocus)decorate(id);return}
      if(st.sel){const o=blockEl(st.sel);if(o){o.classList.remove('is-selected');const bar=o.querySelector(':scope > .epb-bar');if(bar)bar.innerHTML=''}}
      st.sel=id;const el=blockEl(id);if(el)el.classList.add('is-selected');decorate(id);renderSide();updTop();
    }
    /* Block-Werkzeugleiste (schwebt über dem gewählten Block) */
    function decorate(id){
      const r=find(id);if(!r)return;const el=blockEl(id);if(!el)return;const bar=el.querySelector(':scope > .epb-bar');if(!bar)return;bar.innerHTML='';
      const T=E.types[r.b.name]||E.types.unknown;
      const btn=(ic,title,fn,active,text)=>h('button',{type:'button',class:'epb-tb'+(active?' on':''),title,onmousedown:e=>e.preventDefault(),onclick:e=>{e.preventDefault();e.stopPropagation();fn(e)}},text?text:icon(ic));
      const grp=(...k)=>h('span',{class:'epb-grp'},k.filter(Boolean));
      const handle=h('span',{class:'epb-handle',draggable:'true',title:'Ziehen zum Verschieben',ondragstart:e=>{st.drag=id;e.dataTransfer.effectAllowed='move';e.dataTransfer.setData('text/plain',id);el.classList.add('dragging')},ondragend:()=>{st.drag=null;listEl.querySelectorAll('.dragging,.drop-before,.drop-after').forEach(x=>x.classList.remove('dragging','drop-before','drop-after'))}},icon('fa-grip-vertical'));
      const typeBtn=btn(T.icon,T.label+' – Block umwandeln',e=>openTransform(e.currentTarget,r.b));typeBtn.setAttribute('data-pop-owner','1');
      const own=T.toolbar?T.toolbar(r.b,ctx).map(t=>btn(t.icon,t.title,t.run,t.active,t.text)):[];
      const rich=['paragraph','heading','list','quote','details'].includes(r.b.name);
      const fmtBtns=rich?[btn('fa-bold','Fett (Strg+B)',()=>fmt('bold')),btn('fa-italic','Kursiv (Strg+I)',()=>fmt('italic')),btn('fa-underline','Unterstrichen (Strg+U)',()=>fmt('underline')),btn('fa-strikethrough','Durchgestrichen',()=>fmt('strikeThrough',null,true)),
        btn('fa-link','Link einfügen',e=>linkPop(e.currentTarget)),btn('fa-code','Code (Inline)',()=>wrapTag('code')),btn('fa-highlighter','Markieren',()=>fmt('hiliteColor','#fde68a',true)),
        btn('fa-palette','Textfarbe',e=>colorPop(e.currentTarget)),btn('fa-superscript','Hochgestellt',()=>fmt('superscript')),btn('fa-subscript','Tiefgestellt',()=>fmt('subscript')),btn('fa-eraser','Formatierung entfernen',()=>fmt('removeFormat'))]:[];
      bar.append(handle,grp(typeBtn),own.length?grp(...own):null,fmtBtns.length?grp(...fmtBtns):null,
        grp(btn('fa-arrow-up','Nach oben',()=>move(id,-1)),btn('fa-arrow-down','Nach unten',()=>move(id,1)),btn('fa-clone','Duplizieren',()=>duplicate(id)),btn('fa-plus','Block darunter einfügen',e=>openInserter(e.currentTarget,r.list,r.i+1)),btn('fa-trash','Löschen',()=>remove(id))));
      bar.querySelectorAll('button').forEach(x=>x.hasAttribute('data-pop-owner')||['Link einfügen','Textfarbe','Block darunter einfügen'].includes(x.title)&&x.setAttribute('data-pop-owner','1'));
    }
    /* ───── Aktionen ───── */
    function insertAt(list,index,name,attrs,focus){
      const b=E.newBlock(name,attrs);list.splice(index,0,b);st.sel=b.id;renderAll();structural();if(focus!==false)setTimeout(()=>focusBlock(b.id),0);return b;
    }
    function remove(id){
      const r=find(id);if(!r)return;const prev=r.list[r.i-1]||r.list[r.i+1]||null;r.list.splice(r.i,1);
      if(!r.list.length){if(r.parent&&r.parent.name==='column'){r.list.push(WRAP_NEW())}else if(r.list===st.blocks)r.list.push(WRAP_NEW())}
      st.sel=prev?prev.id:null;renderAll();structural();if(st.sel)focusBlock(st.sel,true);
    }
    function move(id,d){
      const r=find(id);if(!r)return;const j=r.i+d;if(j<0||j>=r.list.length)return;const x=r.list.splice(r.i,1)[0];r.list.splice(j,0,x);renderAll();structural();const el=blockEl(id);el&&el.scrollIntoView({block:'nearest'});
    }
    function clone(b){const c=JSON.parse(JSON.stringify(b));(function re(x){x.id=E.util.uid();(x.children||[]).forEach(re)})(c);return c}
    function duplicate(id){const r=find(id);if(!r)return;const c=clone(r.b);r.list.splice(r.i+1,0,c);st.sel=c.id;renderAll();structural()}
    function focusBlock(id,end){
      const el=blockEl(id);if(!el)return;const r=find(id),T=r&&E.types[r.b.name];
      if(T&&T.focus){T.focus(el);return}
      const t=el.querySelector('[contenteditable="true"]');if(!t){el.scrollIntoView({block:'nearest'});return}
      t.focus();const rg=document.createRange();rg.selectNodeContents(t);rg.collapse(!end);const s=getSelection();s.removeAllRanges();s.addRange(rg);
    }
    function transform(b,to){
      const get=()=>{const d=document.createElement('div');d.innerHTML=b.html||'';return d};
      let nb;
      if(['paragraph','heading','quote'].includes(b.name)&&['paragraph','heading','quote','code','list'].includes(to)){
        if(to==='code')nb=E.newBlock('code',{html:esc(get().textContent)});
        else if(to==='list')nb=E.newBlock('list',{html:'<li>'+(b.html||'')+'</li>'});
        else nb=E.newBlock(to,{html:b.html||'',align:b.align,color:b.color,bg:b.bg,size:b.size,level:to==='heading'?2:undefined});
      }else if(b.name==='list'&&['paragraph','heading','quote'].includes(to)){
        const items=[...get().querySelectorAll('li')].map(li=>li.innerHTML);nb=E.newBlock(to,{html:items.join('<br>'),level:to==='heading'?2:undefined});
      }else if(b.name==='code'&&['paragraph','heading','quote'].includes(to)){nb=E.newBlock(to,{html:b.html,level:to==='heading'?2:undefined})}
      if(!nb)return;const r=find(b.id);nb.id=b.id;r.list[r.i]=nb;renderAll();structural();focusBlock(nb.id);
    }
    function openTransform(anchor,b){
      const opts2=[['paragraph','Absatz'],['heading','Überschrift'],['quote','Zitat'],['list','Liste'],['code','Code']].filter(o=>o[0]!==b.name&&(['paragraph','heading','quote','list','code'].includes(b.name)));
      if(!opts2.length)return;
      popup(anchor,h('div',{class:'epb-menu'},h('div',{class:'epb-menu-h'},'Umwandeln in'),opts2.map(o=>h('button',{type:'button',onclick:()=>{closePop();transform(b,o[0])}},icon(E.types[o[0]].icon),' '+o[1]))));
    }
    /* ───── Formatierung (Rich Text) ───── */
    function fmt(cmd,arg,css){
      const rich=st.lastRich;if(!rich||!document.body.contains(rich)){return}
      rich.focus();restoreSel();
      try{document.execCommand('styleWithCSS',false,!!css);document.execCommand(cmd,false,arg||null)}catch(e){}
      rich.dispatchEvent(new Event('input',{bubbles:true}));
    }
    let savedRange=null;
    function saveSel(){const s=getSelection();if(s&&s.rangeCount){const r=s.getRangeAt(0);if(st.lastRich&&st.lastRich.contains(r.commonAncestorContainer))savedRange=r.cloneRange()}}
    function restoreSel(){if(!savedRange)return;const s=getSelection();s.removeAllRanges();s.addRange(savedRange)}
    function wrapTag(tag){
      const rich=st.lastRich;if(!rich)return;rich.focus();restoreSel();const s=getSelection();if(!s.rangeCount||s.isCollapsed)return;
      const r=s.getRangeAt(0),anc=r.commonAncestorContainer,ex=(anc.nodeType===1?anc:anc.parentElement).closest(tag);
      if(ex&&rich.contains(ex)){const p=ex.parentNode;while(ex.firstChild)p.insertBefore(ex.firstChild,ex);p.removeChild(ex)}
      else{const el=document.createElement(tag);try{el.appendChild(r.extractContents());r.insertNode(el)}catch(e){}}
      rich.dispatchEvent(new Event('input',{bubbles:true}));
    }
    function popup(anchor,content){
      closePop();pop=h('div',{class:'epb-pop'},content);root.append(pop);
      const a=anchor.getBoundingClientRect(),rr=root.getBoundingClientRect();
      pop.style.left=Math.max(4,Math.min(a.left-rr.left,rr.width-pop.offsetWidth-8))+'px';pop.style.top=(a.bottom-rr.top+6)+'px';
      return pop;
    }
    function linkPop(anchor){
      saveSel();const s=getSelection();let cur='',nt=false;
      if(s.rangeCount){const a=(s.anchorNode&&(s.anchorNode.nodeType===1?s.anchorNode:s.anchorNode.parentElement)||document.body).closest('a');if(a&&st.lastRich&&st.lastRich.contains(a)){cur=a.getAttribute('href')||'';nt=a.target==='_blank'}}
      const url=h('input',{class:'fc',placeholder:'https://… oder /seite/',value:cur}),tgt=h('input',{type:'checkbox',checked:nt});
      const apply=()=>{closePop();const v=url.value.trim();const rich=st.lastRich;if(!rich)return;rich.focus();restoreSel();
        if(!v){document.execCommand('unlink');}else{document.execCommand('createLink',false,v);rich.querySelectorAll('a').forEach(a=>{if(a.getAttribute('href')===v){if(tgt.checked){a.setAttribute('target','_blank');a.setAttribute('rel','noopener noreferrer')}else{a.removeAttribute('target');a.removeAttribute('rel')}}})}
        rich.dispatchEvent(new Event('input',{bubbles:true}))};
      url.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();apply()}});
      const p=popup(anchor,h('div',{class:'epb-linkpop'},url,h('label',{class:'epb-toggle'},tgt,h('span',null,'In neuem Tab öffnen')),h('div',{class:'epb-ph-btns'},h('button',{type:'button',class:'btn-a',onclick:apply},'Übernehmen'),cur?h('button',{type:'button',class:'btn-g',onclick:()=>{url.value='';apply()}},'Link entfernen'):null)));
      setTimeout(()=>url.focus(),0);
    }
    function colorPop(anchor){
      saveSel();const cols=['#000000','#ffffff','#ef4444','#f59e0b','#16a34a','#2f7bff','#8b3dff','#ec4899','#64748b'];
      popup(anchor,h('div',{class:'epb-colorpop'},cols.map(c=>h('button',{type:'button',class:'epb-sw',style:'background:'+c,title:c,onclick:()=>{closePop();fmt('foreColor',c,true)}})),h('input',{type:'color',oninput:e=>{fmt('foreColor',e.target.value,true)}})));
    }
    /* ───── Rich-Text-Anbindung ───── */
    function cleanInline(htmlStr){
      const d=document.createElement('div');d.innerHTML=htmlStr;
      (function walk(n){[...n.childNodes].forEach(c=>{
        if(c.nodeType===3)return;if(c.nodeType!==1){c.remove();return}
        const tag=c.tagName;
        if(['SCRIPT','STYLE','IFRAME','OBJECT','META','LINK'].includes(tag)){c.remove();return}
        if(/^(P|DIV|H[1-6]|LI|UL|OL|BLOCKQUOTE|TR|TABLE|SECTION|ARTICLE|FIGURE)$/.test(tag)){walk(c);const br=document.createElement('br');while(c.firstChild)n.insertBefore(c.firstChild,c);n.insertBefore(br,c);c.remove();return}
        if(!INLINE_OK.has(tag)){walk(c);while(c.firstChild)n.insertBefore(c.firstChild,c);c.remove();return}
        [...c.attributes].forEach(a=>{const nm=a.name.toLowerCase();if(tag==='A'&&(nm==='href'||nm==='target'||nm==='rel'))return;if(nm==='style'&&tag==='SPAN'&&/^(color|background-color|text-decoration|font-weight|font-style)/i.test(a.value.trim()))return;c.removeAttribute(a.name)});
        walk(c);
      })})(d);
      return d.innerHTML.replace(/(<br>\s*)+$/,'');
    }
    function atStart(el){const s=getSelection();if(!s.rangeCount||!s.isCollapsed)return false;const r=document.createRange();r.selectNodeContents(el);r.setEnd(s.anchorNode,s.anchorOffset);return r.toString()===''&&!r.cloneContents().querySelector('img,br')}
    function atEnd(el){const s=getSelection();if(!s.rangeCount||!s.isCollapsed)return false;const r=document.createRange();r.selectNodeContents(el);r.setStart(s.anchorNode,s.anchorOffset);return r.toString()===''&&!r.cloneContents().querySelector('img')}
    function splitAt(el){
      const s=getSelection(),r=s.getRangeAt(0);r.deleteContents();const t=document.createRange();t.selectNodeContents(el);t.setStart(r.endContainer,r.endOffset);const frag=t.extractContents();const tmp=document.createElement('div');tmp.appendChild(frag);
      return {before:el.innerHTML,after:tmp.innerHTML};
    }
    function rich(hostEl,b,key,o){
      o=o||{};hostEl.contentEditable='true';hostEl.setAttribute('data-ph',o.ph||'');hostEl.spellcheck=true;hostEl.innerHTML=b[key]||'';
      const sync=()=>{let v=hostEl.innerHTML;if(hostEl.textContent===''&&!hostEl.querySelector('img'))v='';if(v==='<br>')v='';b[key]=v;changed();};
      hostEl.addEventListener('input',()=>{sync();
        if(b.name==='paragraph'&&key==='html'){const m=/^\/([a-zäöüß]*)$/i.exec(hostEl.textContent.trim());if(m&&(b.html||'').replace(/<[^>]*>/g,'').trim().startsWith('/'))openSlash(b,hostEl,m[1]);else if(st.slash)closePop()}});
      hostEl.addEventListener('focus',()=>{st.lastRich=hostEl;});
      hostEl.addEventListener('keyup',saveSel);hostEl.addEventListener('mouseup',saveSel);hostEl.addEventListener('blur',saveSel);
      hostEl.addEventListener('paste',e=>{
        const cd=e.clipboardData;if(!cd)return;
        const files=[...(cd.files||[])].filter(f=>/^image\//.test(f.type));
        if(files.length&&opts.uploadFile){e.preventDefault();opts.uploadFile(files[0],r=>{const rr=find(b.id);if(rr)insertAt(rr.list,rr.i+1,'image',{url:r.url,alt:r.alt||''})});return}
        e.preventDefault();
        let t=cd.getData('text/html');const plain=cd.getData('text/plain');
        let parts;
        if(t){parts=cleanInline(t).split(/(?:<br>\s*){2,}/).map(x=>x.trim()).filter(Boolean)}else parts=plain.split(/\n{2,}/).map(x=>esc(x).replace(/\n/g,'<br>')).filter(Boolean);
        if(!parts.length)return;
        document.execCommand('insertHTML',false,parts[0]);
        if(parts.length>1&&!o.list&&o.enter){const rr=find(b.id);let idx=rr.i;parts.slice(1).forEach(x=>{rr.list.splice(++idx,0,E.newBlock('paragraph',{html:x}))});renderAll();structural();}
      });
      hostEl.addEventListener('keydown',e=>{
        const rr=find(b.id);if(!rr)return;
        if(st.slash&&['ArrowDown','ArrowUp','Enter','Escape'].includes(e.key)){slashKey(e);return}
        const mod=e.ctrlKey||e.metaKey;
        if(mod&&!e.shiftKey&&e.key.toLowerCase()==='k'){e.preventDefault();linkPop(top.querySelector('.epb-tb')||hostEl);return}
        if(mod&&e.key.toLowerCase()==='z'&&!e.shiftKey){e.preventDefault();undo();return}
        if(mod&&(e.key.toLowerCase()==='y'||(e.key.toLowerCase()==='z'&&e.shiftKey))){e.preventDefault();redo();return}
        if(e.key==='Enter'&&!e.shiftKey&&!mod&&(o.enter||o.single)&&!o.list){
          e.preventDefault();
          if(o.enter||o.single){
            const sp=splitAt(hostEl);b[key]=sp.before;
            const nb=E.newBlock('paragraph',{html:sp.after});
            // Überschrift: Enter am Ende → neuer Absatz; Absatz: teilt in zwei Absätze
            rr.list.splice(rr.i+1,0,nb);st.sel=nb.id;renderAll();structural();setTimeout(()=>focusBlock(nb.id),0);
          }return;
        }
        if(e.key==='Enter'&&o.list){ // leeres letztes Listenelement beendet die Liste
          const li=(getSelection().anchorNode||{}).nodeType===3?getSelection().anchorNode.parentElement.closest('li'):(getSelection().anchorNode||{}).closest&&getSelection().anchorNode.closest('li');
          if(li&&li.textContent.trim()===''&&li===hostEl.lastElementChild){e.preventDefault();li.remove();b[key]=hostEl.innerHTML;const nb=E.newBlock('paragraph');rr.list.splice(rr.i+1,0,nb);st.sel=nb.id;renderAll();structural();setTimeout(()=>focusBlock(nb.id),0)}
          return;
        }
        if(e.key==='Backspace'&&atStart(hostEl)&&!o.list){
          const empty=hostEl.textContent.trim()==='';
          if(empty&&(b.name==='paragraph'||b.name==='heading')&&!(rr.list.length===1&&rr.list===st.blocks)){e.preventDefault();remove(b.id);return}
          if(!empty&&(b.name==='paragraph')&&rr.list[rr.i-1]&&rr.list[rr.i-1].name==='paragraph'){e.preventDefault();const pv=rr.list[rr.i-1];const len=pv.html||'';pv.html=(pv.html||'')+b.html;rr.list.splice(rr.i,1);st.sel=pv.id;renderAll();structural();setTimeout(()=>focusBlock(pv.id,true),0);return}
          if(!empty&&b.name==='heading'){e.preventDefault();transform(b,'paragraph');return}
        }
        if(e.key==='ArrowUp'&&atStart(hostEl)){const pv=prevFocusable(b.id);if(pv){e.preventDefault();focusBlock(pv,true)}}
        if(e.key==='ArrowDown'&&atEnd(hostEl)&&!o.list){const nx=nextFocusable(b.id);if(nx){e.preventDefault();focusBlock(nx)}}
      });
      return hostEl;
    }
    const flat=()=>{const o=[];each(st.blocks,b=>{if(!b.children)o.push(b.id)});return o};
    const prevFocusable=id=>{const f=flat(),i=f.indexOf(id);for(let k=i-1;k>=0;k--){const el=blockEl(f[k]);if(el&&el.querySelector('[contenteditable="true"],textarea,input'))return f[k]}return null};
    const nextFocusable=id=>{const f=flat(),i=f.indexOf(id);for(let k=i+1;k<f.length;k++){const el=blockEl(f[k]);if(el&&el.querySelector('[contenteditable="true"],textarea,input'))return f[k]}return null};
    /* ───── Inserter / Slash ───── */
    function inserterContent(onPick,query){
      const wrap=h('div',{class:'epb-ins'}),search=h('input',{class:'fc',placeholder:'Block suchen …',value:query||''}),grid=h('div',{class:'epb-ins-grid'});
      const draw=()=>{grid.innerHTML='';const q=search.value.trim().toLowerCase();let any=false;
        E.cats.forEach(([cid,cl])=>{const items=Object.values(E.types).filter(t=>t.cat===cid&&(!q||(t.label+' '+t.kw).toLowerCase().includes(q)));if(!items.length)return;any=true;
          grid.append(h('div',{class:'epb-ins-cat'},cl),h('div',{class:'epb-ins-items'},items.map(t=>h('button',{type:'button',class:'epb-ins-item','data-name':t.name,onclick:()=>onPick(t.name)},icon(t.icon),h('span',null,t.label)))))});
        if(!any)grid.append(h('div',{class:'epb-note'},'Kein Block gefunden.'))};
      search.addEventListener('input',draw);draw();wrap.append(search,grid);wrap.focusSearch=()=>search.focus();wrap.setQuery=q=>{search.value=q;draw()};return wrap;
    }
    function openInserter(anchor,list,index){
      const c=inserterContent(name=>{closePop();insertAt(list,index,name)});popup(anchor,c);setTimeout(()=>c.focusSearch(),0);
    }
    function openSlash(b,hostEl,q){
      const c=inserterContent(name=>{closePop();const r=find(b.id);if(!r)return;const nb=E.newBlock(name);nb.id=b.id;r.list[r.i]=nb;renderAll();structural();setTimeout(()=>focusBlock(nb.id),0)},q);
      st.slash={b,hostEl,c};popup(hostEl,c);st.slash={b,hostEl,c};const it=c.querySelector('.epb-ins-item');it&&it.classList.add('is-active');
    }
    function slashKey(e){
      const c=st.slash&&st.slash.c;if(!c)return;const items=[...c.querySelectorAll('.epb-ins-item')];if(!items.length){if(e.key==='Escape'){closePop()}return}
      let i=items.findIndex(x=>x.classList.contains('is-active'));
      if(e.key==='Escape'){e.preventDefault();closePop();return}
      if(e.key==='Enter'){e.preventDefault();items[Math.max(0,i)].click();return}
      e.preventDefault();items.forEach(x=>x.classList.remove('is-active'));i=(i+(e.key==='ArrowDown'?1:-1)+items.length)%items.length;items[i].classList.add('is-active');items[i].scrollIntoView({block:'nearest'});
    }
    /* ───── Drag & Drop ───── */
    function inside(id,targetId){const r=find(id);if(!r)return false;let ok=false;each(r.b.children||[],x=>{if(x.id===targetId)ok=true});return ok}
    function dragOver(e,b,wrap){
      if(!st.drag||st.drag===b.id||inside(st.drag,b.id))return;e.preventDefault();e.stopPropagation();
      const r=wrap.getBoundingClientRect(),before=e.clientY<r.top+r.height/2;wrap.classList.toggle('drop-before',before);wrap.classList.toggle('drop-after',!before);
    }
    function dropOn(e,b,wrap){
      if(!st.drag||st.drag===b.id)return;e.preventDefault();e.stopPropagation();
      const before=wrap.classList.contains('drop-before');wrap.classList.remove('drop-before','drop-after');
      const src=find(st.drag);if(!src||inside(st.drag,b.id))return;const item=src.list.splice(src.i,1)[0];
      const dst=find(b.id);dst.list.splice(dst.i+(before?0:1),0,item);st.sel=item.id;st.drag=null;renderAll();structural();
    }
    /* ───── ctx für Blocktypen ───── */
    const ctx={
      rich,changed:b=>changed(),rerender,canRaw:opts.canRaw!==false,upload:opts.upload?cb=>opts.upload(cb):null,
      set(b,patch,silent){
        Object.assign(b,patch);
        if(!silent)rerender(b);
        changed();
        const ae=document.activeElement;
        const typing=ae&&side.contains(ae)&&/^(INPUT|TEXTAREA)$/.test(ae.tagName)&&ae.type!=='checkbox';
        if(!typing)renderSide();
      },
      aiImage:opts.aiImage||null,
      pickImage(cb){if(opts.pickImage)opts.pickImage(cb);else{const u=prompt('Bild-Adresse (https://…)');if(u)cb({url:u.trim()})}},
      renderList(parent){
        const w=h('div',{class:'epb-sublist'});
        (parent.children||[]).forEach(c=>w.append(blockNode(c)));w.append(appender(parent.children,parent.children.length));return w;
      },
      renderChild(c,parent){const n=blockNode(c);return n},
    };
    /* ───── Seitenleiste (Einstellungen / Struktur) ───── */
    function renderSide(){
      side.style.display=st.sideOpen&&st.mode==='visual'?'':'none';side.innerHTML='';
      const tabs=h('div',{class:'epb-tabs'},[['block','Block'],['outline','Struktur']].map(t=>h('button',{type:'button',class:st.tab===t[0]?'on':'',onclick:()=>{st.tab=t[0];renderSide()}},t[1])));
      side.append(tabs);
      if(st.tab==='outline'){
        const ul=h('div',{class:'epb-outline'});
        each(null,(b,d)=>{const T=E.types[b.name]||E.types.unknown;if(T.cat==='hidden'&&b.name!=='column')return;
          const label=T.label+((b.html||b.label)?' – '+String(b.html||b.label).replace(/<[^>]*>/g,'').slice(0,28):'');
          ul.append(h('button',{type:'button',class:'epb-ol'+(b.id===st.sel?' on':''),style:'padding-left:'+(10+d*14)+'px',onclick:()=>{selectBlock(b.id);const el=blockEl(b.id);el&&el.scrollIntoView({block:'center',behavior:'smooth'})}},icon(T.icon),' '+label))});
        side.append(ul);return;
      }
      const r=st.sel?find(st.sel):null;
      if(!r){side.append(h('div',{class:'epb-note',style:'margin:14px'},'Wähle einen Block, um seine Einstellungen zu sehen. Mit „/“ im leeren Absatz oder dem „+“ fügst du neue Blöcke ein.'));return}
      const T=E.types[r.b.name]||E.types.unknown;
      side.append(h('div',{class:'epb-side-h'},icon(T.icon),' '+T.label,r.parent?h('button',{type:'button',class:'epb-link',onclick:()=>selectBlock(r.parent.id)},'Übergeordneten Block wählen'):null));
      const secs=T.inspector?T.inspector(r.b,ctx):[];secs.forEach(s=>side.append(s));
    }
    /* ───── Obere Leiste ───── */
    function updTop(){
      top.innerHTML='';
      const tb=(ic,title,fn,dis,on)=>h('button',{type:'button',class:'epb-tb'+(on?' on':''),title,disabled:dis,'data-pop-owner':'1',onclick:e=>{e.preventDefault();fn(e)}},icon(ic));
      const vis=st.mode==='visual';
      top.append(
        tb('fa-plus','Block hinzufügen',e=>{const r=st.sel?find(st.sel):null;openInserter(e.currentTarget,r?r.list:st.blocks,r?r.i+1:st.blocks.length)},!vis),
        tb('fa-rotate-left','Rückgängig (Strg+Z)',undo,!vis||st.hpos<1),tb('fa-rotate-right','Wiederholen (Strg+Y)',redo,!vis||st.hpos>=st.hist.length-1),
        h('span',{class:'epb-sep'}),
        h('div',{class:'epb-seg epb-modes'},h('button',{type:'button',class:vis?'on':'',onclick:()=>setMode('visual')},icon('fa-pen-ruler'),' Visuell'),h('button',{type:'button',class:vis?'':'on',onclick:()=>setMode('code')},icon('fa-code'),' Code-Editor')),
        h('span',{class:'epb-grow'}),stats,
        tb('fa-sliders','Einstellungen ein/aus',()=>{st.sideOpen=!st.sideOpen;renderSide();updTop()},!vis,st.sideOpen&&vis));
      updStats();
    }
    function updStats(){
      const d=document.createElement('div');d.innerHTML=html().replace(/<!--[\s\S]*?-->/g,' ');const txt=d.textContent.replace(/\s+/g,' ').trim();
      let n=0;each(null,()=>n++);stats.textContent=(txt?txt.split(' ').length:0)+' Wörter · '+n+' Blöcke · ca. '+Math.max(1,Math.round((txt?txt.split(' ').length:0)/200))+' Min. Lesezeit';
    }
    function setMode(m){
      if(m===st.mode)return;
      if(m==='code'){code.value=html();code.style.display='';main.style.display='none';st.mode='code'}
      else{st.blocks=E.parse(code.value);if(!st.blocks.length)st.blocks=[WRAP_NEW()];st.sel=null;code.style.display='none';main.style.display='';st.mode='visual';renderAll();structural()}
      closePop();updTop();renderSide();
    }
    code.addEventListener('input',()=>{emit()});
    /* ───── API ───── */
    const api={
      el:root,
      getHTML(){return st.mode==='code'?code.value:html()},
      setHTML(v){st.blocks=E.parse(v||'');if(!st.blocks.length)st.blocks=[WRAP_NEW()];st.sel=null;st.hist=[];st.hpos=-1;if(st.mode==='code')code.value=html();else renderAll();pushHist();updTop()},
      isEmpty(){const s=api.getHTML().replace(/<!--[\s\S]*?-->/g,'').replace(/<[^>]*>/g,'').trim();return s===''&&!/<(img|iframe|video|audio|hr)\b/i.test(api.getHTML())},
      insertBlock(name,attrs){if(st.mode==='code'){return}const r=st.sel?find(st.sel):null;return insertAt(r?r.list:st.blocks,r?r.i+1:st.blocks.length,name,attrs,false)},
      insertImage(o){return api.insertBlock('image',{url:o.url,alt:o.alt||'',html:o.caption||''})},
      insertHTML(v){const bl=E.parse(v);const r=st.sel?find(st.sel):null,list=r?r.list:st.blocks;let i=r?r.i+1:list.length;bl.forEach(b=>list.splice(i++,0,b));renderAll();structural()},
      stats:()=>stats.textContent,focus(){if(st.mode==='visual')focusBlock(st.blocks[0].id)},
      setMode,undo,redo,destroy(){closePop();host.innerHTML=''},
    };
    renderAll();pushHist();
    return api;
  };

  /* Vollbild-Fenster um einen Editor (z. B. für HTML-Blöcke der Seitenverwaltung) */
  E.modal=function(o){
    o=o||{};const ov=h('div',{class:'epb-modal'}),box=h('div',{class:'epb-modal-box'}),body=h('div',{class:'epb-modal-body'});
    const close=()=>{ed.destroy();ov.remove();document.removeEventListener('keydown',esc)};const esc=e=>{if(e.key==='Escape')close()};
    box.append(h('div',{class:'epb-modal-h'},h('b',null,o.title||'Inhalt bearbeiten'),h('span',{class:'epb-grow'}),h('button',{type:'button',class:'btn-g',onclick:close},'Abbrechen'),h('button',{type:'button',class:'btn-a',onclick:()=>{const v=ed.getHTML();close();o.onSave&&o.onSave(v)}},icon('fa-check'),' Übernehmen')),body);
    ov.append(box);document.body.append(ov);document.addEventListener('keydown',esc);
    const ed=E.create(body,Object.assign({},o,{html:o.html||''}));return ed;
  };
})(window.EPB);
