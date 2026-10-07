'use strict';
// Websites: mehrere eigenständige Homepages (cms/docs/MULTISITE.md). Liste, anlegen (leer oder als Kopie), Domains, aktivieren/deaktivieren, zum Verwalten wechseln.
window.SitesManager=(()=>{
 const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const S={main:{name:'Hauptwebsite'},sites:[],current:'',loaded:false};
 const cur=()=>{try{return localStorage.getItem('ep.site')||''}catch(e){return ''}};
 async function load(){
  try{const d=await cmsApi('sites_list');S.main=d.main||S.main;S.sites=d.sites||[];S.current=cur();S.loaded=true;}catch(e){S.sites=[];S.loaded=true;cmsToast(e.message,true);}
  render();
 }
 function card(id,name,domains,enabled,main){
  const on=(cur()||'')===id,dom=(domains||[]).length?domains.join(', '):'– keine Domain –';
  return `<div class="brand-card ${on?'on':''}" style="cursor:default">
   <div class="brand-logo"><i class="fas ${main?'fa-house':'fa-globe'}"></i></div>
   <div class="grow"><b>${esc(name)}</b>${main?' <span class="brand-main">Hauptwebsite</span>':''}${on?' <span class="brand-main" style="background:rgba(52,211,153,.18);color:#34d399">wird verwaltet</span>':''}<small>${main?'Immer erreichbar, auch für unbekannte Domains':esc(dom)}</small></div>
   <div>${main?'':`<span class="brand-status ${enabled?'on':''}"><i class="fas fa-circle"></i> ${enabled?'Aktiv':'Inaktiv'}</span>`}</div>
   <div class="brand-card-actions" style="display:flex;gap:6px;flex-wrap:wrap">
    ${on?'':`<button class="btn-a" type="button" onclick="SitesManager.manage('${esc(id)}')"><i class="fas fa-right-to-bracket"></i> Verwalten</button>`}
    ${main?'':`<button class="btn-g" type="button" onclick="SitesManager.edit('${esc(id)}')"><i class="fas fa-pen"></i> Domains</button><button class="btn-g" type="button" onclick="SitesManager.toggle('${esc(id)}')">${enabled?'Deaktivieren':'Aktivieren'}</button>`}
   </div></div>`;
 }
 function render(){
  const h=document.getElementById('sitesManager');if(!h)return;
  h.innerHTML='<div class="brand-list">'+card('',S.main.name||'Hauptwebsite',[],true,true)+S.sites.map(s=>card(s.id,s.name,s.domains,s.enabled,false)).join('')+'</div>'
   +(S.sites.length?'':'<div class="hint" style="padding:10px 4px">Noch keine weitere Website. „Neue Website“ legt eine eigenständige Homepage an – leer oder als Kopie einer vorhandenen.</div>');
 }
 function modal(title,inner,okLabel,onOk){
  document.getElementById('siteWiz')?.remove();
  const o=document.createElement('div');o.id='siteWiz';o.style.cssText='position:fixed;inset:0;z-index:5000;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
  o.innerHTML=`<div class="card" style="max-width:560px;width:100%;max-height:92vh;overflow:auto" role="dialog" aria-modal="true" aria-label="${esc(title)}"><div class="th" style="margin-bottom:8px"><div><div class="wp-page-title" style="font-size:1.1rem">${esc(title)}</div></div></div>${inner}<div style="display:flex;justify-content:flex-end;gap:8px;margin-top:14px"><button class="btn-g" type="button" id="swCancel">Abbrechen</button><button class="btn-a" type="button" id="swOk">${okLabel}</button></div></div>`;
  document.body.appendChild(o);const close=()=>o.remove();
  o.querySelector('#swCancel').onclick=close;o.addEventListener('mousedown',e=>{if(e.target===o)close()});o.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  o.querySelector('#swOk').onclick=async()=>{const b=o.querySelector('#swOk');b.disabled=true;try{await onOk(o);close()}catch(e){cmsToast(e.message,true);b.disabled=false}};
  return o;
 }
 const slug=t=>String(t).toLowerCase().replace(/ä/g,'ae').replace(/ö/g,'oe').replace(/ü/g,'ue').replace(/ß/g,'ss').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'').slice(0,30);
 const doms=v=>String(v).split(/[,\s]+/).map(x=>x.trim().toLowerCase()).filter(Boolean);
 async function dns(domain,out){
  const el=document.getElementById(out);if(!el)return;const d0=String(domain||'').trim();if(!d0){el.textContent='Bitte zuerst eine Domain eintragen.';return}
  el.textContent='Prüfe …';try{const d=await cmsApi('brand_domain_check',{domain:d0.split(/[,\s]+/)[0]});el.style.color=!d.ok?'#f87171':(d.match?'#34d399':'#fbbf24');el.textContent=(d.ok&&d.match?'✓ ':'')+d.message}catch(e){el.style.color='#f87171';el.textContent=e.message}
 }
 const api={
  load,render,dns,
  add(){
   const o=modal('Neue Website',`<div class="hint" style="margin-bottom:8px">Eine eigenständige Homepage mit eigenen Inhalten, Einstellungen, Marken und Medien.</div>
    <div class="section-grid">
     <div><label class="news-lbl">Name</label><input class="fc w-100" id="swName" placeholder="z. B. Mein Blog" maxlength="80"></div>
     <div><label class="news-lbl">Kennung</label><input class="fc w-100" id="swId" placeholder="mein-blog" maxlength="30"><div class="hint">Technisch, nur Kleinbuchstaben, Ziffern, Bindestrich.</div></div>
     <div style="grid-column:1/-1"><label class="news-lbl">Domains</label><input class="fc w-100" id="swDoms" placeholder="mein-blog.de, www.mein-blog.de"><div class="hint">Jede Domain gehört genau einer Website.</div></div>
    </div>
    <div style="margin:6px 0"><button class="btn-g" type="button" id="swDns"><i class="fas fa-network-wired"></i> Domain prüfen (DNS)</button> <span class="hint" id="swDnsOut" style="display:inline-block;margin-left:6px"></span></div>
    <div class="widget-category-title" style="margin-top:10px">Starten mit</div>
    <select class="fc w-100" id="swFrom"><option value="">Leere Website</option><option value="main">Kopie der Hauptwebsite (${esc(S.main.name||'')})</option>${S.sites.map(s=>`<option value="${esc(s.id)}">Kopie von „${esc(s.name)}“</option>`).join('')}</select>
    <label class="wm-check" id="swMediaRow" style="margin:8px 0 0;display:none"><input type="checkbox" id="swMedia"> Medien mitkopieren (Bilder, Dateien)</label>
    <div class="hint" style="margin-top:4px">Eine Kopie übernimmt Einstellungen, Inhalte, Layouts und erzeugte Dateien – nicht Anmeldungen, Sitzungen, Protokolle und Geheimnisse.</div>
    <label class="wm-check" style="margin:10px 0 0"><input type="checkbox" id="swOn" checked> Sofort aktivieren (die Domains zeigen dann diese Website)</label>`,'<i class="fas fa-plus"></i> Website anlegen',async o=>{
     const q=id=>o.querySelector('#'+id);
     const d=await cmsApi('sites_create',{name:q('swName').value,id:q('swId').value,domains:doms(q('swDoms').value),enabled:q('swOn').checked,copy_from:q('swFrom').value,copy_media:q('swMedia').checked});
     S.sites=d.sites||[];render();window.CMS_SITES_REFRESH?.();cmsToast('Website angelegt ✓'+(d.copied?' ('+d.copied+' Dateien kopiert)':''));
    });
   let idTouched=false;const q=id=>o.querySelector('#'+id);
   q('swName').addEventListener('input',()=>{if(!idTouched)q('swId').value=slug(q('swName').value)});q('swId').addEventListener('input',()=>{idTouched=true});
   q('swFrom').addEventListener('change',()=>{q('swMediaRow').style.display=q('swFrom').value?'':'none'});
   q('swDns').onclick=()=>dns(q('swDoms').value,'swDnsOut');setTimeout(()=>q('swName').focus(),30);
  },
  edit(id){
   const s=S.sites.find(x=>x.id===id);if(!s)return;
   const o=modal('Website „'+s.name+'“',`<div class="section-grid"><div><label class="news-lbl">Name</label><input class="fc w-100" id="seName" value="${esc(s.name)}" maxlength="80"></div>
    <div style="grid-column:1/-1"><label class="news-lbl">Domains</label><input class="fc w-100" id="seDoms" value="${esc((s.domains||[]).join(', '))}" placeholder="mein-blog.de, www.mein-blog.de"></div></div>
    <div style="margin:6px 0"><button class="btn-g" type="button" id="seDns"><i class="fas fa-network-wired"></i> Domain prüfen (DNS)</button> <span class="hint" id="seDnsOut" style="display:inline-block;margin-left:6px"></span></div>`,'<i class="fas fa-floppy-disk"></i> Speichern',async o=>{
     const d=await cmsApi('sites_update',{id,name:o.querySelector('#seName').value,domains:doms(o.querySelector('#seDoms').value)});S.sites=d.sites||[];render();window.CMS_SITES_REFRESH?.();cmsToast('Gespeichert ✓');
    });
   o.querySelector('#seDns').onclick=()=>dns(o.querySelector('#seDoms').value,'seDnsOut');
  },
  async toggle(id){const s=S.sites.find(x=>x.id===id);if(!s)return;
   if(s.enabled&&!confirm('Website „'+s.name+'“ deaktivieren? Ihre Domains zeigen danach die Hauptwebsite.'))return;
   try{const d=await cmsApi('sites_update',{id,enabled:!s.enabled});S.sites=d.sites||[];render();window.CMS_SITES_REFRESH?.();cmsToast(s.enabled?'Deaktiviert':'Aktiviert ✓')}catch(e){cmsToast(e.message,true)}},
  manage(id){try{if(id)localStorage.setItem('ep.site',id);else localStorage.removeItem('ep.site')}catch(e){}location.reload()},
 };
 return api;
})();
