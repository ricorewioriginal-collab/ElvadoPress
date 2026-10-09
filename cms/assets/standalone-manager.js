'use strict';
// Verwaltung von Betriebsmodus, Produktname und Version (Tab "Betrieb & Produkt", nur Administratoren).
window.StandaloneManager=(()=>{
 const tok=()=>sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const $=id=>document.getElementById(id);
 async function api(action,body,query=''){
  const opt=body===undefined?{headers:{'X-AnMaCha-Token':tok()}}:{method:'POST',headers:{'Content-Type':'application/json','X-AnMaCha-Token':tok()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+query+'&_='+Date.now(),opt),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 const toast=(m,bad=false)=>window.cmsToast?.(m,bad);
 const F={name:'prodName',slug:'prodSlug',logo:'prodLogo',title:'prodTitle',heading:'prodHeading',access_name:'prodAccess',generator:'prodGen'};
 function fill(d){
  const o=d.product_overrides||{};
  Object.entries(F).forEach(([k,id])=>{const el=$(id);if(el){el.value=o[k]||'';el.placeholder=(d.product_defaults||{})[k]||el.placeholder||'';}});
  if($('sysLanguage'))$('sysLanguage').value=d.system?.language||'';
  if($('sysTimezone'))$('sysTimezone').value=d.system?.timezone||'';
  if($('sysInfo'))$('sysInfo').innerHTML='Produkt: <b>'+esc(d.product?.name)+'</b> · Lokale Administratoren: <b>'+esc(d.local_admins)+'</b>';
  if($('sysVersion'))$('sysVersion').innerHTML='CMS-Version: <b>'+esc(d.version)+'</b>'+(d.system?.installed_at?' · eingerichtet am '+esc(d.system.installed_at.slice(0,10)):'');
 }
 async function load(){try{fill(await api('system_get'));}catch(e){if($('sysInfo'))$('sysInfo').textContent=e.message;}}
 async function saveMode(){
  try{const d=await api('system_save',{language:$('sysLanguage').value,timezone:$('sysTimezone').value.trim()});toast('Gespeichert');await load();}
  catch(e){toast(e.message,true);}
 }
 async function saveProduct(){
  const p={};Object.entries(F).forEach(([k,id])=>{p[k]=$(id).value.trim();});
  try{await api('system_save',{product:p});toast('Produktname gespeichert – Seite neu laden, um ihn überall zu sehen');await load();}catch(e){toast(e.message,true);}
 }
 async function resetProduct(){
  if(!confirm('Alle Produktbezeichnungen auf die Standardwerte zurücksetzen?'))return;
  try{await api('system_save',{product:{}});toast('Standard wiederhergestellt');await load();}catch(e){toast(e.message,true);}
 }
 async function checksum(){
  const el=$('sysChecksum');el.textContent='Berechne …';
  try{const d=await api('system_get',undefined,'&checksum=1');const c=d.checksum||{};el.innerHTML='SHA-256: <code>'+esc(c.sha256)+'</code> ('+esc(c.files)+' Dateien'+(c.truncated?', gekürzt':'')+')';}
  catch(e){el.textContent=e.message;}
 }
 return {load,saveMode,saveProduct,resetProduct,checksum};
})();
