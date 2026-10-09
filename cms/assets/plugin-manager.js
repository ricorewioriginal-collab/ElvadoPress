'use strict';
window.RRWCmsPluginAPI=window.RRWCmsPluginAPI||(()=>{
 const listeners=new Map();
 const on=(name,fn)=>{if(typeof fn!=='function')return()=>{};const a=listeners.get(name)||[];a.push(fn);listeners.set(name,a);return()=>listeners.set(name,(listeners.get(name)||[]).filter(x=>x!==fn));};
 const emit=(name,payload)=>{(listeners.get(name)||[]).slice().forEach(fn=>{try{fn(payload);}catch(e){console.warn('RRW CMS plugin hook',name,e);}});};
 return {version:'1.0',on,emit,getConfig:()=>window.CMS||null,refresh:()=>window.cmsReload?.()};
})();
const __loadedAdminPlugins=new Set();
function loadAdminPluginScripts(plugins){
 (plugins||[]).filter(p=>p.active&&p.admin_js).forEach(p=>{
  if(__loadedAdminPlugins.has(p.id))return;__loadedAdminPlugins.add(p.id);
  const s=document.createElement('script');s.src=p.admin_js+'?v='+Date.now();s.defer=true;s.onload=()=>RRWCmsPluginAPI.emit('plugin:admin-loaded',{id:p.id});s.onerror=()=>s.remove();document.body.appendChild(s);
 });
}
window.PluginManager=(()=>{
 let plugins=[];
 const token=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function api(action,body){
  const opts=body===undefined?{headers:{'X-ElvadoPress-Token':token()}}:{method:'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':token()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opts);
  const d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 function card(p){
  const del=p.active?'':'<button class="btn-d" data-plugin-delete="'+esc(p.id)+'"><i class="fas fa-trash"></i> Löschen</button>';
  return '<article class="plugin-card '+(p.active?'on':'')+'">'+
   '<div class="th"><div><div class="tt"><i class="fas fa-plug"></i>'+esc(p.name)+'</div><div class="hint">v'+esc(p.version)+' · '+esc(p.author||'Unbekannt')+'</div></div><span class="plugin-state '+(p.active?'on':'')+'">'+(p.active?'Aktiv':'Inaktiv')+'</span></div>'+
   '<p class="hint">'+esc(p.description||'Keine Beschreibung.')+'</p>'+
   '<div class="plugin-meta"><span><i class="fas fa-code"></i> '+(p.frontend_js?'Frontend JS':'')+(p.frontend_css?' + CSS':'')+'</span><span><i class="fas fa-anchor"></i> '+((p.hooks||[]).length)+' Hooks</span><span><i class="fas fa-scale-balanced"></i> '+esc(p.license||'–')+'</span></div>'+
   '<div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:12px"><button class="'+(p.active?'btn-d':'btn-a')+'" data-plugin-toggle="'+esc(p.id)+'" data-enabled="'+(p.active?'0':'1')+'"><i class="fas '+(p.active?'fa-pause':'fa-play')+'"></i> '+(p.active?'Deaktivieren':'Aktivieren')+'</button>'+del+'</div></article>';
 }
 function bindCards(){
  document.querySelectorAll('[data-plugin-toggle]').forEach(b=>b.addEventListener('click',()=>toggle(b.dataset.pluginToggle,b.dataset.enabled==='1')));
  document.querySelectorAll('[data-plugin-delete]').forEach(b=>b.addEventListener('click',()=>remove(b.dataset.pluginDelete)));
 }
 function render(){const host=document.getElementById('pluginGrid');if(!host)return;host.innerHTML=plugins.length?plugins.map(card).join(''):'<div class="empty" style="grid-column:1/-1"><i class="fas fa-plug"></i>Noch keine Plugins installiert.</div>';bindCards();}
 async function load(){try{const d=await api('plugins_list');plugins=d.plugins||[];render();loadAdminPluginScripts(plugins);RRWCmsPluginAPI.emit('plugins:loaded',{plugins});}catch(e){const h=document.getElementById('pluginGrid');if(h)h.innerHTML='<div class="empty" style="grid-column:1/-1;color:var(--bad)">'+esc(e.message)+'</div>';}}
 async function upload(file){if(!file)return;const fd=new FormData();fd.append('file',file);try{const r=await fetch('api.php?action=plugin_upload&_='+Date.now(),{method:'POST',headers:{'X-ElvadoPress-Token':token()},body:fd});const d=await r.json();if(!r.ok||d.status!=='ok')throw new Error(d.message||'Upload fehlgeschlagen');window.cmsToast?.('Plugin installiert ✓');await load();}catch(e){window.cmsToast?.(e.message,true)}}
 async function toggle(id,enabled){try{await api('plugin_toggle',{id,enabled});window.cmsToast?.(enabled?'Plugin aktiviert ✓':'Plugin deaktiviert');await load();}catch(e){window.cmsToast?.(e.message,true)}}
 async function remove(id){if(!confirm('Plugin wirklich löschen?'))return;try{await api('plugin_delete',{id});window.cmsToast?.('Plugin gelöscht');await load();}catch(e){window.cmsToast?.(e.message,true)}}
 function bind(){const f=document.getElementById('pluginUpload');if(f&&!f.dataset.bound){f.dataset.bound='1';f.addEventListener('change',()=>{upload(f.files?.[0]);f.value='';});}}
 async function devInfo(){
  const h=document.getElementById('plDev');if(!h||h.dataset.done)return;
  try{const d=await api('api_docs');h.dataset.done='1';
   h.innerHTML='<div><b>Hooks für Portal-Plugins:</b> '+(d.plugin_hooks||[]).map(x=>'<code>'+esc(x)+'</code>').join(' ')+'</div><div style="margin-top:6px"><b>Bereiche, die per API schreibbar sind:</b> '+(d.write_sections||[]).map(x=>'<code>'+esc(x)+'</code>').join(' ')+'</div>';
  }catch(e){h.textContent=e.message}
 }
 return {load,upload,toggle,remove,bind,devInfo};
})();
document.addEventListener('DOMContentLoaded',()=>window.PluginManager?.bind());
