'use strict';
window.MediaHub=(()=>{
 let items=[],selected=null,brandingPick=null;
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot',"'":'&#39;'}[c]));
 const token=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const BRAND_LABELS={
  portal_logo:'Portal-Logo',portal_icon:'Portal-Icon',favicon:'Favicon',
  android_inapp_logo:'Android In-App-Logo',android_startscreen:'Android Startscreen',
  android_app_icon:'Android App-Icon',windows_logo:'Windows App-Logo'
 };
 function usageText(item){const use=Array.isArray(item?.used_as)?item.used_as:[];return use.map(x=>BRAND_LABELS[x]||x).join(', ')}

 async function api(action,body){
  const opt=body===undefined?{headers:{'X-ElvadoPress-Token':token()}}:{method:'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':token()},body:JSON.stringify(body)};
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
  const r=await fetch('api.php?action=media_library_upload&_='+Date.now(),{method:'POST',headers:{'X-ElvadoPress-Token':token()},body:fd});
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
   (isLib(selected)?'<label class="news-lbl" style="margin-top:10px">Alternativtext (Alt-Text)</label><div style="display:flex;gap:6px"><input id="mediaHubAlt" class="fc w-100" maxlength="250" placeholder="Kurz beschreiben, was zu sehen ist" value="'+esc(selected.alt||'')+'"><button class="btn-g" id="mediaHubAltAi" onclick="MediaHub.altSuggest()" title="Die KI beschreibt das Bild"><i class="fas fa-wand-magic-sparkles"></i> KI-Vorschlag</button><button class="btn-a" onclick="MediaHub.altSave()"><i class="fas fa-floppy-disk"></i></button></div><div class="hint" style="margin-top:4px">Wichtig für Barrierefreiheit und Suchmaschinen. Leer lassen für rein dekorative Bilder.</div>':'')+
   (selected.credit?'<div class="hint" style="margin:10px 0"><i class="fas fa-copyright"></i> Bildnachweis: <b>'+esc(selected.credit.text||'')+'</b>'+(selected.credit.license?' · '+esc(selected.credit.license):'')+(selected.credit.source_url?' · <a href="'+esc(selected.credit.source_url)+'" target="_blank" rel="noopener">Quelle</a>':'')+'</div>':'')+
   (usageText(selected)?'<div class="danger-note" style="margin:10px 0"><i class="fas fa-link"></i> Verwendet als: <b>'+esc(usageText(selected))+'</b></div>':'')+
   '<label class="news-lbl" style="margin-top:10px">Erzeugte Größen</label>'+variantList(selected)+
   '<p class="hint" style="margin-top:8px">Original und Varianten gehören zu einem Medium. Die Website kann für Branding automatisch die passende Größe wählen.</p></div></div>'+
   brandingPickerHtml(selected);
 }

 const isLib=x=>!!x&&/^library\/[A-Za-z0-9_-]+$/.test(String(x.path||''))&&/^image\/(jpeg|png|webp|gif)$/.test(String(x.mime||''));
 async function altSave(){
  if(!selected)return;const v=document.getElementById('mediaHubAlt')?.value||'';
  try{const d=await api('media_alt_save',{id:selected.id,alt:v});selected.alt=d.alt;const it=items.find(x=>x.id===selected.id);if(it)it.alt=d.alt;window.cmsToast?.('Alt-Text gespeichert ✓');renderAltBar();}catch(e){window.cmsToast?.(e.message,true);}
 }
 async function altSuggest(){
  if(!selected)return;const b=document.getElementById('mediaHubAltAi');if(b){b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> …';}
  try{const d=await api('ai_alt_suggest',{id:selected.id});const f=document.getElementById('mediaHubAlt');if(f)f.value=d.alt||'';window.cmsToast?.('Vorschlag eingetragen – bitte prüfen und speichern'+(d.mode==='text'?' (ohne Blick aufs Bild)':''));}
  catch(e){window.cmsToast?.(e.message,true);}
  if(b){b.disabled=false;b.innerHTML='<i class="fas fa-wand-magic-sparkles"></i> KI-Vorschlag';}
 }
 // Sammelvorschlag: Bilder ohne Alt-Text der Reihe nach von der KI beschreiben lassen, prüfen, gemeinsam speichern
 let bulk=null;
 function missingAlt(){return items.filter(x=>isLib(x)&&!(x.alt||'').trim());}
 function renderAltBar(){
  const h=document.getElementById('mediaAltBar');if(!h)return;const n=missingAlt().length;
  h.innerHTML=n?'<i class="fas fa-universal-access"></i> <b>'+n+' Bild'+(n===1?'':'er')+' ohne Alt-Text.</b> <button class="btn-g" onclick="MediaHub.altBulkStart()"><i class="fas fa-wand-magic-sparkles"></i> Alt-Texte per KI vorschlagen</button>':'';
  h.style.display=n?'':'none';
 }
 function altBulkStart(){
  const list=missingAlt().slice(0,40);if(!list.length)return;
  bulk={rows:list.map(x=>({id:x.id,url:x.url,name:x.name,alt:'',state:'wait'})),running:true,stop:false};altBulkDraw();altBulkRun();
 }
 function altBulkDraw(){
  const h=document.getElementById('mediaAltBulk');if(!h)return;if(!bulk){h.style.display='none';h.innerHTML='';return}
  h.style.display='';
  h.innerHTML='<div class="th"><div class="tt"><i class="fas fa-universal-access"></i>Alt-Texte per KI vorschlagen</div><div style="display:flex;gap:8px"><button class="btn-g" onclick="MediaHub.altBulkClose()">Schließen</button><button class="btn-a" onclick="MediaHub.altBulkSave()"><i class="fas fa-floppy-disk"></i> Alle gefüllten speichern</button></div></div>'
   +'<div class="hint" style="margin-bottom:8px">Prüfe die Vorschläge und passe sie an – gespeichert wird erst mit „Alle gefüllten speichern“. Bei Anbietern ohne Bildverständnis entstehen Vorschläge nur aus Titel und Dateiname.</div>'
   +bulk.rows.map((r,i)=>'<div class="aib-item" style="display:flex;gap:10px;align-items:center"><img src="'+esc(r.url)+'" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:8px"><div style="flex:1;min-width:0"><div class="hint" style="font-size:.66rem">'+esc(r.name)+'</div><input class="fc w-100" maxlength="250" value="'+esc(r.alt)+'" oninput="MediaHub.altBulkSet('+i+',this.value)" placeholder="'+(r.state==='run'?'Die KI beschreibt …':(r.state==='wait'?'wartet …':(r.state==='err'?'':'Alt-Text')))+'">'+(r.state==='err'?'<div class="aic-bad" style="font-size:.7rem">'+esc(r.err||'Fehler')+'</div>':'')+'</div></div>').join('');
 }
 async function altBulkRun(){
  for(let i=0;bulk&&i<bulk.rows.length&&!bulk.stop;i++){
   const r=bulk.rows[i];r.state='run';altBulkDraw();
   try{const d=await api('ai_alt_suggest',{id:r.id});r.alt=d.alt||'';r.state='ok';}catch(e){r.state='err';r.err=e.message;if(/Anbieter|Bildverständnis|Limit|zu viele/i.test(e.message)){for(let j=i+1;j<bulk.rows.length;j++){bulk.rows[j].state='err';bulk.rows[j].err='übersprungen';}altBulkDraw();break;}}
   altBulkDraw();
  }
  if(bulk)bulk.running=false;
 }
 function altBulkSet(i,v){if(bulk&&bulk.rows[i])bulk.rows[i].alt=v;}
 function altBulkClose(){if(bulk)bulk.stop=true;bulk=null;altBulkDraw();}
 async function altBulkSave(){
  if(!bulk)return;let ok=0;
  for(const r of bulk.rows){if(!(r.alt||'').trim())continue;try{await api('media_alt_save',{id:r.id,alt:r.alt});const it=items.find(x=>x.id===r.id);if(it)it.alt=r.alt;ok++;}catch(e){window.cmsToast?.(e.message,true);}}
  window.cmsToast?.(ok+' Alt-Text'+(ok===1?'':'e')+' gespeichert ✓');bulk.stop=true;bulk=null;altBulkDraw();renderAltBar();
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
 async function load(force=false){bind();try{const d=await api('media_library_list');items=d.items||[];render();renderAltBar()}catch(e){const h=document.getElementById('mediaHubGrid');if(h)h.innerHTML='<div class="empty" style="grid-column:1/-1">'+esc(e.message)+'</div>'}}
 /** Datei aus der Mediathek wählen (z. B. für Widget-Felder): cb(url) bekommt die Adresse. kind: 'image' (Standard) oder 'audio'; erkannt am MIME-Typ oder, wo dieser fehlt, an der Dateiendung. */
 async function pick(cb,kind){
  const isAudio=kind==='audio',extRe=isAudio?/\.(mp3|ogg|oga|wav|m4a|aac|flac|opus)(\?|$)/i:/\.(png|jpe?g|gif|webp|avif|svg)(\?|$)/i;
  const wanted=x=>String(x.mime||'').startsWith(isAudio?'audio/':'image/')||extRe.test(String(x.url||x.name||''));
  let modal=document.getElementById('mediaHubPick');
  if(modal)modal.remove();
  modal=document.createElement('div');modal.id='mediaHubPick';
  modal.style.cssText='position:fixed;inset:0;background:rgba(6,6,10,.7);z-index:3000;display:flex;align-items:center;justify-content:center;padding:20px';
  modal.innerHTML='<div role="dialog" aria-label="Aus Mediathek wählen" style="background:var(--surface,#101522);border:1px solid var(--border,#232b45);border-radius:16px;max-width:760px;width:100%;max-height:82vh;display:flex;flex-direction:column;overflow:hidden"><div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid var(--border,#232b45)"><b>Aus Mediathek wählen</b><button type="button" class="btn-g" id="mediaHubPickX" aria-label="Schließen">&times;</button></div><div id="mediaHubPickGrid" style="padding:16px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:10px"><div class="empty">Lädt…</div></div></div>';
  const close=()=>modal.remove();
  modal.addEventListener('click',e=>{if(e.target===modal)close();});
  document.body.appendChild(modal);
  modal.querySelector('#mediaHubPickX').addEventListener('click',close);
  const grid=modal.querySelector('#mediaHubPickGrid');
  try{
   const d=await api('media_library_list');
   const list=(d.items||[]).filter(wanted);
   grid.innerHTML=list.length?'':'<div class="empty" style="grid-column:1/-1">'+(isAudio?'Noch keine Audio-Dateien':'Noch keine Bilder')+' in der Mediathek. Lade zuerst eine im Tab „Medien“ hoch.</div>';
   list.forEach(x=>{
    const url=x.original?.url||x.url||'';if(!url)return;
    const b=document.createElement('button');b.type='button';b.title=x.name||'';
    b.style.cssText='border:1px solid var(--border,#232b45);border-radius:10px;padding:6px;background:#0b0f26;cursor:pointer';
    if(isAudio){b.textContent=x.name||url.split('/').pop();b.style.cssText+=';color:inherit;font-size:12px;word-break:break-all;padding:10px';}
    else{const img=document.createElement('img');img.src=url;img.alt=x.name||'';img.loading='lazy';img.style.cssText='width:100%;height:80px;object-fit:cover;border-radius:6px';b.appendChild(img);}
    b.addEventListener('click',()=>{close();try{cb(url);}catch(e){window.cmsToast?.(e.message,true);}});
    grid.appendChild(b);
   });
  }catch(e){grid.textContent=e.message||'Fehler';}
 }
 return {pick,load,select,remove,copyVariant,assignBranding,beginBrandingPick,cancelBrandingPick,uploadFiles,altSave,altSuggest,altBulkStart,altBulkSet,altBulkClose,altBulkSave};
})();