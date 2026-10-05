/* Schlagwort-Verwaltung (CMS → Inhalte → Schlagwörter): Übersicht, Umbenennen/Zusammenführen, Löschen. Server: news_tags, news_tag_change in cms/api.php */
(function(){
  'use strict';
  var tags=[];
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function note(t,bad){var el=$('tgMsg');if(!el)return;el.textContent=t||'';el.style.color=bad?'var(--bad)':'var(--muted)'}
  async function load(){
    try{var d=await cmsApi('news_tags');tags=d.tags||[];render()}catch(e){note(e.message||'Laden fehlgeschlagen',true)}
  }
  function render(){
    var host=$('tgList');if(!host)return;
    var q=(($('tgFilter')||{}).value||'').trim().toLowerCase();
    var rows=tags.filter(function(t){return !q||t.tag.toLowerCase().indexOf(q)>=0});
    host.innerHTML=rows.length?'<table class="tr-table"><thead><tr><th>Schlagwort</th><th>Beiträge</th><th>veröffentlicht</th><th></th></tr></thead><tbody>'+rows.map(function(t){
      return '<tr><td><b>'+esc(t.tag)+'</b></td><td>'+t.count+'</td><td>'+t.live+'</td><td style="text-align:right;white-space:nowrap">'
        +'<button class="btn-g" data-t="'+esc(t.tag)+'" onclick="TagsManager.rename(this.dataset.t)"><i class="fas fa-pen"></i> Umbenennen</button> '
        +'<button class="btn-g" data-t="'+esc(t.tag)+'" onclick="TagsManager.remove(this.dataset.t)" title="Überall entfernen"><i class="fas fa-trash"></i></button></td></tr>';
    }).join('')+'</tbody></table>':'<div class="hint">'+(tags.length?'Kein Schlagwort passt zum Filter.':'Noch keine Schlagwörter vergeben.')+'</div>';
  }
  async function change(from,to,msg){
    try{
      var d=await cmsApi('news_tag_change',{from:from,to:to});
      note(msg+' ('+d.changed+' Beitrag/Beiträge'+(d.denied?', '+d.denied+' ohne Berechtigung übersprungen':'')+')');
      await load();
      if(window.NewsMagazine&&NewsMagazine.reload)NewsMagazine.reload();
    }catch(e){note(e.message||'Fehlgeschlagen',true)}
  }
  function rename(t){
    var to=prompt('Neuer Name für „'+t+'“\n(existiert er schon, werden beide zusammengeführt):',t);
    if(to==null)return;to=to.trim();if(!to||to===t)return;
    var exists=tags.some(function(x){return x.tag.toLowerCase()===to.toLowerCase()&&x.tag.toLowerCase()!==t.toLowerCase()});
    if(exists&&!confirm('„'+to+'“ gibt es schon. „'+t+'“ damit zusammenführen?'))return;
    change(t,to,exists?'Zusammengeführt':'Umbenannt');
  }
  function remove(t){if(confirm('Schlagwort „'+t+'“ aus allen Beiträgen entfernen?'))change(t,'','Entfernt')}
  window.TagsManager={load:load,render:render,rename:rename,remove:remove};
})();
