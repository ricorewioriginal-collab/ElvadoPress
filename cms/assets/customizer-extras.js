/* Live-Customizer: Schnellzugriff (Menüs, Widgets, Startseite) und „In der Vorschau anklicken“ – ein Klick auf Kopfzeile, Fußzeile, Button, Text oder Bild öffnet den passenden Bereich.
   Gilt für den Portal-Customizer (ThemeManager) und den WordPress-Customizer (WpThemes); die Vorschau liegt auf derselben Adresse, daher ist der Zugriff auf ihren Inhalt möglich. */
(function(){
  'use strict';
  var LINKS=[['baukasten','fa-table-cells-large','Startseite'],['menus','fa-bars','Menüs'],['widgets','fa-puzzle-piece','Widgets']];
  var RE={header:/kopf|header|navig|men[üu]|logo|website-info|titel/i,footer:/fuß|footer/i,button:/button|schaltfl/i,text:/typo|schrift|text|font/i,media:/header-medien|bild|hero|medien/i,color:/farb|color/i};
  var pickOn=false;
  function $(id){return document.getElementById(id)}
  function classify(el){
    if(!el||!el.closest)return 'color';
    if(el.closest('footer,.site-footer,[role=contentinfo]'))return 'footer';
    if(el.closest('button,.btn,.button,input[type=submit],a[class*=btn],a[class*=button]'))return 'button';
    if(el.closest('header,.site-header,[role=banner],nav,.menu,[class*=nav]'))return 'header';
    if(el.closest('img,figure,picture,.hero,[class*=hero],[class*=banner]'))return 'media';
    if(el.closest('h1,h2,h3,h4,h5,h6,p,li,blockquote,span'))return 'text';
    return 'color';
  }
  function openFor(cat){
    var re=RE[cat]||RE.color;
    $('themeCustomizer').classList.remove('hide-panel');
    if(window.WpThemes&&WpThemes.czActive&&WpThemes.czActive()&&WpThemes.czOpenMatch)WpThemes.czOpenMatch(re);
    else if(window.ThemeManager&&ThemeManager.openMatch)ThemeManager.openMatch(re);
  }
  function attach(){
    var f=$('themeCustomizerFrame'),doc;try{doc=f&&f.contentDocument}catch(e){return}
    if(!doc||!doc.body||doc.__czx)return;doc.__czx=true;
    var st=doc.createElement('style');st.textContent='.__czh{outline:2px solid #2fb8ff!important;outline-offset:-2px;cursor:pointer!important}';(doc.head||doc.body).appendChild(st);
    var last=null;
    doc.addEventListener('mouseover',function(e){if(!pickOn)return;if(last)last.classList.remove('__czh');last=e.target;if(last.classList)last.classList.add('__czh')},true);
    doc.addEventListener('click',function(e){if(!pickOn)return;e.preventDefault();e.stopPropagation();if(last&&last.classList)last.classList.remove('__czh');openFor(classify(e.target))},true);
  }
  function leave(go){
    var cur=function(){return window.WpThemes&&WpThemes.czActive&&WpThemes.czActive()};
    if(cur())WpThemes.czClose();else if(window.ThemeManager)ThemeManager.closeCustomizer();
    var b=document.querySelector('.tab[data-tab="'+go+'"]');if(b)b.click();
  }
  function setPick(on){pickOn=on;var b=$('czPick');if(b){b.classList.toggle('on',on);b.setAttribute('aria-pressed',on?'true':'false')}if(on)attach()}
  function mount(){
    var side=document.querySelector('.theme-customizer-side');if(!side||side.querySelector('.cz-quick'))return;
    var q=document.createElement('div');q.className='cz-quick';
    q.innerHTML=LINKS.filter(function(l){return document.querySelector('.tab[data-tab="'+l[0]+'"]')}).map(function(l){return '<button type="button" class="btn-g" data-go="'+l[0]+'"><i class="fas '+l[1]+'"></i> '+l[2]+'</button>'}).join('')
      +'<button type="button" class="btn-g" id="czPick" aria-pressed="false" title="Dann in der Vorschau ein Element antippen – der passende Bereich öffnet sich"><i class="fas fa-arrow-pointer"></i> In Vorschau wählen</button>';
    var heading=side.querySelector('.theme-customizer-heading');heading.parentNode.insertBefore(q,heading.nextSibling);
    q.addEventListener('click',function(e){var b=e.target.closest('button');if(!b)return;if(b.id==='czPick'){setPick(!pickOn);return}
      if(!confirm('Den Customizer verlassen und zu „'+b.textContent.trim()+'“ wechseln? Nicht veröffentlichte Änderungen gehen verloren.'))return;leave(b.getAttribute('data-go'))});
    var f=$('themeCustomizerFrame');if(f)f.addEventListener('load',function(){if(pickOn)attach()});
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',mount);else mount();
})();
