/* ElvadoPress Creator: Rabattcode kopieren, Countdown, Player nach Klick (YouTube/Vimeo), Teilen. Ohne Fremd-Bibliotheken. */
(function(){
  'use strict';
  document.addEventListener('click',function(e){
    var cb=e.target.closest('[data-copy]');
    if(cb){var t=cb.getAttribute('data-copy'),done=function(){var o=cb.textContent;cb.textContent='Kopiert ✓';setTimeout(function(){cb.textContent=o},1600)};
      if(navigator.clipboard&&navigator.clipboard.writeText)navigator.clipboard.writeText(t).then(done,function(){});
      else{var ta=document.createElement('textarea');ta.value=t;document.body.appendChild(ta);ta.select();try{document.execCommand('copy');done()}catch(x){}ta.remove()}
      return}
    var b=e.target.closest('[data-embed]');
    if(b){var src=b.getAttribute('data-embed');if(!/^https:\/\/(www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/)/.test(src))return;
      var f=document.createElement('iframe');f.src=src+(src.indexOf('youtube')>0?'?autoplay=1':'');f.title=b.getAttribute('data-title')||'Video';f.allow='autoplay; encrypted-media; fullscreen; picture-in-picture';f.setAttribute('allowfullscreen','');f.loading='lazy';b.parentNode.replaceChild(f,b);return}
    if(e.target.closest('[data-share]')){var d={title:document.title,url:location.href};if(navigator.share)navigator.share(d).catch(function(){})}
  });
  var sh=document.querySelector('[data-share]');if(sh&&navigator.share)sh.hidden=false;
  var cd=document.querySelectorAll('[data-countdown]');
  function tick(){
    var now=Date.now();cd.forEach(function(el){
      var t=Date.parse(el.getAttribute('data-countdown'));if(isNaN(t))return;var s=Math.max(0,Math.floor((t-now)/1000));
      var v={d:Math.floor(s/86400),h:Math.floor(s%86400/3600),m:Math.floor(s%3600/60),s:s%60};
      Object.keys(v).forEach(function(k){var n=el.querySelector('[data-u="'+k+'"]');if(n)n.textContent=String(v[k]).padStart(k==='d'?1:2,'0')});
      if(s===0)el.setAttribute('data-done','1');
    });
  }
  if(cd.length){tick();setInterval(tick,1000)}
})();
