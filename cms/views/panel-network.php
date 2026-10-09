<section id="panel-network" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-tower-broadcast"></i>Sender-Netzwerk</div><div class="hint">Diese Liste steuert das Kernnetzwerk in Radioportal, API, Tracker und weiteren Netzwerkfunktionen.</div></div></div>
        <div style="display:grid;grid-template-columns:minmax(0,1fr) auto;gap:8px;margin-bottom:12px"><input id="coreNewStation" class="fc w-100" placeholder="laut.fm Sendername, z. B. neuer-sender"><button class="btn-a" onclick="validateAndAddCore()"><i class="fas fa-plus"></i> Prüfen & hinzufügen</button></div>
        <div id="coreValidation" class="hint" style="margin-bottom:12px"></div>
        <div id="coreStationList"></div>
      </div>
      <div class="danger-note"><i class="fas fa-triangle-exclamation"></i> Entfernen ist eine kritische Netzwerkaktion. Sie ist nur für Superadmins freigeschaltet, verlangt den exakten Sendernamen und die Bestätigungsphrase <b>ENTFERNEN sendername</b>.</div>
    </section>
