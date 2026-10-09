'use strict';
window.ActivityLog=(()=>{
 const tok=()=>sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const fmt=d=>{if(!d)return'–';try{return new Date(String(d).replace(' ','T')).toLocaleString('de-DE',{dateStyle:'medium',timeStyle:'short'});}catch(e){return d;}};
 const ICONS={news_create:'fa-plus',news_update:'fa-pen',news_trash:'fa-trash',news_restore:'fa-rotate-left',news_delete_permanent:'fa-trash-can',comment_approve:'fa-check',comment_delete:'fa-comment-slash',comment_reply:'fa-reply',user_add:'fa-user-plus',user_update:'fa-user-pen',user_delete:'fa-user-minus'};
 async function api(action,body){
  const opt=body===undefined?{headers:{'X-ElvadoPress-Token':tok()}}:{method:'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:JSON.stringify(body)};
  const r=await fetch('api.php?action='+encodeURIComponent(action)+'&_='+Date.now(),opt),d=await r.json().catch(()=>({status:'error',message:'Ungültige Serverantwort'}));
  if(r.status===401)window.cmsSessionExpired?.();
  if(!r.ok||d.status==='error')throw new Error(d.message||'Fehler');return d;
 }
 async function load(){
  const host=document.getElementById('activityLogList'); if(!host)return;
  host.innerHTML='<div class="empty"><i class="fas fa-spinner fa-spin"></i>Aktivitätslog wird geladen …</div>';
  try{
   const d=await api('activity_log_list');
   const entries=d.entries||[];
   host.innerHTML=entries.length?entries.map(e=>`<div style="display:flex;gap:12px;padding:9px 0;border-bottom:1px solid var(--border);align-items:start;">
     <i class="fas ${ICONS[e.action]||'fa-circle-info'}" style="color:var(--accent);margin-top:3px;width:16px;text-align:center"></i>
     <div style="min-width:0;flex:1">
       <div style="font-size:.78rem;color:#dfe2f5;line-height:1.4;">${esc(e.summary)}</div>
       <div style="font-size:.66rem;color:var(--muted);margin-top:3px;">${esc(e.user)}${e.role?' · '+esc(e.role==='admin'?'Admin':'Autor'):''} · ${fmt(e.created_at)}</div>
     </div>
   </div>`).join(''):'<div class="empty">Noch keine Aktivitäten protokolliert.</div>';
  }catch(e){host.innerHTML='<div class="empty" style="color:var(--bad)">'+esc(e.message)+'</div>'}
 }
 return {load};
})();
