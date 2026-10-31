<section id="panel-settings" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-gear"></i><span id="wpsTitle">Einstellungen</span></div><div class="hint" id="wpsHint"></div></div></div>
        <div class="wps-tabs" id="wpsTabs" role="tablist" aria-label="Einstellungen">
          <button type="button" class="btn-g" data-g="general" onclick="WpSettings.open('general')">Allgemein</button>
          <button type="button" class="btn-g" data-g="writing" onclick="WpSettings.open('writing')">Schreiben</button>
          <button type="button" class="btn-g" data-g="reading" onclick="WpSettings.open('reading')">Lesen</button>
          <button type="button" class="btn-g" data-g="discussion" onclick="WpSettings.open('discussion')">Diskussion</button>
          <button type="button" class="btn-g" data-g="media" onclick="WpSettings.open('media')">Medien</button>
          <button type="button" class="btn-g" data-g="permalinks" onclick="WpSettings.open('permalinks')">Permalinks</button>
        </div>
        <div id="wpsBody" class="wps-body"><div class="hint">Lädt …</div></div>
      </div>
      <div class="card" id="mlCard" style="margin-top:14px">
        <div class="th"><div><div class="tt"><i class="fas fa-language"></i> Mehrsprachige Website</div><div class="hint">Beiträge in mehreren Sprachen: Die Standardsprache bleibt unter <code>/</code>, weitere Sprachen liegen unter <code>/en/</code>, <code>/fr/</code> … Jeder Beitrag hat eine Sprache; im Beitrags-Editor legst du verknüpfte Übersetzungen an. Die Website zeigt je Sprache nur passende Beiträge, setzt hreflang-Verweise und bietet einen Sprachumschalter (auch als Shortcode <code>[elvado_languages]</code>).</div></div></div>
        <div id="mlBody"><div class="hint">Lädt …</div></div>
      </div>
    </section>
