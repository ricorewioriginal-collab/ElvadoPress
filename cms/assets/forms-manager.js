/* Einsendungen (CMS → Inhalte): Kontaktanfragen und Newsletter-Anmeldungen ansehen, als gelesen markieren, löschen, als CSV exportieren.
   Server: forms_list, forms_update, forms_export (cms/api.php, cms/lib/forms.php). */
(function(){
  'use strict';
  var items=[],kind='contact';
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function note(t,bad){var e=$('fmMsg');if(e){e.textContent=t||'';e.style.color=bad?'var(--bad)':'var(--muted)'}}
  function counts(){
    var c={contact:0,newsletter:0},u=0;
    items.forEach(function(i){if(c[i.kind]!=null){c[i.kind]++;if(!i.read)u++}});
    if($('fmCntContact'))$('fmCntContact').textContent='('+c.contact+')';
    if($('fmCntNewsletter'))$('fmCntNewsletter').textContent='('+c.newsletter+')';
    var tab=document.querySelector('.tab[data-tab="forms"]');
    if(tab){var b=tab.querySelector('.dm-badge');if(!b){b=document.createElement('span');b.className='dm-badge';tab.appendChild(b)}b.textContent=u>0?String(u):'';b.hidden=u===0}
  }
  async function load(){
    try{var d=await cmsApi('forms_list');items=d.items||[];counts();render();note('')}catch(e){note(e.message||'Laden fehlgeschlagen',true)}
  }
  function setKind(k){
    kind=k;$('fmTabContact').classList.toggle('on',k==='contact');$('fmTabNewsletter').classList.toggle('on',k==='newsletter');
    var a=$('fmExport');a.href='api.php?action=forms_export&kind='+k+'&_tok='+encodeURIComponent(cmsToken());
    render();
  }
  function render(){
    var host=$('fmList');if(!host)return;var rows=items.filter(function(i){return i.kind===kind});
    setKind._init||(setKind._init=1,$('fmExport').href='api.php?action=forms_export&kind='+kind+'&_tok='+encodeURIComponent(cmsToken()));
    if(!rows.length){host.innerHTML='<div class="hint">Noch keine Einsendungen.</div>';return}
    host.innerHTML=kind==='newsletter'
      ?'<table class="tr-table"><thead><tr><th></th><th>E-Mail</th><th>Angemeldet</th><th>Formular</th></tr></thead><tbody>'+rows.map(function(i){return '<tr><td><input type="checkbox" class="fm-ck" value="'+esc(i.id)+'"></td><td>'+esc(i.data.email)+'</td><td>'+esc(i.created_at)+'</td><td>'+esc(i.widget_title)+'</td></tr>'}).join('')+'</tbody></table>'
      :rows.map(function(i){var d=i.data||{};return '<div class="fm-item'+(i.read?'':' unread')+'"><label class="fm-head"><input type="checkbox" class="fm-ck" value="'+esc(i.id)+'"> <b>'+esc(d.name)+'</b> <a href="mailto:'+esc(d.email)+'">'+esc(d.email)+'</a> <span class="hint">'+esc(i.created_at)+(i.widget_title?' · '+esc(i.widget_title):'')+'</span></label>'
          +(d.subject?'<div class="fm-subj">'+esc(d.subject)+'</div>':'')+'<div class="fm-body">'+esc(d.message).replace(/\n/g,'<br>')+'</div></div>'}).join('');
  }
  async function bulk(op){
    var ids=[].slice.call(document.querySelectorAll('.fm-ck:checked')).map(function(x){return x.value});
    if(!ids.length){note('Bitte Einträge markieren.',true);return}
    if(op==='delete'&&!confirm(ids.length+' Einsendung(en) endgültig löschen?'))return;
    try{await cmsApi('forms_update',{ids:ids,op:op});await load()}catch(e){note(e.message||'Fehlgeschlagen',true)}
  }
  window.FormsManager={load:load,setKind:setKind,bulk:bulk,badge:function(){load()}};
  window.addEventListener('load',function(){setTimeout(function(){var app=$('cmsApp');if(app&&app.style.display!=='none')load()},2500)});
})();
