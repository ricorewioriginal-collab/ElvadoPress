<section id="panel-contentfiles" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Dateibasierte Inhalte</div><div class="wp-subtitle">Grav-inspiriert, aber eigenständig: Eigene Seiten werden zusätzlich als lesbare Markdown-Dateien unter <code>/cms/content/pages/</code> gespiegelt.</div></div><div style="display:flex;gap:7px;flex-wrap:wrap"><button class="btn-g" onclick="SystemManager.importContent()"><i class="fas fa-file-import"></i> Markdown → CMS</button><button class="btn-a" onclick="SystemManager.syncContent()"><i class="fas fa-rotate"></i> CMS → Markdown</button></div></div>
        <div class="danger-note" style="margin-bottom:12px"><i class="fas fa-circle-info"></i> Das ist kein Grav-Code und kein Grav-Plugin-System. Das Prinzip „Inhalt als Datei + Frontmatter“ wird nur als eigene, kompatible CMS-Funktion genutzt.</div>
        <div id="contentFiles"><div class="empty">Dateiinhalte werden geladen …</div></div>
      </div>
    </section>
