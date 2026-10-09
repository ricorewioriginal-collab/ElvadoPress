'use strict';
const q=new URLSearchParams(location.search),id=q.get('id')||'';
const root=document.getElementById('preview');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let CFG={};
async function loadCfg(){const r=await fetch('/cms/api.php?action=public&_='+Date.now(),{cache:'no-store'});const d=await r.json();CFG=d.config||{};render();}
function widget(){
 try{
   const raw=sessionStorage.getItem('elvado_widget_preview_'+id);
   if(raw){const draft=JSON.parse(raw);if(draft&&draft.id===id&&draft.enabled!==false)return draft;}
 }catch(e){}
 return (CFG.widgets||[]).find(x=>x.id===id&&x.enabled!==false)
}
function render(){
 const w=widget();if(!w){root.innerHTML='<div class="empty">Widget nicht gefunden oder deaktiviert.</div>';return} const title=w.title||w.name||'Widget';
 if(w.type==='html'){root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3>'+String(w.html||'')+'</section>';return}
 if(w.type==='iframe'){root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3><iframe class="frame" sandbox="allow-scripts allow-same-origin allow-forms allow-popups" src="'+esc(w.url||'about:blank')+'"></iframe></section>';return}
 root.innerHTML='<section class="widget"><h3>'+esc(title)+'</h3><p>Dieses Widget wird auf der Website ausgeführt.</p></section>';
}
loadCfg().catch(e=>root.innerHTML='<div class="empty">Vorschau konnte nicht geladen werden.</div>');