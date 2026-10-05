<section id="panel-portal" class="panel">
      <div class="card"><div class="th"><div><div class="tt"><i class="fas fa-globe"></i>Portal & Startseite</div><div class="hint">Texte für Startseite, Magazin und Footer zentral pflegen.</div></div><button class="btn-a" onclick="savePortal()"><i class="fas fa-floppy-disk"></i> Speichern</button></div>
        <div class="section-grid">
          <div><label class="news-lbl">Portalname</label><input id="cmsSiteName" class="fc w-100" maxlength="80"></div>
          <div><label class="news-lbl">Hero-Kicker</label><input id="cmsHeroEyebrow" class="fc w-100" maxlength="60"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Hero-Überschrift</label><input id="cmsHeroTitle" class="fc w-100" maxlength="160"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">Hero-Text</label><textarea id="cmsHeroText" class="fc w-100" rows="4" maxlength="700"></textarea></div>
          <div><label class="news-lbl">News-Überschrift</label><input id="cmsNewsTitle" class="fc w-100" maxlength="120"></div>
          <div><label class="news-lbl">Footer-Text</label><input id="cmsFooterText" class="fc w-100" maxlength="180"></div>
          <div style="grid-column:1/-1"><label class="news-lbl">News-Einleitung</label><textarea id="cmsNewsIntro" class="fc w-100" rows="3" maxlength="400"></textarea></div>
        </div>
      </div>
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-bullhorn"></i>Portal-Hinweis</div><div class="hint">Für Wartung, Aktionen oder wichtige kurzfristige Hinweise oberhalb des Inhalts.</div></div></div>
        <label style="display:flex;align-items:center;gap:10px;margin-bottom:10px"><input id="cmsNoticeEnabled" class="switch" type="checkbox"> Hinweis anzeigen</label>
        <textarea id="cmsNoticeText" class="fc w-100" rows="3" maxlength="500" placeholder="z. B. Heute Wartungsarbeiten ab 23 Uhr."></textarea>
      </div>
    </section>
