/* Anbindung der React-Oberfläche (cms/assets/react/elvado-react.js, Quellen in frontend/) an die klassischen CMS-Seiten.
   EpAi.hub()                         Menü „KI & Lovable“ einhängen
   EpAi.newsAssistant()               KI-Assistent im Beitrags-Editor (Titel / Teaser / Artikeltext)
   EpAi.layoutAssistant(onApply)      KI-Layout für den Homepage-Baukasten (Fenster)
   Das Bündel wird erst beim ersten Gebrauch geladen. */
(function(){
  'use strict';
  var loading=null;
  function $(id){return document.getElementById(id)}
  function ensure(){
    if(window.ElvadoReact)return Promise.resolve(window.ElvadoReact);
    if(loading)return loading;
    loading=new Promise(function(resolve,reject){
      var css=document.createElement('link');css.rel='stylesheet';css.href='assets/react/elvado-react.css?v=1';document.head.appendChild(css);
      var s=document.createElement('script');s.src='assets/react/elvado-react.js?v=1';s.async=true;
      s.onload=function(){window.ElvadoReact?resolve(window.ElvadoReact):reject(new Error('Oberfläche nicht verfügbar'))};
      s.onerror=function(){loading=null;reject(new Error('Die Oberfläche konnte nicht geladen werden'))};
      document.head.appendChild(s);
    });
    return loading;
  }
  function fail(e){if(window.cmsToast)window.cmsToast(e.message||'Fehler',true)}
  function hub(){
    var el=$('epAiHub');if(!el)return;
    ensure().then(function(R){if(!el.dataset.mounted){el.dataset.mounted='1';R.mountAdminHub(el)}}).catch(function(e){el.innerHTML='<div class="empty">'+(e.message||'Fehler')+'</div>'});
  }
  /* Text in Absätzen in ein contenteditable-Feld einfügen (Text, nie HTML des Modells) */
  function appendParagraphs(ed,text){
    String(text).split(/\n{2,}/).forEach(function(block){
      if(!block.trim())return;var p=document.createElement('p');block.split('\n').forEach(function(line,i){if(i)p.appendChild(document.createElement('br'));p.appendChild(document.createTextNode(line))});ed.appendChild(p);
    });
    ed.dispatchEvent(new Event('input',{bubbles:true}));
  }
  function newsAssistant(){
    var host=$('newsAiHost');if(!host)return;
    if(host.dataset.mounted){host.style.display=host.style.display==='none'?'':'none';return}
    host.style.display='';
    ensure().then(function(R){
      host.dataset.mounted='1';
      var body=function(){return $('newsBody')};
      var editorApi=function(){return window.NewsMagazine&&window.NewsMagazine.bodyApi?window.NewsMagazine.bodyApi():null};
      var toParagraphHtml=function(t){return String(t).split(/\n{2,}/).filter(function(x){return x.trim()}).map(function(x){return '<p>'+x.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')+'</p>'}).join('')};
      R.mountAiAssistant(host,{
        targets:[
          {label:'Als Titel',apply:function(t){var i=$('newsTitle');if(i){i.value=t.replace(/\s+/g,' ').trim().slice(0,255);i.dispatchEvent(new Event('input',{bubbles:true}))}}},
          {label:'Als Teaser',apply:function(t){var i=$('newsExcerpt');if(i){i.value=t.replace(/\s+/g,' ').trim().slice(0,600);i.dispatchEvent(new Event('input',{bubbles:true}))}}},
          {label:'In Artikeltext einfügen',apply:function(t){var be=editorApi();if(be)be.insertHTML(toParagraphHtml(t))}}
        ],
        getEditorText:function(){var sel=window.getSelection&&String(window.getSelection());var ed=body();if(sel&&sel.trim()&&ed&&ed.contains(window.getSelection().anchorNode))return sel;var be=editorApi();if(!be)return '';var d=document.createElement('div');d.innerHTML=be.getHTML().replace(/<!--[\s\S]*?-->/g,' ').replace(/<\/(p|h[1-6]|li|blockquote|div)>/gi,'$&\n');return d.textContent.trim()}
      });
    }).catch(function(e){host.style.display='none';fail(e)});
  }
  function layoutAssistant(onApply){
    var m=document.createElement('div');m.className='hb-picker';
    m.innerHTML='<div class="hb-picker-box" style="width:min(820px,96vw);max-height:90vh"><div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px"><b>KI-Layout für die Startseite</b><button type="button" class="btn-g" id="epAiLx" aria-label="Schließen"><i class="fas fa-xmark"></i></button></div><div id="epAiLayoutHost"></div></div>';
    document.body.appendChild(m);
    function close(){var h=$('epAiLayoutHost');if(h&&window.ElvadoReact)try{window.ElvadoReact.unmount(h)}catch(e){}m.remove()}
    m.addEventListener('click',function(e){if(e.target===m||e.target.closest('#epAiLx'))close()});
    ensure().then(function(R){R.mountAiAssistant($('epAiLayoutHost'),{initialTask:'layout',onApplyLayout:function(sections){onApply(sections);close()}})}).catch(function(e){close();fail(e)});
  }
  window.EpAi={ensure:ensure,hub:hub,newsAssistant:newsAssistant,layoutAssistant:layoutAssistant};
})();
