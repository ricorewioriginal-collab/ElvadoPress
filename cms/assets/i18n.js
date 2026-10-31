/* Sprache der Verwaltung (Deutsch/Englisch). Die Oberfläche ist auf Deutsch geschrieben; für Englisch übersetzt dieses Skript alle sichtbaren Texte
   (Textknoten, placeholder/title/aria-label, Meldungen von alert/confirm/prompt) anhand des Wörterbuchs assets/i18n/en.json – auch Inhalte, die später
   nachgeladen werden (MutationObserver, gebündelt). Das Wörterbuch wird nur geladen, wenn Englisch gewählt ist; bei Deutsch tut das Skript nichts.
   Wahl pro Benutzer (Server: admin_prefs, lang) mit localStorage als Zwischenspeicher; ohne Wahl gilt die Sprache aus der Einrichtung (data-site-lang). */
(function(){
  'use strict';
  var KEY='ep_admin_lang',root=document.documentElement,LANGS=[['de','Deutsch'],['en','English']];
  function stored(){try{var v=localStorage.getItem(KEY);return v==='de'||v==='en'?v:''}catch(e){return ''}}
  function lang(){return stored()||(root.getAttribute('data-site-lang')==='en'?'en':'de')}
  var L=lang(),DICT=null,SKIP={SCRIPT:1,STYLE:1,TEXTAREA:1,CODE:1,PRE:1,NOSCRIPT:1,IFRAME:1,svg:1},ATTRS=['placeholder','title','aria-label'];
  root.lang=L;
  function tr(s){
    if(!DICT||typeof s!=='string')return s;var t=s.trim();if(!t)return s;
    var v=DICT[t];
    if(v===undefined&&t.indexOf('\n')>=0){var ls=t.split('\n'),hit=false;ls=ls.map(function(x){var y=DICT[x.trim()];if(y!==undefined){hit=true;return y}return x});if(hit)v=ls.join('\n')}
    if(v===undefined&&t.indexOf(' · ')>0){var ps=t.split(' · '),h2=false;ps=ps.map(function(x){var y=DICT[x];if(y!==undefined){h2=true;return y}return x});if(h2)v=ps.join(' · ')}
    if(v===undefined)return s;
    var i=s.indexOf(t);return s.slice(0,i)+v+s.slice(i+t.length);
  }
  function skip(el){for(var e=el;e&&e!==document.body;e=e.parentElement){if(SKIP[e.tagName]||e.isContentEditable||(e.hasAttribute&&e.hasAttribute('data-no-i18n')))return true}return false}
  function doText(n){var p=n.parentElement;if(!p||skip(p))return;var v=n.nodeValue,w=tr(v);if(w!==v)n.nodeValue=w}
  function doEl(el){
    if(el.nodeType!==1||skip(el))return;
    for(var i=0;i<ATTRS.length;i++){var a=el.getAttribute(ATTRS[i]);if(a){var b=tr(a);if(b!==a)el.setAttribute(ATTRS[i],b)}}
    if(el.tagName==='INPUT'&&(el.type==='button'||el.type==='submit')&&el.value){var x=tr(el.value);if(x!==el.value)el.value=x}
  }
  function walk(node){
    if(node.nodeType===3){doText(node);return}
    if(node.nodeType!==1||skip(node))return;
    doEl(node);
    var it=document.createTreeWalker(node,NodeFilter.SHOW_ELEMENT|NodeFilter.SHOW_TEXT,null),n;
    while((n=it.nextNode())){if(n.nodeType===3)doText(n);else doEl(n)}
  }
  var queue=[],sched=false;
  function flush(){sched=false;var q=queue;queue=[];for(var i=0;i<q.length;i++){if(q[i].isConnected!==false)walk(q[i])}}
  function push(n){queue.push(n);if(!sched){sched=true;(window.requestAnimationFrame||setTimeout)(flush)}}
  function start(){
    walk(document.body);if(document.title)document.title=tr(document.title);
    new MutationObserver(function(ms){for(var i=0;i<ms.length;i++){var m=ms[i];
      if(m.type==='childList'){for(var j=0;j<m.addedNodes.length;j++)push(m.addedNodes[j])}
      else if(m.type==='characterData')push(m.target);
      else if(m.type==='attributes')doEl(m.target)}
    }).observe(document.body,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:ATTRS});
    root.classList.remove('ep-i18n-wait');
  }
  // Meldungsfenster übersetzen (Texte aus den Skripten der Verwaltung)
  ['alert','confirm','prompt'].forEach(function(k){var o=window[k];if(typeof o!=='function')return;window[k]=function(msg){var a=Array.prototype.slice.call(arguments);if(L==='en'&&DICT)a[0]=tr(String(msg==null?'':msg));return o.apply(window,a)}});
  function set(l){if(l!=='de'&&l!=='en')return;try{localStorage.setItem(KEY,l)}catch(e){}try{if(window.cmsApi)window.cmsApi('admin_prefs_save',{lang:l}).catch(function(){})}catch(e){}if(l!==L)setTimeout(function(){location.reload()},150)}
  function pull(n){   // Wahl des Benutzers vom Server (gilt auf allen Geräten)
    if(!window.cmsApi){if(n<20)setTimeout(function(){pull(n+1)},500);return}
    window.cmsApi('admin_prefs_get').then(function(d){var l=d&&d.lang;if((l==='de'||l==='en')&&l!==L){try{localStorage.setItem(KEY,l)}catch(e){}location.reload()}}).catch(function(){})
  }
  function picker(){return LANGS.map(function(x){return '<button type="button" data-admin-lang="'+x[0]+'" class="'+(x[0]===L?'on':'')+'">'+x[1]+'</button>'}).join('')}
  function wire(r){r.querySelectorAll('[data-admin-lang]').forEach(function(b){b.addEventListener('click',function(){set(b.getAttribute('data-admin-lang'))})})}
  function mount(){
    var bar=document.querySelector('.cms-navbar-actions');
    if(bar&&!document.getElementById('cmsLangBtn')){var b=document.createElement('button');b.type='button';b.id='cmsLangBtn';b.className='btn btn-sm btn-outline-light';b.setAttribute('data-no-i18n','');b.title=L==='en'?'Admin language':'Sprache der Verwaltung';b.textContent=L==='en'?'EN':'DE';b.addEventListener('click',function(){set(L==='en'?'de':'en')});bar.insertBefore(b,bar.firstChild)}
    var card=document.querySelector('#panel-profile .card');
    if(card&&!document.getElementById('profileLangBox')){var d=document.createElement('div');d.id='profileLangBox';d.style.cssText='margin-top:22px;padding-top:16px;border-top:1px solid var(--border)';
      d.innerHTML='<label class="news-lbl">'+(L==='en'?'Admin language':'Sprache der Verwaltung')+'</label><div class="hint" style="margin-bottom:8px">'+(L==='en'?'Applies to your account on all devices. The website language is set under Settings → General.':'Gilt für dein Konto auf allen Geräten. Die Sprache der Website stellst du unter Einstellungen → Allgemein ein.')+'</div><div class="theme-choice" data-no-i18n>'+picker()+'</div>';
      card.appendChild(d);wire(d)}
  }
  function boot(){mount();setTimeout(mount,700);setTimeout(mount,2000);pull(0)}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  window.AdminLang={get:function(){return L},set:set,t:function(s){return L==='en'?tr(s):s}};
  if(L!=='en')return;
  root.classList.add('ep-i18n-wait');setTimeout(function(){root.classList.remove('ep-i18n-wait')},2500);   // nie dauerhaft verstecken
  var ver=(document.currentScript&&document.currentScript.src.split('?')[1])||'';
  fetch((document.currentScript?document.currentScript.src.replace(/i18n\.js.*$/,''):'assets/')+'i18n/en.json'+(ver?'?'+ver:''),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){DICT=d;
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
  }).catch(function(){root.classList.remove('ep-i18n-wait')});
})();
