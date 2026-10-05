/* WordPress-artige Navigation für das CMS: Menüpunkte klappen als Gruppen auf, „Menü einklappen“, „+ Neu“ in der Kopfleiste.
   Reine Bedienhilfe: setzt nur CSS-Klassen und ruft vorhandene Funktionen (cmsTab, cmsGoto) auf. */
(function(){
  'use strict';
  function store(k,v){try{if(v===undefined)return localStorage.getItem(k);localStorage.setItem(k,v)}catch(e){return null}}
  function groups(){return Array.prototype.slice.call(document.querySelectorAll('.tabs .tab-group'))}
  function syncOpen(){
    var on=document.querySelector('.tabs .tab.on');
    groups().forEach(function(g){
      if(g.classList.contains('single')){g.classList.add('open');return}
      if(on&&g.contains(on))g.classList.add('open');
      else if(!g.dataset.userOpen)g.classList.remove('open');
    });
  }
  // Gruppen ohne sichtbaren Reiter (z. B. für Redakteure) ausblenden
  window.cmsNavRefresh=function(){groups().forEach(function(g){var any=false;g.querySelectorAll('.tab').forEach(function(t){if(!t.hidden&&t.style.display!=='none')any=true});g.style.display=any?'':'none'});syncOpen()};
  function toggleGroup(g){
    if(g.classList.contains('single'))return;
    var open=!g.classList.contains('open');
    groups().forEach(function(x){if(x!==g&&!x.classList.contains('single')){x.classList.remove('open');delete x.dataset.userOpen}});
    g.classList.toggle('open',open);
    if(open)g.dataset.userOpen='1';else delete g.dataset.userOpen;
  }
  function init(){
    var tabs=document.querySelector('.tabs');if(!tabs||tabs.dataset.wp)return;tabs.dataset.wp='1';
    tabs.addEventListener('click',function(e){
      var t=e.target.closest&&e.target.closest('.tab-group-title');if(t)toggleGroup(t.parentNode);
    });
    tabs.addEventListener('keydown',function(e){
      if(e.key!=='Enter'&&e.key!==' ')return;
      var t=e.target.closest&&e.target.closest('.tab-group-title');if(t){e.preventDefault();toggleGroup(t.parentNode)}
    });
    var search=document.getElementById('cmsNavSearch');
    if(search)search.addEventListener('input',function(){tabs.classList.toggle('searching',!!search.value.trim())});
    var fold=document.getElementById('cmsFoldBtn');
    function setFold(f){document.body.classList.toggle('cms-folded',f);if(fold){fold.title=f?'Menü ausklappen':'Menü einklappen';var l=fold.querySelector('span');if(l)l.textContent=f?'':'Menü einklappen'}}
    if(fold)fold.addEventListener('click',function(){var f=!document.body.classList.contains('cms-folded');setFold(f);store('rrw_cms_folded',f?'1':'0')});
    if(store('rrw_cms_folded')==='1')setFold(true);
    var orig=window.cmsTab;
    if(typeof orig==='function'){window.cmsTab=function(){var r=orig.apply(this,arguments);syncOpen();return r}}
    syncOpen();
  }
  function newMenu(){
    var bar=document.querySelector('.cms-navbar-actions');if(!bar||document.getElementById('cmsNewMenu'))return;
    var w=document.createElement('div');w.id='cmsNewMenu';w.className='cms-newmenu';
    w.innerHTML='<button type="button" class="btn btn-sm btn-outline-light cms-new-btn" aria-haspopup="true" aria-expanded="false"><i class="fas fa-plus"></i> <span class="cms-new-label">Neu</span></button>'
      +'<div class="cms-new-pop" hidden>'
      +'<button type="button" data-go="news"><i class="fas fa-newspaper"></i>Beitrag</button>'
      +'<button type="button" data-go="pages"><i class="fas fa-file-lines"></i>Seite</button>'
      +'<button type="button" data-go="media"><i class="fas fa-photo-film"></i>Medien</button>'
      +'<button type="button" data-go="menus"><i class="fas fa-bars"></i>Menü</button>'
      +'<button type="button" data-go="widgets"><i class="fas fa-puzzle-piece"></i>Widget</button>'
      +'</div>';
    bar.insertBefore(w,bar.firstChild);
    var btn=w.querySelector('.cms-new-btn'),pop=w.querySelector('.cms-new-pop');
    function close(){pop.hidden=true;btn.setAttribute('aria-expanded','false')}
    btn.addEventListener('click',function(e){e.stopPropagation();pop.hidden=!pop.hidden;btn.setAttribute('aria-expanded',String(!pop.hidden))});
    pop.addEventListener('click',function(e){var b=e.target.closest('[data-go]');if(!b)return;close();if(typeof cmsGoto==='function')cmsGoto(b.dataset.go);window.scrollTo(0,0)});
    document.addEventListener('click',close);
    document.addEventListener('keydown',function(e){if(e.key==='Escape')close()});
  }
  // Direktlinks aus der Admin-Leiste der Website: /cms/#tab=themes öffnet den Reiter, sobald die Anmeldung geprüft ist
  function deepLink(){
    var m=/^#tab=([a-z]+)$/.exec(location.hash||'');if(!m)return;var n=0;
    var t=setInterval(function(){
      var app=document.getElementById('cmsApp'),btn=document.querySelector('.tab[data-tab="'+m[1]+'"]');
      if(app&&app.style.display!=='none'&&btn){clearInterval(t);if(!btn.hidden)btn.click()}
      else if(++n>60)clearInterval(t);
    },250);
  }
  function boot(){init();newMenu();deepLink()}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
/* Willkommens-Kasten auf dem Dashboard ausblendbar (merkt sich die Wahl nur im Browser) */
window.cmsWelcomeHide=function(){var el=document.getElementById('cmsWelcome');if(el)el.style.display='none';try{localStorage.setItem('rrw_cms_welcome','0')}catch(e){}};
(function(){try{if(localStorage.getItem('rrw_cms_welcome')==='0'){var el=document.getElementById('cmsWelcome');if(el)el.style.display='none'}}catch(e){}})();
