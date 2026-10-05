/* Design: gemeinsamer Bereich für Portal-Designs (CMS-Themes) und WordPress-Themes – Anzeige "Aktives Design", Filter, Suche nach Art. */
(function(){
  'use strict';
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function refresh(){
    var box=$('dgActive');if(!box)return;
    var wp=window.WpThemes&&WpThemes.state?WpThemes.state():{front:false,name:''};
    var portal=window.ThemeManager&&ThemeManager.activeName?ThemeManager.activeName():'';
    if(wp.front&&wp.name){
      box.innerHTML='<div class="dg-active-in"><i class="fab fa-wordpress"></i><div><div class="hint">Aktives Design</div><b>'+esc(wp.name)+'</b> <span class="dg-badge wp">WordPress-Theme</span><div class="hint">Deine Website wird mit diesem WordPress-Theme ausgeliefert.</div></div><button class="btn-g" onclick="WpThemes.off()"><i class="fas fa-rotate-left"></i> Zurück zum Portal-Design</button></div>';
    }else{
      box.innerHTML='<div class="dg-active-in"><i class="fas fa-radio"></i><div><div class="hint">Aktives Design</div><b>'+esc(portal||'…')+'</b> <span class="dg-badge">Portal-Design</span><div class="hint">Radio-Player, Community und Layout bleiben erhalten.</div></div></div>';
    }
  }
  function mark(sel,btn){document.querySelectorAll(sel).forEach(function(b){b.classList.toggle('on',b===btn)})}
  function filter(f,btn){
    var p=$('dgPortal'),w=$('dgWp');if(p)p.style.display=(f==='all'||f==='portal')?'':'none';if(w)w.style.display=(f==='all'||f==='wp')?'':'none';var sb=$('dgSbx');if(sb)sb.style.display=(f==='all'||f==='wp')?'':'none';
    mark('#panel-themes .dg-chips:first-of-type .dg-chip',btn);
  }
  var wpSearched=false;
  function kind(k,btn){
    var a=$('dgFindPortal'),b=$('dgFindWp');if(a)a.style.display=k==='portal'?'':'none';if(b)b.style.display=k==='wp'?'':'none';
    document.querySelectorAll('#themeDir .dg-chip').forEach(function(x){x.classList.toggle('on',x===btn)});
    if(k==='wp'&&!wpSearched&&window.WpThemes){wpSearched=true;WpThemes.search(1)}
  }
  var stT=0;
  /* Sichtbare Rückmeldung zum Hochladen (busy = läuft, ok = fertig, bad = Fehler) */
  function status(msg,kind){
    var b=$('dgUpState');if(!b)return;clearTimeout(stT);
    b.className='dg-status '+(kind||'');b.innerHTML=(kind==='busy'?'<i class="fas fa-spinner fa-spin"></i> ':kind==='ok'?'<i class="fas fa-circle-check"></i> ':'<i class="fas fa-triangle-exclamation"></i> ')+esc(msg);b.hidden=false;
    if(kind==='ok')stT=setTimeout(function(){b.hidden=true},15000);
  }
  document.addEventListener('change',function(e){var t=e.target;if(t&&(t.id==='themeUpload'||t.id==='wtUpload')){var d=document.querySelector('.dg-upload');if(d)d.open=false;var f=t.files&&t.files[0];if(f)status('Datei „'+f.name+'“ gewählt – wird hochgeladen …','busy')}},true);
  function load(force){if(window.ThemeManager)ThemeManager.load(force);if(window.WpThemes)WpThemes.load()}
  window.DesignHub={status:status,refresh:refresh,filter:filter,kind:kind,load:load};
})();
