<section id="panel-livebuilder" class="panel">
  <div class="lb">
    <div class="lb-bar">
      <div class="lb-modes" role="tablist" aria-label="Ansicht">
        <button type="button" class="on" data-lbmode="edit"><i class="fas fa-pen-to-square"></i><span>Bearbeiten</span></button>
        <button type="button" data-lbmode="preview"><i class="fas fa-eye"></i><span>Vorschau</span></button>
        <button type="button" data-lbmode="structure"><i class="fas fa-list-ul"></i><span>Struktur</span></button>
        <button type="button" data-lbmode="settings"><i class="fas fa-sliders"></i><span>Einstellungen</span></button>
      </div>
      <span id="lbState" class="lb-state" aria-live="polite"></span><span id="lbSched" class="lb-sched" hidden></span>
      <div class="lb-actions">
        <button type="button" class="btn-g" id="lbDraft"><i class="fas fa-floppy-disk"></i> Entwurf speichern</button>
        <button type="button" class="btn-a" id="lbPublish"><i class="fas fa-rocket"></i> Veröffentlichen</button>
        <div class="lb-more"><button type="button" class="btn-g" id="lbMore" aria-haspopup="true" aria-label="Weitere Aktionen"><i class="fas fa-ellipsis-vertical"></i></button>
          <div class="lb-pop" id="lbMorePop" hidden>
            <button type="button" data-lbmore="discard"><i class="fas fa-rotate-left"></i>Entwurf verwerfen</button>
            <button type="button" data-lbmore="history"><i class="fas fa-clock-rotate-left"></i>Verlauf / Fassung wiederherstellen</button>
            <button type="button" data-lbmore="schedule"><i class="fas fa-calendar-check"></i>Veröffentlichung planen …</button>
            <button type="button" data-lbmore="reset"><i class="fas fa-eraser"></i>Auf Customizer-Positionen zurücksetzen</button>
            <button type="button" data-lbmore="ai"><i class="fas fa-wand-magic-sparkles"></i>Layout mit KI entwerfen</button>
            <button type="button" data-lbmore="customizer"><i class="fas fa-sliders"></i>Design (Customizer) öffnen</button>
          </div></div>
      </div>
    </div>
    <div id="lbNotice" class="hint" style="display:none;margin:0 0 8px"></div>
    <div id="lbRegion" class="lb-region" hidden></div>
    <div id="lbModal" class="lb-modal" hidden></div>
    <div class="lb-grid" data-lbview="edit">
      <aside class="lb-col lb-structure" aria-label="Seitenstruktur">
        <div class="lb-h"><b>Seitenstruktur</b><button type="button" class="lb-ic" id="lbAddTop" aria-label="Bereich hinzufügen"><i class="fas fa-plus"></i></button></div>
        <div id="lbList" class="lb-list"></div>
        <button type="button" class="lb-add" id="lbAdd"><i class="fas fa-plus"></i> Bereich hinzufügen</button>
        <div id="lbPalette" class="lb-palette" hidden></div>
      </aside>
      <section class="lb-col lb-settings" aria-label="Einstellungen des Bereichs">
        <div class="lb-h"><b id="lbSetTitle">Bereich bearbeiten</b></div>
        <div class="lb-tabs" role="tablist"><button type="button" class="on" data-lbtab="content">Inhalt</button><button type="button" data-lbtab="design">Design</button><button type="button" data-lbtab="visibility">Sichtbarkeit</button></div>
        <div id="lbFields" class="lb-fields"></div>
      </section>
      <section class="lb-col lb-preview" aria-label="Live-Vorschau">
        <div class="lb-h">
          <div class="lb-dev" role="group" aria-label="Gerät"><button type="button" class="on" data-lbdev="desktop"><i class="fas fa-desktop"></i><span>Desktop</span></button><button type="button" data-lbdev="tablet"><i class="fas fa-tablet-screen-button"></i><span>Tablet</span></button><button type="button" data-lbdev="mobile"><i class="fas fa-mobile-screen"></i><span>Mobil</span></button></div>
          <span class="lb-live"><i class="fas fa-circle"></i> Live-Vorschau</span>
        </div>
        <div class="lb-url"><i class="fas fa-lock"></i><span id="lbUrl">/</span><button type="button" class="lb-ic" id="lbReload" aria-label="Vorschau neu laden"><i class="fas fa-rotate"></i></button></div>
        <div class="lb-stage"><iframe id="lbFrame" title="Vorschau der Startseite" src="about:blank" data-dev="desktop"></iframe></div>
      </section>
    </div>
  </div>
</section>
