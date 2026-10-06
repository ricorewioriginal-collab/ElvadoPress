<?php $own=!function_exists('rrw_standalone')||!rrw_standalone(); /* eigenständig: nur eigene Apps, die mitgelieferten Apps des Herstellers bleiben verborgen */ ?>
<section id="panel-apps" class="panel">
  <div class="card">
    <div class="th" style="margin-bottom:10px">
      <div><div class="wp-page-title">Apps verwalten</div><div class="wp-subtitle">Deine Apps im laufenden Betrieb: Hinweise an alle Nutzer, Wartungsmodus, Funktionen ein-/ausschalten, Startseite der Radio-Apps und anonyme Nutzungszahlen. Die Apps holen diese Einstellungen beim Start ab; Änderungen gelten ohne neuen Build. Apps des Herstellers zeigen zusätzlich Stand, Mindestversion und Updates.</div></div>
      <button class="btn-a" onclick="saveApps()"><i class="fas fa-floppy-disk"></i> Speichern</button>
    </div>
    <div id="appsManager"></div>
  </div>
  <div class="card sa-only" id="abCard">
    <div class="th" style="margin-bottom:10px"><div><div class="wp-page-title" style="font-size:18px"><i class="fas fa-hammer"></i> Eigene App bauen (Android &amp; Windows)</div><div class="wp-subtitle">Aus deiner Website wird eine installierbare App mit eigenem Namen, Icon und Paketnamen – als Radio-App oder als Website-App für jedes Thema. Der Build läuft kostenlos bei GitHub (Actions) – das CMS richtet ihn ein, startet ihn und liefert die fertigen Pakete aus. <a href="docs/APP-BUILDER.md" target="_blank" rel="noopener">Anleitung</a></div></div></div>
    <details id="abSetup" class="dg-links" open>
      <summary><i class="fas fa-plug"></i> 1 · Verbindung zu GitHub</summary>
      <div id="abConn" class="ab-conn"><div class="hint">Lädt …</div></div>
    </details>
    <div id="abBrands" class="ab-brands"></div>
  </div>
  <div class="card" data-pack="ricorewi-radio"<?= $own?'':' hidden' ?>>
    <div class="th"><div class="tt"><i class="fas fa-mobile-screen"></i>Darstellung im Portal</div></div>
    <label style="display:flex;align-items:center;gap:10px;margin:12px 0"><input id="cmsAndroid" class="switch" type="checkbox"> Android-App im App-Bereich der Website anzeigen</label>
    <label style="display:flex;align-items:center;gap:10px;margin:12px 0"><input id="cmsWindows" class="switch" type="checkbox"> Windows-App im App-Bereich der Website anzeigen</label>
    <p class="hint">Die Build-/Download-Automationen bleiben davon unberührt; hier wird nur die öffentliche Darstellung gesteuert.</p>
  </div>
</section>
