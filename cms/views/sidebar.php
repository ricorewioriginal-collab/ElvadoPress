<div id="cmsNavBackdrop" onclick="cmsCloseNav()"></div>
    <div class="tabs">
      <div class="cms-nav-search-wrap">
        <i class="fas fa-magnifying-glass"></i>
        <input id="cmsNavSearch" type="text" placeholder="Bereich suchen…" oninput="cmsFilterNav(this.value)" autocomplete="off">
      </div>
      <div id="cmsNavEmpty" class="hint" style="display:none;padding:8px 9px">Keine Treffer.</div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-gauge-high"></i><span>Dashboard</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab on" data-tab="overview" onclick="cmsTab('overview',this)"><i class="fas fa-house"></i>Startseite</button>
          <button class="tab" data-tab="plugins-upd" onclick="cmsTab('plugins',this);window.PluginManager?.load();window.WpPlugins?.load();window.WpPlugins?.tab('inst');setTimeout(()=>document.getElementById('wpUpd')?.scrollIntoView({block:'center'}),300)"><i class="fas fa-arrows-rotate"></i>Aktualisierungen</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-thumbtack"></i><span>Beiträge</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="news" onclick="cmsTab('news',this)"><i class="fas fa-newspaper"></i>Alle Beiträge</button>
          <button class="tab" data-tab="news" onclick="cmsTab('news',this);setTimeout(()=>window.NewsMagazine?.newArticle(),350)"><i class="fas fa-pen"></i>Erstellen</button>
          <button class="tab" data-tab="news" onclick="cmsTab('news',this);setTimeout(()=>document.getElementById('newsCategoryChips')?.scrollIntoView({behavior:'smooth',block:'center'}),250)"><i class="fas fa-folder-tree"></i>Kategorien</button>
          <button class="tab" data-tab="tags" onclick="cmsTab('tags',this);window.TagsManager?.load()"><i class="fas fa-tags"></i>Schlagwörter</button>
          <button class="tab" data-tab="feeds" onclick="cmsTab('feeds',this)"><i class="fas fa-rss"></i>Feeds &amp; RSS</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-photo-film"></i><span>Medien</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="media" onclick="cmsTab('media',this);window.MediaHub?.load()"><i class="fas fa-photo-film"></i>Mediathek</button>
          <button class="tab" data-tab="media" onclick="cmsTab('media',this);window.MediaHub?.load();document.getElementById('mediaHubUpload')?.click()"><i class="fas fa-upload"></i>Datei hinzufügen</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-file-lines"></i><span>Seiten</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="pages" onclick="cmsTab('pages',this)"><i class="fas fa-file-lines"></i>Alle Seiten</button>
          <button class="tab" data-tab="pages" onclick="cmsTab('pages',this);cmsAddPage()"><i class="fas fa-file-circle-plus"></i>Erstellen</button>
          <button class="tab" data-tab="contents" onclick="cmsTab('contents',this);window.ContentsManager?.load()"><i class="fas fa-layer-group"></i>Alle Inhalte</button>
        </div>
      </div>
      <div class="tab-group single">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-comments"></i><span>Kommentare</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="comments" onclick="cmsTab('comments',this)"><i class="fas fa-comments"></i>Kommentare</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-paintbrush"></i><span>Design</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="themes" onclick="cmsTab('themes',this);window.DesignHub?.load()"><i class="fas fa-brush"></i>Themes</button>
          <button class="tab" data-tab="baukasten" onclick="cmsTab('baukasten',this);window.HomeBuilder?.load()"><i class="fas fa-table-cells-large"></i>Homepage-Baukasten</button>
          <button class="tab" data-tab="menus" onclick="cmsTab('menus',this)"><i class="fas fa-bars"></i>Menüs</button>
          <button class="tab" data-tab="widgets" onclick="cmsTab('widgets',this)"><i class="fas fa-puzzle-piece"></i>Widgets</button>
          <button class="tab" data-tab="branding" onclick="cmsTab('branding',this)"><i class="fas fa-palette"></i>Branding</button>
        </div>
      </div>
      <div class="tab-group single" data-feature="radio" hidden>
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-radio"></i><span>Radio</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="radio" onclick="cmsTab('radio',this);window.RadioAdmin?.load()"><i class="fas fa-tower-broadcast"></i>Sender &amp; Sendeplan</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-plug"></i><span>Plugins</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="plugins" onclick="cmsTab('plugins',this);window.PluginManager?.load();window.WpPlugins?.load();window.WpPlugins?.tab('inst')"><i class="fas fa-plug"></i>Installierte Plugins</button>
          <button class="tab" data-tab="plugins" onclick="cmsTab('plugins',this);window.PluginManager?.load();window.WpPlugins?.load();window.WpPlugins?.tab('new')"><i class="fas fa-plus"></i>Plugin hinzufügen</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-users"></i><span>Benutzer</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button id="tab-users" class="tab" data-tab="users" onclick="cmsTab('users',this);window.UsersManager?.load()"><i class="fas fa-users-gear"></i>Alle Benutzer</button>
          <button class="tab" data-tab="profile" onclick="cmsTab('profile',this);window.loadProfile?.()"><i class="fas fa-user-gear"></i>Profil</button>
          <button class="tab" data-tab="community" onclick="cmsTab('community',this);window.CommunityManager?.load()"><i class="fas fa-people-group"></i>Community</button>
          <button id="tab-activity" class="tab" data-tab="activity" onclick="cmsTab('activity',this);window.ActivityLog?.load()"><i class="fas fa-clock-rotate-left"></i>Aktivitätslog</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-toolbox"></i><span>Werkzeuge</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="wptools" onclick="cmsTab('wptools',this)"><i class="fas fa-screwdriver-wrench"></i>Verfügbare Werkzeuge</button>
          <button class="tab" data-tab="wptools" onclick="cmsTab('wptools',this);document.getElementById('wptImport')?.scrollIntoView({block:'center'})"><i class="fas fa-file-import"></i>Daten importieren</button>
          <button class="tab" data-tab="wptools" onclick="cmsTab('wptools',this);document.getElementById('wptExport')?.scrollIntoView({block:'center'})"><i class="fas fa-file-export"></i>Daten exportieren</button>
          <button class="tab" data-tab="wptools" onclick="cmsTab('wptools',this);WpTools.health()"><i class="fas fa-heart-pulse"></i>Website-Zustand</button>
          <button class="tab" data-tab="maintenance" onclick="cmsTab('maintenance',this);window.ToolsManager?.loadMaint()"><i class="fas fa-person-digging"></i>Wartungsmodus</button>
          <button class="tab" data-tab="redirects" onclick="cmsTab('redirects',this);window.ToolsManager?.loadRules()"><i class="fas fa-route"></i>Weiterleitungen &amp; 404</button>
          <button class="tab" data-tab="privacy" onclick="cmsTab('privacy',this);window.ToolsManager?.loadPrivacy()"><i class="fas fa-user-shield"></i>Datenschutz</button>
          <button class="tab" data-tab="forms" onclick="cmsTab('forms',this);window.FormsManager?.load()"><i class="fas fa-inbox"></i>Einsendungen</button>
          <button class="tab" data-tab="polls" onclick="cmsTab('polls',this);window.PollsManager?.load()"><i class="fas fa-square-poll-vertical"></i>Umfragen</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-sliders"></i><span>Einstellungen</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('general')"><i class="fas fa-gear"></i>Allgemein</button>
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('writing')"><i class="fas fa-pen-nib"></i>Schreiben</button>
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('reading')"><i class="fas fa-book-open"></i>Lesen</button>
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('discussion')"><i class="fas fa-comment-dots"></i>Diskussion</button>
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('media')"><i class="fas fa-image"></i>Medien</button>
          <button class="tab" data-tab="settings" onclick="cmsTab('settings',this);WpSettings.open('permalinks')"><i class="fas fa-link"></i>Permalinks</button>
          <button class="tab" data-pack="ricorewi-radio" data-tab="portal" onclick="cmsTab('portal',this)"><i class="fas fa-sliders"></i>Website-Inhalte</button>
          <button class="tab" data-tab="seo" onclick="cmsTab('seo',this);window.SystemManager?.loadSeo()"><i class="fas fa-magnifying-glass-chart"></i>SEO &amp; Suche</button>
          <button class="tab" data-tab="legal" onclick="cmsTab('legal',this)"><i class="fas fa-scale-balanced"></i>Rechtliches</button>
          <button class="tab" data-pack-app="ricorewi-radio" data-tab="assistant" onclick="cmsTab('assistant',this);window.AssistantManager?.render()"><i class="fas fa-wand-magic-sparkles"></i>KI-Assistent</button>
          <button class="tab" data-pack="ricorewi-radio" data-tab="brands" onclick="cmsTab('brands',this);window.BrandsManager?.render()"><i class="fas fa-globe"></i>Domains &amp; Branding</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-mobile-screen-button"></i><span>Apps &amp; Kanäle</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="apps" onclick="cmsTab('apps',this)"><i class="fas fa-mobile-screen"></i>Apps</button>
          <button class="tab" data-pack-app="ricorewi-radio" data-tab="alexa" onclick="cmsTab('alexa',this);window.AlexaManager?.render()"><i class="fab fa-amazon"></i>Alexa-Skill</button>
          <button class="tab" data-pack="ricorewi-radio" data-tab="directory" onclick="cmsTab('directory',this);window.DirectoryManager?.render()"><i class="fas fa-tower-broadcast"></i>Radioverzeichnis<span id="dmTabBadge" class="dm-badge" hidden></span></button>
          <button class="tab" data-tab="social" onclick="cmsTab('social',this)"><i class="fas fa-share-nodes"></i>Social</button>
        </div>
      </div>
      <div class="tab-group">
        <div class="tab-group-title" role="button" tabindex="0"><i class="fas fa-screwdriver-wrench"></i><span>System</span><i class="fas fa-chevron-down tg-chev"></i></div>
        <div class="tab-group-body">
          <button class="tab" data-tab="services" onclick="cmsTab('services',this)"><i class="fas fa-plug"></i>Verbundene Dienste</button>
          <button class="tab" data-pack="ricorewi-radio" data-tab="network" onclick="cmsTab('network',this)"><i class="fas fa-tower-broadcast"></i>Sender-Netzwerk</button>
          <button class="tab" data-tab="contentfiles" onclick="cmsTab('contentfiles',this);window.SystemManager?.loadContent()"><i class="fas fa-folder-tree"></i>Datei-Ablage</button>
          <button class="tab" data-tab="database" onclick="cmsTab('database',this);window.SystemManager?.loadDatabase()"><i class="fas fa-database"></i>Datenbank</button>
          <button class="tab" data-tab="backups" onclick="cmsTab('backups',this);window.SystemManager?.loadBackup()"><i class="fas fa-clock-rotate-left"></i>Backups</button>
          <button class="tab" data-tab="architecture" onclick="cmsTab('architecture',this)"><i class="fas fa-diagram-project"></i>Systemübersicht</button>
          <button class="tab" data-tab="system" hidden onclick="cmsTab('system',this);window.StandaloneManager?.load()"><i class="fas fa-sliders"></i>Betriebsart &amp; Produktname</button>
        </div>
      </div>
          <button type="button" id="cmsFoldBtn" class="cms-fold-btn" title="Menü einklappen"><i class="fas fa-circle-chevron-left"></i><span>Menü einklappen</span></button>
    </div>
