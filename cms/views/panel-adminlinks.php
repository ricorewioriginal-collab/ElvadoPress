<section id="panel-adminlinks" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-link"></i>Eigene Links im Menü</div><div class="hint">Zusätzliche Einträge unten im Menü der Verwaltung, sichtbar für alle Benutzer. Jeder Link öffnet sich im Rahmen der Verwaltung oder in einem neuen Tab. Erlaubt sind <code>https://…</code>, <code>http://…</code> und Pfade dieser Website wie <code>/statistik/</code>.</div></div></div>
        <div id="alList" style="margin-top:12px"></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
          <button type="button" class="btn-g" onclick="AdminLinks.add()"><i class="fas fa-plus"></i> Link hinzufügen</button>
          <button type="button" class="btn-a" onclick="AdminLinks.save()"><i class="fas fa-floppy-disk"></i> Speichern</button>
        </div>
        <div id="alMsg" class="hint" style="margin-top:8px"></div>
        <div class="hint" style="margin-top:8px">Hinweis: Manche Websites verbieten das Einbetten in einen Rahmen (Header <code>X-Frame-Options</code>) – dort bitte „Neuer Tab“ wählen. Eine <code>http://</code>-Adresse lässt sich in einer verschlüsselt (https) aufgerufenen Verwaltung nicht einbetten.</div>
      </div>
    </section>
    <section id="panel-customlink" class="panel">
      <div class="card" style="padding:0;overflow:hidden">
        <div class="th" style="padding:12px 16px;margin:0">
          <div class="tt"><i class="fas fa-link" id="clIcon"></i><span id="clTitle">Link</span></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button type="button" class="btn-g" onclick="AdminLinks.reload()"><i class="fas fa-rotate"></i> Neu laden</button>
            <a id="clNew" class="btn-g" href="#" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square"></i> In neuem Tab öffnen</a>
          </div>
        </div>
        <iframe id="clFrame" title="Eigener Link" class="cl-frame" referrerpolicy="no-referrer" allow="fullscreen"></iframe>
      </div>
    </section>
