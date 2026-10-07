<section id="panel-menus" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Menüs</div><div class="wp-subtitle">Wie bei WordPress: Seiten links auswählen, rechts ins Menü aufnehmen und per Drag & Drop anordnen.</div></div><div style="display:flex;gap:7px;flex-wrap:wrap"><button class="btn-g" type="button" data-vischeck="visCheckMenus"><i class="fas fa-eye"></i> Sichtbarkeit prüfen</button><button class="btn-a" onclick="saveMenus()"><i class="fas fa-floppy-disk"></i> Menü speichern</button></div></div>
        <div id="visCheckMenus" class="card vis-box" hidden></div>
        <div class="wp-menu-select">
          <b>Zu bearbeitendes Menü:</b>
          <select id="menuEditingSelect" class="fc" onchange="switchMenuEditor(this.value)"><option value="top">Top Navigation · Desktop</option><option value="bottom">Bottom Navigation · Mobil</option></select>
          <span id="menuEditingHint" class="hint"></span>
        </div>
        <div class="wp-menu-layout">
          <aside class="wp-menu-add">
            <h4><i class="fas fa-file-lines" style="color:var(--accent);margin-right:6px"></i>Seiten hinzufügen</h4>
            <div class="hint" style="margin-bottom:8px">Wähle vorhandene oder eigene Seiten aus.</div>
            <div id="menuAvailablePages"></div>
            <button class="btn-g" style="margin-top:10px;width:100%;justify-content:center" onclick="addSelectedPagesToMenu()"><i class="fas fa-plus"></i> Zum Menü hinzufügen</button>
            <hr style="border:0;border-top:1px solid var(--border);margin:14px 0">
            <h4>Individueller Link</h4>
            <input id="customMenuLabel" class="fc w-100" placeholder="Linktext">
            <input id="customMenuUrl" class="fc w-100" style="margin-top:6px" placeholder="https://… oder /pfad">
            <button class="btn-g" style="margin-top:8px;width:100%;justify-content:center" onclick="addCustomMenuLink()"><i class="fas fa-link"></i> Link hinzufügen</button>
          </aside>
          <div class="wp-menu-main">
            <div class="wp-menu-structure">
              <div class="th"><div><div class="tt" id="menuStructureTitle">Menüstruktur</div><div class="hint">Ziehen = Reihenfolge. Beim Ablegen weiter rechts wird der Punkt zum Untermenü, weiter links wieder Hauptebene.</div></div><button class="btn-g" onclick="addMenuItem(document.getElementById('menuEditingSelect').value)"><i class="fas fa-plus"></i> Leerer Punkt</button></div>
              <div id="menuStructure"></div>
            </div>
          </div>
        </div>
      </div>
    </section>
