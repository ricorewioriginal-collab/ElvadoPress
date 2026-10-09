'use strict';
// Systemstatus und Update-Center (System → Systemstatus, Updates). Spricht cms/engine-api.php an (system_status, updates_overview, updates_check); nur Administratoren.
window.SysStatus=(()=>{
 const S={sys:null,upd:null,tab:'status',busy:false,msg:null,at:''};
 const esc=s=>String(s==null?'':s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const tok=()=>{try{return sessionStorage.getItem('elvadopress_session_token')||localStorage.getItem('elvadopress_session_token')||'';}catch(e){return '';}};
 const root=()=>document.getElementById('ssRoot');
 async function api(action,body){
  const r=await fetch('/cms/engine-api.php?action='+action,{method:body===undefined?'GET':'POST',headers:{'Content-Type':'application/json','X-ElvadoPress-Token':tok()},body:body===undefined?undefined:JSON.stringify(body)});
  let d;try{d=await r.json();}catch(e){throw new Error('Unerwartete Antwort des Servers');}
  if(d.status!=='ok')throw new Error(d.message||'Fehler');return d;
 }
 const ST={ok:['OK','#22a06b'],warn:['Warnung','#e0a100'],fail:['Fehler','#e5484d'],info:['Info','#6b7a99']};
 const pill=s=>{const b=ST[s]||ST.info;return '<span style="color:#fff;background:'+b[1]+';border-radius:6px;padding:2px 9px;font-size:.72rem;font-weight:800;white-space:nowrap">'+b[0]+'</span>'};
 function status(){
  const s=S.sys;if(!s)return '<p class="hint">Lade …</p>';const m=s.summary;
  let h='<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px">'+['ok','warn','fail','info'].map(k=>'<div class="stat" style="min-width:110px"><div class="l">'+ST[k][0]+'</div><div class="v" style="color:'+ST[k][1]+'">'+esc(m[k])+'</div></div>').join('')+'</div>';
  const groups={};s.items.forEach(i=>{(groups[i.group]=groups[i.group]||[]).push(i)});
  Object.keys(groups).forEach(g=>{h+='<div class="widget-category-title" style="margin-top:14px">'+esc(g)+'</div>'+groups[g].map(i=>'<div class="core-row"><div><div class="core-name">'+esc(i.label)+'</div><div class="core-meta" style="font-size:.78rem;line-height:1.5;white-space:normal">'+esc(i.detail)+'</div></div><div>'+pill(i.status)+'</div></div>').join('')});
  return h;
 }
 function updates(){
  const u=S.upd;if(!u)return '<p class="hint">Lade …</p>';
  return u.map(r=>'<div class="core-row" style="align-items:flex-start"><div style="min-width:0"><div class="core-name">'+esc(r.label)+(r.installed?' <span class="hint">'+esc(r.installed)+(r.latest&&r.latest!==r.installed?' → '+esc(r.latest):'')+'</span>':'')+'</div><div class="core-meta" style="font-size:.78rem;white-space:normal">'+esc(r.detail)+(r.checked_at?' · geprüft '+esc(String(r.checked_at).slice(0,16).replace('T',' ')):'')+'</div>'
   +((r.items||[]).length?'<ul style="margin:6px 0 0 18px;font-size:.8rem">'+r.items.map(i=>'<li><b>'+esc(i.name)+'</b> '+esc(i.installed)+' → '+esc(i.latest)+'</li>').join('')+'</ul>':'')+'</div><div>'+pill(r.available?'warn':'ok')+'</div></div>').join('');
 }
 function draw(){
  const r=root();if(!r)return;
  let h='';if(S.msg)h+='<div class="'+(S.msg.err?'danger-note':'hint')+'" style="margin:8px 0;padding:8px 12px;border-radius:8px">'+esc(S.msg.text)+'</div>';
  h+=S.tab==='updates'?updates():status();
  if(S.busy)h+='<p class="hint" style="margin-top:10px"><i class="fas fa-spinner fa-spin"></i> '+esc(S.busy)+' …</p>';
  r.innerHTML=h;
  document.querySelectorAll('#panel-sysstatus [data-sstab]').forEach(b=>b.classList.toggle('on',b.dataset.sstab===S.tab));
 }
 async function load(tab){
  if(tab)S.tab=tab;S.msg=null;draw();
  try{const [a,b]=await Promise.all([api('system_status'),api('updates_overview')]);S.sys=a.system;S.upd=b.updates;}catch(e){S.msg={err:true,text:e.message}}
  draw();
 }
 async function check(){
  if(S.busy)return;S.busy='Suche nach Updates (ElvadoPress, WordPress, Plugins, Themes)';S.msg=null;draw();
  try{const d=await api('updates_check',{});S.sys=d.system;S.upd=d.updates;S.msg={err:!!(d.notes||[]).length,text:(d.notes||[]).length?'Gesucht – mit Hinweisen: '+d.notes.join(' · '):'Suche abgeschlossen.'};}catch(e){S.msg={err:true,text:e.message}}
  S.busy=false;draw();
 }
 document.addEventListener('click',e=>{const b=e.target.closest&&e.target.closest('#panel-sysstatus [data-sstab]');if(b){S.tab=b.dataset.sstab;draw()}});
 async function badge(){   // Zähler der verfügbaren Updates an „Updates“ (nur zwischengespeicherte Ergebnisse, kein Netzzugriff)
  try{const d=await api('updates_overview');const n=(d.updates||[]).filter(r=>r.available).length;const b=document.getElementById('epUpdBadge');if(b){b.textContent=n;b.hidden=n===0;b.classList.add('blue')}}catch(e){}
 }
 let tries=0;const t=setInterval(()=>{const app=document.getElementById('cmsApp');if(app&&app.style.display!=='none'){clearInterval(t);badge()}else if(++tries>40)clearInterval(t)},1500);
 return {load,check,badge};
})();
