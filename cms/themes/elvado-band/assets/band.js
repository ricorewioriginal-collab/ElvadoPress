/* ElvadoPress Band: Player (YouTube/Vimeo/Spotify) laden erst nach Klick – vorher gibt es keine Verbindung zu Fremdanbietern. */
(function(){
  'use strict';
  document.addEventListener('click',function(e){
    var b=e.target.closest('[data-embed]');if(!b)return;
    var src=b.getAttribute('data-embed');if(!/^https:\/\/(www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/|open\.spotify\.com\/embed\/)/.test(src))return;
    var f=document.createElement('iframe');f.src=src+(src.indexOf('?')<0&&src.indexOf('youtube')>0?'?autoplay=1':'');f.title=b.getAttribute('data-title')||'Player';f.allow='autoplay; encrypted-media; fullscreen; picture-in-picture';f.setAttribute('allowfullscreen','');f.loading='lazy';
    b.parentNode.replaceChild(f,b);
  });
})();
