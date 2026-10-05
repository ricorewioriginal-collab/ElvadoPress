<section id="panel-contents" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-layer-group"></i>Alle Inhalte</div><div class="hint">Beiträge und Seiten aus dem CMS und aus der WordPress-Datenbank in einer Liste. Jeder Eintrag öffnet im passenden Editor: CMS-Beiträge und -Seiten im CMS-Editor, WordPress-Seiten (z. B. von Elementor) im WordPress-Editor. Die Bereiche „Beiträge / News“ und „Seiten“ funktionieren unverändert weiter.</div></div><button class="btn-g" onclick="ContentsManager.load()"><i class="fas fa-rotate"></i> Neu laden</button></div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">
          <input id="ctFilter" class="fc" style="max-width:260px" placeholder="Titel filtern…" oninput="ContentsManager.render()">
          <select id="ctSource" class="fc" style="max-width:200px" onchange="ContentsManager.render()"><option value="">Alle Quellen</option><option value="cms-news">CMS-Beiträge</option><option value="cms-page">CMS-Seiten</option><option value="wp">WordPress-Datenbank</option></select>
          <select id="ctType" class="fc" style="max-width:160px" onchange="ContentsManager.render()"><option value="">Beiträge und Seiten</option><option value="post">Nur Beiträge</option><option value="page">Nur Seiten</option></select>
          <select id="ctStatus" class="fc" style="max-width:180px" onchange="ContentsManager.render()"><option value="">Alle Status</option><option value="publish">Veröffentlicht</option><option value="draft">Entwurf</option><option value="future">Geplant</option><option value="trash">Papierkorb</option></select>
        </div>
        <div id="ctList" style="margin-top:10px"></div>
        <div id="ctMsg" class="hint" style="margin-top:8px"></div>
        <details style="margin-top:14px;border:1px solid var(--line);border-radius:12px;padding:10px 14px">
          <summary style="cursor:pointer;font-weight:600"><i class="fas fa-right-left"></i> WordPress darf Inhalte ändern <span id="ctBridgeState" class="hint"></span></summary>
          <div class="hint" style="margin:8px 0">Standardmäßig lesen WordPress-Plugins und -Themes die CMS-Inhalte nur. Wenn du einen Bereich freigibst, dürfen sie ihn auch ändern: Die Änderungen landen in den normalen CMS-Dateien (mit Verlauf, Revisionen und Aktivitätsprotokoll); nicht abbildbare Felder bleiben unberührt. Neue Beiträge entstehen im CMS, neue Seiten (z. B. mit Elementor) in der WordPress-Datenbank. Bereichsweise zuschaltbar:</div>
          <div id="ctBridgeBox" style="display:flex;gap:14px;flex-wrap:wrap;margin:8px 0"></div>
          <button class="btn-a" onclick="ContentsManager.saveBridge()"><i class="fas fa-floppy-disk"></i> Speichern</button>
        </details>
      </div>
    </section>
