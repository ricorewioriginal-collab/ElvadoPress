'use strict';
// Elvado SEO – Vorschau im Beitragseditor: Suchergebnis (Google-Stil) und Social-Karte, mit Längenhinweisen. Rein clientseitig; nutzt die vorhandenen Felder des Editors.
(function(){
  if(window.__elvadoSeoPreview)return;window.__elvadoSeoPreview=true;
  var esc=function(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});};
  var $=function(id){return document.getElementById(id);};
  var val=function(id){var e=$(id);return e?String(e.value||'').trim():'';};
  function cut(t,n){t=t.replace(/\s+/g,' ').trim();return t.length>n?t.slice(0,n-1).replace(/\s+\S*$/,'')+'…':t;}
  function badge(len,min,max){var c=len===0?'#8a8fa8':(len<min||len>max?'#e0a100':'#22a06b');return '<span style="color:'+c+';font-weight:700">'+len+' Zeichen</span> <span style="opacity:.7">(empfohlen '+min+'–'+max+')</span>';}
  function build(){
    var t=$('newsSeoTitle');if(!t||$('elvSeoPrev'))return;
    var box=document.createElement('div');box.id='elvSeoPrev';box.style.cssText='margin-top:12px;display:grid;gap:10px';
    var anchor=$('newsNoindex');anchor=anchor&&anchor.closest('label')?anchor.closest('label'):t.parentNode.lastElementChild;
    anchor.parentNode.insertBefore(box,anchor.nextSibling);
    update();
  }
  function update(){
    var box=$('elvSeoPrev');if(!box)return;
    var title=val('newsSeoTitle')||val('newsTitle')||'Titel des Beitrags';
    var desc=val('newsSeoDescription')||val('newsExcerpt')||'Ohne SEO-Beschreibung nutzt die Website den Teaser oder den Anfang des Textes.';
    var slug=val('newsSlug')||'beitrag';var host=location.host;
    var img=val('newsImage');
    var tl=val('newsSeoTitle').length,dl=val('newsSeoDescription').length;
    box.innerHTML=
      '<div style="font-size:.78rem;font-weight:700;opacity:.8">Vorschau Suchergebnis</div>'+
      '<div style="background:#fff;color:#202124;border-radius:10px;padding:10px 12px;font-family:Arial,sans-serif">'+
        '<div style="font-size:.74rem;color:#4d5156;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+esc(host)+' › '+esc(slug)+'</div>'+
        '<div style="font-size:1.05rem;color:#1a0dab;line-height:1.3;margin:2px 0">'+esc(cut(title,60))+'</div>'+
        '<div style="font-size:.84rem;color:#4d5156;line-height:1.4">'+esc(cut(desc,160))+'</div></div>'+
      '<div style="font-size:.76rem">Titel: '+badge(tl,30,60)+' &nbsp; Beschreibung: '+badge(dl,70,160)+'</div>'+
      '<div style="font-size:.78rem;font-weight:700;opacity:.8">Vorschau beim Teilen</div>'+
      '<div style="background:#fff;color:#202124;border:1px solid #dadce0;border-radius:10px;overflow:hidden;max-width:420px;font-family:Arial,sans-serif">'+
        (img?'<div style="height:150px;background:#e8eaed url(\''+esc(img).replace(/'/g,'%27')+'\') center/cover"></div>':'<div style="height:60px;background:#e8eaed;display:grid;place-items:center;font-size:.74rem;color:#5f6368">Kein Vorschaubild (Titelbild des Beitrags)</div>')+
        '<div style="padding:8px 12px"><div style="font-size:.7rem;color:#5f6368;text-transform:uppercase">'+esc(host)+'</div>'+
        '<div style="font-size:.95rem;font-weight:700;line-height:1.3">'+esc(cut(title,70))+'</div>'+
        '<div style="font-size:.8rem;color:#5f6368">'+esc(cut(desc,110))+'</div></div></div>';
  }
  document.addEventListener('input',function(e){if(e.target&&/^news(SeoTitle|SeoDescription|Title|Excerpt|Image|Slug)$/.test(e.target.id||''))update();},true);
  new MutationObserver(function(){build();update();}).observe(document.body,{childList:true,subtree:true});
  build();
})();
