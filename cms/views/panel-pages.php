<section id="panel-pages" class="panel">
      <div class="builder-shell">
        <aside class="card builder-sidebar">
          <div class="th"><div><div class="tt"><i class="fas fa-file-lines"></i>Seiten</div><div class="hint">Systemseiten und eigene Seiten.</div></div><button class="btn-a" onclick="cmsAddPage()"><i class="fas fa-plus"></i></button></div>
          <div id="pageList" class="builder-list"></div>
        </aside>
        <div>
          <div class="card">
            <div class="th"><div><div class="tt" id="pageEditorTitle"><i class="fas fa-pen-ruler"></i>Seite bearbeiten</div><div class="hint">Blöcke per Drag & Drop sortieren. Systeminhalt kann erhalten oder ausgeblendet werden.</div></div><div style="display:flex;gap:7px"><button class="btn-g" onclick="previewCurrentPage()"><i class="fas fa-eye"></i> Vorschau</button><button class="btn-a" onclick="savePages()"><i class="fas fa-floppy-disk"></i> Alle Seiten speichern</button></div></div>
            <div id="pageEditorEmpty" class="empty"><i class="fas fa-arrow-left"></i>Links eine Seite auswählen.</div>
            <div id="pageEditor" style="display:none">
              <div class="section-grid" style="margin-bottom:12px">
                <div><label class="news-lbl">Seitentitel</label><input id="peTitle" class="fc w-100" oninput="pageFieldChanged()"></div>
                <div><label class="news-lbl">Slug / Adresse</label><input id="peSlug" class="fc w-100" oninput="pageFieldChanged()"></div>
                <div><label class="news-lbl">Typ</label><input id="peType" class="fc w-100" disabled></div>
                <div><label class="news-lbl">Status</label><select id="peStatus" class="fc w-100" onchange="pageFieldChanged()"><option value="live">Veröffentlicht</option><option value="draft">Entwurf (nicht sichtbar)</option><option value="scheduled">Geplant</option></select></div>
                <div id="pePublishWrap" style="display:none"><label class="news-lbl">Erscheint am</label><input id="pePublishAt" type="datetime-local" class="fc w-100" onchange="pageFieldChanged()"><div class="hint" style="font-size:.66rem;margin-top:3px">Bis dahin ist die Seite für Besucher nicht erreichbar (Vorschau über den Link unter Werkzeuge → Wartungsmodus).</div></div>
                <div><label class="news-lbl">Systeminhalt</label><label style="display:flex;gap:8px;align-items:center;margin-top:8px"><input id="peNative" class="switch" type="checkbox" onchange="pageFieldChanged()"> Originalen Seiteninhalt anzeigen</label><input id="peEnabled" type="checkbox" hidden></div>
                <div style="grid-column:1/-1"><label class="news-lbl">Seiten-Überschrift / Titel überschreiben</label><input id="peHeadline" class="fc w-100" placeholder="leer = vorhandenen Titel verwenden" oninput="pageFieldChanged()"></div>
                <div style="grid-column:1/-1"><label class="news-lbl">Einleitung / Beschreibung überschreiben</label><textarea id="peIntro" class="fc w-100" rows="3" placeholder="leer = vorhandenen Text verwenden" oninput="pageFieldChanged()"></textarea></div>
                <div style="grid-column:1/-1" class="pe-seo"><label class="news-lbl"><i class="fas fa-magnifying-glass-chart"></i> Suchmaschinen (SEO)</label>
                  <input id="peMetaTitle" class="fc w-100" maxlength="160" placeholder="Meta-Titel (leer = Seitentitel – RicoReWi Radio)" oninput="pageFieldChanged()">
                  <textarea id="peMetaDesc" class="fc w-100" rows="2" maxlength="300" placeholder="Meta-Beschreibung (leer = Einleitung)" oninput="pageFieldChanged()" style="margin-top:6px"></textarea>
                  <label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input id="peNoindex" class="switch" type="checkbox" onchange="pageFieldChanged()"> Nicht in Suchmaschinen aufnehmen (noindex, nicht in der Sitemap)</label></div>
              </div>
              <div class="block-palette" style="margin-bottom:12px">
                <button class="palette-btn" onclick="addBlock('heading')"><i class="fas fa-heading"></i> Überschrift</button>
                <button class="palette-btn" onclick="addBlock('text')"><i class="fas fa-paragraph"></i> Text</button>
                <button class="palette-btn" onclick="addBlock('html')"><i class="fas fa-code"></i> HTML</button>
                <button class="palette-btn" onclick="addBlock('html');editHtmlBlock('before',blockArray('before').length-1)"><i class="fas fa-table-columns"></i> Block-Editor</button>
                <button class="palette-btn" onclick="addBlock('image')"><i class="fas fa-image"></i> Bild</button>
                <button class="palette-btn" onclick="addBlock('button')"><i class="fas fa-square-up-right"></i> Button</button>
                <button class="palette-btn" onclick="addBlock('widget')"><i class="fas fa-puzzle-piece"></i> Widget</button>
                <button class="palette-btn" onclick="addBlock('quote')"><i class="fas fa-quote-left"></i> Zitat</button>
                <button class="palette-btn" onclick="addBlock('divider')"><i class="fas fa-minus"></i> Trennlinie</button>
                <button class="palette-btn" onclick="addBlock('spacer')"><i class="fas fa-arrows-up-down"></i> Abstand</button>
              </div>
              <div class="hint" style="margin-bottom:6px">INHALT VOR DEM SYSTEMBEREICH</div>
              <div id="blocksBefore" class="dropzone" data-zone="before"></div>
              <div id="nativeMarker" class="native-live">
                <div class="native-live-head">
                  <div><b><i class="fas fa-eye" style="color:var(--cyan);margin-right:6px"></i>Vorhandener Seiteninhalt</b><div class="hint">Echte Live-Seite von <?=rrw_pack_available()?'ricorewi-radio.de':'deiner Website'?> – nicht nur ein Platzhalter.</div></div>
                  <div style="display:flex;gap:6px"><button class="btn-g" onclick="refreshNativePreview()"><i class="fas fa-rotate"></i> Vorschau laden</button><button class="btn-a" onclick="scanNativeTexts()"><i class="fas fa-pen"></i> Texte bearbeiten</button></div>
                </div>
                <iframe id="nativePreviewFrame" title="Live-Vorschau der vorhandenen Seite"></iframe>
                <div id="nativeTextEditor" class="native-text-editor" style="display:none;padding:12px"></div>
              </div>
              <div class="hint" style="margin-bottom:6px">INHALT NACH DEM SYSTEMBEREICH</div>
              <div id="blocksAfter" class="dropzone" data-zone="after"></div>
              <div style="display:flex;gap:8px;margin-top:12px;justify-content:flex-end;flex-wrap:wrap"><button class="btn-g" onclick="duplicateCurrentPage()"><i class="fas fa-copy"></i> Duplizieren</button><button class="btn-g" onclick="pageRevisionsOpen()"><i class="fas fa-clock-rotate-left"></i> Versionen</button><button id="deletePageBtn" class="btn-d" onclick="deleteCurrentPage()"><i class="fas fa-trash"></i> Eigene Seite löschen</button></div>
            </div>
          </div>
        </div>
      </div>
      <div id="pageRevBox" class="card" style="display:none;margin-top:12px"><div class="th"><div class="tt"><i class="fas fa-clock-rotate-left"></i>Frühere Versionen dieser Seite</div><button class="btn-g" onclick="document.getElementById('pageRevBox').style.display='none'"><i class="fas fa-xmark"></i></button></div><div id="pageRevList" class="hint">…</div></div>
</section>
