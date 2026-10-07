/* Sichtbarkeits-Check: erklärt, warum Seiten, Beiträge und Menüpunkte nicht erscheinen (nur lesen; api.php?action=visibility_report). */
(function(){
  'use strict';
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  async function open(boxId){
    var box=document.getElementById(boxId||'visCheckBox');if(!box)return;box.hidden=false;box.innerHTML='<div class="hint">Prüfe Sichtbarkeit …</div>';
    try{
      var d=await cmsApi('visibility_report'),it=d.items||[],bl=it.filter(function(x){return x.level==='blocker'}),inf=it.filter(function(x){return x.level!=='blocker'});
      function row(x){var k={page:'Seite',post:'Beitrag',menu:'Menü'}[x.kind]||x.kind;return '<li><b>'+esc(x.title||x.id)+'</b> <span class="hint">('+k+')</span><br>'+esc(x.problem)+'<br><span class="hint">→ '+esc(x.hint)+'</span></li>'}
      box.innerHTML='<div class="th"><b>Sichtbarkeits-Check</b><button type="button" class="btn-g" data-viscl="1">Schließen</button></div>'
        +'<p class="hint">'+d.summary.pages+' Seiten · '+d.summary.posts+' Beiträge · '+d.summary.blocker+' nicht sichtbar · '+d.summary.info+' Hinweise</p>'
        +(bl.length?'<h4 style="margin:10px 0 4px">Nicht sichtbar</h4><ul class="vis-list">'+bl.map(row).join('')+'</ul>':'<p>✓ Alles Veröffentlichte ist sichtbar.</p>')
        +(inf.length?'<details style="margin-top:10px"><summary>Hinweise ('+inf.length+')</summary><ul class="vis-list">'+inf.map(row).join('')+'</ul></details>':'');
    }catch(e){box.innerHTML='<div class="hint" style="color:var(--bad)">'+esc(e.message||'Prüfung fehlgeschlagen')+'</div>'}
  }
  document.addEventListener('click',function(e){
    var b=e.target.closest&&e.target.closest('[data-vischeck]');if(b){open(b.getAttribute('data-vischeck'));return}
    var c=e.target.closest&&e.target.closest('[data-viscl]');if(c){var bx=c.closest('.vis-box');if(bx)bx.hidden=true}
  });
  window.VisibilityCheck={open:open};
})();
