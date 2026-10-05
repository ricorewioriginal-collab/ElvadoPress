<section id="panel-maintenance" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-person-digging"></i>Wartungsmodus</div><div class="hint">Zeigt Besuchern der Website eine Wartungsseite (Status 503, Suchmaschinen merken sich nichts). Apps, Alexa und das CMS laufen unverändert weiter.</div></div><span id="tmStatus" class="publish-state"></span></div>
        <label class="news-lbl" style="display:flex;align-items:center;gap:8px;margin-top:12px"><input type="checkbox" id="tmEnabled"> Wartungsmodus aktiv</label>
        <label class="news-lbl" style="margin-top:10px">Überschrift</label><input id="tmTitle" class="fc w-100" maxlength="80">
        <label class="news-lbl" style="margin-top:10px">Text</label><textarea id="tmText" class="fc w-100" rows="3" maxlength="600"></textarea>
        <label class="news-lbl" style="margin-top:10px">Voraussichtlich wieder erreichbar (optional)</label><input id="tmEta" class="fc w-100" maxlength="60" placeholder="z. B. heute 18:00 Uhr">
        <div style="margin-top:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center"><button class="btn-a" onclick="ToolsManager.saveMaint()"><i class="fas fa-floppy-disk"></i> Speichern</button><span id="tmMsg" class="hint"></span></div>
      </div>
      <div class="card">
        <div class="tt"><i class="fas fa-key"></i>Vorschau-Link für dich</div>
        <div class="hint" style="margin:4px 0 10px">Mit diesem Link siehst du die echte Website, auch wenn der Wartungsmodus aktiv ist (merkt sich der Browser für 12 Stunden). Nicht weitergeben.</div>
        <div style="display:flex;gap:8px;flex-wrap:wrap"><input id="tmLink" class="fc" style="flex:1;min-width:240px" readonly onclick="this.select()"><button class="btn-g" onclick="ToolsManager.copyLink()"><i class="fas fa-copy"></i> Kopieren</button><a id="tmOpen" class="btn-g" target="_blank" rel="noopener"><i class="fas fa-arrow-up-right-from-square"></i> Öffnen</a><button class="btn-g" onclick="ToolsManager.newKey()"><i class="fas fa-rotate"></i> Neuer Schlüssel</button></div>
      </div>
    </section>
    <section id="panel-redirects" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-route"></i>Weiterleitungen</div><div class="hint">Leitet alte oder falsche Adressen um, damit Links und Suchtreffer nicht ins Leere laufen. „Von“ ist ein Pfad wie <code>/alte-seite.html</code>, mit <code>*</code> am Ende gilt er für alles darunter. Code 410 meldet „dauerhaft entfernt“.</div></div></div>
        <div id="trList" style="margin-top:10px"></div>
        <div class="tr-add">
          <input id="trFrom" class="fc" placeholder="Von: /alte-seite.html" maxlength="300">
          <input id="trTo" class="fc" placeholder="Nach: /neue-seite.html oder https://…" maxlength="600">
          <select id="trCode" class="fc"><option value="301">301 dauerhaft</option><option value="302">302 vorübergehend</option><option value="307">307 temporär</option><option value="410">410 entfernt</option></select>
          <button class="btn-a" onclick="ToolsManager.addRule()"><i class="fas fa-plus"></i> Hinzufügen</button>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center"><button class="btn-a" onclick="ToolsManager.saveRules()"><i class="fas fa-floppy-disk"></i> Speichern</button><span id="trMsg" class="hint"></span></div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-triangle-exclamation"></i>404-Protokoll</div><div class="hint">Adressen, die Besucher oder Suchmaschinen aufgerufen haben, die es nicht gibt (ohne Bilder/Skripte). Mit „Weiterleiten“ übernimmst du die Adresse oben.</div></div><button class="btn-g" onclick="ToolsManager.clearLog()"><i class="fas fa-trash"></i> Leeren</button></div>
        <div id="trLog" style="margin-top:10px"></div>
      </div>
    </section>
    <section id="panel-privacy" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-user-shield"></i>Datenschutz-Werkzeuge</div><div class="hint">Findet gespeicherte Daten zu einer Person (Name, Benutzername, E-Mail) – für Auskunfts- und Löschwünsche. Das Portal setzt keine Tracking-Cookies; personenbezogen sind vor allem Kommentare (Name und Text) und die Benutzerkonten der Redaktion.</div></div></div>
        <div id="tpOverview" class="tp-stats"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><input id="tpQuery" class="fc" style="flex:1;min-width:220px" placeholder="Name, Benutzername oder E-Mail" maxlength="80" onkeydown="if(event.key==='Enter')ToolsManager.privacySearch()"><button class="btn-a" onclick="ToolsManager.privacySearch()"><i class="fas fa-magnifying-glass"></i> Suchen</button></div>
        <div id="tpMsg" class="hint" style="margin-top:8px"></div>
      </div>
      <div id="tpResult"></div>
    </section>
