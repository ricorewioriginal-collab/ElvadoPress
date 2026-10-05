<section id="panel-tags" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-tags"></i>Schlagwörter</div><div class="hint">Alle Schlagwörter deiner Beiträge mit Anzahl. Umbenennen ändert sie in allen Beiträgen; heißt das neue Schlagwort schon, werden beide zusammengeführt. Leeres Ziel = Schlagwort überall entfernen. Schlagwörter vergibst du weiterhin im Beitrag.</div></div><button class="btn-g" onclick="TagsManager.load()"><i class="fas fa-rotate"></i> Aktualisieren</button></div>
        <div style="margin-top:10px"><input id="tgFilter" class="fc" style="max-width:320px" placeholder="Schlagwörter filtern…" oninput="TagsManager.render()"></div>
        <div id="tgList" style="margin-top:10px"></div>
        <div id="tgMsg" class="hint" style="margin-top:8px"></div>
      </div>
    </section>
