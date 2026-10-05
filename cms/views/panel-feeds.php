<?php $__fb=rtrim(rrw_default_canonical_base()?:'https://deine-domain.example','/'); ?>
<section id="panel-feeds" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Feeds & RSS</div><div class="wp-subtitle">Eigener RSS-Feed für das Magazin und externe RSS-/Atom-Quellen, die automatisch in die News-Übersicht gemischt werden.</div></div><div style="display:flex;gap:7px;flex-wrap:wrap"><a class="btn-g" href="/cms/rss.php" target="_blank" rel="noopener"><i class="fas fa-rss"></i> Live-RSS öffnen</a><a class="btn-g" href="/feed/" target="_blank" rel="noopener"><i class="fas fa-link"></i> /feed/</a><a class="btn-g" href="/rss.xml" target="_blank" rel="noopener"><i class="fas fa-file-code"></i> RSS.xml-Mirror</a><button class="btn-a" onclick="saveFeeds()"><i class="fas fa-floppy-disk"></i> Speichern</button></div></div>
        <div class="section-grid">
          <div style="grid-column:1/-1"><label style="display:flex;align-items:center;gap:8px"><input id="rssEnabled" class="switch" type="checkbox"> Eigenen RSS-Feed aktivieren</label></div>
          <div><label class="news-lbl">Feed-Titel</label><input id="rssTitle" class="fc w-100"></div>
          <div><label class="news-lbl">Max. Einträge</label><input id="rssMaxItems" class="fc w-100" type="number" min="5" max="100"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Beschreibung</label><textarea id="rssDescription" class="fc w-100" rows="3"></textarea></div>
          <div style="grid-column:1/-1"><label style="display:flex;align-items:center;gap:8px"><input id="rssIncludeExternal" class="switch" type="checkbox"> Externe Feed-Beiträge auch im eigenen RSS ausgeben (Link führt zur Originalquelle)</label></div>
        </div>
        <div class="hint" style="margin-top:10px"><b>Empfohlene Feed-Adresse:</b> <code><?=htmlspecialchars($__fb)?>/cms/rss.php</code><br><b>Kurzadresse:</b> <code><?=htmlspecialchars($__fb)?>/feed/</code> · <b>statischer Mirror:</b> <code><?=htmlspecialchars($__fb)?>/rss.xml</code><br>Alle liefern RSS 2.0; der PHP-Endpunkt ist die zuverlässigste Live-Quelle für Reader und Fremdseiten.</div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-satellite-dish"></i>Externe Magazin-Quellen</div><div class="hint">RSS oder Atom eintragen. Die Beiträge erscheinen chronologisch im Magazin, bleiben aber als externe Quelle gekennzeichnet und verlinken zum Original.</div></div><button class="btn-g" onclick="addFeedSource()"><i class="fas fa-plus"></i> Quelle hinzufügen</button></div>
        <div id="feedSources"></div>
      </div>
    </section>
