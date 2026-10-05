<section id="panel-system" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Betrieb &amp; Produkt</div><div class="wp-subtitle">Betriebsmodus, Produktname und Version dieser Installation. Nur für Administratoren.</div></div></div>
        <div id="sysInfo" class="hint">Lädt …</div>
      </div>
      <div class="card">
        <div class="tt"><i class="fas fa-plug-circle-xmark"></i>Betriebsmodus</div>
        <label data-pack="ricorewi-radio" style="display:flex;align-items:center;gap:8px;margin-top:12px"><input id="sysControlCenter" type="checkbox"> Control-Center-Anbindung aktiv (Standard)</label>
        <div class="hint" data-pack="ricorewi-radio" style="margin-top:6px">Ausgeschaltet = eigenständiger Betrieb: Anmeldung nur lokal, keine Anfragen an das Control Center. Funktionen, die es brauchen (Altdaten-Übernahme, Studiomail/Voicemail des Assistenten), sind dann nicht verfügbar. Vorher muss ein lokaler Administrator unter „Redakteure“ existieren.</div>
        <div class="section-grid" style="margin-top:10px">
          <div><label class="news-lbl">Sprache</label><select id="sysLanguage" class="fc w-100"><option value="">Standard</option><option value="de">Deutsch</option><option value="en">English</option></select></div>
          <div><label class="news-lbl">Zeitzone (z. B. Europe/Berlin, leer = Server)</label><input id="sysTimezone" class="fc w-100" placeholder="Europe/Berlin"></div>
        </div>
        <div style="margin-top:12px"><button class="btn-a" onclick="StandaloneManager.saveMode()"><i class="fas fa-floppy-disk"></i> Betrieb speichern</button></div>
      </div>
      <div class="card">
        <div class="tt"><i class="fas fa-signature"></i>Produktname</div>
        <div class="hint" style="margin-top:6px">Alle sichtbaren Bezeichnungen der Verwaltung kommen aus <code>cms/data/product.json</code>. Leer lassen = bisherige Anzeige. Wird nur der Name gesetzt, folgen Titel, Überschrift, Anmeldetext und Generator-Angabe automatisch.</div>
        <div class="section-grid" style="margin-top:10px">
          <div><label class="news-lbl">Produktname</label><input id="prodName" class="fc w-100" maxlength="60"></div>
          <div><label class="news-lbl">Kurzname (Slug)</label><input id="prodSlug" class="fc w-100" maxlength="40" placeholder="wird aus dem Namen abgeleitet"></div>
          <div><label class="news-lbl">Logo (Pfad oder https-Adresse, optional)</label><input id="prodLogo" class="fc w-100" maxlength="300" placeholder="/assets/logo.png"></div>
          <div data-pack="ricorewi-radio"><label class="news-lbl">Control-Center-Bezeichnung</label><input id="prodCc" class="fc w-100" maxlength="60"></div>
          <div><label class="news-lbl">Fenstertitel</label><input id="prodTitle" class="fc w-100" maxlength="80"></div>
          <div><label class="news-lbl">Überschrift</label><input id="prodHeading" class="fc w-100" maxlength="80"></div>
          <div><label class="news-lbl">Name im Anmeldetext</label><input id="prodAccess" class="fc w-100" maxlength="60"></div>
          <div><label class="news-lbl">Generator-Angabe (RSS)</label><input id="prodGen" class="fc w-100" maxlength="60"></div>
        </div>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap"><button class="btn-a" onclick="StandaloneManager.saveProduct()"><i class="fas fa-floppy-disk"></i> Produktname speichern</button><button class="btn-g" onclick="StandaloneManager.resetProduct()"><i class="fas fa-rotate-left"></i> Standard wiederherstellen</button></div>
      </div>
      <div class="card">
        <div class="tt"><i class="fas fa-code-compare"></i>Version &amp; Update</div>
        <div id="sysVersion" class="hint" style="margin-top:8px"></div>
        <div id="updUi" style="margin-top:12px"><div class="hint">Lädt …</div></div>
        <div class="hint" style="margin-top:12px">Updates kommen aus dem eingestellten GitHub-Repository (Releases/Tags oder Branch). Vor jedem Update entsteht eine Sicherung; schlägt die Gesundheitsprüfung fehl, wird automatisch zurückgespielt. Betreiberdaten, Medien, Plugins und eigene Themes bleiben unangetastet. Siehe <code>cms/docs/UPDATE.md</code>.</div>
        <div style="margin-top:10px"><button class="btn-g" onclick="StandaloneManager.checksum()"><i class="fas fa-fingerprint"></i> Prüfsumme berechnen</button> <span id="sysChecksum" class="hint"></span></div>
      </div>
    </section>
