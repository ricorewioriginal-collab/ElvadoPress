'use strict';
window.ThemeManager=(()=>{
 let themes=[], themeState={active:'ricorewi-neon',variant:'default',settings:{}}, editing=null, draft=null;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const token=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
 async function api(action,body){
  const opt=body===undefined?{headers:{'X-AnMaCha-Token':token()}}:{method:'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':token()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opt),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 function bind(){
  const up=document.getElementById('themeUpload');
  if(up&&!up.dataset.bound){up.dataset.bound='1';up.addEventListener('change',()=>upload(up.files?.[0]));}
 }
 async function upload(file){
  if(!file)return;const inp=document.getElementById('themeUpload'),st=(m,k)=>window.DesignHub&&DesignHub.status(m,k);
  st('Lade „'+file.name+'“ hoch …','busy');
  try{
   const fd=new FormData();fd.append('file',file);
   const r=await fetch('api.php?action=theme_upload&_='+Date.now(),{method:'POST',headers:{'X-AnMaCha-Token':token()},body:fd});
   const d=await r.json().catch(()=>null);
   if(r.status===401)window.cmsSessionExpired?.();
   if(!d)throw new Error('Upload fehlgeschlagen (Serverantwort '+r.status+'). Möglicherweise ist die Datei größer als das Upload-Limit des Servers.');
   if(!r.ok||d.status!=='ok')throw new Error(d.message||'Theme-Upload fehlgeschlagen');
   const kind=d.compatibility==='wordpress+bootstrap'?'WordPress + Bootstrap':d.wordpress?'WordPress-kompatibel':d.bootstrap?'Bootstrap-kompatibel':'RicoReWi CMS';
   window.cmsToast?.(kind+' Theme importiert ✓');
   await load(true);
   const name=(themes.find(x=>x.id===d.id)||{}).name||d.id||'Theme';
   st('Portal-Design „'+name+'“ ist installiert. Du findest es unter „Portal-Designs“.','ok');
   document.getElementById('dgPortal')?.scrollIntoView({behavior:'smooth',block:'start'});
  }catch(e){window.cmsToast?.(e.message,true);st(e.message,'bad')}
  finally{if(inp)inp.value=''}
 }
 let dirPage=1,dirPages=1,dirItems=[];
 async function dirSearch(page){
  const g=document.getElementById('tdGrid');if(!g)return;dirPage=page;
  const src=document.getElementById('tdSource').value,q=document.getElementById('tdQuery').value.trim();
  g.innerHTML='<div class="empty">Suche läuft …</div>';
  try{const r=await fetch('api.php?action=theme_directory&source='+encodeURIComponent(src)+'&q='+encodeURIComponent(q)+'&page='+page,{headers:{'X-AnMaCha-Token':token()}}),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));if(r.status===401)window.cmsSessionExpired?.();if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');dirItems=d.items||[];dirPages=d.pages||1;renderDir()}
  catch(e){g.innerHTML='<div class="empty">'+esc(e.message)+'</div>'}
 }
 function renderDir(){
  const g=document.getElementById('tdGrid'),pg=document.getElementById('tdPager');
  g.innerHTML=dirItems.length?dirItems.map((t,i)=>'<article class="theme-card"><div class="theme-shot">'+(t.thumbnail?'<img loading="lazy" referrerpolicy="no-referrer" src="'+esc(t.thumbnail)+'" alt="">':'<div class="theme-placeholder"><i class="fas fa-brush"></i><span>'+esc(t.name)+'</span></div>')+'</div><div class="theme-copy"><b>'+esc(t.name)+'</b><div class="hint">'+esc(t.author)+' · Lizenz '+esc(t.license)+(t.rating!=null?' · ★ '+esc(t.rating):'')+(t.installs?' · '+esc(t.installs.toLocaleString('de-DE'))+'+ Installationen':'')+'</p><p>'+esc(t.description)+'</p><div style="display:flex;gap:4px;flex-wrap:wrap;margin:6px 0">'+(t.supports||[]).map(x=>'<span style="font-size:11px;padding:2px 7px;border-radius:99px;border:1px solid var(--line,#2a3550);color:var(--dim,#9aa7c0)">'+esc(x)+'</span>').join('')+'</div><div style="display:flex;gap:6px;flex-wrap:wrap">'+(t.preview?'<a class="btn-g" target="_blank" rel="noopener noreferrer" href="'+esc(t.preview)+'"><i class="fas fa-up-right-from-square"></i> Original</a>':'')+(t.installed?'<span class="hint">Bereits installiert</span>':'<button class="btn-a" type="button" onclick="ThemeManager.dirInstall('+i+',this)"><i class="fas fa-download"></i> Installieren &amp; Vorschau</button>')+'</div></div></article>').join(''):'<div class="empty">Keine Themes gefunden.</div>';
  pg.innerHTML=dirPages>1?'<button class="btn-g" type="button" '+(dirPage<=1?'disabled':'')+' onclick="ThemeManager.dirSearch('+(dirPage-1)+')">‹</button><span class="hint">Seite '+dirPage+' / '+dirPages+'</span><button class="btn-g" type="button" '+(dirPage>=dirPages?'disabled':'')+' onclick="ThemeManager.dirSearch('+(dirPage+1)+')">›</button>':'';
 }
 async function dirInstall(i,btn){
  const t=dirItems[i];if(!t)return;
  if(!confirm('Theme „'+t.name+'“ installieren? Es wird nur das Design übernommen (kein PHP/JS). Danach öffnet sich die Live-Vorschau – aktiv wird es erst, wenn du es veröffentlichst.'))return;
  if(btn)btn.disabled=true;
  try{const d=await api('theme_install',{source:t.source,slug:t.slug});await load(true);const idx=themes.findIndex(x=>x.id===d.id);if(idx>=0)customize(idx);dirItems[i].installed=true;renderDir()}
  catch(e){window.cmsToast?.(e.message,true);if(btn)btn.disabled=false}
 }
 let modsSaved=[];
 const LAYOUT_LABELS={sidebar:{right:'Sidebar rechts',left:'Sidebar links',none:'ohne Sidebar'},header:{bar:'Balken-Header',stacked:'gestapelter Header',centered:'zentrierter Header'},hero:{split:'geteilter Hero',full:'voller Hero',compact:'kompakter Hero'},news:{cards:'News als Karten',list:'News als Liste',magazine:'Magazin-Raster'}};
 function layoutSummary(t){const l=t.layout||{};return ['sidebar','header','hero','news'].map(k=>LAYOUT_LABELS[k][l[k]]||'').filter(Boolean).join(' · ')+((t.widget_areas||[]).length?' · '+t.widget_areas.length+' Widget-Bereiche':'');}
 function activeName(){const t=themes.find(x=>x.active);return t?t.name:null;}
 function render(){
  const h=document.getElementById('themeGrid');if(!h)return;
  h.innerHTML=themes.map((t,i)=>'<article class="theme-card '+(t.active?'active':'')+'"><div class="theme-shot">'+(t.screenshot?'<img src="'+esc(t.screenshot)+'?v='+Date.now()+'" alt="">':'<div class="theme-placeholder"><i class="fas fa-brush"></i><span>'+esc(t.name)+'</span></div>')+'</div><div class="theme-copy"><div style="display:flex;justify-content:space-between;gap:8px"><b>'+esc(t.name)+'</b>'+(t.active?'<span class="theme-active">AKTIV</span>':'')+'</div><div class="hint">'+esc(t.description||'')+'</div><div class="hint" style="margin-top:4px;color:var(--dim)"><i class="fas fa-table-columns" style="margin-right:4px"></i>'+esc(layoutSummary(t))+(modsSaved.includes(t.id)&&!t.active?' · <i class="fas fa-floppy-disk"></i> eigene Anpassungen gespeichert':'')+'</div><div class="hint" style="margin-top:4px">'+esc(t.license||'Free Theme')+' · '+(t.compatibility==='wordpress+bootstrap'?'WordPress + Bootstrap':t.wordpress?'WordPress-Kompatibilität':t.bootstrap?'Bootstrap-Kompatibilität':((window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'RicoReWi CMS Theme':RRW_P.name+' Theme'))+'</div><div class="theme-actions"><button class="btn-g" onclick="ThemeManager.customize('+i+')"><i class="fas fa-sliders"></i> Live anpassen</button>'+(t.active?'':'<button class="btn-a" onclick="ThemeManager.activate('+i+')"><i class="fas fa-check"></i> Aktivieren</button>')+(t.builtin?'':'<button class="btn-d" onclick="ThemeManager.remove('+i+')"><i class="fas fa-trash"></i></button>')+'</div></div></article>').join('')||'<div class="empty">Keine Themes vorhanden.</div>';
  if(window.DesignHub)DesignHub.refresh();
 }
 function mergedDraft(t){
  const base={button_style:'gradient',button_radius:String(t.defaults?.radius??16),footer_background:String(t.defaults?.surface??t.defaults?.background??'#101522'),custom_css:'',...(t.defaults||{})};
  if(themeState.active===t.id)Object.assign(base,themeState.settings||{});
  const variant=themeState.active===t.id?(themeState.variant||t.variants?.[0]?.id||'default'):(t.variants?.[0]?.id||'default');
  const v=(t.variants||[]).find(x=>x.id===variant);if(v?.settings)Object.assign(base,v.settings);
  if(themeState.active===t.id)Object.assign(base,themeState.settings||{});
  return {active:t.id,variant,settings:base,layout:t.layout||null};
 }
 function groupedControls(t){
  const map=new Map();
  (t.controls||[]).forEach(c=>{const s=c.section||'Allgemein';if(!map.has(s))map.set(s,[]);map.get(s).push(c)});
  const common=[
   {section:'Buttons',key:'button_radius',label:'Button-Abrundung',type:'range',min:0,max:30,step:1,unit:'px'},
   {section:'Buttons',key:'button_style',label:'Button-Stil',type:'select',options:[['gradient','Gradient'],['solid','Massiv'],['outline','Kontur']]},
   {section:'Footer',key:'footer_background',label:'Footer-Hintergrund',type:'color'},
   {section:'Zusätzliches CSS',key:'custom_css',label:'Eigenes CSS',type:'textarea'}
  ];
  common.forEach(c=>{if(!map.has(c.section))map.set(c.section,[]);if(![...map.values()].flat().some(x=>x.key===c.key))map.get(c.section).push(c)});
  return [...map.entries()];
 }
 function renderControl(ctrl){
  const val=draft.settings?.[ctrl.key] ?? editing.defaults?.[ctrl.key] ?? '';
  if(ctrl.type==='color')return '<div class="customizer-control"><label><span>'+esc(ctrl.label)+'</span><span class="control-value" data-value-for="'+esc(ctrl.key)+'">'+esc(val)+'</span></label><div class="customizer-color"><input type="color" value="'+esc(/^#[0-9a-f]{6}$/i.test(String(val))?val:'#777777')+'" oninput="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value)"><input class="fc" value="'+esc(val)+'" oninput="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value)"></div></div>';
  if(ctrl.type==='range')return '<div class="customizer-control"><label><span>'+esc(ctrl.label)+'</span><span class="control-value" data-value-for="'+esc(ctrl.key)+'">'+esc(val)+(ctrl.unit||'')+'</span></label><input type="range" min="'+esc(ctrl.min)+'" max="'+esc(ctrl.max)+'" step="'+esc(ctrl.step||1)+'" value="'+esc(val)+'" oninput="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value,\''+esc(ctrl.unit||'')+'\')"></div>';
  if(ctrl.type==='select')return '<div class="customizer-control"><label><span>'+esc(ctrl.label)+'</span></label><select class="fc w-100" onchange="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value)">'+(ctrl.options||[]).map(o=>{const a=Array.isArray(o)?o:[o,o];return '<option value="'+esc(a[0])+'" '+(String(a[0])===String(val)?'selected':'')+'>'+esc(a[1])+'</option>'}).join('')+'</select></div>';
  if(ctrl.type==='textarea')return '<div class="customizer-control"><label><span>'+esc(ctrl.label)+'</span><span class="control-value">nur CSS</span></label><textarea class="fc w-100" rows="8" spellcheck="false" oninput="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value)">'+esc(val)+'</textarea><div class="hint" style="margin-top:5px">Wird nur als CSS gespeichert; @import und &lt;style&gt;-Tags werden serverseitig entfernt.</div></div>';
  return '<div class="customizer-control"><label>'+esc(ctrl.label)+'</label><input class="fc w-100" value="'+esc(val)+'" oninput="ThemeManager.change(\''+esc(ctrl.key)+'\',this.value)"></div>';
 }
 function renderCustomizer(){
  if(!editing||!draft)return;
  document.getElementById('themeCustomizerName').textContent=editing.name;
  const h=document.getElementById('themeCustomizerControls');
  const variants=(editing.variants||[]);
  let html='';
  if(variants.length){
    html+='<div class="customizer-section"><button type="button" onclick="this.parentElement.classList.toggle(\'closed\')"><span>Varianten</span><i class="fas fa-chevron-up"></i></button><div class="customizer-section-body"><div class="theme-variants">'+variants.map(v=>'<button class="theme-variant '+(draft.variant===v.id?'on':'')+'" onclick="ThemeManager.variant(\''+esc(v.id)+'\')">'+esc(v.name||v.id)+'</button>').join('')+'</div></div></div>';
  }
  groupedControls(editing).forEach(([name,controls])=>{
    html+='<div class="customizer-section"><button type="button" onclick="this.parentElement.classList.toggle(\'closed\')"><span>'+esc(name)+'</span><i class="fas fa-chevron-up"></i></button><div class="customizer-section-body">'+controls.map(renderControl).join('')+'</div></div>';
  });
  if(!(editing.controls||[]).length)html+='<div class="empty">Dieses importierte CSS-Theme besitzt noch keine eigenen Customizer-Regler. Es kann trotzdem live angesehen und aktiviert werden.</div>';
  h.innerHTML=html;
 }
 function previewPayload(){
  try{sessionStorage.setItem('rrw_theme_customizer',JSON.stringify(draft))}catch(e){}
  const f=document.getElementById('themeCustomizerFrame');
  if(f?.contentWindow)f.contentWindow.postMessage({type:'rrw-theme-customizer-update',theme:draft},location.origin);
  const st=document.getElementById('themeCustomizerState');if(st)st.textContent='Nicht veröffentlicht';
 }
 function customize(i){
  window.WpThemes?.czLeave?.();   // ein offener WordPress-Customizer wird zuerst beendet
  editing=themes[i];if(!editing)return;
  draft=mergedDraft(editing);
  renderCustomizer();
  const shell=document.getElementById('themeCustomizer');shell.style.display='grid';
  document.body.style.overflow='hidden';
  try{sessionStorage.setItem('rrw_theme_customizer',JSON.stringify(draft))}catch(e){}
  const f=document.getElementById('themeCustomizerFrame');
  previewBrandSelect();
  reloadPreviewFrame();
 }
 // Vorschau als Marke: dasselbe Theme lässt sich mit jeder Marke (RicoReWi Radio, SenderWelt, ...) prüfen.
 let previewBrand='';
 function previewBrandSelect(){
  const sel=document.getElementById('themePreviewBrand');if(!sel)return;
  const items=(window.CMS?.brands?.items||[]).filter(b=>b.enabled!==false),def=window.CMS?.brands?.default||'ricorewi-radio';
  if(!previewBrand)previewBrand=def;
  sel.innerHTML=items.map(b=>'<option value="'+b.id+'" '+(b.id===previewBrand?'selected':'')+'>'+String(b.name||b.id).replace(/</g,'&lt;')+'</option>').join('')||'<option value="">Standard</option>';
 }
 function reloadPreviewFrame(){
  if(!editing)return;
  const f=document.getElementById('themeCustomizerFrame');
  f.src='/?cms_theme_customize=1&cms_theme_preview='+encodeURIComponent(editing.id)+(previewBrand?'&rrw_brand='+encodeURIComponent(previewBrand):'')+'&t='+Date.now();
  const b=(window.CMS?.brands?.items||[]).find(x=>x.id===previewBrand);
  document.getElementById('themePreviewUrl').textContent=(b?.primary_domain||((window.CMS_PACKS&&window.CMS_PACKS['ricorewi-radio'])?'ricorewi-radio.de':location.hostname))+' · '+editing.name+(b?' · '+b.name:'');
 }
 function brand(id){previewBrand=id;reloadPreviewFrame();}
 function change(key,value,unit=''){
  if(!draft)return;draft.settings=draft.settings||{};draft.settings[key]=value;
  document.querySelectorAll('[data-value-for="'+CSS.escape(key)+'"]').forEach(x=>x.textContent=value+unit);
  previewPayload();
 }
 function variant(id){
  if(!editing||!draft)return;const v=(editing.variants||[]).find(x=>x.id===id);draft.variant=id;
  draft.settings={...(editing.defaults||{}),...(v?.settings||{})};
  renderCustomizer();previewPayload();
 }
 function resetCustomizer(){
  if(window.WpThemes?.czActive?.())return WpThemes.czReset();
  if(!editing)return;const v=editing.variants?.[0],defaults={button_style:'gradient',button_radius:String(editing.defaults?.radius??16),footer_background:String(editing.defaults?.surface??editing.defaults?.background??'#101522'),custom_css:'',...(editing.defaults||{})};draft={active:editing.id,variant:v?.id||'default',settings:{...defaults,...(v?.settings||{})},layout:editing.layout||null};renderCustomizer();previewPayload();
 }
 function closeCustomizer(){
  if(window.WpThemes?.czActive?.())return WpThemes.czClose();
  document.getElementById('themeCustomizer').style.display='none';document.body.style.overflow='';
  try{sessionStorage.removeItem('rrw_theme_customizer')}catch(e){};editing=null;draft=null;
 }
 function device(mode,btn){
  document.querySelectorAll('.theme-customizer-devices .btn-g').forEach(x=>x.classList.remove('on'));btn?.classList.add('on');
  const b=document.querySelector('.theme-preview-browser');b?.classList.remove('tablet','mobile');if(mode!=='desktop')b?.classList.add(mode);
 }
 async function publishCustomizer(){
  if(window.WpThemes?.czActive?.())return WpThemes.czPublish();
  if(!editing||!draft)return;
  try{
    const d=await api('theme_customize_save',{id:editing.id,variant:draft.variant,settings:draft.settings});
    themeState=d.theme||draft;window.cmsToast?.('Theme aktiviert und veröffentlicht ✓');
    const st=document.getElementById('themeCustomizerState');if(st)st.textContent='Veröffentlicht';
    await load(true);previewPayload();window.cmsReload?.();
  }catch(e){window.cmsToast?.(e.message,true)}
 }
 function preview(i){customize(i)}
 async function activate(i){
  const t=themes[i];if(!t)return;
  const msg=modsSaved.includes(t.id)?'Theme „'+t.name+'“ aktivieren? Deine gespeicherten Anpassungen und die Widget-Anordnung dieses Themes werden wiederhergestellt.':'Theme „'+t.name+'“ aktivieren? Es bringt sein eigenes Layout'+((t.widget_areas||[]).length?' und eine eigene Widget-Anordnung':'')+' mit. Die Anpassungen am aktuellen Theme bleiben gespeichert.';
  if(!confirm(msg))return;
  try{const d=await api('theme_activate',{id:t.id});themeState=d.theme||{active:t.id,variant:'default',settings:{}};window.cmsToast?.('Theme aktiviert ✓');await load(true);window.cmsReload?.();}catch(e){window.cmsToast?.(e.message,true)}
 }
 async function remove(i){
  const t=themes[i];if(!t||t.active||t.builtin)return;if(!confirm('Theme „'+t.name+'“ wirklich löschen?'))return;
  try{await api('theme_delete',{id:t.id});window.cmsToast?.('Theme gelöscht');await load(true)}catch(e){window.cmsToast?.(e.message,true)}
 }
 async function load(force=false){
  bind();
  try{const d=await api('themes_list');themes=d.themes||[];modsSaved=Array.isArray(d.mods_saved)?d.mods_saved:[];themeState=d.theme_state||{active:d.active||'ricorewi-neon',variant:'default',settings:{}};render()}
  catch(e){const h=document.getElementById('themeGrid');if(h)h.innerHTML='<div class="empty">'+esc(e.message)+'</div>'}
 }
 return {dirSearch,dirInstall,load,preview,customize,activate,remove,change,variant,resetCustomizer,closeCustomizer,publishCustomizer,device,activeName,brand,refreshBrandSelect:previewBrandSelect};
})();