/* Website-Editor für Block-Themes: Vorlagen und Teile mit dem WordPress-Blockeditor bearbeiten (läuft im abgeschotteten Plugin-Rahmen). */
(function(){
  'use strict';
  var D=window.rrwSiteEditor,root=document.getElementById('rrw-se-root');
  if(!D||!root||!window.wp||!wp.blockEditor){ if(root)root.innerHTML='<div class="rrw-se-loading">Der Editor konnte nicht geladen werden (Kernressourcen fehlen?).</div>';return; }
  var el=wp.element,h=el.createElement,useState=el.useState,useEffect=el.useEffect,useRef=el.useRef,useCallback=el.useCallback;
  var be=wp.blockEditor,C=wp.components,api=wp.apiFetch;
  try{ wp.blockLibrary.registerCoreBlocks(); }catch(e){ console.error('Kernblöcke',e); }
  try{ if(wp.formatLibrary&&wp.formatLibrary.default)void 0; }catch(e){}

  function Toolbar(p){
    return h('div',{className:'rrw-se-bar'},
      h('b',{className:'rrw-se-title'},D.themeName+' · '+(p.cur?p.cur.title:'')),
      h('span',{className:'rrw-se-state'},p.msg||(p.dirty?'Ungespeicherte Änderungen':'')),
      h(C.Button,{variant:'tertiary',disabled:!p.canUndo,onClick:p.undo,label:'Rückgängig'},'↶'),
      h(C.Button,{variant:'tertiary',disabled:!p.canRedo,onClick:p.redo,label:'Wiederholen'},'↷'),
      h(C.Button,{variant:'tertiary',disabled:!p.cur||!p.cur.custom,onClick:p.reset},'Auf Theme zurücksetzen'),
      h(C.Button,{variant:'primary',disabled:!p.dirty||p.busy,isBusy:p.busy,onClick:p.save},'Speichern'),
      h('a',{className:'components-button is-secondary',href:D.home,target:'_blank',rel:'noopener'},'Website ansehen'));
  }

  function Sidebar(p){
    function group(title,kind,items){
      return h('div',{className:'rrw-se-group'},h('div',{className:'rrw-se-gh'},title),
        items.map(function(it){
          var on=p.cur&&p.cur.kind===kind&&p.cur.slug===it.slug&&!p.styles;
          return h('button',{key:kind+it.slug,className:'rrw-se-item'+(on?' on':''),onClick:function(){p.open(kind,it)}},it.title,it.custom?h('span',{className:'rrw-se-dot',title:'Eigene Fassung'},'●'):null);
        }));
    }
    return h('div',{className:'rrw-se-side'},group('Vorlagen','template',D.list.template),group('Vorlagenteile','part',D.list.part),
      h('div',{className:'rrw-se-group'},h('div',{className:'rrw-se-gh'},'Design'),h('button',{className:'rrw-se-item'+(p.styles?' on':''),onClick:p.showStyles},'Globale Stile (Farben)')));
  }

  function Styles(p){
    var st=useState(D.palette.map(function(c){return Object.assign({},c)})),pal=st[0],setPal=st[1];
    var msg=useState(''),m=msg[0],setM=msg[1];
    function save(){ var colors={};pal.forEach(function(c){colors[c.slug]=c.color});
      api({path:'/rrw/v1/site-editor/palette',method:'POST',data:{colors:colors}}).then(function(r){D.palette=r;setM('Gespeichert. Die Website nutzt die neuen Farben sofort.')}).catch(function(e){setM('Fehler: '+(e&&e.message||e))}); }
    function reset(){ api({path:'/rrw/v1/site-editor/palette',method:'DELETE'}).then(function(r){D.palette=r;setPal(r.map(function(c){return Object.assign({},c)}));setM('Zurückgesetzt.')}); }
    return h('div',{className:'rrw-se-styles'},h('h2',null,'Globale Stile'),h('p',{className:'rrw-se-hint'},'Farbpalette des Themes. Änderungen gelten für die ganze Website.'),
      pal.map(function(c,i){ return h('label',{key:c.slug,className:'rrw-se-color'},h('input',{type:'color',value:/^#[0-9a-f]{6}$/i.test(c.color)?c.color:'#000000',onChange:function(e){var v=e.target.value;setPal(pal.map(function(x,j){return j===i?Object.assign({},x,{color:v}):x}))}}),h('span',null,c.name),h('code',null,c.color)); }),
      h('div',{className:'rrw-se-row'},h(C.Button,{variant:'primary',onClick:save},'Speichern'),h(C.Button,{variant:'secondary',onClick:reset},'Zurücksetzen')),h('div',{className:'rrw-se-hint'},m));
  }

  function Editor(){
    var s=useState(null),cur=s[0],setCur=s[1];
    var b=useState([]),blocks=b[0],setBlocks=b[1];
    var d=useState(false),dirty=d[0],setDirty=d[1];
    var ms=useState(''),msg=ms[0],setMsg=ms[1];
    var bs=useState(false),busy=bs[0],setBusy=bs[1];
    var sy=useState(false),styles=sy[0],setStyles=sy[1];
    var hist=useRef({past:[],future:[]}),tick=useState(0),setTick=tick[1];
    var settings=useRef({hasFixedToolbar:true,styles:D.styles,__experimentalFeatures:D.settings,supportsLayout:true,canLockBlocks:false,__unstableIsPreviewMode:false}).current;

    function load(kind,it){
      setStyles(false);setMsg('Lade …');
      api({path:'/rrw/v1/site-editor/'+kind+'/'+it.slug}).then(function(r){
        var parsed=wp.blocks.parse(r.content||'');
        hist.current={past:[],future:[]};setCur({kind:kind,slug:it.slug,title:it.title,custom:!!r.custom});setBlocks(parsed);setDirty(false);setMsg('');
      }).catch(function(e){setMsg('Fehler: '+(e&&e.message||e))});
    }
    useEffect(function(){ if(D.list.template.length){ var first=D.list.template.filter(function(t){return t.slug==='index'})[0]||D.list.template[0];load('template',first); } },[]);
    function change(next){ hist.current.past.push(blocks);if(hist.current.past.length>50)hist.current.past.shift();hist.current.future=[];setBlocks(next);setDirty(true);setMsg(''); }
    function undo(){ var p=hist.current.past.pop();if(!p)return;hist.current.future.push(blocks);setBlocks(p);setDirty(true);setTick(function(x){return x+1}); }
    function redo(){ var f=hist.current.future.pop();if(!f)return;hist.current.past.push(blocks);setBlocks(f);setDirty(true);setTick(function(x){return x+1}); }
    function save(){ if(!cur)return;setBusy(true);
      api({path:'/rrw/v1/site-editor/'+cur.kind+'/'+cur.slug,method:'PUT',data:{content:wp.blocks.serialize(blocks)}}).then(function(){
        setBusy(false);setDirty(false);setMsg('Gespeichert.');
        var it=(cur.kind==='part'?D.list.part:D.list.template).filter(function(x){return x.slug===cur.slug})[0];if(it)it.custom=true;setCur(Object.assign({},cur,{custom:true}));
      }).catch(function(e){setBusy(false);setMsg('Fehler: '+(e&&e.message||e))}); }
    function reset(){ if(!cur||!confirm('Eigene Fassung verwerfen und die Datei des Themes verwenden?'))return;
      api({path:'/rrw/v1/site-editor/'+cur.kind+'/'+cur.slug,method:'DELETE'}).then(function(){
        var it=(cur.kind==='part'?D.list.part:D.list.template).filter(function(x){return x.slug===cur.slug})[0];if(it)it.custom=false;load(cur.kind,it||cur);}); }
    function open(kind,it){ if(dirty&&!confirm('Ungespeicherte Änderungen verwerfen?'))return;load(kind,it); }

    var main=styles?h(Styles):h(C.SlotFillProvider,null,
      h(be.BlockEditorProvider,{value:blocks,onInput:setBlocks,onChange:change,settings:settings},
        h('div',{className:'rrw-se-main'},
          h(be.BlockTools,{className:'rrw-se-tools'},
            h('div',{className:'rrw-se-canvas'},
              h(be.__unstableEditorStyles||be.EditorStyles,{styles:D.styles}),
              h(be.BlockSelectionClearer,{className:'editor-styles-wrapper rrw-se-paper'},h(be.WritingFlow,null,h(be.ObserveTyping,null,h(be.BlockList)))))),
          h('div',{className:'rrw-se-insp'},h('div',{className:'rrw-se-gh'},'Einstellungen'),h(be.BlockInspector),h('div',{className:'rrw-se-gh',style:{marginTop:12}},'Block einfügen'),h('div',{className:'rrw-se-ins'},h(be.Inserter,{rootClientId:undefined,isAppender:false,position:'bottom right',__experimentalIsQuick:false}))))),
      h(C.Popover.Slot,null));
    return h('div',{className:'rrw-se'},
      h(Toolbar,{cur:cur,dirty:dirty,msg:msg,busy:busy,canUndo:hist.current.past.length>0,canRedo:hist.current.future.length>0,undo:undo,redo:redo,save:save,reset:reset}),
      h('div',{className:'rrw-se-body'},h(Sidebar,{cur:cur,styles:styles,open:open,showStyles:function(){setStyles(true)}}),main));
  }
  try{ (el.createRoot?el.createRoot(root).render(h(Editor)):el.render(h(Editor),root)); }catch(e){ root.innerHTML='<div class="rrw-se-loading">Fehler: '+String(e&&e.message||e)+'</div>'; console.error(e); }
})();
