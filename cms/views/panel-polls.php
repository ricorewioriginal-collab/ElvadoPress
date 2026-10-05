<section id="panel-polls" class="panel">
      <div class="builder-shell">
        <aside class="card builder-sidebar">
          <div class="th"><div><div class="tt"><i class="fas fa-square-poll-vertical"></i>Umfragen</div><div class="hint">Eigene Umfragen für die Website.</div></div><button class="btn-a" onclick="PollsManager.create()" title="Neue Umfrage"><i class="fas fa-plus"></i></button></div>
          <div id="plList" class="builder-list"></div>
        </aside>
        <div class="card">
          <div id="plEmpty" class="empty"><i class="fas fa-arrow-left"></i>Links eine Umfrage wählen oder mit + eine neue anlegen.</div>
          <div id="plEditor" style="display:none">
            <div class="th"><div class="tt" id="plTitle"><i class="fas fa-pen"></i>Umfrage</div><span id="plState" class="publish-state"></span></div>
            <label class="news-lbl" style="margin-top:10px">Frage</label><input id="plQuestion" class="fc w-100" maxlength="200">
            <label class="news-lbl" style="margin-top:10px">Antworten (eine pro Zeile, 2–12)</label><textarea id="plOptions" class="fc w-100" rows="6" placeholder="Ja&#10;Nein&#10;Weiß nicht"></textarea>
            <div class="section-grid" style="margin-top:10px">
              <div><label class="news-lbl">Ergebnisse zeigen</label><select id="plShow" class="fc w-100"><option value="after_vote">Nach der Abstimmung</option><option value="always">Immer</option><option value="after_close">Erst nach Ende der Umfrage</option></select></div>
              <div><label class="news-lbl">Mehrfachauswahl</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px"><input id="plMultiple" class="switch" type="checkbox"> Mehrere Antworten erlaubt</label></div>
              <div><label class="news-lbl">Beginnt am (optional)</label><input id="plStarts" type="datetime-local" class="fc w-100"></div>
              <div><label class="news-lbl">Endet am (optional)</label><input id="plEnds" type="datetime-local" class="fc w-100"></div>
              <div style="grid-column:1/-1"><label style="display:flex;gap:8px;align-items:center"><input id="plClosed" class="switch" type="checkbox"> Umfrage beenden (keine Stimmen mehr)</label></div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px"><button class="btn-a" onclick="PollsManager.save()"><i class="fas fa-floppy-disk"></i> Speichern</button><a id="plExport" class="btn-g" href="#"><i class="fas fa-file-csv"></i> Ergebnis als CSV</a><button class="btn-g" onclick="PollsManager.reset()"><i class="fas fa-eraser"></i> Stimmen zurücksetzen</button><button class="btn-d" onclick="PollsManager.remove()"><i class="fas fa-trash"></i> Löschen</button></div>
            <div class="hint" id="plHint" style="margin-top:8px"></div>
            <div class="tt" style="margin-top:16px"><i class="fas fa-chart-bar"></i>Ergebnis <span id="plTotal" class="hint"></span></div>
            <div id="plResults" style="margin-top:8px"></div>
          </div>
        </div>
      </div>
    </section>
