'use strict';
// WordPress-Meldungen (admin_notices) der aktiven WordPress-Plugins, z. B. der Spruch von „Hello Dolly“: abgeschotteter Rahmen oben in der Verwaltung.
// Der Rahmen hat keinen Zugriff auf die Verwaltung (sandbox ohne allow-same-origin); er meldet nur seine Höhe per postMessage zurück.
(function(){
  if(window.WpNotices)return;
  var box=null,frame=null,busy=false;
  var tok=function(){try{return sessionStorage.getItem('anmacha_session_token')||localStorage.getItem('anmacha_session_token')||'';}catch(e){return '';}};
  function hide(){if(box)box.style.cssText='display:none';if(frame)frame.removeAttribute('src');}
  function show(url){
    box=box||document.getElementById('wpNotices');if(!box)return;
    if(!frame){frame=document.createElement('iframe');frame.title='Meldungen von WordPress-Plugins';frame.setAttribute('sandbox','allow-scripts allow-popups');frame.style.cssText='width:100%;border:0;height:0;display:block';box.appendChild(frame);}
    box.style.cssText='margin:0 0 12px;border-radius:12px;overflow:hidden;border:1px solid rgba(128,128,128,.25)';frame.src=url;
  }
  function refresh(){
    if(busy||!tok())return;busy=true;
    fetch('/cms/api.php?action=wp_admin_notices',{headers:{'X-AnMaCha-Token':tok()}}).then(function(r){return r.json();}).then(function(d){
      busy=false;if(d&&d.status==='ok'&&d.frame)show(d.frame);else hide();
    }).catch(function(){busy=false;});
  }
  window.addEventListener('message',function(e){
    if(!frame||e.source!==frame.contentWindow)return;
    var h=e.data&&+e.data.rrwWpNotices;if(h>0&&h<2000)frame.style.height=h+'px';
  });
  window.WpNotices={refresh:refresh};
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',refresh);else refresh();
})();
