<section id="panel-media" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Medien</div><div class="wp-subtitle">WordPress-artige Medienbibliothek für Bilder, Logos, Thumbnails und weitere CMS-Assets.</div></div><div style="display:flex;gap:7px;flex-wrap:wrap"><label class="btn-a" style="cursor:pointer;margin:0"><i class="fas fa-upload"></i> Hochladen<input id="mediaHubUpload" type="file" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml,image/x-icon,.ico" multiple hidden></label><button class="btn-g" onclick="window.MediaHub?.load(true)"><i class="fas fa-rotate"></i> Aktualisieren</button></div></div>
        <div id="mediaHubDrop" class="dropzone" style="padding:24px;text-align:center;margin-bottom:14px"><i class="fas fa-cloud-arrow-up"></i><br><b>Dateien hier ablegen</b><div class="hint">Bilder werden unter <code>/cms/media/library/</code> gespeichert und können danach in Seiten, Widgets, News und Branding verwendet werden.</div></div>
        <div class="card" style="padding:12px;margin-bottom:12px;background:rgba(255,255,255,.025)">
          <div class="section-grid">
            <div><label class="news-lbl">Automatisch erzeugte Breiten</label><input id="mediaHubSizes" class="fc w-100" value="64,128,192,256,512,1024,1600"><div class="hint" style="margin-top:5px">Kommagetrennt in Pixeln. Original bleibt immer erhalten.</div></div>
            <div><label class="news-lbl">WebP-Qualität</label><input id="mediaHubQuality" class="fc w-100" type="number" min="45" max="100" value="86"><div class="hint" style="margin-top:5px">Für die automatisch erzeugten Varianten.</div></div>
          </div>
        </div>
        <div style="display:flex;gap:8px;align-items:center;margin-bottom:12px"><input id="mediaHubSearch" class="fc w-100" placeholder="Medien durchsuchen …"><select id="mediaHubType" class="fc"><option value="">Alle Typen</option><option value="image">Bilder</option><option value="logo">Logos</option><option value="news">News</option><option value="branding">Branding</option></select></div>
        <div id="mediaHubGrid" class="media-hub-grid"><div class="empty">Medien werden geladen …</div></div>
      </div>
      <div id="stockSettings" class="card"></div>
      <div id="mediaHubDetail" class="card" style="display:none"></div>
    </section>
