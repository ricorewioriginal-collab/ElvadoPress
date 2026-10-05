<section id="panel-baukasten" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-table-cells-large"></i>Homepage-Baukasten</div><div class="hint">Baue deine Startseite aus Abschnitten: hinzufügen, per Drag &amp; Drop sortieren, bearbeiten, ausblenden. Gleiche Abschnittstypen darfst du beliebig oft verwenden. Farben, Schriften und Breiten stellst du im Customizer des Themes „ElvadoPress Baukasten“ ein.</div></div>
          <div style="display:flex;gap:7px;flex-wrap:wrap"><button class="btn-g" onclick="HomeBuilder.customizer()"><i class="fas fa-sliders"></i> Design (Customizer)</button><button class="btn-g" onclick="HomeBuilder.reset()" title="Eigenes Layout verwerfen"><i class="fas fa-rotate-left"></i> Zurücksetzen</button><button class="btn-g" onclick="HomeBuilder.preview()"><i class="fas fa-eye"></i> Vorschau</button><button class="btn-a" id="hbSave" onclick="HomeBuilder.save()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        </div>
        <div id="hbNotice" class="hint" style="display:none;margin-bottom:10px"></div>
        <div class="hb-shell">
          <div>
            <div class="block-palette" id="hbPalette" style="margin-bottom:12px"></div>
            <div id="hbList" class="hb-list"></div>
            <div id="hbEmpty" class="empty" style="display:none"><i class="fas fa-plus"></i>Noch keine Abschnitte. Füge oben einen hinzu.</div>
            <div id="hbMsg" class="hint" style="margin-top:8px"></div>
          </div>
          <div class="hb-prev"><div class="hint" style="margin-bottom:6px">VORSCHAU (zeigt den gespeicherten Stand)</div><iframe id="hbFrame" title="Vorschau der Startseite" src="about:blank"></iframe></div>
        </div>
      </div>
</section>
