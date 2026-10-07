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
/* Kopfleiste und Seitenleiste v2: Suche, Website-Name, Gerätewahl, Profil, „Weitere anzeigen“. Reine Bedienung – nutzt vorhandene Funktionen (CmsSearch, AdminTheme, LiveBuilder, cmsTab). */
(function(){
  'use strict';
  function $(id){return document.getElementById(id)}
  function esc(t){return String(t==null?'':t).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function once(el,k){if(!el||el.dataset[k])return false;el.dataset[k]='1';return true}
  function initials(n){n=String(n||'').trim();if(!n)return '?';var p=n.split(/\s+/);return ((p[0]||'').charAt(0)+(p.length>1?p[p.length-1].charAt(0):'')).toUpperCase()}
  function syncUser(){
    var id=$('cmsUserIdentity');if(!id)return;var t=id.textContent||'',parts=t.split(' · '),name=parts[0]||'',role=parts[1]||'';
    var n=$('epUserName'),r=$('epUserRole'),a=$('epAvatar');if(n)n.textContent=name;if(r)r.textContent=role==='Admin'?'Administrator':role;if(a)a.textContent=initials(name);
  }
  function siteName(){
    var c=window.CMS&&window.CMS.portal&&window.CMS.portal.site_name;var chip=$('epSiteChip');if(chip&&c&&!chip.dataset.brandUrl){var b=chip.querySelector('b');if(b&&b.textContent!==c)b.textContent=c}
  }
  /* Marken-Umschalter: bei mehreren Marken/Websites (Domains & Branding) wählt der Header die Marke, die der Verwaltungsbereich bearbeitet (z. B. Live Editor). */
  var brandState={list:[],cur:''};
  function brandCur(){return brandState.list.filter(function(b){return b.id===brandState.cur})[0]||null}
  function brandApply(fire){
    var b=brandCur();if(!b)return;
    document.documentElement.dataset.cmsBrand=b.id;window.CMS_BRAND=b.id;
    var btn=$('epBrandBtn');if(btn){var l=btn.querySelector('b');if(l)l.textContent=b.short_name||b.name}
    var chip=$('epSiteChip');if(chip){var cb=chip.querySelector('b');if(cb)cb.textContent=b.name;if(b.primary_domain){chip.href='https://'+b.primary_domain+'/';chip.dataset.brandUrl='1'}}
    var menu=$('epBrandMenu');if(menu)[].forEach.call(menu.querySelectorAll('button'),function(x){x.classList.toggle('on',x.getAttribute('data-brand')===b.id)});
    if(fire)window.dispatchEvent(new CustomEvent('cms:brand',{detail:{id:b.id,brand:b}}));
  }
  function brandSet(id,fire){
    if(!brandState.list.some(function(b){return b.id===id}))return;brandState.cur=id;try{localStorage.setItem('cms.brand',id)}catch(e){}brandApply(fire);
  }
  function brandInit(){
    var host=document.querySelector('.ep-top .ep-site');if(!host||$('epBrand'))return;
    fetch('api.php?action=brands_public',{cache:'no-store'}).then(function(r){return r.json()}).then(function(d){
      var list=(d&&d.brands||[]).filter(function(b){return b.enabled!==false});if(list.length<2||$('epBrand'))return;
      brandState.list=list;var saved='';try{saved=localStorage.getItem('cms.brand')||''}catch(e){}
      brandState.cur=list.some(function(b){return b.id===saved})?saved:(d.default||list[0].id);
      var w=document.createElement('div');w.className='ep-brand';w.id='epBrand';
      w.innerHTML='<button type="button" class="ep-brand-btn" id="epBrandBtn" aria-haspopup="true" aria-expanded="false" title="Website/Marke wechseln"><i class="fas fa-layer-group"></i><b></b><i class="fas fa-chevron-down ep-brand-ch"></i></button><div class="ep-brand-menu" id="epBrandMenu" role="menu" hidden>'
        +list.map(function(b){return '<button type="button" role="menuitem" data-brand="'+String(b.id).replace(/[^a-z0-9_-]/gi,'')+'"><i class="fas fa-globe"></i><span><b>'+esc(b.name)+'</b><small>'+esc(b.primary_domain||'')+'</small></span><i class="fas fa-check ep-brand-ok"></i></button>'}).join('')+'</div>';
      host.parentNode.insertBefore(w,host);brandApply(false);
      w.addEventListener('click',function(e){
        var bt=e.target.closest('button');if(!bt)return;var m=$('epBrandMenu');
        if(bt.id==='epBrandBtn'){m.hidden=!m.hidden;bt.setAttribute('aria-expanded',m.hidden?'false':'true');return}
        var id=bt.getAttribute('data-brand');if(id){m.hidden=true;$('epBrandBtn').setAttribute('aria-expanded','false');if(id!==brandState.cur)brandSet(id,true)}
      });
      document.addEventListener('click',function(e){var m=$('epBrandMenu');if(m&&!m.hidden&&!e.target.closest('#epBrand')){m.hidden=true;$('epBrandBtn').setAttribute('aria-expanded','false')}});
      document.addEventListener('keydown',function(e){if(e.key==='Escape'){var m=$('epBrandMenu');if(m)m.hidden=true}});
      window.CMS_BRANDS={list:function(){return brandState.list.slice()},current:function(){return brandState.cur},set:function(id){brandSet(id,true)}};
      window.dispatchEvent(new CustomEvent('cms:brand-ready',{detail:{id:brandState.cur}}));
    }).catch(function(){});
  }
  window.CMS_BRANDS_REFRESH=function(){var w=$('epBrand');if(w)w.remove();brandInit()};   /* nach Anlegen/Ändern von Marken: Umschalter neu aufbauen */
  function themeIcon(){
    var b=$('cmsThemeBtn');if(!b)return;var m=document.documentElement.getAttribute('data-admin-theme')||'neon',i=b.querySelector('i');if(i)i.className='fas '+(m==='light'?'fa-sun':'fa-moon');
  }
  function devices(){
    var box=$('epDevs');if(!box||!once(box,'wired'))return;
    box.addEventListener('click',function(e){var b=e.target.closest('[data-epdev]');if(!b)return;var d=b.dataset.epdev;mark(d);try{localStorage.setItem('ep_device',d)}catch(x){}
      if(window.LiveBuilder&&LiveBuilder.device)LiveBuilder.device(d);
      var lb=document.querySelector('.tab[data-tab="livebuilder"]');if(lb&&!document.getElementById('panel-livebuilder').classList.contains('on')&&lb.click)lb.click()});
    document.addEventListener('ep-device',function(e){mark(e.detail)});
    function mark(d){box.querySelectorAll('[data-epdev]').forEach(function(x){x.classList.toggle('on',x.dataset.epdev===d)})}
  }
  function user(){
    var u=$('epUser');if(!u||!once(u,'wired'))return;var btn=$('epUserBtn'),pop=$('epUserPop');
    btn.addEventListener('click',function(e){e.stopPropagation();pop.hidden=!pop.hidden;btn.setAttribute('aria-expanded',String(!pop.hidden))});
    pop.addEventListener('click',function(e){var g=e.target.closest('[data-epgo]');if(g){pop.hidden=true;var t=document.querySelector('.tab[data-tab="'+g.dataset.epgo+'"]');if(t)t.click()}});
    document.addEventListener('click',function(){pop.hidden=true});
    document.addEventListener('keydown',function(e){if(e.key==='Escape')pop.hidden=true});
    var id=$('cmsUserIdentity');if(id&&window.MutationObserver)new MutationObserver(syncUser).observe(id,{childList:true,characterData:true,subtree:true});
    syncUser();
  }
  function search(){var b=$('epTopSearch');if(!b||!once(b,'wired'))return;b.addEventListener('click',function(){if(window.CmsSearch)CmsSearch.open()})}
  function more(){
    document.querySelectorAll('.tabs .tab-group').forEach(function(g){
      if(g.dataset.moreBtn||!g.querySelector('.tab-more'))return;g.dataset.moreBtn='1';
      var body=g.querySelector('.tab-group-body');if(!body)return;
      var b=document.createElement('button');b.type='button';b.className='ep-more-btn';b.innerHTML='<i class="fas fa-ellipsis"></i><span>Weitere anzeigen</span>';
      b.addEventListener('click',function(e){e.stopPropagation();var on=g.classList.toggle('show-more');b.querySelector('span').textContent=on?'Weniger anzeigen':'Weitere anzeigen'});
      if(g.classList.contains('single'))g.appendChild(b);else body.appendChild(b);
    });
  }
  function keepOpen(){   // aktiver Eintrag in „weiteren“: Gruppe zeigt sie
    var on=document.querySelector('.tabs .tab.tab-more.on');if(on){var g=on.closest('.tab-group');if(g)g.classList.add('show-more')}
  }
  function order(){   // Reihenfolge wie im Entwurf: Website-Name, Website öffnen, Geräte, Design, Benachrichtigungen, Profil
    var bar=document.querySelector('.cms-navbar-actions'),th=$('cmsThemeMenu'),bell=document.querySelector('.ep-bellwrap');
    if(bar&&th&&bell&&th.nextElementSibling!==bell)bar.insertBefore(th,bell);
  }
  function nolabel(){document.querySelectorAll('.tabs .tab').forEach(function(t){if(!t.textContent.trim())t.classList.add('ep-nolabel')})}
  function boot(){nolabel();search();user();devices();more();siteName();brandInit();themeIcon();order();keepOpen()}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  [300,900,2200,5000].forEach(function(ms){setTimeout(boot,ms)});
  setInterval(function(){siteName();themeIcon();keepOpen()},2500);
  document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('.tabs .tab'))setTimeout(keepOpen,50)});
})();
