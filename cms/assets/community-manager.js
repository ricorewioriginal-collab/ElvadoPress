/* Community (CMS → Benutzer → Community): Einstellungen und Mitgliederverwaltung (nur Administratoren).
   Server: community_admin_get, community_config_save, community_member_op (cms/api.php, cms/lib/community.php). */
(function(){
  'use strict';
  var members=[],cfg=null,cats=[];
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function note(t,bad){var e=$('cmMsg');if(e){e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}}
  var ST={active:['aktiv','var(--good)'],pending:['wartet','#fbbf24'],banned:['gesperrt','var(--bad)']};
  async function load(){
    try{var d=await cmsApi('community_admin_get');cfg=d.config;members=d.members||[];fill();render();loadForum()}catch(e){note(e.message||'Laden fehlgeschlagen',true)}
  }
  function fill(){
    if(!cfg||!$('cmEnabled'))return;
    $('cmEnabled').checked=!!cfg.enabled;$('cmReg').value=cfg.registration;$('cmMinPw').value=cfg.min_password;$('cmDays').value=cfg.session_days;
    $('cmForum').checked=!!cfg.forum;$('cmSocial').checked=!!cfg.social;$('cmRules').value=cfg.rules||'';
    var s=$('cmState');s.className='publish-state '+(cfg.enabled?'':'bad');s.innerHTML=cfg.enabled?'<i class="fas fa-circle-check"></i> Community aktiv':'<i class="fas fa-power-off"></i> Community aus';
  }
  async function saveConfig(){
    var c={enabled:$('cmEnabled').checked,registration:$('cmReg').value,min_password:+$('cmMinPw').value||8,session_days:+$('cmDays').value||30,forum:$('cmForum').checked,social:$('cmSocial').checked,rules:$('cmRules').value};
    try{var d=await cmsApi('community_config_save',{config:c});cfg=d.config;fill();note('Gespeichert.')}catch(e){note(e.message||'Speichern fehlgeschlagen',true)}
  }
  function render(){
    var host=$('cmList');if(!host)return;
    var q=(($('cmFilter')||{}).value||'').trim().toLowerCase();
    var rows=members.filter(function(m){return !q||(m.username+' '+m.name+' '+m.email).toLowerCase().indexOf(q)>=0});
    $('cmCount').textContent='('+members.length+')';
    host.innerHTML=rows.length?'<table class="tr-table"><thead><tr><th>Mitglied</th><th>E-Mail</th><th>Status</th><th>Seit</th><th></th></tr></thead><tbody>'+rows.map(function(m){
      var st=ST[m.status]||ST.active,id=esc(m.id);
      var btn=function(op,label,icon,danger){return '<button class="btn-g" data-id="'+id+'" data-op="'+op+'" onclick="CommunityManager.op(this.dataset.id,this.dataset.op)" title="'+label+'"'+(danger?' style="color:var(--bad)"':'')+'><i class="fas '+icon+'"></i></button>'};
      return '<tr><td><b>'+esc(m.name)+'</b><div class="hint" style="font-size:.74rem">@'+esc(m.username)+(m.role==='moderator'?' · Moderator':'')+'</div></td><td>'+esc(m.email)+'</td><td><span style="color:'+st[1]+'">'+st[0]+'</span></td><td>'+esc((m.created_at||'').slice(0,10))+'</td><td style="text-align:right;white-space:nowrap">'
        +(m.status==='pending'?btn('approve','Freischalten','fa-check'):'')
        +(m.status==='banned'?btn('unban','Entsperren','fa-unlock'):(m.status==='active'?btn('ban','Sperren','fa-ban',true):''))
        +(m.role==='moderator'?btn('member','Zum normalen Mitglied machen','fa-user'):btn('moderator','Zum Moderator machen','fa-user-shield'))
        +btn('delete','Konto löschen','fa-trash',true)+'</td></tr>';
    }).join('')+'</tbody></table>':'<div class="hint">'+(members.length?'Kein Mitglied passt zum Filter.':'Noch keine Mitglieder.')+'</div>';
  }
  async function op(id,o){
    var m=members.find(function(x){return x.id===id});
    if(o==='delete'&&!confirm('Konto von „'+(m?m.username:id)+'“ endgültig löschen? Beiträge bleiben anonymisiert erhalten.'))return;
    if(o==='ban'&&!confirm('„'+(m?m.username:id)+'“ sperren? Alle Sitzungen werden beendet.'))return;
    try{await cmsApi('community_member_op',{id:id,op:o});await load()}catch(e){note(e.message||'Fehlgeschlagen',true)}
  }
  /* Forum: Kategorien und Meldungen */
  async function loadForum(){
    var c=$('foCats');if(!c)return;
    try{var o=await cmsApi('forum_overview');cats=(o.categories||[]).map(function(x){return {id:x.id,name:x.name,desc:x.desc||'',topics:x.topics}});drawCats()}
    catch(e){cats=[];c.innerHTML='<div class="hint">Das Forum ist ausgeschaltet oder die Community inaktiv – zuerst oben aktivieren und speichern.</div>';$('foReports').innerHTML='';return}
    try{var r=await cmsApi('forum_reports');drawReports(r.reports||[])}catch(e){}
  }
  function drawCats(){
    $('foCats').innerHTML=cats.length?cats.map(function(c,i){return '<div style="display:grid;grid-template-columns:1fr 2fr auto;gap:8px;margin-bottom:6px"><input class="fc" value="'+esc(c.name)+'" maxlength="60" placeholder="Name" oninput="CommunityManager.catSet('+i+',\'name\',this.value)"><input class="fc" value="'+esc(c.desc)+'" maxlength="200" placeholder="Beschreibung" oninput="CommunityManager.catSet('+i+',\'desc\',this.value)"><button class="btn-g" style="color:var(--bad)" onclick="CommunityManager.catDel('+i+')" title="Entfernen"><i class="fas fa-trash"></i></button></div>'}).join(''):'<div class="hint">Noch keine Kategorien – füge die erste hinzu.</div>';
  }
  function drawReports(list){
    $('foRepCount').textContent='('+list.length+')';
    $('foReports').innerHTML=list.length?list.map(function(r){return '<div style="border:1px solid var(--line);border-radius:10px;padding:9px 11px;margin-bottom:8px"><b>'+esc(r.topic_title)+'</b> <span class="hint">· Beitrag von '+esc(r.post_author)+' · gemeldet von '+esc(r.by)+(r.reason?' („'+esc(r.reason)+'“)':'')+'</span><div style="white-space:pre-wrap;margin:6px 0">'+esc(r.excerpt)+'</div><button class="btn-g" onclick="CommunityManager.repDismiss(\''+esc(r.id)+'\')"><i class="fas fa-check"></i> Erledigt</button> <button class="btn-g" style="color:var(--bad)" onclick="CommunityManager.repDelete(\''+esc(r.topic)+'\',\''+esc(r.post)+'\')"><i class="fas fa-trash"></i> Beitrag löschen</button></div>'}).join(''):'<div class="hint">Keine offenen Meldungen.</div>';
  }
  function catAdd(){cats.push({id:'',name:'',desc:''});drawCats()}
  function catSet(i,k,v){if(cats[i])cats[i][k]=v}
  function catDel(i){var c=cats[i];if(c&&c.topics&&!confirm('Kategorie „'+c.name+'“ entfernen? Ihre '+c.topics+' Themen wandern in die erste verbleibende Kategorie.'))return;cats.splice(i,1);drawCats()}
  async function catSave(){try{var d=await cmsApi('forum_categories_save',{categories:cats});cats=d.categories;await loadForum()}catch(e){note(e.message||'Speichern fehlgeschlagen',true)}}
  async function repDismiss(id){try{await cmsApi('forum_report_dismiss',{id:id});await loadForum()}catch(e){note(e.message||'Fehlgeschlagen',true)}}
  async function repDelete(t,p){if(!confirm('Beitrag endgültig löschen?'))return;try{await cmsApi('forum_mod_delete_post',{topic:t,post:p});await loadForum()}catch(e){note(e.message||'Fehlgeschlagen',true)}}
  window.CommunityManager={load:load,render:render,saveConfig:saveConfig,op:op,catAdd:catAdd,catSet:catSet,catDel:catDel,catSave:catSave,repDismiss:repDismiss,repDelete:repDelete};
})();
