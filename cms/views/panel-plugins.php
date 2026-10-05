<section id="panel-plugins" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Plugins</div><div class="wp-subtitle">Erweiterungen für deine Website: installieren, aktivieren, einstellen und aktuell halten – an einem Ort.</div></div></div>
        <div class="danger-note" style="margin:10px 0"><i class="fas fa-shield-halved"></i> WordPress-Plugins sind fremder Code und laufen mit den Rechten des CMS. Installiere nur Plugins aus vertrauenswürdigen Quellen. Ein Plugin, das beim Laden abstürzt, wird automatisch abgeschaltet.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-g on" id="wpTabInst" onclick="WpPlugins.tab('inst')"><i class="fas fa-plug"></i> Installiert <span id="wpInstCount" class="hint"></span></button>
          <button class="btn-g" id="wpTabNew" onclick="WpPlugins.tab('new')"><i class="fas fa-magnifying-glass"></i> Neu hinzufügen</button>
          <button class="btn-g" id="wpTabPages" onclick="WpPlugins.tab('pages')"><i class="fas fa-sliders"></i> Einstellungen</button>
          <button class="btn-g" id="wpTabContent" onclick="WpPlugins.tab('content')"><i class="fas fa-pen-ruler"></i> Seiten &amp; Editor</button>
          <label class="btn-g" style="cursor:pointer;margin:0"><i class="fas fa-file-arrow-up"></i> ZIP hochladen<input id="wpUpload" type="file" accept=".zip,application/zip" hidden></label>
        </div>
        <div style="margin-top:10px;display:flex;gap:8px;align-items:center;flex-wrap:wrap"><button class="btn-g" onclick="WpPlugins.updates()"><i class="fas fa-arrows-rotate"></i> Nach Updates suchen (Plugins &amp; Themes)</button><button class="btn-g" onclick="WpPlugins.translations(this)" title="Deutsche Übersetzungen von translate.wordpress.org für WordPress, Plugins und Themes"><i class="fas fa-language"></i> Übersetzungen laden</button><span id="wpUpdState" class="hint"></span></div>
        <div id="wpUpd" style="margin-top:8px"></div>
        <style>#wpAuto label.hint{display:flex;flex-direction:column;gap:4px;min-width:0}#wpAuto label.hint select{width:100%;max-width:100%;box-sizing:border-box}#wpAuto label.hint.chk{flex-direction:row;align-items:center;gap:8px}#wpAuto label.hint.chk input{flex:none;width:auto}#wpAuto .wpau-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;align-items:end}</style>
        <details id="wpAuto" style="margin-top:10px;border:1px solid var(--line);border-radius:12px;padding:10px 14px">
          <summary style="cursor:pointer;font-weight:600"><i class="fas fa-rotate"></i> Automatische Updates <span id="wpAutoState" class="hint"></span></summary>
          <div class="hint" style="margin:8px 0">Neue Versionen aus dem WordPress-Verzeichnis werden automatisch eingespielt. Vorher wird die alte Fassung gesichert; schlägt die Prüfung (Syntax aller PHP-Dateien) fehl, wird sie wiederhergestellt. Lege dafür den Cron-Job <code>cron/wp-cron.php</code> an (z. B. stündlich); ohne Cron-Job startest du den Lauf hier von Hand.</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:10px">
            <label class="hint">Plugins<select id="wpAuP" class="fc"><option value="off">Aus</option><option value="all">Alle automatisch</option><option value="selected">Nur ausgewählte</option></select></label>
            <label class="hint">Themes<select id="wpAuT" class="fc"><option value="off">Aus</option><option value="all">Alle automatisch</option><option value="selected">Nur ausgewählte</option></select></label>
            <label class="hint">Prüfen alle<select id="wpAuI" class="fc"><option value="6">6 Stunden</option><option value="12">12 Stunden</option><option value="24">Täglich</option><option value="168">Wöchentlich</option></select></label>
          </div>
          <div style="border:1px solid var(--line);border-radius:10px;padding:10px 12px;margin:10px 0">
            <div><b>WordPress-Version</b> <span id="wpVerNow" class="hint"></span></div>
            <div id="wpVerInfo" class="hint" style="margin:4px 0"></div>
            <div class="wpau-row"><label class="hint">Neue Versionen übernehmen<select id="wpAuW" class="fc"><option value="off">Nur von Hand</option><option value="minor">Automatisch: nur Fehlerkorrekturen (z. B. 6.8.3 → 6.8.4)</option><option value="all">Automatisch: jede neue Version</option></select></label>
              <button class="btn-g" id="wpVerBtn" onclick="WpPlugins.verUpdate(this)" hidden><i class="fas fa-arrow-up"></i> Jetzt übernehmen</button><button class="btn-g" id="wpVerRb" onclick="WpPlugins.verRollback(this)" hidden><i class="fas fa-rotate-left"></i> Vorherige Version</button></div>
          </div>
          <div style="display:flex;gap:14px;flex-wrap:wrap;margin:10px 0"><label class="hint chk"><input type="checkbox" id="wpAuL"> Sprachpakete nach Updates neu laden</label><label class="hint chk"><input type="checkbox" id="wpAuC"> Kernressourcen (React-Seiten) aktuell halten</label></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button class="btn-a" onclick="WpPlugins.autoSave()"><i class="fas fa-floppy-disk"></i> Speichern</button><button class="btn-g" onclick="WpPlugins.autoRun(this)"><i class="fas fa-play"></i> Jetzt prüfen &amp; aktualisieren</button></div>
          <div id="wpAuLog" style="margin-top:10px"></div>
        </details>
        <div id="wpHead" class="dg-head" style="margin-top:12px"><b>WordPress-Plugins</b><span class="hint">Echte WordPress-Plugins (PHP). Ein Plugin, das beim Laden abstürzt, wird automatisch abgeschaltet.</span></div>
        <div id="wpInst"></div>
        <div id="plInst" style="margin-top:18px">
          <div class="dg-head"><b>Portal-Plugins</b><span class="hint">Kleine Erweiterungen für das Portal (Frontend-Skripte, Stile, Hooks). Sie führen kein PHP aus.</span></div>
          <div style="display:flex;gap:7px;flex-wrap:wrap;margin:8px 0">
            <label class="btn-g" style="cursor:pointer;margin:0"><i class="fas fa-file-arrow-up"></i> Portal-Plugin hochladen<input id="pluginUpload" type="file" accept=".zip,application/zip" hidden></label>
            <a class="btn-g" href="docs/PLUGINS.md" target="_blank" rel="noopener"><i class="fas fa-book"></i> Plugin-Doku</a>
          </div>
          <div id="pluginGrid" class="plugin-grid"><div class="empty">Plugins werden geladen …</div></div>
          <details style="margin-top:12px" ontoggle="if(this.open)window.PluginManager?.devInfo()"><summary class="hint" style="cursor:pointer"><i class="fas fa-code"></i> Entwickler-Schnittstelle</summary>
            <div style="display:flex;gap:7px;flex-wrap:wrap;margin-top:8px"><a class="btn-g" href="docs/API.md" target="_blank" rel="noopener"><i class="fas fa-book-open"></i> API-Doku</a></div>
            <div id="plDev" class="hint" style="margin-top:8px">Lädt …</div>
          </details>
        </div>
        
        <div id="wpPages" style="margin-top:12px;display:none">
          <div class="hint" style="margin-bottom:8px">Einstellungs- und Verwaltungsseiten der aktiven Plugins. Sie laufen abgeschottet in einem eigenen Rahmen; Formulare und Ajax-Aufrufe werden vom CMS ausgeführt.</div>
          <div id="wpCore" class="hint" style="display:none;margin-bottom:10px;border:1px solid var(--line);border-radius:10px;padding:8px 12px"></div>
          <div id="wpMenu" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:10px"></div>
          <iframe id="wpFrame" title="Plugin-Seite" sandbox="allow-scripts allow-popups" style="display:none;width:100%;height:68vh;border:1px solid var(--line);border-radius:10px;background:#fff"></iframe>
        </div>
        <div id="wpContent" style="margin-top:12px;display:none">
          <div class="hint" style="margin-bottom:8px">WordPress-Seiten und -Beiträge (aus der WordPress-Datenbank). Mit aktivem Elementor öffnet „Mit Elementor bearbeiten“ den Seiten-Editor in einem neuen Tab; die Website wird dort live mit dem aktiven WordPress-Theme angezeigt. Block-Themes bearbeitest du unter „Website-Editor“.</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><input id="wpNewTitle" class="fc" style="flex:1;min-width:200px" maxlength="200" placeholder="Titel der neuen Seite" onkeydown="if(event.key==='Enter')WpPlugins.newPage()"><button class="btn-a" onclick="WpPlugins.newPage()"><i class="fas fa-plus"></i> Neue Seite</button></div>
          <div id="wpContentList"></div>
        </div>
        <div id="wpNew" style="margin-top:12px;display:none">
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px">
            <input id="wpQuery" class="fc" style="flex:1;min-width:200px" type="search" placeholder="Plugins im WordPress-Verzeichnis suchen …" onkeydown="if(event.key==='Enter')WpPlugins.search(1)">
            <button class="btn-a" onclick="WpPlugins.search(1)"><i class="fas fa-magnifying-glass"></i> Suchen</button>
          </div>
          <div id="wpResults"></div>
          <div id="wpPager" style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:10px"></div>
        </div>
      </div>
    </section>
