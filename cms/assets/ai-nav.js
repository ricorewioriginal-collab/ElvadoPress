/* KI-Menü: ein Menüpunkt „KI“ in der Seitenleiste, darin Unterreiter für Zentrale (Anbieter, Schlüssel, Modelle), Medien (Bilder/Videos), Website-Generator, Entwickler, Texte & Lovable und Assistent.
   Die Leiste wird oben in jedes dieser Panels gesetzt; die Seitenleiste bleibt auf „KI“ markiert. */
(function(){
  'use strict';
  var TABS=[
    ['aicenter','fa-brain','Zentrale',function(){window.AiCenter&&AiCenter.open()}],
    ['aimedia','fa-image','Bilder & Videos',function(){window.AiMedia&&AiMedia.open()}],
    ['aibuilder','fa-wand-magic-sparkles','Website-Generator',function(){window.AiBuilder&&AiBuilder.open()}],
    ['aidev','fa-code','Entwickler',function(){window.AiDev&&AiDev.open()}],
    ['ai','fa-robot','Texte & Lovable',function(){window.EpAi&&EpAi.hub()}],
    ['assistant','fa-comments','Assistent',function(){window.AssistantManager&&AssistantManager.render()}]
  ];
  function go(id){
    var t=TABS.filter(function(x){return x[0]===id})[0];if(!t)return;
    cmsTab(id,document.querySelector('.tab[data-tab="aicenter"]'));t[3]();
  }
  function bar(cur){
    return '<nav class="ai-nav" aria-label="KI">'+TABS.filter(function(t){return document.getElementById('panel-'+t[0])}).map(function(t){
      return '<button type="button" class="ai-nav-btn'+(t[0]===cur?' on':'')+'" onclick="AiNav.go(\''+t[0]+'\')"><i class="fas '+t[1]+'"></i> '+t[2]+'</button>';
    }).join('')+'</nav>';
  }
  function mount(){
    TABS.forEach(function(t){
      var p=document.getElementById('panel-'+t[0]);if(!p||p.querySelector(':scope > .ai-nav'))return;
      p.insertAdjacentHTML('afterbegin',bar(t[0]));
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
  window.AiNav={go:go,mount:mount};
})();
