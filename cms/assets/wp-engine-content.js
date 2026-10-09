'use strict';
// Menüs, Widgets und Blöcke der WordPress-Engine. Spricht cms/engine-api.php (nav_*, widgets_*, blocks_*, content_list, term_list) an.
window.WpEC=(()=>{
 const S={tab:'nav',busy:'',msg:null,inactive:false,menus:[],locs:[],menu:'',tree:[],dirty:false,pick:{pages:[],posts:[],cats:[]},ov:null,blocks:null,bq:'',bm:'',bres:null,drag:null};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>{try{return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';}catch(e){return '';}};
 const root=()=>document.getElementById('wpecRoot');
 async function api(action,body,query){
  const r=await fetch('/cms/engine-api.php?action='+action+(query||''),{method:body===undefined?'GET':'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:body===undefined?undefined:JSON.stringify(body)});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  return d;
 }
 const uid=()=>'n'+Math.random().toString(36).slice(2,9);
 const flat=(l,f)=>l.forEach(x=>{f(x);flat(x.children||[],f)});
 function find(id,list,parent){list=list||S.tree;for(let i=0;i<list.length;i++){if(list[i].uid===id)return {list,i,node:list[i],parent:parent||null};const r=find(id,list[i].children||[],list[i]);if(r)return r}return null}
 function tag(l){l.forEach(x=>{x.uid=x.uid||uid();x.children=x.children||[];tag(x.children)});return l}
 function strip(l){return l.map(x=>({id:/^\d+$/.test(x.id||'')?x.id:'',type:x.type,object_id:x.object_id||'',label:x.label||'',url:x.url||'',target:x.target||'',rel:x.rel||'',classes:x.classes||'',children:strip(x.children||[])}))}
 async function run(label,fn){if(S.busy)return;S.busy=label;S.msg=null;draw();try{await fn();}catch(e){S.msg={err:true,text:e.message};}S.busy='';draw();}
 const fail=d=>{if(d.status!=='ok')throw new Error(d.message||'Fehlgeschlagen');return d};

 async function load(){
  try{
   const d=await api('nav_list');if(d.status!=='ok'){S.inactive=true;S.msg={err:true,text:d.message||'Nicht verfügbar'};draw();return}
   S.inactive=false;S.menus=d.menus;S.locs=d.locations;
   if(!S.menu||!S.menus.some(m=>m.id===S.menu))S.menu=S.menus.length?S.menus[0].id:'';
   if(S.tab==='nav')await loadMenu();else if(S.tab==='widgets')await loadWidgets();else await loadBlocks();
  }catch(e){S.msg={err:true,text:e.message};}
  draw();
 }
 async function loadMenu(){
  if(!S.menu){S.tree=[];return}
  const d=fail(await api('nav_get',undefined,'&menu='+encodeURIComponent(S.menu)));S.tree=tag(d.menu.items);S.dirty=false;
  if(!S.pick.pages.length){
   const [p,po,c]=await Promise.all([api('content_list',undefined,'&type=page&per_page=100&status=published'),api('content_list',undefined,'&type=post&per_page=50&status=published'),api('term_list',undefined,'&taxonomy=category')]);
   S.pick.pages=(p.items||[]);S.pick.posts=(po.items||[]);S.pick.cats=(c.items||[]);
  }
 }
 async function loadWidgets(){S.ov=fail(await api('widgets_overview'))}
 async function loadBlocks(){if(!S.blocks)S.blocks=fail(await api('blocks_registry')).blocks}

 /* ───────── Menüs ───────── */
 function itemRow(x,depth,i,len){
  return '<div class="wpec-item" style="margin-left:'+(depth*22)+'px" draggable="true" data-u="'+x.uid+'">'
   +'<span class="wpec-grip" title="Ziehen zum Sortieren">⋮⋮</span><span class="wpec-type">'+({custom:'Link',page:'Seite',post:'Beitrag',category:'Kategorie',tag:'Schlagwort'}[x.type]||x.type)+'</span>'
   +'<input class="fc" data-f="label" data-u="'+x.uid+'" value="'+esc(x.label)+'" placeholder="Text"'+'>'
   +(x.type==='custom'?'<input class="fc" data-f="url" data-u="'+x.uid+'" value="'+esc(x.url)+'" placeholder="https://… oder /pfad">':'<span class="hint wpec-url" title="'+esc(x.url)+'">'+esc(x.url||'')+'</span>')
   +'<label class="hint" title="In neuem Tab öffnen"><input type="checkbox" data-f="target" data-u="'+x.uid+'"'+(x.target==='_blank'?' checked':'')+'> neuer Tab</label>'
   +'<span class="wpec-ops"><button class="btn-g" data-op="up" data-u="'+x.uid+'"'+(i===0?' disabled':'')+' aria-label="Nach oben">↑</button><button class="btn-g" data-op="down" data-u="'+x.uid+'"'+(i===len-1?' disabled':'')+' aria-label="Nach unten">↓</button>'
   +'<button class="btn-g" data-op="in" data-u="'+x.uid+'"'+(i===0||depth>=3?' disabled':'')+' aria-label="Einrücken (Untermenü)">→</button><button class="btn-g" data-op="out" data-u="'+x.uid+'"'+(depth===0?' disabled':'')+' aria-label="Ausrücken">←</button>'
   +'<button class="btn-g" data-op="del" data-u="'+x.uid+'" aria-label="Entfernen"><i class="fas fa-trash"></i></button></span></div>';
 }
 function treeHtml(l,depth){return l.map((x,i)=>itemRow(x,depth,i,l.length)+treeHtml(x.children||[],depth+1)).join('')}
 function navHtml(){
  const m=S.menus.find(x=>x.id===S.menu);
  let h='<div class="wpec-bar"><select class="fc" id="wpecMenu">'+S.menus.map(x=>'<option value="'+esc(x.id)+'"'+(x.id===S.menu?' selected':'')+'>'+esc(x.name)+' ('+x.count+')</option>').join('')+'</select>'
   +'<button class="btn-g" data-a="mnew">Neues Menü</button>'+(m?'<button class="btn-g" data-a="mren">Umbenennen</button><button class="btn-g" data-a="mdel">Löschen</button>':'')
   +'<button class="btn-g" data-a="mimp" title="Menü aus der bisherigen ElvadoPress-Verwaltung als neues Menü übernehmen">Aus ElvadoPress übernehmen</button></div>';
  if(!m)return h+'<p class="hint">Noch kein Menü. Lege oben ein neues an.</p>';
  h+='<div class="wpec-cols"><div class="wpec-add">'
   +'<b>Seiten</b><div class="wpec-list">'+S.pick.pages.map(p=>'<label><input type="checkbox" data-pk="page" value="'+esc(p.id)+'"> '+esc(p.title)+'</label>').join('')+'</div>'
   +'<b>Beiträge</b><div class="wpec-list">'+S.pick.posts.map(p=>'<label><input type="checkbox" data-pk="post" value="'+esc(p.id)+'"> '+esc(p.title)+'</label>').join('')+'</div>'
   +'<b>Kategorien</b><div class="wpec-list">'+S.pick.cats.map(p=>'<label><input type="checkbox" data-pk="category" value="'+esc(p.id)+'"> '+esc(p.name)+'</label>').join('')+'</div>'
   +'<button class="btn-g" data-a="addsel">Auswahl ins Menü</button><hr><b>Eigener Link</b><input class="fc" id="wpecLl" placeholder="Text"><input class="fc" id="wpecLu" placeholder="https://… oder /pfad"><button class="btn-g" data-a="addlink">Link hinzufügen</button></div>'
   +'<div class="wpec-main"><div class="wpec-tree" id="wpecTree">'+(S.tree.length?treeHtml(S.tree,0):'<p class="hint">Das Menü ist leer. Füge links Einträge hinzu.</p>')+'</div>'
   +'<div class="wpec-bar"><button class="btn-a" data-a="msave"'+(S.dirty?'':' ')+'>Menü speichern'+(S.dirty?' *':'')+'</button></div>'
   +'<h4>Orte im Theme</h4>'+(S.locs.length?S.locs.map(l=>'<div class="wpec-loc"><span>'+esc(l.label)+'</span><select class="fc" data-loc="'+esc(l.id)+'"><option value="">– kein Menü –</option>'+S.menus.map(x=>'<option value="'+esc(x.id)+'"'+(l.menu===x.id?' selected':'')+'>'+esc(x.name)+'</option>').join('')+'</select></div>').join(''):'<p class="hint">Das aktive Theme hat keine Menü-Orte.</p>')
   +'</div></div>';
  return h;
 }
 function moveNode(op,u){
  const f=find(u);if(!f)return;const {list,i}=f;
  if(op==='up'&&i>0){list.splice(i-1,0,list.splice(i,1)[0])}
  else if(op==='down'&&i<list.length-1){list.splice(i+1,0,list.splice(i,1)[0])}
  else if(op==='in'&&i>0){const n=list.splice(i,1)[0];list[i-1].children.push(n)}
  else if(op==='out'&&f.parent){const g=find(f.parent.uid);const n=list.splice(i,1)[0];g.list.splice(g.i+1,0,n)}
  else if(op==='del'){list.splice(i,1)}
  S.dirty=true;draw();
 }
 function addItems(items){items.forEach(x=>S.tree.push(Object.assign({uid:uid(),children:[],target:'',rel:'',classes:'',url:'',object_id:'',id:''},x)));S.dirty=true;draw()}

 /* ───────── Widgets ───────── */
 function fieldHtml(f,v,wid){
  const at=' data-wf="'+esc(f.k)+'" data-w="'+esc(wid)+'"';
  if(f.type==='checkbox')return '<label class="wpec-f"><input type="checkbox"'+at+(v?' checked':'')+'> '+esc(f.label)+'</label>';
  if(f.type==='select')return '<label class="wpec-f"><span>'+esc(f.label)+'</span><select class="fc"'+at+'>'+Object.keys(f.options).map(k=>'<option value="'+esc(k)+'"'+(String(v)===k?' selected':'')+'>'+esc(f.options[k])+'</option>').join('')+'</select></label>';
  if(f.type==='html'||f.type==='textarea')return '<label class="wpec-f"><span>'+esc(f.label)+'</span><textarea class="fc" rows="4"'+at+'>'+esc(v)+'</textarea></label>';
  if(f.type==='number')return '<label class="wpec-f"><span>'+esc(f.label)+'</span><input class="fc" type="number" min="'+f.min+'" max="'+f.max+'"'+at+' value="'+esc(v)+'"></label>';
  return '<label class="wpec-f"><span>'+esc(f.label)+'</span><input class="fc"'+at+' value="'+esc(v)+'"></label>';
 }
 function widgetsHtml(){
  const o=S.ov;if(!o)return '<p class="hint">Lade …</p>';
  const types=o.types;
  if(!o.areas.some(a=>!a.inactive))return '<p class="hint">Das aktive Theme (oder der abgesicherte Modus) hat keine Widget-Bereiche. Block-Themes verwalten Bereiche im Website-Editor; mit einem klassischen Theme erscheinen sie hier.</p>';
  let h='<div class="wpec-bar"><select class="fc" id="wpecWArea">'+o.areas.filter(a=>!a.inactive).map(a=>'<option value="'+esc(a.id)+'">'+esc(a.name)+'</option>').join('')+'</select>'
   +'<select class="fc" id="wpecWType">'+types.map(t=>'<option value="'+esc(t.id_base)+'" title="'+esc(t.description)+'">'+esc(t.name)+'</option>').join('')+'</select><button class="btn-a" data-a="wadd">Widget hinzufügen</button></div>';
  h+=o.areas.map(a=>'<div class="card wpec-area"><div class="tt">'+esc(a.name)+(a.description?' <span class="hint">· '+esc(a.description)+'</span>':'')+'</div>'+(a.widgets.length?a.widgets.map((w,i)=>widgetCard(w,a,i,o)).join(''):'<p class="hint">Leer.</p>')+'</div>').join('');
  return h;
 }
 function widgetCard(w,a,i,o){
  const t=o.types.find(x=>x.id_base===w.id_base)||{name:w.id_base,schema:null};
  const body=t.schema?t.schema.map(f=>fieldHtml(f,w.settings[f.k]!==undefined?w.settings[f.k]:f.default,w.id)).join(''):'<label class="wpec-f"><span>Einstellungen (JSON, vom Widget geprüft)</span><textarea class="fc" rows="5" data-wjson="'+esc(w.id)+'">'+esc(JSON.stringify(w.settings,null,1))+'</textarea></label>';
  return '<details class="wpec-w"><summary><b>'+esc(w.summary||t.name)+'</b> <span class="hint">'+esc(t.name)+' · '+esc(w.id)+'</span></summary><div class="wpec-wbody">'+body
   +'<div class="wpec-bar"><button class="btn-a" data-a="wsave" data-w="'+esc(w.id)+'">Speichern</button>'
   +'<button class="btn-g" data-a="wup" data-w="'+esc(w.id)+'"'+(i===0?' disabled':'')+'>↑</button><button class="btn-g" data-a="wdown" data-w="'+esc(w.id)+'"'+(i===a.widgets.length-1?' disabled':'')+'>↓</button>'
   +'<select class="fc" data-wmove="'+esc(w.id)+'"><option value="">Verschieben nach …</option>'+o.areas.filter(x=>x.id!==a.id).map(x=>'<option value="'+esc(x.id)+'">'+esc(x.name)+'</option>').join('')+'</select>'
   +'<button class="btn-g" data-a="wdel" data-w="'+esc(w.id)+'"><i class="fas fa-trash"></i></button></div></div></details>';
 }
 function collect(wid){
  const t=S.ov.types.find(x=>wid.replace(/-\d+$/,'')===x.id_base);const out={};
  if(!t||!t.schema){const ta=root().querySelector('[data-wjson="'+wid+'"]');try{return JSON.parse(ta.value||'{}')}catch(e){throw new Error('Die Einstellungen sind kein gültiges JSON.')}}
  root().querySelectorAll('[data-w="'+wid+'"][data-wf]').forEach(el=>{out[el.dataset.wf]=el.type==='checkbox'?el.checked:el.value});
  return out;
 }
 function layoutOf(){const l={};S.ov.areas.forEach(a=>{l[a.id]=a.widgets.map(w=>w.id)});return l}

 /* ───────── Blöcke ───────── */
 function blocksHtml(){
  const q=S.bq.toLowerCase(),list=(S.blocks||[]).filter(b=>!q||(b.name+' '+b.title).toLowerCase().includes(q));
  let h='<div class="wpec-cols"><div class="wpec-add"><input class="fc" id="wpecBq" placeholder="Block-Typen suchen …" value="'+esc(S.bq)+'"><div class="wpec-list" style="max-height:420px">'
   +list.slice(0,200).map(b=>'<div title="'+esc(b.description)+'"><b>'+esc(b.title)+'</b> <span class="hint">'+esc(b.name)+(b.core?'':' · Plugin')+(b.dynamic?' · dynamisch':'')+'</span></div>').join('')+'</div><p class="hint">'+(S.blocks||[]).length+' Block-Typen registriert.</p></div>'
   +'<div class="wpec-main"><textarea class="fc" id="wpecBm" rows="10" placeholder="Block-Markup einfügen (<!-- wp:paragraph --> … oder <!-- ep:paragraph --> …)">'+esc(S.bm)+'</textarea>'
   +'<div class="wpec-bar"><button class="btn-g" data-a="bcheck">Prüfen</button><button class="btn-g" data-a="brender">Ausgabe ansehen</button><button class="btn-g" data-a="bwp">Nach WordPress umwandeln</button><button class="btn-g" data-a="bep">Nach ElvadoPress umwandeln</button></div>';
  const r=S.bres;
  if(r){
   if(r.check)h+='<div class="hint">'+r.check.blocks+' Blöcke · unbekannt: '+(r.check.unknown.length?esc(r.check.unknown.join(', ')):'keine')+' · dynamisch: '+(r.check.dynamic.length?esc(r.check.dynamic.join(', ')):'keine')+(r.check.freeform?' · enthält losen Text (Freiform)':'')+(r.converted!==undefined?' · umgewandelt: '+r.converted+', als HTML-Block belassen: '+r.fallback:'')+'</div>';
   if(r.html!==undefined)h+='<iframe class="wpec-prev" sandbox="" srcdoc="'+esc('<base target="_blank"><body style="font-family:system-ui;padding:12px">'+r.html)+'" title="Ausgabe"></iframe>';
  }
  return h+'</div></div>';
 }

 function draw(){
  const r=root();if(!r)return;
  let h='';
  if(S.msg)h+='<div class="'+(S.msg.err?'danger-note':'hint')+'" style="margin:8px 0;padding:8px 12px;border-radius:8px;'+(S.msg.err?'':'background:rgba(34,160,107,.12)')+'">'+esc(S.msg.text)+'</div>';
  if(S.inactive){r.innerHTML=h+'<p class="hint">Die WordPress-Engine ist nicht aktiv. Richte sie unter <b>System → WordPress-Engine</b> ein.</p>';return}
  h+='<div class="wpec-tabs">'+[['nav','Menüs'],['widgets','Widgets'],['blocks','Blöcke']].map(t=>'<button class="'+(S.tab===t[0]?'btn-a':'btn-g')+'" data-t="'+t[0]+'">'+t[1]+'</button>').join('')+'</div>';
  h+=S.tab==='nav'?navHtml():S.tab==='widgets'?widgetsHtml():blocksHtml();
  if(S.busy)h+='<p class="hint"><i class="fas fa-spinner fa-spin"></i> '+esc(S.busy)+'</p>';
  r.innerHTML=h;
 }
 function bind(){
  const r=root();if(!r||r.dataset.bound)return;r.dataset.bound='1';
  r.addEventListener('click',e=>{
   const t=e.target.closest('[data-t]');if(t){S.tab=t.dataset.t;S.msg=null;load();return}
   const op=e.target.closest('[data-op]');if(op){moveNode(op.dataset.op,op.dataset.u);return}
   const b=e.target.closest('[data-a]');if(!b)return;const a=b.dataset.a;
   if(a==='mnew'){const n=prompt('Name des neuen Menüs:');if(n)run('Lege an …',async()=>{const d=fail(await api('nav_create',{name:n}));S.menu=d.menu.id;await load()})}
   else if(a==='mren'){const m=S.menus.find(x=>x.id===S.menu);const n=prompt('Neuer Name:',m.name);if(n)run('Speichere …',async()=>{fail(await api('nav_rename',{menu:S.menu,name:n}));await load()})}
   else if(a==='mdel'){if(confirm('Menü wirklich löschen?'))run('Lösche …',async()=>{fail(await api('nav_delete',{menu:S.menu}));S.menu='';await load()})}
   else if(a==='mimp'){const k=prompt('Welches Menü übernehmen? „top“ (Top Navigation) oder „bottom“ (Bottom Navigation)','top');if(k)run('Übernehme …',async()=>{const d=fail(await api('nav_import',{from:k}));S.menu=d.menu.id;S.msg={text:d.items+' Einträge übernommen.'};await load()})}
   else if(a==='msave')run('Speichere Menü …',async()=>{const d=fail(await api('nav_save',{menu:S.menu,items:strip(S.tree)}));S.tree=tag(d.items);S.dirty=false;S.msg={text:'Menü gespeichert.'};const l=fail(await api('nav_list'));S.menus=l.menus})
   else if(a==='addsel'){const sel=[];r.querySelectorAll('[data-pk]:checked').forEach(c=>{const kind=c.dataset.pk;const src=kind==='page'?S.pick.pages:kind==='post'?S.pick.posts:S.pick.cats;const o=src.find(x=>x.id===c.value);if(o)sel.push({type:kind,object_id:o.id,label:o.title||o.name,url:''})});if(sel.length)addItems(sel)}
   else if(a==='addlink'){const l=document.getElementById('wpecLl').value.trim(),u=document.getElementById('wpecLu').value.trim();if(l&&u)addItems([{type:'custom',label:l,url:u}]);else{S.msg={err:true,text:'Text und Adresse eintragen.'};draw()}}
   else if(a==='wadd'){const area=document.getElementById('wpecWArea').value,ty=document.getElementById('wpecWType').value;run('Füge hinzu …',async()=>{fail(await api('widgets_add',{area:area,id_base:ty,settings:{}}));await loadWidgets()})}
   else if(a==='wsave'){const id=b.dataset.w;run('Speichere …',async()=>{fail(await api('widgets_update',{id:id,settings:collect(id)}));await loadWidgets();S.msg={text:'Widget gespeichert.'}})}
   else if(a==='wup'||a==='wdown'){const id=b.dataset.w;const l=layoutOf();for(const k in l){const i=l[k].indexOf(id);if(i>=0){const j=a==='wup'?i-1:i+1;if(j>=0&&j<l[k].length){l[k].splice(j,0,l[k].splice(i,1)[0])}}}run('Verschiebe …',async()=>{S.ov=fail(await api('widgets_move',{layout:l}))})}
   else if(a==='wdel'){if(confirm('Widget entfernen?'))run('Entferne …',async()=>{S.ov=fail(await api('widgets_delete',{id:b.dataset.w}))})}
   else if(['bcheck','brender','bwp','bep'].includes(a)){S.bm=document.getElementById('wpecBm').value;run('Arbeite …',async()=>{
     if(a==='bcheck'){const d=fail(await api('blocks_check',{markup:S.bm}));S.bres={check:d}}
     else if(a==='brender'){const d=fail(await api('blocks_render',{markup:S.bm}));S.bres={html:d.html,check:await api('blocks_check',{markup:S.bm})}}
     else{const d=fail(await api('blocks_convert',{markup:S.bm,to:a==='bwp'?'wp':'ep'}));S.bm=d.markup;S.bres={check:d.check,converted:d.converted,fallback:d.fallback}}})}
  });
  r.addEventListener('change',e=>{
   const el=e.target;
   if(el.id==='wpecMenu'){S.menu=el.value;run('Lade …',loadMenu)}
   else if(el.dataset.loc!==undefined){run('Weise zu …',async()=>{const d=fail(await api('nav_assign',{location:el.dataset.loc,menu:el.value}));S.locs=d.locations;const l=fail(await api('nav_list'));S.menus=l.menus})}
   else if(el.dataset.wmove&&el.value){const id=el.dataset.wmove,to=el.value,l=layoutOf();for(const k in l)l[k]=l[k].filter(x=>x!==id);l[to]=(l[to]||[]).concat([id]);run('Verschiebe …',async()=>{S.ov=fail(await api('widgets_move',{layout:l}))})}
   else if(el.dataset.f&&el.dataset.u){const f=find(el.dataset.u);if(f){f.node[el.dataset.f]=el.dataset.f==='target'?(el.checked?'_blank':''):el.value;S.dirty=true}}
  });
  r.addEventListener('input',e=>{const el=e.target;if(el.dataset.f&&el.dataset.u&&el.type!=='checkbox'){const f=find(el.dataset.u);if(f){f.node[el.dataset.f]=el.value;S.dirty=true}}else if(el.id==='wpecBq'){S.bq=el.value;const pos=el.selectionStart;draw();const n=document.getElementById('wpecBq');if(n){n.focus();n.setSelectionRange(pos,pos)}}});
  r.addEventListener('dragstart',e=>{const it=e.target.closest('.wpec-item');if(it){S.drag=it.dataset.u;try{e.dataTransfer.setData('text/plain',S.drag)}catch(x){}}});
  r.addEventListener('dragover',e=>{if(S.drag&&e.target.closest('.wpec-item'))e.preventDefault()});
  r.addEventListener('drop',e=>{const it=e.target.closest('.wpec-item');if(!S.drag||!it||it.dataset.u===S.drag)return;e.preventDefault();
   const from=find(S.drag),to=find(it.dataset.u);S.drag=null;if(!from||!to)return;let inside=false;(function ch(n){if(n.uid===to.node.uid)inside=true;(n.children||[]).forEach(ch)})(from.node);if(inside)return;
   const asChild=e.offsetX>it.clientWidth*0.6&&to.node.type!=='x';from.list.splice(from.i,1);const t2=find(it.dataset.u);
   if(asChild)t2.node.children.push(from.node);else t2.list.splice(t2.i,0,from.node);S.dirty=true;draw()});
 }
 return {load:()=>{bind();load()}};
})();
