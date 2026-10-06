/* Admin-Design: Neon (Standard), Hell, Dunkel, System. Wahl pro Benutzer (Server, api admin_prefs_*) mit localStorage als Zwischenspeicher gegen Aufblitzen, Schalter in der Kopfleiste und im Profil.
   Das Attribut data-admin-theme wird schon im <head> gesetzt (index.php), damit nichts aufblitzt. */
(function(){
  var KEY='ep_admin_theme',MODES=[['neon','Neon (Standard)','sw-neon'],['light','Hell (klassisch)','sw-light'],['dark','Dunkel','sw-dark'],['auto','System folgen','sw-auto']];
  var mq=window.matchMedia?window.matchMedia('(prefers-color-scheme: dark)'):null;
  function get(){try{var v=localStorage.getItem(KEY);return MODES.some(function(m){return m[0]===v})?v:'neon'}catch(e){return'neon'}}
  function effective(m){return m==='auto'?(mq&&mq.matches?'dark':'light'):m}
  function apply(m){var e=effective(m),r=document.documentElement;if(e==='neon')r.removeAttribute('data-admin-theme');else r.setAttribute('data-admin-theme',e);r.setAttribute('data-admin-mode',m)}
  function set(m){try{localStorage.setItem(KEY,m)}catch(e){}apply(m);refresh();push(m)}
  function push(m){try{if(window.cmsApi)window.cmsApi('admin_prefs_save',{theme:m}).catch(function(){})}catch(e){}}
  function pull(n){   // Wahl des Benutzers vom Server holen (gilt dann auf allen Geräten); noch keine gespeichert → lokale Wahl hochladen
    if(!window.cmsApi){if(n<20)setTimeout(function(){pull(n+1)},500);return}
    window.cmsApi('admin_prefs_get').then(function(d){var t=d&&d.theme;if(t&&MODES.some(function(m){return m[0]===t})){if(t!==get()){try{localStorage.setItem(KEY,t)}catch(e){}apply(t);refresh()}}else if(get()!=='neon')push(get())}).catch(function(){})
  }
  function refresh(){var m=get();document.querySelectorAll('[data-theme-mode]').forEach(function(b){b.classList.toggle('on',b.getAttribute('data-theme-mode')===m)});var t=document.getElementById('cmsThemeBtn');if(t)t.title='Design der Verwaltung: '+(MODES.filter(function(x){return x[0]===m})[0]||MODES[0])[1]}
  function btns(cls){return MODES.map(function(m){return '<button type="button" data-theme-mode="'+m[0]+'">'+(cls==='pop'?'<span class="sw '+m[2]+'"></span>'+m[1]:'<span class="prev '+m[2]+'"></span>'+m[1])+'</button>'}).join('')}
  function wire(root){root.querySelectorAll('[data-theme-mode]').forEach(function(b){b.addEventListener('click',function(){set(b.getAttribute('data-theme-mode'));var p=document.getElementById('cmsThemePop');if(p)p.hidden=true})})}
  function mountButton(){
    var bar=document.querySelector('.cms-navbar-actions');if(!bar||document.getElementById('cmsThemeMenu'))return;
    var w=document.createElement('span');w.className='cms-theme-menu';w.id='cmsThemeMenu';
    w.innerHTML='<button type="button" id="cmsThemeBtn" class="btn btn-sm btn-outline-light" aria-haspopup="true"><i class="fas fa-circle-half-stroke"></i></button><div id="cmsThemePop" class="cms-theme-pop" hidden>'+btns('pop')+'</div>';
    bar.insertBefore(w,bar.firstChild);wire(w);
    w.querySelector('#cmsThemeBtn').addEventListener('click',function(e){e.stopPropagation();var p=document.getElementById('cmsThemePop');p.hidden=!p.hidden});
    document.addEventListener('click',function(e){var p=document.getElementById('cmsThemePop');if(p&&!w.contains(e.target))p.hidden=true});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){var p=document.getElementById('cmsThemePop');if(p)p.hidden=true}});
    refresh();
  }
  function mountProfile(){
    var card=document.querySelector('#panel-profile .card');if(!card||document.getElementById('profileThemeBox'))return;
    var d=document.createElement('div');d.id='profileThemeBox';d.style.cssText='margin-top:22px;padding-top:16px;border-top:1px solid var(--border)';
    d.innerHTML='<label class="news-lbl">Design der Verwaltung</label><div class="hint" style="margin-bottom:8px">Gilt für dein Konto auf allen Geräten. „System folgen“ richtet sich nach Hell/Dunkel deines Geräts.</div><div class="theme-choice">'+btns('card')+'</div>';
    card.appendChild(d);wire(d);refresh();
  }
  function boot(){mountButton();mountProfile();setTimeout(function(){mountButton();mountProfile()},700);setTimeout(function(){mountButton();mountProfile()},2000)}
  if(mq&&mq.addEventListener)mq.addEventListener('change',function(){if(get()==='auto')apply('auto')});
  apply(get());
  pull(0);
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
  window.AdminTheme={get:get,set:set};
})();
