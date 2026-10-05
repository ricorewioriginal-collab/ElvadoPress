<section id="panel-seo" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">SEO & Suchmaschinen</div><div class="wp-subtitle">Zentrale Meta-Angaben plus automatische <code>sitemap.xml</code> und <code>robots.txt</code>.</div></div><button class="btn-a" onclick="SystemManager.saveSeo()"><i class="fas fa-floppy-disk"></i> SEO speichern</button></div>
        <div class="section-grid">
          <div style="grid-column:1/-1"><label style="display:flex;align-items:center;gap:8px"><input id="seoEnabled" class="switch" type="checkbox"> SEO-Generator aktiv</label></div>
          <div><label class="news-lbl">Seitentitel</label><input id="seoTitle" class="fc w-100"></div>
          <div><label class="news-lbl">Canonical-Basis</label><input id="seoCanonical" class="fc w-100" placeholder="https://www.ricorewi-radio.de"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Meta-Beschreibung</label><textarea id="seoDescription" class="fc w-100" rows="3"></textarea></div>
          <div><label class="news-lbl">Robots</label><select id="seoRobots" class="fc w-100"><option value="index,follow">index, follow</option><option value="noindex,nofollow">noindex, nofollow</option></select></div>
          <div><label class="news-lbl">OpenGraph-Bild</label><input id="seoOg" class="fc w-100" placeholder="/icon-512.png"></div>
          <div><label style="display:flex;align-items:center;gap:8px"><input id="seoCustom" type="checkbox"> Eigene Seiten indexieren</label></div>
          <div><label style="display:flex;align-items:center;gap:8px"><input id="seoNews" type="checkbox"> News in Sitemap aufnehmen</label></div>
        </div>
        <div class="hint" style="margin-top:12px">Erzeugt: <a href="/sitemap.xml" target="_blank">/sitemap.xml</a> · <a href="/robots.txt" target="_blank">/robots.txt</a> · RSS bleibt unter <a href="/rss.xml" target="_blank">/rss.xml</a>.</div>
      </div>
    </section>
