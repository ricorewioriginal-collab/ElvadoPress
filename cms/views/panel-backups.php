<section id="panel-backups" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Backups & Wiederherstellung</div><div class="wp-subtitle">Sichert CMS-Daten, Themes, Plugins, generierte Seiten und optional den Medien-Hub.</div></div><button class="btn-a" onclick="SystemManager.createBackup()"><i class="fas fa-box-archive"></i> Backup erstellen</button></div>
        <label style="display:flex;align-items:center;gap:8px;margin-bottom:12px"><input id="backupMedia" type="checkbox" checked> Medien in Backup aufnehmen</label>
        <div class="danger-note" style="margin-bottom:12px"><i class="fas fa-shield-halved"></i> Wiederherstellung ist eine Superadmin-Aktion und verlangt eine zusätzliche Bestätigung.</div>
        <div id="backupList"><div class="empty">Backups werden geladen …</div></div>
      </div>
    </section>
