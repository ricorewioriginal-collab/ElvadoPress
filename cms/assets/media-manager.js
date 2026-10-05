'use strict';
window.MediaHub=(()=>{
 let items=[],selected=null,brandingPick=null;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#39;'}[c]));
 const token=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
 const BRAND_LABELS={
  portal_logo:'Portal-Logo',portal_icon:'Portal-Icon',favicon:'Favicon',
  android_inapp_logo:'Android In-App-Logo',android_startscreen:'Android Startscreen',
  android_app_icon:'Android App-Icon',windows_logo:'Windows App-Logo'
 };
 function usageText(item){const use=Array.isArray(item?.used_as)?item.used_as:[];return use.map(x=>BRAND_LABELS[x]||x).join(', ')}

 async function api(action,body){
  const opt=body===undefined?{headers:{'X-AnMaCha-Token':token()}}:{method:'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':token()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opt);
  const d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 function bind(){
  const up=document.getElementById('mediaHubUpload'),drop=document.getElementById('mediaHubDrop'),search=document.getElementById('mediaHubSearch'),type=document.getElementById('mediaHubType');
  if(up&&!up.dataset.bound){up.dataset.bound='1';up.addEventListener('change',()=>uploadFiles([...up.files]));}
  if(drop&&!drop.dataset.bound){
   drop.dataset.bound='1';
   ['dragenter','dragover'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('dragover')}));
   ['dragleave','drop'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('dragover')}));
   drop.addEventListener('drop',e=>uploadFiles([...e.dataTransfer.files]));
  }
  if(search&&!search.dataset.bound){search.dataset.bound='1';search.addEventListener('input',render);}
  if(type&&!type.dataset.bound){type.dataset.bound='1';type.addEventListener('change',render);}
 }
 function uploadConfig(){
  const sizes=(document.getElementById('mediaHubSizes')?.value||'64,128,192,256,512,1024,1600').trim();
  const quality=Math.max(45,Math.min(100,parseInt(document.getElementById('mediaHubQuality')?.value||'86',10)||86));
  return {sizes,quality};
 }
 async function uploadOne(file){
  const cfg=uploadConfig(),fd=new FormData();fd.append('file',file);fd.append('sizes',cfg.sizes);fd.append('quality',String(cfg.quality));
  const r=await fetch('api.php?action=media_library_upload&_='+Date.now(),{method:'POST',headers:{'X-AnMaCha-Token':token()},body:fd});
  const d=await r.json().catch(()=>({status:'error'}));
  if(!r.ok||d.status!=='ok')throw new Error(d.message||('Upload fehlgeschlagen: '+file.name));
  return d;
 }
 async function uploadFiles(files){
  if(!files.length)return;
  let ok=0,warnings=[];
  for(const file of files){
   try{const d=await uploadOne(file);ok++;if(Array.isArray(d.warnings))warnings.push(...d.warnings);}
   catch(e){window.cmsToast?.(e.message||('Upload fehlgeschlagen: '+file.name),true);}
  }
  if(ok)window.cmsToast?.(ok+' Medium'+(ok===1?'':'en')+' hochgeladen'+(warnings.length?' · '+warnings.join(' | '):'')+' ✓',!!warnings.length);
  await load(true);
 }
 function formatBytes(n){n=+n||0;if(n<1024)return n+' B';if(n<1048576)return (n/1024).toFixed(1)+' KB';return (n/1048576).toFixed(1)+' MB'}
 function render(){
  const host=document.getElementById('mediaHubGrid');if(!host)return;
  const q=(document.getElementById('mediaHubSearch')?.value||'').toLowerCase(),type=document.getElementById('mediaHubType')?.value||'';
  const list=items.filter(x=>(!q||(String(x.name)+' '+String(x.url)+' '+String(x.bucket||'')).toLowerCase().includes(q))&&(!type||(type==='image'?String(x.mime||'').startsWith('image/'):x.bucket===type)));
  host.innerHTML=list.length?list.map(x=>{
   const idx=items.indexOf(x),vars=Array.isArray(x.variants)?x.variants.length:0,dim=x.width&&x.height?(x.width+'×'+x.height):'';
   return '<button class="media-tile" onclick="MediaHub.select('+idx+')"><span class="media-thumb">'+(x.url?'<img src="'+esc(x.url)+'?m='+encodeURIComponent(x.mtime||'')+'" alt="">':'<i class="fas fa-file"></i>')+'</span><span class="media-name">'+esc(x.name)+'</span><span class="media-meta">'+esc(x.bucket||'library')+(dim?' · '+esc(dim):'')+(vars?' · '+vars+' Größen':'')+(usageText(x)?' · verwendet als '+esc(usageText(x)):'')+'</span></button>';
  }).join(''):'<div class="empty" style="grid-column:1/-1">Keine passenden Medien.</div>';
 }
 function variantOptions(item){
  const vars=Array.isArray(item.variants)?item.variants:[];
  let html='<option value="auto">Automatisch passend</option><option value="original">Original</option>';
  vars.forEach(v=>html+='<option value="'+Number(v.width||0)+'">'+Number(v.width||0)+' px · '+esc(v.format||'webp')+' · '+formatBytes(v.size||0)+'</option>');
  return html;
 }
 function variantList(item){
  const vars=Array.isArray(item.variants)?item.variants:[];
  if(!vars.length)return '<div class="hint">Keine Größenvarianten vorhanden. Original wird verwendet.</div>';
  return '<div style="display:flex;gap:6px;flex-wrap:wrap">'+vars.map(v=>'<button class="btn-g" style="padding:6px 9px" onclick="MediaHub.copyVariant('+Number(v.width||0)+')">'+Number(v.width||0)+' px</button>').join('')+'</div>';
 }
 function brandingPickerHtml(item){
  if(!brandingPick)return '';
  return '<div class="card" style="margin-top:12px;background:rgba(47,184,255,.07);border-color:rgba(47,184,255,.28)"><div class="tt"><i class="fas fa-palette"></i>Als '+esc(BRAND_LABELS[brandingPick]||brandingPick)+' verwenden</div><div class="hint" style="margin:5px 0 8px">Wähle eine Variante oder lasse das CMS automatisch die passende Größe bestimmen.</div><div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap"><select id="mediaBrandSize" class="fc">'+variantOptions(item)+'</select><button class="btn-a" onclick="MediaHub.assignBranding()"><i class="fas fa-check"></i> Übernehmen</button><button class="btn-g" onclick="MediaHub.cancelBrandingPick()">Abbrechen</button></div></div>';
 }
 function select(i){
  selected=items[i];const h=document.getElementById('mediaHubDetail');if(!selected||!h)return;
  const original=selected.original?.url||selected.url||'';
  h.style.display='';
  h.innerHTML='<div class="th"><div><div class="tt"><i class="fas fa-photo-film"></i>'+esc(selected.name)+'</div><div class="hint">'+esc(selected.mime||'')+' · '+formatBytes(selected.size)+(selected.width&&selected.height?' · '+selected.width+'×'+selected.height:'')+'</div></div><button class="btn-d" onclick="MediaHub.remove()"><i class="fas fa-trash"></i> Löschen</button></div>'+
   '<div class="media-detail"><div class="asset-preview" style="height:220px">'+(original?'<img src="'+esc(original)+'?m='+encodeURIComponent(selected.mtime||'')+'" alt="">':'')+'</div><div>'+
   '<label class="news-lbl">Original-URL</label><div style="display:flex;gap:6px"><input id="mediaHubUrl" class="fc w-100" readonly value="'+esc(original)+'"><button class="btn-g" onclick="navigator.clipboard.writeText(document.getElementById(\'mediaHubUrl\').value);cmsToast(\'URL kopiert ✓\')"><i class="fas fa-copy"></i></button></div>'+
   (usageText(selected)?'<div class="danger-note" style="margin:10px 0"><i class="fas fa-link"></i> Verwendet als: <b>'+esc(usageText(selected))+'</b></div>':'')+
   '<label class="news-lbl" style="margin-top:10px">Erzeugte Größen</label>'+variantList(selected)+
   '<p class="hint" style="margin-top:8px">Original und Varianten gehören zu einem Medium. Die Website kann für Branding automatisch die passende Größe wählen.</p></div></div>'+
   brandingPickerHtml(selected);
 }
 function copyVariant(width){
  if(!selected)return;const v=(selected.variants||[]).find(x=>Number(x.width)===Number(width));const url=v?.url||selected.original?.url||selected.url||'';if(url){navigator.clipboard.writeText(url);window.cmsToast?.(width+'-px-URL kopiert ✓');}
 }
 async function remove(){
  if(!selected||!confirm('Medium „'+selected.name+'“ wirklich löschen? Original und alle erzeugten Größen werden entfernt. Bereits verwendete URLs können danach ins Leere zeigen.'))return;
  await api('media_library_delete',{path:selected.path});selected=null;const h=document.getElementById('mediaHubDetail');if(h)h.style.display='none';window.cmsToast?.('Medium samt Varianten gelöscht');await load(true);
 }
 async function assignBranding(){
  if(!selected||!brandingPick)return;
  const size=document.getElementById('mediaBrandSize')?.value||'auto';
  // Marken-Assets (Domains & Branding): "brand:<id>:<kind>" geht an brand_asset_assign
  if(String(brandingPick).startsWith('brand:')){
   const [,brandId,kind]=String(brandingPick).split(':');
   try{await window.BrandsManager?.assign(brandId,kind,selected.path,size);brandingPick=null;select(items.indexOf(selected));const btn=document.querySelector('.tab[data-tab="brands"]');if(btn&&typeof window.cmsTab==='function')window.cmsTab('brands',btn);}
   catch(e){window.cmsToast?.(e.message,true);}
   return;
  }
  try{
   const d=await api('branding_assign',{kind:brandingPick,path:selected.path,size});
   if(typeof window.cmsReload==='function')await window.cmsReload();
   else if(typeof window.renderBranding==='function')window.renderBranding();
   const warnings=Array.isArray(d.warnings)&&d.warnings.length?' · '+d.warnings.join(' | '):'';
   window.cmsToast?.((BRAND_LABELS[brandingPick]||brandingPick)+' aktualisiert ✓'+warnings,!!warnings);
   brandingPick=null;select(items.indexOf(selected));
  }catch(e){window.cmsToast?.(e.message,true);}
 }
 function beginBrandingPick(kind){
  brandingPick=kind;
  const btn=document.querySelector('.tab[data-tab="media"]');if(btn&&typeof window.cmsTab==='function')window.cmsTab('media',btn);
  const label=String(kind).startsWith('brand:')?'Marke „'+kind.split(':')[1]+'“ · '+kind.split(':')[2]:(BRAND_LABELS[kind]||kind);
  load(true).then(()=>{const h=document.getElementById('mediaHubDetail');if(h){h.style.display='';h.innerHTML='<div class="empty"><i class="fas fa-photo-film"></i>Wähle links ein Medium für <b>'+esc(label)+'</b>.</div>';}}); 
 }
 function cancelBrandingPick(){brandingPick=null;if(selected)select(items.indexOf(selected));}
 async function load(force=false){bind();try{const d=await api('media_library_list');items=d.items||[];render()}catch(e){const h=document.getElementById('mediaHubGrid');if(h)h.innerHTML='<div class="empty" style="grid-column:1/-1">'+esc(e.message)+'</div>'}}
 return {load,select,remove,copyVariant,assignBranding,beginBrandingPick,cancelBrandingPick,uploadFiles};
})();