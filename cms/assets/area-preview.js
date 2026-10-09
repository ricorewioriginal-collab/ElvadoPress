'use strict';
const q=new URLSearchParams(location.search),id=q.get('id')||'',root=document.getElementById('preview');
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
fetch('/cms/api.php?action=public&_='+Date.now(),{cache:'no-store'}).then(r=>r.json()).then(d=>{
 const c=d.config||{};
 let area=(c.widget_areas||[]).find(x=>x.id===id),widgets=c.widgets||[];
 try{
   const ar=sessionStorage.getItem('elvado_widget_area_preview_'+id),wr=sessionStorage.getItem('elvado_widget_area_widgets_'+id);
   if(ar)area=JSON.parse(ar);
   if(wr)widgets=JSON.parse(wr);
 }catch(e){}
 if(!area){root.innerHTML='<div class="empty">Widget-Bereich nicht gefunden.</div>';return}
 root.innerHTML='<section class="widget"><h2>'+esc(area.name||'Widget-Bereich')+'</h2><p>'+(area.scope==='global'?'Auf allen Seiten':'Nur auf Seite: '+esc(area.page_id||''))+' · '+esc(area.kind||'')+'</p><div id="frames"></div></section>';
 const h=document.getElementById('frames');(area.widgets||[]).forEach(wid=>{const w=widgets.find(x=>x.id===wid);if(!w)return;const f=document.createElement('iframe');f.className='frame';f.style.marginBottom='12px';f.src='/cms/widget-preview.html?id='+encodeURIComponent(wid)+'&t='+Date.now();h.appendChild(f)});
 if(!(area.widgets||[]).length)h.innerHTML='<div class="empty">Dieser Bereich enthält noch keine Widgets.</div>';
}).catch(()=>root.innerHTML='<div class="empty">Bereichsvorschau konnte nicht geladen werden.</div>');