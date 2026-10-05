/* Umfragen (CMS → Inhalte): anlegen, bearbeiten, Ergebnis ansehen, zurücksetzen, löschen, als CSV laden.
   Server: polls_list, poll_save, poll_delete, poll_reset, polls_export (cms/api.php, cms/lib/polls.php). Anzeige per Widget „Umfrage (CMS)“. */
(function(){
  'use strict';
  var polls=[],cur=null;
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function toInput(v){var m=/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(String(v||''));return m?m[1]+'T'+m[2]:''}
  var STATE={open:['Läuft','var(--good)'],closed:['Beendet','var(--bad)'],upcoming:['Geplant','#fbbf24']};
  async function load(){
    try{var d=await cmsApi('polls_list');polls=d.polls||[];window.__cmsPolls=polls.map(function(p){return {id:p.id,question:p.question}});renderList();if(cur)select(cur.id,true)}catch(e){}
  }
  function renderList(){
    var h=polls.slice().reverse().map(function(p){var st=STATE[p.state]||STATE.open;
      return '<div class="builder-item'+(cur&&cur.id===p.id?' on':'')+'" onclick="PollsManager.select(\''+esc(p.id)+'\')"><i class="fas fa-square-poll-vertical" style="color:var(--accent)"></i><div class="grow"><b>'+esc(p.question)+'</b><small><span style="color:'+st[1]+'">'+st[0]+'</span> · '+p.total+' Stimme'+(p.total===1?'':'n')+'</small></div></div>'}).join('');
    $('plList').innerHTML=h||'<div class="hint" style="padding:10px">Noch keine Umfragen.</div>';
  }
  function fill(p){
    $('plEmpty').style.display='none';$('plEditor').style.display='';
    $('plQuestion').value=p.question||'';$('plOptions').value=(p.options||[]).map(function(o){return o.text}).join('\n');
    $('plShow').value=p.show_results||'after_vote';$('plMultiple').checked=!!p.multiple;$('plStarts').value=toInput(p.starts_at);$('plEnds').value=toInput(p.ends_at);$('plClosed').checked=!!p.closed;
    var st=p.id?(STATE[p.state]||STATE.open):['Neu','var(--accent)'];$('plState').textContent=st[0];$('plState').style.color=st[1];
    $('plExport').style.display=p.id?'':'none';if(p.id)$('plExport').href='api.php?action=polls_export&id='+encodeURIComponent(p.id)+'&_tok='+encodeURIComponent(cmsToken());
    $('plHint').textContent=p.id?'Auf der Website anzeigen: Design → Widgets → „Umfrage (CMS)“ hinzufügen und diese Umfrage wählen (ID '+p.id+').':'';
    renderResults(p);
  }
  function renderResults(p){
    var total=(p.options||[]).reduce(function(n,o){return n+(+o.votes||0)},0);$('plTotal').textContent=p.id?'· '+total+' Stimme'+(total===1?'':'n'):'';
    $('plResults').innerHTML=(p.options||[]).map(function(o){var pc=total?Math.round(1000*o.votes/total)/10:0;
      return '<div class="pl-row"><div class="pl-lbl"><span>'+esc(o.text)+'</span><b>'+(+o.votes||0)+' · '+pc+' %</b></div><div class="pl-bar"><i style="width:'+pc+'%"></i></div></div>'}).join('')||'';
  }
  function select(id,quiet){
    var p=polls.find(function(x){return x.id===id});if(!p)return;cur=p;if(!quiet)renderList();fill(p);renderList();
  }
  function create(){cur={id:'',question:'',options:[],show_results:'after_vote',multiple:false};renderList();fill({question:'',options:[],show_results:'after_vote'});$('plQuestion').focus()}
  async function save(){
    var body={id:cur&&cur.id||'',question:$('plQuestion').value,options:$('plOptions').value,show_results:$('plShow').value,multiple:$('plMultiple').checked,
      starts_at:$('plStarts').value,ends_at:$('plEnds').value,closed:$('plClosed').checked};
    try{var d=await cmsApi('poll_save',body);cur={id:d.poll.id};await load();select(d.poll.id)}catch(e){if(window.CmsToast)CmsToast.show(e.message||'Speichern fehlgeschlagen','error')}
  }
  async function reset(){
    if(!cur||!cur.id||!confirm('Alle Stimmen dieser Umfrage zurücksetzen? Das lässt sich nicht rückgängig machen.'))return;
    try{await cmsApi('poll_reset',{id:cur.id});await load()}catch(e){}
  }
  async function remove(){
    if(!cur||!cur.id||!confirm('Umfrage endgültig löschen?'))return;
    try{await cmsApi('poll_delete',{id:cur.id});cur=null;$('plEditor').style.display='none';$('plEmpty').style.display='';await load()}catch(e){}
  }
  window.PollsManager={load:load,select:select,create:create,save:save,reset:reset,remove:remove};
  window.addEventListener('load',function(){setTimeout(function(){var app=$('cmsApp');if(app&&app.style.display!=='none')load()},3000)});
})();
