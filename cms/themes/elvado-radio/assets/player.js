/* ElvadoPress Radio: Player-Leiste, Sender wechseln, „Jetzt läuft“/Verlauf per Endpunkt cms/radio.php aktualisieren. Ohne Fremd-Bibliotheken. */
(function(){
  'use strict';
  var bar=document.querySelector('[data-radio-bar]');if(!bar)return;
  var audio=new Audio();audio.preload='none';
  var st={id:bar.dataset.station,stream:bar.dataset.stream,name:bar.dataset.name,logo:bar.dataset.logo||''};
  var endpoint=bar.dataset.endpoint,poll=Math.max(10,parseInt(bar.dataset.poll,10)||15)*1000,timer=null,last=null;
  function $all(sel){return Array.prototype.slice.call(document.querySelectorAll(sel))}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function lineOf(d){var l=[d.artist,d.title].filter(Boolean).join(' – ');return l||'Live-Stream'}
  function setPlaying(on){
    document.body.classList.toggle('is-playing',on);
    $all('[data-radio-play]').forEach(function(b){var r=b.closest('[data-radio-root],[data-radio-bar]');var mine=!r||!r.dataset.station||r.dataset.station===st.id;
      if(r)r.classList.toggle('is-playing',on&&mine);b.setAttribute('aria-pressed',on&&mine?'true':'false')});
    try{sessionStorage.setItem('rrw_radio_playing',on?st.id:'')}catch(e){}
  }
  function apply(d){
    last=d;var line=lineOf(d);
    $all('[data-np]').forEach(function(el){
      var root=el.closest('[data-radio-root],[data-radio-bar]');if(root&&root.dataset.station&&root.dataset.station!==st.id&&!root.matches('[data-radio-bar]'))return;
      var k=el.dataset.np;
      if(k==='cover'){var u=d.cover||st.logo;if(u&&el.getAttribute('src')!==u)el.setAttribute('src',u)}
      else if(k==='artist')el.textContent=d.artist||(d.title?'':st.name);
      else if(k==='title')el.textContent=d.title||(d.artist?'':'Live-Stream');
      else if(k==='show')el.textContent=d.show||'';
      else if(k==='listeners')el.textContent=d.listeners!=null?d.listeners+' Hörer':'';
      else if(k==='line')el.textContent=line;
      else if(k==='station')el.textContent=st.name;
    });
    $all('[data-radio-live]').forEach(function(el){el.classList.toggle('on',!!(d.artist||d.title)&&document.body.classList.contains('is-playing'))});
    var h=document.querySelector('[data-radio-history]');
    if(h&&Array.isArray(d.history)&&d.history.length)h.innerHTML=d.history.map(function(t){var img=t.cover||st.logo;return '<div class="hist-item">'+(img?'<img loading="lazy" src="'+esc(img)+'" alt="">':'')+'<div><b>'+esc(t.artist)+'</b><br>'+esc(t.title)+'</div></div>'}).join('');
    if('mediaSession' in navigator&&window.MediaMetadata){try{navigator.mediaSession.metadata=new MediaMetadata({title:d.title||st.name,artist:d.artist||st.name,album:st.name,artwork:(d.cover||st.logo)?[{src:d.cover||st.logo}]:[]})}catch(e){}}
  }
  function refresh(){
    if(document.hidden&&audio.paused)return;
    fetch(endpoint+'?a=now&station='+encodeURIComponent(st.id),{credentials:'same-origin'}).then(function(r){return r.json()}).then(function(d){if(d&&d.status==='ok'&&d.ok!==false)apply(d)}).catch(function(){});
  }
  function schedule(){clearInterval(timer);timer=setInterval(refresh,poll)}
  function play(){
    if(!st.stream)return;
    if(audio.src!==st.stream){audio.src=st.stream}
    var p=audio.play();if(p&&p.catch)p.catch(function(){setPlaying(false)});
  }
  function toggle(){if(audio.paused)play();else{audio.pause();audio.removeAttribute('src');audio.load()}}
  function select(data){
    var same=data.station===st.id;
    st={id:data.station,stream:data.stream,name:data.name,logo:data.logo||''};
    $all('[data-radio-bar],[data-radio-root]').forEach(function(r){if(r.matches('[data-radio-bar]')){r.dataset.station=st.id;r.dataset.stream=st.stream;r.dataset.name=st.name;r.dataset.logo=st.logo}});
    if(!same){audio.pause();audio.removeAttribute('src');last=null}
    play();refresh();
  }
  audio.addEventListener('playing',function(){setPlaying(true);refresh()});
  audio.addEventListener('pause',function(){setPlaying(false)});
  audio.addEventListener('error',function(){setPlaying(false)});
  document.addEventListener('click',function(e){
    var sel=e.target.closest('[data-radio-select]');if(sel){e.preventDefault();select(sel.dataset);return}
    var b=e.target.closest('[data-radio-play]');if(!b)return;e.preventDefault();
    var r=b.closest('[data-radio-root]');if(r&&r.dataset.station&&r.dataset.station!==st.id){select(r.dataset);return}
    toggle();
  });
  var vol=document.querySelector('[data-radio-volume]');
  if(vol){var v=NaN;try{v=parseInt(localStorage.getItem('rrw_radio_vol'),10)}catch(e){}if(!isNaN(v)){vol.value=v}audio.volume=(parseInt(vol.value,10)||80)/100;
    vol.addEventListener('input',function(){audio.volume=vol.value/100;try{localStorage.setItem('rrw_radio_vol',vol.value)}catch(e){}})}
  if('mediaSession' in navigator){try{navigator.mediaSession.setActionHandler('play',play);navigator.mediaSession.setActionHandler('pause',function(){audio.pause()})}catch(e){}}
  refresh();schedule();
  document.addEventListener('visibilitychange',function(){if(!document.hidden)refresh()});
})();
