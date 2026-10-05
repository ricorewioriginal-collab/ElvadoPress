/* Seiten-Zusatzfunktionen: Status (Veröffentlicht/Entwurf/Geplant), SEO-Felder, Duplizieren und frühere Versionen.
   Wird von renderPageEditor()/pageFieldChanged() in cms-app.js aufgerufen; der Server bereinigt alle Werte erneut (lib/publish.php). */
(function(){
  'use strict';
  function $(id){return document.getElementById(id)}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}
  function toInput(v){var m=/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/.exec(String(v||''));return m?m[1]+'T'+m[2]:''}

  async function getApi(qs){
    var r=await fetch(CRON+'?action='+qs+'&_='+Date.now(),{headers:cmsHeaders(false)});
    var d=await r.json().catch(function(){return {status:'error',message:'Ungültige Serverantwort'}});
    if(d.status!=='ok')throw new Error(d.message||'Fehler');return d;
  }
  window.pageStatusLoad=function(p){
    if(!$('peStatus'))return;
    var sched=p.type==='custom'&&p.enabled!==false&&!!p.publish_at;
    $('peStatus').value=p.enabled===false?'draft':(sched?'scheduled':'live');
    var opt=$('peStatus').querySelector('option[value="scheduled"]');if(opt)opt.disabled=p.type!=='custom';
    $('pePublishAt').value=toInput(p.publish_at);
    $('pePublishWrap').style.display=$('peStatus').value==='scheduled'?'':'none';
    $('peMetaTitle').value=p.meta_title||'';$('peMetaDesc').value=p.meta_description||'';$('peNoindex').checked=!!p.noindex;
  };
  window.pageStatusSave=function(p){
    var st=$('peStatus').value;
    if(st==='scheduled'&&p.type!=='custom')st='live';
    p.enabled=st!=='draft';
    if(st==='scheduled'){
      var v=$('pePublishAt').value;
      if(!v){var d=new Date(Date.now()+3600e3);d.setMinutes(0,0,0);var z=function(n){return String(n).padStart(2,'0')};v=d.getFullYear()+'-'+z(d.getMonth()+1)+'-'+z(d.getDate())+'T'+z(d.getHours())+':00';$('pePublishAt').value=v}
      p.publish_at=v.replace('T',' ')+':00';
    }else p.publish_at='';
    $('peEnabled').checked=p.enabled;
    $('pePublishWrap').style.display=st==='scheduled'?'':'none';
    p.meta_title=$('peMetaTitle').value.trim();p.meta_description=$('peMetaDesc').value.trim();p.noindex=$('peNoindex').checked;
  };
  window.pageBadge=function(p){return p.enabled===false?' · <span style="color:var(--bad)">Entwurf</span>':(p.type==='custom'&&p.publish_at?' · <span style="color:#fbbf24">Geplant '+esc(String(p.publish_at).slice(0,16))+'</span>':'')};

  window.duplicateCurrentPage=function(){
    var p=currentPage();if(!p)return;
    if(p.type!=='custom'){alert('Systemseiten können nicht dupliziert werden – lege stattdessen eine eigene Seite an.');return}
    var copy=JSON.parse(JSON.stringify(p));copy.id=uid('page');copy.title=(p.title||p.slug)+' (Kopie)';
    var base=(p.slug||'seite')+'-kopie',slug=base,n=2,have=function(s){return (CMS.pages||[]).some(function(x){return x.slug===s})};
    while(have(slug))slug=base+'-'+(n++);
    copy.slug=slug;copy.enabled=false;copy.publish_at='';
    var i=CMS.pages.findIndex(function(x){return x.id===p.id});CMS.pages.splice(i+1,0,copy);
    CURRENT_PAGE_ID=copy.id;renderPages();
    if(typeof setPublishState==='function')setPublishState(false,'Kopie angelegt – als Entwurf, noch nicht gespeichert');
  };

  window.pageRevisionsOpen=async function(){
    var p=currentPage();if(!p)return;var box=$('pageRevBox'),list=$('pageRevList');box.style.display='';list.textContent='Lädt…';
    try{
      var d=await getApi('pages_revisions&id='+encodeURIComponent(p.id));
      var r=d.revisions||[];
      list.innerHTML=r.length?'<table class="tr-table"><thead><tr><th>Gespeichert</th><th>Von</th><th>Titel</th><th></th></tr></thead><tbody>'+r.map(function(x){
        return '<tr><td>'+esc(x.saved_at)+'</td><td>'+esc(x.user)+'</td><td>'+esc(x.title)+'</td><td style="text-align:right"><button class="btn-g" onclick="pageRevisionLoad('+x.index+')"><i class="fas fa-rotate-left"></i> Laden</button></td></tr>';
      }).join('')+'</tbody></table><div class="hint" style="margin-top:8px">„Laden“ übernimmt die Version in den Editor; sie gilt erst nach „Alle Seiten speichern“.</div>':'Noch keine früheren Versionen – sie entstehen, sobald du eine Seite änderst und speicherst.';
    }catch(e){list.textContent=e.message||'Laden fehlgeschlagen'}
  };
  window.pageRevisionLoad=async function(i){
    var p=currentPage();if(!p)return;
    try{
      var d=await getApi('pages_revision&id='+encodeURIComponent(p.id)+'&index='+i);
      var snap=d.page,idx=CMS.pages.findIndex(function(x){return x.id===p.id});
      if(idx<0||!snap)return;snap.id=p.id;CMS.pages[idx]=snap;renderPages();
      if(typeof setPublishState==='function')setPublishState(false,'Version geladen – noch nicht gespeichert');
    }catch(e){alert(e.message||'Laden fehlgeschlagen')}
  };
})();
