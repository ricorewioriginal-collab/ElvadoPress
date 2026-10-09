'use strict';
window.UsersManager=(()=>{
 const tok=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 async function api(action,body){
  const opt=body===undefined?{headers:{'X-ElvadoPress-Token':tok()}}:{method:'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opt),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 function toast(m,bad=false){window.cmsToast?.(m,bad)}
 async function load(){
  const host=document.getElementById('usersList'); if(!host)return;
  host.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Redakteure werden geladen …</div>';
  try{
   const d=await api('users_list');
   host.innerHTML=(d.users||[]).map(u=>`<div class="core-row" data-username="${esc(u.username)}">
     <div>
       <b>${esc(u.display_name||u.username)}</b>
       <div class="hint">${esc(u.username)} · ${u.role==='admin'?'Administrator (voller Zugriff)':'Autor (nur eigene Beiträge)'}${u.email?' · '+esc(u.email):' · keine E-Mail hinterlegt'}</div>
     </div>
     <div style="display:flex;gap:6px;align-items:center;">
       <select class="fc" style="width:auto;padding:5px 8px;" onchange="UsersManager.changeRole(${esc(JSON.stringify(u.username))},this.value)">
         <option value="autor" ${u.role!=='admin'?'selected':''}>Autor</option>
         <option value="admin" ${u.role==='admin'?'selected':''}>Administrator</option>
       </select>
       <button class="btn-g" title="E-Mail ändern" onclick="UsersManager.changeEmail(${esc(JSON.stringify(u.username))},${esc(JSON.stringify(u.email||''))})"><i class="fas fa-envelope"></i></button>
       <button class="btn-g" title="Passwort zurücksetzen" onclick="UsersManager.resetPassword(${esc(JSON.stringify(u.username))})"><i class="fas fa-key"></i></button>
       <button class="btn-d" title="Entfernen" onclick="UsersManager.remove(${esc(JSON.stringify(u.username))})"><i class="fas fa-trash"></i></button>
     </div>
   </div>`).join('')||'<div class="empty">Noch keine Redakteure.</div>';
  }catch(e){host.innerHTML='<div class="empty" style="color:var(--bad)">'+esc(e.message)+'</div>'}
 }
 async function add(){
  const user=document.getElementById('userNewName')?.value.trim();
  const display=document.getElementById('userNewDisplay')?.value.trim();
  const email=document.getElementById('userNewEmail')?.value.trim();
  const pass=document.getElementById('userNewPass')?.value;
  const role=document.getElementById('userNewRole')?.value||'autor';
  if(!user||!pass)return toast('Benutzername und Passwort sind Pflicht',true);
  try{
   await api('user_add',{username:user,password:pass,role,display_name:display,email});
   toast('Redakteur angelegt ✓');
   document.getElementById('userNewName').value='';
   document.getElementById('userNewDisplay').value='';
   document.getElementById('userNewEmail').value='';
   document.getElementById('userNewPass').value='';
   load();
  }catch(e){toast(e.message,true)}
 }
 async function changeRole(username,role){
  try{ await api('user_update',{username,role}); toast('Rolle aktualisiert ✓'); load(); }
  catch(e){ toast(e.message,true); load(); }
 }
 async function changeEmail(username,current){
  const email=prompt('E-Mail-Adresse für „'+username+'“ (für Kommentar-Benachrichtigungen, leer lassen zum Entfernen):',current||'');
  if(email===null)return;
  try{ await api('user_update',{username,email:email.trim()}); toast('E-Mail aktualisiert ✓'); load(); }
  catch(e){ toast(e.message,true); }
 }
 async function resetPassword(username){
  const pass=prompt('Neues Passwort für „'+username+'“ (mind. 8 Zeichen):');
  if(!pass)return;
  try{ await api('user_update',{username,password:pass}); toast('Passwort geändert ✓'); }
  catch(e){ toast(e.message,true); }
 }
 async function remove(username){
  if(!confirm('Redakteur „'+username+'“ wirklich entfernen? Der Zugang wird sofort gesperrt.'))return;
  try{ await api('user_delete',{username}); toast('Redakteur entfernt ✓'); load(); }
  catch(e){ toast(e.message,true); }
 }
 return {load,add,changeRole,changeEmail,resetPassword,remove};
})();
