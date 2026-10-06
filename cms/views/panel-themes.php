<section id="panel-themes" class="panel">
      <div class="card">
        <div class="th">
          <div><div class="wp-page-title">Themes</div><div class="wp-subtitle">Das Aussehen deiner Website: Designs ansehen, anpassen und mit einem Klick wechseln – jederzeit rückgängig zu machen.</div></div>
          <div style="display:flex;gap:7px;flex-wrap:wrap;align-items:flex-start">
            <details class="dg-upload"><summary class="btn-a"><i class="fas fa-file-arrow-up"></i> ZIP hochladen</summary>
              <div class="dg-upload-menu">
                <label class="sa-only"><b>Als WordPress-Theme</b><span class="hint">ZIP mit PHP (z. B. von wordpress.org oder einem Theme-Shop)</span><input id="wtUpload" type="file" accept=".zip,application/zip" hidden></label>
                <label><b>Nur als Portal-Design</b><span class="hint">Übernimmt nur Farben, Schrift und Bilder (CSS) – kein PHP</span><input id="themeUpload" type="file" accept=".zip,application/zip" hidden></label>
              </div>
            </details>
            <button class="btn-g" onclick="window.ThemeManager?.load(true);window.WpThemes?.load()"><i class="fas fa-rotate"></i> Aktualisieren</button>
          </div>
        </div>
        <div id="dgUpState" class="dg-status" hidden role="status"></div>
        <div id="dgActive" class="dg-active"></div>
        <div class="dg-chips" role="tablist" aria-label="Themes filtern">
          <button class="dg-chip on" data-f="all" onclick="DesignHub.filter('all',this)">Alle</button>
          <button class="dg-chip" data-f="portal" onclick="DesignHub.filter('portal',this)"><i class="fas fa-radio"></i> Portal-Designs</button>
          <button class="dg-chip sa-only" data-f="wp" onclick="DesignHub.filter('wp',this)"><i class="fab fa-wordpress"></i> WordPress-Themes</button>
        </div>
        <section id="dgPortal" class="dg-sec">
          <div class="dg-head"><b>Portal-Designs</b><span class="hint">Behalten Radio-Player, Community und das Layout deiner Website – es ändert sich nur das Aussehen.</span></div>
          <div id="themeGrid" class="theme-grid"><div class="empty">Themes werden geladen …</div></div>
        </section>
        <section id="dgSbx" class="dg-sec sa-only">
          <div class="dg-head"><b><i class="fas fa-flask"></i> Sandbox</b><span class="hint">Ein Spielraum zwischen deiner Live-Seite und dem Deployment: Themes installieren, aktivieren und anpassen, über einen geheimen Link live ansehen – und erst später live stellen. Deine Besucher sehen davon nichts.</span></div>
          <div id="sbxBody" class="sbx-body"><div class="hint">Lädt …</div></div>
        </section>
        <section id="dgWp" class="dg-sec sa-only">
          <div class="sbx-switch" id="sbxSwitch" hidden role="tablist" aria-label="Bearbeiten in"><span class="hint">Themes verwalten in:</span><button type="button" class="on" data-m="live" onclick="Sandbox.mode(false)">Live-Seite</button><button type="button" data-m="sbx" onclick="Sandbox.mode(true)"><i class="fas fa-flask"></i> Sandbox</button></div>
          <div class="sbx-banner" id="sbxBanner" hidden><i class="fas fa-flask"></i> Du bearbeitest die <b>Sandbox</b>. Die Live-Seite bleibt unverändert, bis du die Sandbox live stellst.</div>
          <div class="dg-head"><b>WordPress-Themes</b><span class="hint">Echte WordPress-Themes (PHP, klassisch oder Block-Themes) liefern die ganze Website aus. Beiträge, Seiten, Menüs und Widgets aus dem CMS erscheinen darin.</span></div>
          <div id="wtState" class="hint" style="margin:6px 0"></div>
          <div style="margin-bottom:8px"><button class="btn-g" id="wtOff" onclick="WpThemes.off()" hidden><i class="fas fa-rotate-left"></i> Zurück zum Portal-Design</button> <span id="wtInstCount" hidden></span></div>
          <div id="wtInst" class="dg-wpgrid"></div>
          <div class="danger-note" style="margin:10px 0 0"><i class="fas fa-shield-halved"></i> WordPress-Themes sind fremder PHP-Code und laufen mit den Rechten des CMS. Nutze die Vorschau, bevor du aktivierst.</div>
          <details class="dg-links" id="wrBox" ontoggle="if(this.open)WpLinks.loadReading()">
            <summary><i class="fas fa-house"></i> Startseite und Beitragsseite</summary>
            <div class="hint" style="margin:8px 0">Wie in WordPress unter „Einstellungen → Lesen“: Die Startseite zeigt entweder deine neuesten Beiträge oder eine feste Seite. Mit fester Startseite kannst du zusätzlich eine eigene Seite für die Beitragsübersicht (Blog) wählen.</div>
            <div id="wrForm" class="dg-links-form"><div class="hint">Lädt …</div></div>
          </details>
          <details class="dg-links" id="wlBox" ontoggle="if(this.open)WpLinks.load()">
            <summary><i class="fas fa-link"></i> Link-Struktur (Permalinks)</summary>
            <div class="hint" style="margin:8px 0">So sehen die Adressen deiner Beiträge aus, wenn ein WordPress-Theme die Website ausliefert. Alte Adressen werden automatisch auf die neue Form umgeleitet. Seiten und Portal-Bereiche behalten ihre Adresse.</div>
            <div id="wlForm" class="dg-links-form"><div class="hint">Lädt …</div></div>
          </details>
        </section>
      </div>
      <div class="card" id="themeDir">
        <div class="th"><div><div class="wp-page-title" style="font-size:18px">Neue Themes entdecken</div><div class="wp-subtitle">Kostenlos suchen, live ansehen und mit einem Klick installieren.</div></div></div>
        <div class="dg-chips" role="tablist" aria-label="Art des Themes">
          <button class="dg-chip on" data-k="portal" onclick="DesignHub.kind('portal',this)"><i class="fas fa-radio"></i> Portal-Designs</button>
          <button class="dg-chip sa-only" data-k="wp" onclick="DesignHub.kind('wp',this)"><i class="fab fa-wordpress"></i> WordPress-Themes</button>
        </div>
        <div id="dgFindPortal">
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
          <select id="tdSource" class="fc" style="max-width:260px" onchange="ThemeManager.dirSearch(1)"><option value="bootswatch">Bootstrap-Themes (Bootswatch)</option><option value="wordpress">WordPress.org (nur Design, ohne PHP)</option></select>
          <input id="tdQuery" class="fc" style="flex:1;min-width:180px" type="search" placeholder="Themes suchen …" onkeydown="if(event.key==='Enter')ThemeManager.dirSearch(1)">
          <button class="btn-a" type="button" onclick="ThemeManager.dirSearch(1)"><i class="fas fa-magnifying-glass"></i> Suchen</button>
        </div>
        <div class="hint" style="margin-bottom:10px">Installiert wird nur das Design (CSS und Bilder). WordPress-PHP, Plugins und Skripte werden nie ausgeführt – Layout und Funktionen des Portals bleiben erhalten.</div>
        <div id="tdGrid" class="theme-grid"><div class="empty">Zum Durchsuchen auf „Suchen“ klicken.</div></div>
        <div id="tdPager" style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:10px"></div>
        </div>
        <div id="dgFindWp" class="sa-only" style="display:none">
          <div class="hint" style="margin-bottom:8px">Suche im WordPress-Verzeichnis. Die Vorschau zeigt die Demo-Seite des Themes, bevor du es installierst.</div>
          <div id="wtNew">
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
              <input id="wtQuery" class="fc" style="flex:1;min-width:200px" type="search" placeholder="Themes im WordPress-Verzeichnis suchen (z. B. blog, magazin, radio) …" onkeydown="if(event.key==='Enter')WpThemes.search(1)">
              <button class="btn-a" onclick="WpThemes.search(1)"><i class="fas fa-magnifying-glass"></i> Suchen</button>
            </div>
            <div id="wtResults" class="dg-wpgrid"></div>
            <div id="wtPager" style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:10px"></div>
          </div>
        </div>
      </div>
      <div id="themeDetail" class="card" style="display:none"></div>
      <div id="themeCustomizer" class="theme-customizer" style="display:none">
        <aside class="theme-customizer-side">
          <div class="theme-customizer-top">
            <button class="theme-customizer-close" type="button" onclick="ThemeManager.closeCustomizer()" title="Customizer schließen"><i class="fas fa-xmark"></i></button>
            <div class="theme-customizer-publish">
              <span id="themeCustomizerState" class="hint">Vorschau</span>
              <button class="btn-a" type="button" onclick="ThemeManager.publishCustomizer()"><i class="fas fa-check"></i> Aktivieren & Veröffentlichen</button>
              <button class="btn-g cz-gear" id="czGear" type="button" onclick="WpThemes.czToggleActions()" title="Veröffentlichungs-Optionen" aria-label="Veröffentlichungs-Optionen" aria-expanded="false" hidden><i class="fas fa-gear"></i></button>
            </div>
          </div>
          <div class="cz-actions" id="czActions" hidden></div>
          <div class="theme-customizer-heading">
            <div class="hint">Live-Customizer</div>
            <h2 id="themeCustomizerName">Theme</h2>
            <button class="btn-g" type="button" onclick="ThemeManager.resetCustomizer()"><i class="fas fa-rotate-left"></i> Theme-Werte zurücksetzen</button>
          </div>
          <div class="cz-theme-row" id="czThemeRow" hidden></div>
          <div id="themeCustomizerControls" class="theme-customizer-controls"></div>
          <div class="theme-customizer-devices">
            <button class="btn-g cz-hide" id="czHide" type="button" onclick="ThemeManager.togglePanel()"><i class="fas fa-eye-slash"></i> Ausblenden</button>
            <button class="btn-g on" data-device="desktop" onclick="ThemeManager.device('desktop',this)"><i class="fas fa-desktop"></i></button>
            <button class="btn-g" data-device="tablet" onclick="ThemeManager.device('tablet',this)"><i class="fas fa-tablet-screen-button"></i></button>
            <button class="btn-g" data-device="mobile" onclick="ThemeManager.device('mobile',this)"><i class="fas fa-mobile-screen"></i></button>
          </div>
        </aside>
        <main class="theme-customizer-preview">
          <div class="theme-preview-browser">
            <div class="theme-preview-bar">
              <span></span><span></span><span></span>
              <div id="themePreviewUrl">ricorewi-radio.de</div>
              <label class="theme-preview-brand" title="Vorschau als Marke">Vorschau als: <select id="themePreviewBrand" class="fc" onchange="ThemeManager.brand(this.value)"></select></label>
            </div>
            <iframe id="themeCustomizerFrame" title="Live Theme Vorschau"></iframe>
          </div>
        </main>
      </div>
      <div id="wtPrev" style="display:none;position:fixed;inset:0;z-index:99999;background:#070d18;flex-direction:column;padding:10px;gap:8px;height:100vh;height:100dvh;box-sizing:border-box">
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;color:#fff;flex:0 0 auto">
          <div style="flex:1 1 100%;min-width:0"><b id="wtPrevName" style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></b><span class="hint" id="wtPrevNote" style="color:#ccd">Vorschau (15 Minuten gültig)</span> <a id="wtPrevOpen" class="hint" hidden target="_blank" rel="noopener noreferrer" style="color:#8cf">In neuem Tab öffnen</a></div>
          <button class="btn-a" id="wtPrevAct" style="flex:1 1 140px;justify-content:center"><i class="fas fa-check"></i> Aktivieren</button>
          <button class="btn-g" onclick="WpThemes.closePreview()" style="flex:1 1 140px;justify-content:center"><i class="fas fa-xmark"></i> Schließen</button>
        </div>
        <iframe id="wtFrame" title="Theme-Vorschau" style="flex:1 1 0;min-height:0;border:0;border-radius:10px;background:#fff;width:100%" sandbox="allow-same-origin allow-scripts allow-forms allow-popups"></iframe>
      </div>
    </section>
