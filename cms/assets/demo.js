/* Demo-Betrieb: Leiste mit Rücksetz-Zähler und Zugangsdaten; in der Verwaltung Anmeldung vorausfüllen (und mit ?demo=1 automatisch anmelden). Nur aktiv, wenn window.RRW_DEMO gesetzt ist. */
(function(){
  var D=window.RRW_DEMO;if(!D||!D.user)return;
  var inCms=/\/cms\/(index\.php)?$/.test(location.pathname);
  var off=(D.now||0)*1000-Date.now();
  function fmt(ms){var s=Math.max(0,Math.ceil(ms/1000));return ('0'+Math.floor(s/60)).slice(-2)+':'+('0'+(s%60)).slice(-2);}
  function esc(s){return String(s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
  function bar(){
    if(document.getElementById('rrwDemoBar'))return;
    var st=document.createElement('style');
    st.textContent='#rrwDemoBar{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;display:flex;flex-wrap:wrap;gap:6px 14px;align-items:center;justify-content:center;padding:8px 12px;background:#0b1220;color:#e8edf7;font:13px/1.35 system-ui,sans-serif;border-top:2px solid #2fb8ff;box-shadow:0 -4px 18px rgba(0,0,0,.35)}'
      +'#rrwDemoBar b{color:#2fb8ff}#rrwDemoBar a{color:#021018;background:#2fb8ff;border-radius:7px;padding:3px 10px;text-decoration:none;font-weight:700}#rrwDemoBar a.g{background:transparent;color:#cfe9ff;border:1px solid rgba(255,255,255,.3)}'
      +'#rrwDemoBar code{background:rgba(255,255,255,.1);padding:1px 6px;border-radius:5px}#rrwDemoT{font-variant-numeric:tabular-nums;font-weight:700}body{padding-bottom:64px!important}';
    document.head.appendChild(st);
    var d=document.createElement('div');d.id='rrwDemoBar';d.setAttribute('role','status');
    d.innerHTML='<span><b>Demo</b> · Testinstanz, Daten werden zurückgesetzt in <span id="rrwDemoT">--:--</span></span>'
      +'<span>Zugang: <code>'+esc(D.user)+'</code> / <code>'+esc(D.password)+'</code></span>'
      +'<a href="/cms/?demo=1">Verwaltung</a><a class="g" href="/">Website</a><a class="g" href="/demo/">Info</a>';
    document.body.appendChild(d);
  }
  function tick(){
    var t=document.getElementById('rrwDemoT');if(!t)return;
    var left=D.reset_at*1000-(Date.now()+off);
    if(left>0){t.textContent=fmt(left);return;}
    t.textContent='wird zurückgesetzt …';
    if(tick.done)return;tick.done=true;
    var last=0;try{last=+sessionStorage.getItem('rrwDemoReload')||0;}catch(e){}
    if(Date.now()-last<5000)return;
    try{sessionStorage.setItem('rrwDemoReload',String(Date.now()));}catch(e){}
    setTimeout(function(){
      if(inCms&&location.search.indexOf('demo=1')<0)location.search=(location.search?location.search+'&':'?')+'demo=1';else location.reload();
    },1500);
  }
  function login(){
    var f=document.getElementById('cmsLoginForm'),u=document.getElementById('cmsLoginUser'),p=document.getElementById('cmsLoginPass');
    if(!f||!u||!p)return;
    var n=0,auto=location.search.indexOf('demo=1')>=0,iv=setInterval(function(){
      n++;var box=document.getElementById('cmsLogin');
      if(box&&box.style.display!=='none'&&box.offsetParent!==null){
        u.value=D.user;p.value=D.password;clearInterval(iv);
        if(auto){var b=document.getElementById('cmsLoginSubmit');if(b)b.click();}
      }else if(n>60)clearInterval(iv);
    },250);
  }
  function start(){bar();tick();setInterval(tick,1000);if(inCms)login();}
  if(document.body)start();else document.addEventListener('DOMContentLoaded',start);
})();
