<section id="panel-database" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Optionale Datenbank</div><div class="wp-subtitle">Das schnelle Datei-CMS bleibt Standard. Optional kannst du SQLite oder MySQL als synchronisierten Datenbankspiegel nutzen.</div></div><div style="display:flex;gap:7px"><button class="btn-g" onclick="SystemManager.loadDatabase()"><i class="fas fa-rotate"></i> Prüfen</button><button class="btn-a" onclick="SystemManager.saveDatabase()"><i class="fas fa-floppy-disk"></i> Speichern</button></div></div>
        <div id="dbStatus"></div>
        <div class="section-grid" style="margin-top:12px">
          <div><label class="news-lbl">Treiber</label><select id="dbDriver" class="fc w-100" onchange="SystemManager.toggleDbFields()"><option value="none">Keine Datenbank</option><option value="sqlite">SQLite</option><option value="mysql">MySQL</option><option value="mariadb">MariaDB</option><option value="pgsql">PostgreSQL (nur Spiegel)</option></select></div>
          <div><label style="display:flex;align-items:center;gap:8px;margin-top:27px"><input id="dbMirror" type="checkbox"> Bei jedem CMS-Publish automatisch spiegeln</label></div>
        </div>
        <div id="dbSqliteFields" style="display:none;margin-top:10px"><label class="news-lbl">SQLite-Datei</label><input id="dbSqlite" class="fc w-100" placeholder="cms.sqlite"></div>
        <div id="dbMysqlFields" class="section-grid" style="display:none;margin-top:10px">
          <div><label class="news-lbl">Host</label><input id="dbHost" class="fc w-100" placeholder="127.0.0.1"></div><div><label class="news-lbl">Port</label><input id="dbPort" class="fc w-100" type="number"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Socket (optional, statt Host/Port)</label><input id="dbSocket" class="fc w-100" placeholder="/var/run/mysqld/mysqld.sock"></div>
          <div><label class="news-lbl">Datenbank</label><input id="dbName" class="fc w-100"></div><div><label class="news-lbl">Benutzer</label><input id="dbUser" class="fc w-100"></div>
          <div><label class="news-lbl">Tabellen-Präfix (WordPress-Schicht)</label><input id="dbPrefix" class="fc w-100" placeholder="wp_"></div><div style="display:flex;align-items:flex-end"><label style="display:flex;align-items:center;gap:8px"><input id="dbCreate" type="checkbox"> Datenbank anlegen, falls sie fehlt</label></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Passwort</label><input id="dbPassword" class="fc w-100" type="password" autocomplete="new-password" placeholder="leer lassen = bisheriges behalten"><div class="hint">Wird nur serverseitig in <code>cms/data/database.local.php</code> gespeichert, nicht in site.json.</div></div>
        </div>
        <div id="dbTestResult" class="hint" style="margin-top:10px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:14px"><button class="btn-g" onclick="SystemManager.testDatabase()"><i class="fas fa-plug-circle-check"></i> Verbindung testen</button><button class="btn-g" onclick="SystemManager.pushDatabase()"><i class="fas fa-arrow-right-to-bracket"></i> Datei-CMS → Datenbank</button><button class="btn-d" onclick="SystemManager.pullDatabase()"><i class="fas fa-arrow-left"></i> Datenbank → Datei-CMS</button></div>
      </div>
    </section>
