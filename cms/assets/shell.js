/* Verwaltungs-Oberfläche: Markenblock oben in der Seitenleiste (Logo und Name aus der Produktkonfiguration). Reine Darstellung. */
(function(){
  'use strict';
  function init(){
    var tabs=document.querySelector('.tabs');if(!tabs||tabs.querySelector('.cms-brand'))return;
    var p=window.RRW_PRODUCT||{},name=String(p.name||p.title||'ElvadoPress').replace(/\s*(Verwaltung|CMS)$/i,'')||'ElvadoPress';
    var box=document.createElement('div');box.className='cms-brand';
    var mark=p.logo?'<img src="'+String(p.logo).replace(/"/g,'&quot;')+'" alt="">':'<span class="cms-brand-mark" aria-hidden="true">'+name.charAt(0).toUpperCase()+'</span>';
    var b=document.createElement('b');b.textContent=name;var s=document.createElement('small');s.textContent='CMS · DESIGN · APPS · AI';
    box.innerHTML=mark;var d=document.createElement('div');d.appendChild(b);d.appendChild(s);box.appendChild(d);
    tabs.insertBefore(box,tabs.firstChild);
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
