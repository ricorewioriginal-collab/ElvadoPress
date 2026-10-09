<section id="panel-apps" class="panel">
  <div class="card">
    <div class="th" style="margin-bottom:10px">
      <div><div class="wp-page-title">Apps verwalten</div><div class="wp-subtitle">Deine Apps im laufenden Betrieb: Hinweise an alle Nutzer, Wartungsmodus, Tab-Leiste der Baukasten-Apps und anonyme Nutzungszahlen. Die Apps holen diese Einstellungen beim Start ab; Änderungen gelten ohne neuen Build. Apps des Herstellers zeigen zusätzlich Stand, Mindestversion und Updates.</div></div>
      <button class="btn-a" onclick="saveApps()"><i class="fas fa-floppy-disk"></i> Speichern</button>
    </div>
    <div id="appsManager"></div>
  </div>
  <div class="card sa-only" id="abCard">
    <div class="th" style="margin-bottom:10px"><div><div class="wp-page-title" style="font-size:18px"><i class="fas fa-hammer"></i> Eigene App bauen (Android &amp; Windows)</div><div class="wp-subtitle">Aus deiner Website wird eine installierbare App mit eigenem Namen, Icon und Paketnamen – als Website-App oder Baukasten-App für jedes Thema. Der Build läuft kostenlos bei GitHub (Actions) – das CMS richtet ihn ein, startet ihn und liefert die fertigen Pakete aus. <a href="docs/APP-BUILDER.md" target="_blank" rel="noopener">Anleitung</a></div></div></div>
    <details id="abSetup" class="dg-links" open>
      <summary><i class="fas fa-plug"></i> 1 · Verbindung zu GitHub</summary>
      <div id="abConn" class="ab-conn"><div class="hint">Lädt …</div></div>
    </details>
    <div id="abBrands" class="ab-brands"></div>
  </div>
</section>
