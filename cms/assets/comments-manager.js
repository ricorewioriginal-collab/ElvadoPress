/* Kommentare moderieren (CMS → Kommentare): Filter, Suche, Freigeben, Antworten, Löschen, Sammelaktionen.
   Server: comments_admin_list, comment_approve, comment_delete, comment_reply in cms/api.php. */
(function(){
  'use strict';
  var all=[],flt='all',sel={};
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function toast(m,bad){if(window.cmsToast)window.cmsToast(m,bad);else alert(m)}
  function when(s){var d=new Date(String(s).replace(' ','T'));return isNaN(d)?String(s||''):d.toLocaleString('de-DE',{dateStyle:'medium',timeStyle:'short'})}
  function pending(c){return c.status==='pending'}
  async function load(){
    try{var d=await cmsApi('comments_admin_list');all=d.comments||[];sel={};render()}
    catch(e){var h=$('cmList');if(h)h.innerHTML='<div class="hint" style="color:var(--bad)">'+esc(e.message||'Laden fehlgeschlagen')+'</div>'}
  }
  function rows(){
    var q=(($('cmQuery')||{}).value||'').trim().toLowerCase();
    return all.filter(function(c){
      if(flt==='pending'&&!pending(c))return false;if(flt==='approved'&&pending(c))return false;
      return !q||(String(c.name||'')+' '+String(c.text||'')+' '+String(c.article_title||'')).toLowerCase().indexOf(q)>=0;
    });
  }
  function render(){
    var host=$('cmList');if(!host)return;
    var np=all.filter(pending).length;
    $('cmNAll').textContent='('+all.length+')';$('cmNPending').textContent='('+np+')';$('cmNApproved').textContent='('+(all.length-np)+')';
    document.querySelectorAll('#cmFilter [data-f]').forEach(function(b){b.classList.toggle('on',b.dataset.f===flt)});
    var list=rows();
    host.innerHTML=list.length?list.map(function(c){
      var id=c.id;
      return '<div class="cm-row'+(pending(c)?' pend':'')+'" id="cmr'+id+'"><label class="cm-chk"><input type="checkbox" data-id="'+id+'"'+(sel[id]?' checked':'')+' onchange="CommentsManager.pick('+id+',this.checked)"></label>'
        +'<div class="cm-main"><div class="cm-head"><b>'+esc(c.name||'Anonym')+'</b>'+(c.is_staff?' <span class="cm-tag">Redaktion</span>':'')+(pending(c)?' <span class="cm-tag warn">Ausstehend</span>':'')+(c.parent_id?' <span class="hint">Antwort</span>':'')
        +' <span class="hint">· '+esc(when(c.created_at))+' · zu „'+esc(c.article_title||'(Beitrag gelöscht)')+'“</span></div>'
        +'<div class="cm-text">'+esc(c.text||'')+'</div>'
        +'<div class="cm-act">'+(pending(c)?'<button type="button" class="btn-g" onclick="CommentsManager.approve('+id+')"><i class="fas fa-check"></i> Freigeben</button>':'')
        +'<button type="button" class="btn-g" onclick="CommentsManager.reply('+id+')"><i class="fas fa-reply"></i> Antworten</button>'
        +'<button type="button" class="btn-g" onclick="CommentsManager.remove('+id+')"><i class="fas fa-trash"></i> Löschen</button></div>'
        +'<div class="cm-reply" id="cmrep'+id+'" hidden><textarea class="fc w-100" rows="3" maxlength="2000" placeholder="Antwort der Redaktion …"></textarea><div style="margin-top:6px"><button type="button" class="btn-a" onclick="CommentsManager.sendReply('+id+')"><i class="fas fa-paper-plane"></i> Antworten</button></div></div>'
        +'</div></div>';
    }).join(''):'<div class="hint">'+(all.length?'Keine Kommentare für diese Auswahl.':'Noch keine Kommentare.')+'</div>';
    var a=$('cmAll');if(a)a.checked=false;
  }
  function find(id){return all.find(function(c){return c.id===id})}
  async function approve(id){try{await cmsApi('comment_approve',{id:id});var c=find(id);if(c)c.status='approved';render();toast('Kommentar freigegeben ✓')}catch(e){toast(e.message,true)}}
  async function remove(id){
    var c=find(id),n=all.filter(function(x){return x.parent_id===id}).length;
    if(!confirm('Kommentar von „'+(c?c.name:'')+'“ endgültig löschen?'+(n?'\nDie '+n+' Antwort(en) bleiben bestehen.':'')))return;
    try{await cmsApi('comment_delete',{id:id});all=all.filter(function(x){return x.id!==id});delete sel[id];render();toast('Kommentar gelöscht ✓')}catch(e){toast(e.message,true)}
  }
  function reply(id){var b=$('cmrep'+id);if(b){b.hidden=!b.hidden;if(!b.hidden)b.querySelector('textarea').focus()}}
  async function sendReply(id){
    var c=find(id),b=$('cmrep'+id);if(!c||!b)return;var t=b.querySelector('textarea').value.trim();if(!t)return toast('Bitte einen Text eingeben',true);
    try{await cmsApi('comment_reply',{article_id:c.article_id,parent_id:c.id,text:t});toast('Antwort gesendet ✓');load()}catch(e){toast(e.message,true)}
  }
  function pick(id,on){if(on)sel[id]=1;else delete sel[id]}
  function toggleAll(on){sel={};if(on)rows().forEach(function(c){sel[c.id]=1});document.querySelectorAll('#cmList [data-id]').forEach(function(i){i.checked=on})}
  async function bulk(kind){
    var ids=Object.keys(sel).map(Number);if(!ids.length)return toast('Bitte zuerst Kommentare auswählen',true);
    if(kind==='delete'&&!confirm(ids.length+' Kommentar(e) endgültig löschen?'))return;
    var ok=0,bad=0;
    for(var i=0;i<ids.length;i++){try{await cmsApi(kind==='approve'?'comment_approve':'comment_delete',{id:ids[i]});ok++}catch(e){bad++}}
    toast(ok+' '+(kind==='approve'?'freigegeben':'gelöscht')+(bad?', '+bad+' fehlgeschlagen':''),bad>0);load();
  }
  function filter(f){flt=f;sel={};render()}
  window.CommentsManager={load:load,render:render,filter:filter,approve:approve,remove:remove,reply:reply,sendReply:sendReply,pick:pick,toggleAll:toggleAll,bulk:bulk};
})();
