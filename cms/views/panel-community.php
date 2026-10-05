<section id="panel-community" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-people-group"></i>Community</div><div class="hint">Optionales Modul für Mitglieder (und später Forum und soziales Netzwerk). Solange es ausgeschaltet ist, erscheint auf der Website nichts davon. Die Oberfläche bauen die Widgets <b>Mitgliederbereich</b> (Design → Widgets) auf.</div></div><span id="cmState" class="publish-state"></span></div>
        <label class="news-lbl" style="display:flex;align-items:center;gap:8px;margin-top:12px"><input type="checkbox" id="cmEnabled"> Community aktiv</label>
        <div class="section-grid" style="margin-top:10px">
          <div><label class="news-lbl">Registrierung</label><select id="cmReg" class="fc w-100"><option value="open">Offen (sofort nutzbar)</option><option value="approval">Mit Freischaltung durch mich</option><option value="closed">Geschlossen</option></select></div>
          <div><label class="news-lbl">Mindestlänge Passwort</label><input id="cmMinPw" type="number" min="8" max="64" class="fc w-100"></div>
          <div><label class="news-lbl">Angemeldet bleiben (Tage)</label><input id="cmDays" type="number" min="1" max="365" class="fc w-100"></div>
          <div><label class="news-lbl">Module</label><label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" id="cmForum"> Forum</label><label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="checkbox" id="cmSocial"> Soziales Netzwerk</label></div>
        </div>
        <label class="news-lbl" style="margin-top:10px">Regeln (werden bei der Registrierung angezeigt)</label><textarea id="cmRules" class="fc w-100" rows="4" maxlength="2000"></textarea>
        <div style="margin-top:12px;display:flex;gap:8px;align-items:center"><button class="btn-a" onclick="CommunityManager.saveConfig()"><i class="fas fa-floppy-disk"></i> Speichern</button><span id="cmMsg" class="hint"></span></div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-users"></i>Mitglieder <span id="cmCount" class="hint"></span></div><div class="hint">E-Mail-Adressen sieht nur die Administration. Passwörter werden nur als Hash gespeichert.</div></div><button class="btn-g" onclick="CommunityManager.load()"><i class="fas fa-rotate"></i> Aktualisieren</button></div>
        <input id="cmFilter" class="fc" style="max-width:320px;margin-top:10px" placeholder="Mitglieder filtern…" oninput="CommunityManager.render()">
        <div id="cmList" style="margin-top:10px"></div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-comments"></i>Forum – Kategorien</div><div class="hint">Mitglieder sehen die Kategorien im Widget „Forum“. Beim Entfernen einer Kategorie wandern ihre Themen in die erste verbleibende.</div></div></div>
        <div id="foCats" style="margin-top:10px"></div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn-g" onclick="CommunityManager.catAdd()"><i class="fas fa-plus"></i> Kategorie</button><button class="btn-a" onclick="CommunityManager.catSave()"><i class="fas fa-floppy-disk"></i> Kategorien speichern</button></div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-flag"></i>Gemeldete Beiträge <span id="foRepCount" class="hint"></span></div><div class="hint">Moderatoren (Mitglieder mit Rolle „Moderator“) können direkt im Forum moderieren; hier siehst du alle Meldungen.</div></div></div>
        <div id="foReports" style="margin-top:10px"></div>
      </div>
    </section>
