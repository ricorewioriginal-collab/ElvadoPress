/* Rückmeldung bei Aktionen im CMS: zeigt bei jedem Speichern, Löschen, Zurücksetzen, Aktualisieren usw. einen Hinweis
   („Wird gespeichert…“ → „Gespeichert“ / Fehlermeldung) und setzt den angeklickten Button in einen Lade-Zustand.
   Funktioniert zentral über alle Aufrufe an api.php (fetch), ohne die einzelnen Bereiche anzufassen. */
(function(){
  'use strict';
  if(window.CmsToast)return;
  var origFetch=window.fetch?window.fetch.bind(window):null;if(!origFetch)return;

  /* ── Hinweise ── */
  var box=null;
  function ensureBox(){
    if(box&&document.body.contains(box))return box;
    box=document.createElement('div');box.id='cmsToasts';box.setAttribute('role','status');box.setAttribute('aria-live','polite');
    document.body.appendChild(box);return box;
  }
  var ICON={progress:'fa-spinner fa-spin',success:'fa-circle-check',error:'fa-triangle-exclamation',warn:'fa-circle-exclamation',info:'fa-circle-info'};
  function make(type,text){
    var el=document.createElement('div');el.className='cms-toast';
    el.innerHTML='<i class="fas"></i><span class="ct-t"></span><button type="button" class="ct-x" aria-label="Schließen">×</button>';
    el.querySelector('.ct-x').addEventListener('click',function(){remove(el)});
    ensureBox().appendChild(el);set(el,type,text);
    while(box.children.length>5)box.removeChild(box.firstChild);
    return el;
  }
  function set(el,type,text,ms){
    el.className='cms-toast ct-'+type;
    el.querySelector('i').className='fas '+(ICON[type]||ICON.info);
    el.querySelector('.ct-t').textContent=text;
    clearTimeout(el._t);
    var life=ms!=null?ms:(type==='progress'?0:type==='error'?8000:type==='warn'?6000:3200);
    if(life>0)el._t=setTimeout(function(){remove(el)},life);
  }
  function remove(el){clearTimeout(el._t);if(!el.parentNode)return;el.classList.add('ct-out');setTimeout(function(){if(el.parentNode)el.parentNode.removeChild(el)},220)}
  function show(text,type,ms){var el=make(type||'info',text);if(ms!=null)set(el,type||'info',text,ms);return el}

  /* ── Klick-Kontext (für Lade-Zustand am Button und „Aktualisiert“) ── */
  var last=null;
  document.addEventListener('click',function(e){
    var el=e.target&&e.target.closest?e.target.closest('button,a,[onclick],[role="button"]'):null;
    last=el?{el:el,t:Date.now(),used:false}:null;
  },true);
  function recent(){return last&&Date.now()-last.t<900&&last.el.isConnected?last:null}
  var busyN=new WeakMap();
  function busyOn(el){var n=(busyN.get(el)||0)+1;busyN.set(el,n);el.classList.add('cms-busy');el.setAttribute('aria-busy','true')}
  function busyOff(el){var n=(busyN.get(el)||1)-1;busyN.set(el,n);if(n<=0){el.classList.remove('cms-busy');el.removeAttribute('aria-busy')}}
  function isRefreshButton(el){
    if(!el||el.closest('.tab,.tabs,.cms-new-pop,#cmsSearch'))return false;
    var s=((el.textContent||'')+' '+(el.title||'')+' '+(el.getAttribute('aria-label')||'')+' '+((el.querySelector('i')||{}).className||'')).toLowerCase();
    return /aktualisier|neu laden|prüf|scan|synchron|fa-rotate|fa-arrows-rotate|fa-sync/.test(s);
  }
  function refreshWords(el){
    var s=((el.textContent||'')+' '+(el.title||'')).toLowerCase();
    if(/prüf/.test(s))return ['Prüfung läuft…','Prüfung abgeschlossen'];
    if(/scan|synchron/.test(s))return ['Wird ausgeführt…','Abgeschlossen'];
    return ['Wird aktualisiert…','Aktualisiert'];
  }

  /* ── Texte je Aktion ── */
  var SECTION={portal:'Website',branding:'Branding',social:'Social',apps:'Apps',pages:'Seiten',menus:'Menüs',widgets:'Widgets',widget_areas:'Widget-Bereiche',widget_inactive:'Widgets',legal:'Rechtliches',feed_sources:'Feeds',rss:'RSS',theme:'Theme',brands:'Domains & Branding',assistant:'KI-Assistent',alexa:'Alexa-Skill',services:'Dienste',comments:'Kommentare',storage:'Speicher',backup:'Backup-Einstellungen',news_categories:'Kategorien',seo:'SEO',core_network:'Core-Netzwerk',plugins:'Plugins',navigation:'Navigation'};
  var DONE={
    news_save:'Beitrag gespeichert',news_quick_edit:'Beitrag gespeichert',news_delete:'In den Papierkorb verschoben',news_restore:'Beitrag wiederhergestellt',news_delete_permanent:'Endgültig gelöscht',
    news_duplicate:'Beitrag dupliziert',news_bulk_action:'Sammelaktion ausgeführt',news_category_rename:'Kategorie umbenannt',news_revision_restore:'Version wiederhergestellt',news_import:'Import abgeschlossen',
    news_tag_change:'Schlagwort geändert',news_thumbnail_upload:'Bild hochgeladen',media_upload:'Hochgeladen',media_library_upload:'Hochgeladen',media_library_delete:'Datei gelöscht',branding_upload:'Bild hochgeladen',
    branding_assign:'Zugewiesen',brand_asset_assign:'Zugewiesen',theme_upload:'Theme hochgeladen',theme_install:'Theme installiert',forum_categories_save:'Kategorien gespeichert',forum_report_dismiss:'Meldung erledigt',forum_mod_delete_post:'Beitrag gelöscht',forum_mod:'Gespeichert',theme_activate:'Theme aktiviert',theme_delete:'Theme gelöscht',theme_customize_save:'Anpassungen gespeichert',
    theme_reset_areas:'Widget-Bereiche zurückgesetzt',plugin_upload:'Plugin hochgeladen',plugin_toggle:'Plugin-Status geändert',plugin_delete:'Plugin gelöscht',backup_create:'Backup erstellt',
    backup_restore:'Backup wiederhergestellt',database_config_save:'Datenbank-Einstellungen gespeichert',database_push:'In die Datenbank übertragen',database_pull:'Aus der Datenbank übernommen',
    comment_approve:'Kommentar freigegeben',comment_delete:'Kommentar gelöscht',comment_reply:'Antwort gesendet',user_add:'Benutzer angelegt',user_update:'Benutzer gespeichert',user_delete:'Benutzer gelöscht',
    profile_update_self:'Profil gespeichert',core_add:'Station hinzugefügt',core_remove:'Station entfernt',import_legacy:'Altdaten übernommen',content_import:'Inhalte übernommen',content_sync:'Inhalte synchronisiert',
    seo_rebuild:'SEO-Dateien neu erzeugt',maintenance_save:'Wartungsmodus gespeichert',redirects_save:'Weiterleitungen gespeichert',redirects_log_clear:'404-Protokoll geleert',
    privacy_comments_delete:'Kommentare gelöscht',poll_save:'Umfrage gespeichert',community_config_save:'Community-Einstellungen gespeichert',community_member_op:'Mitglied aktualisiert',poll_delete:'Umfrage gelöscht',poll_reset:'Stimmen zurückgesetzt',forms_update:'Einsendungen aktualisiert',alexa_token_reset:'Token erneuert',alexa_stats_clear:'Statistik zurückgesetzt',apps_stats_clear:'Statistik zurückgesetzt',apps_geo_update:'Geodaten aktualisiert',
    directory_admin_save:'Verzeichnis-Einstellungen gespeichert',local_auth_setup:'Zugang gespeichert',assistant_test:'Test abgeschlossen'
  };
  var SILENT={wp_plugins:1,wp_plugin_search:1,wp_plugin_install:1,wp_plugin_upload:1,wp_plugin_activate:1,wp_plugin_deactivate:1,wp_plugin_delete:1,database_test:1,database_drivers:1,wp_translations:1,wp_updates:1,wp_update_apply:1,wp_admin_menu:1,wp_admin_page:1,wp_admin_ajax:1,wp_admin_rest:1,wp_core_status:1,wp_themes:1,wp_theme_search:1,wp_theme_install:1,wp_theme_upload:1,wp_theme_activate:1,wp_theme_deactivate:1,wp_theme_delete:1,wp_theme_preview:1,social_feed:1,social_profile:1,social_post:1,social_delete:1,social_like:1,social_follow:1,forum_overview:1,forum_topics:1,forum_topic:1,forum_topic_create:1,forum_reply:1,forum_edit:1,forum_delete:1,forum_report:1,forum_reports:1,theme_directory:1,member_register:1,member_login:1,member_logout:1,member_me:1,member_update:1,member_delete:1,member_reset_request:1,member_reset:1,member_profile:1,login:1,logout:1,access:1,local_auth_status:1,news_track_view:1,notifications_mark_read:1,comment_submit:1,app_error:1,app_listen:1,alexa_stat:1,directory_report:1,assistant_chat:1,assistant_send:1,assistant_voice:1,directory_search:1,directory_frame:1,directory_meta:1,directory_preview:1,directory_random:1};
  function words(action,section,method){
    if(action==='save'){var n=SECTION[section]||'Einstellungen';return [n+' wird gespeichert…',n+' gespeichert']}
    var done=DONE[action];
    if(!done)return method==='POST'?['Wird ausgeführt…','Erledigt']:['Wird ausgeführt…','Fertig'];
    var del=/löschen|entfernt|gelöscht|Papierkorb|geleert|zurückgesetzt/.test(done);
    var up=/hochgeladen|Import|Upload/.test(done);
    return [del?'Wird gelöscht…':(up?'Wird hochgeladen…':(/zurückgesetzt/.test(done)?'Wird zurückgesetzt…':'Wird gespeichert…')),done];
  }

  /* ── fetch-Hülle ── */
  window.fetch=function(input,init){
    var url=typeof input==='string'?input:(input&&input.url)||'';
    if(!/api\.php\?/.test(url))return origFetch(input,init);
    var m=/[?&]action=([a-z0-9_]+)/.exec(url),action=m?m[1]:'';
    var method=String((init&&init.method)||(input&&input.method)||'GET').toUpperCase();
    var click=recent();
    var mode=null;
    if(action&&!SILENT[action]){
      if(method==='POST')mode='post';
      else if(click&&!click.used&&isRefreshButton(click.el))mode='refresh';
    }
    var btn=click&&!click.el.closest('.tab,.tabs')?click.el:null;
    if(btn)busyOn(btn);
    if(!mode){
      var p0=origFetch(input,init);
      if(btn)p0.then(function(){busyOff(btn)},function(){busyOff(btn)});
      return p0;
    }
    var section='';
    try{if(init&&typeof init.body==='string'){var b=JSON.parse(init.body);section=(b&&b.section)||''}}catch(e){}
    var w;
    if(mode==='refresh'){click.used=true;w=refreshWords(click.el)}else w=words(action,section,method);
    var toast=make('progress',w[0]);toast._kind=action;
    var p=origFetch(input,init);
    p.then(function(r){
      if(btn)busyOff(btn);
      r.clone().json().then(function(d){
        if(r.status===401){remove(toast);return}                         // Sitzung abgelaufen: das CMS zeigt selbst die Anmeldung
        if(d&&d.status==='conflict')set(toast,'warn',d.message||'Wurde zwischenzeitlich geändert – bitte neu laden');
        else if(!r.ok||(d&&d.status==='error'))set(toast,'error',(d&&d.message)||('Fehlgeschlagen (HTTP '+r.status+')'));
        else{
          set(toast,'success',w[1]);toast._doneAt=Date.now();
          // Mehrere Speicherungen kurz hintereinander (z. B. „Alle Seiten speichern“) zu einem Hinweis zusammenfassen
          if(action==='save'){
            var prev=[].slice.call(box.children).reverse().find(function(x){return x!==toast&&x._kind==='save'&&x._doneAt&&Date.now()-x._doneAt<2500});
            if(prev){set(prev,'success','Gespeichert');prev._doneAt=Date.now();toast.parentNode&&toast.parentNode.removeChild(toast)}
          }
        }
      }).catch(function(){
        if(r.ok)set(toast,'success',w[1]);else set(toast,'error','Fehlgeschlagen (HTTP '+r.status+')');
      });
    },function(){
      if(btn)busyOff(btn);
      set(toast,'error','Keine Verbindung zum Server – bitte erneut versuchen');
    });
    return p;
  };

  window.CmsToast={show:show};
})();
