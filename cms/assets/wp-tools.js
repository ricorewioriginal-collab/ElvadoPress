/* Werkzeuge wie in WordPress (CMS → Werkzeuge): Import, Export, Website-Zustand. Nutzt die vorhandenen Funktionen der Beitragsverwaltung. */
(function(){
  'use strict';
  window.WpTools={
    importPosts:function(){if(window.NewsMagazine&&NewsMagazine.importPrompt)NewsMagazine.importPrompt();else{cmsGoto('news');alert('Die Beitragsverwaltung wird geladen – bitte erneut versuchen.')}},
    exportPosts:function(){location.href='api.php?action=news_export&_tok='+encodeURIComponent(cmsToken())},
    health:function(){cmsGoto('overview');var d=document.querySelector('.dash-tech');if(d)d.open=true;if(typeof loadSiteHealth==='function')loadSiteHealth(true);var h=document.getElementById('siteHealthList');if(h)setTimeout(function(){h.scrollIntoView({behavior:'smooth',block:'center'})},200)}
  };
})();
