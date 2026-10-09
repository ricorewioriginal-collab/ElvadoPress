<section id="panel-overview" class="panel on">
      <div id="cmsWelcome" class="card cms-welcome">
        <button type="button" class="cms-welcome-x" title="Ausblenden" onclick="cmsWelcomeHide()"><i class="fas fa-xmark"></i></button>
        <h2>Willkommen im CMS</h2>
        <p class="hint">Hier die wichtigsten Wege, um loszulegen.</p>
        <div class="cms-welcome-cols">
          <div><h3>Los geht’s</h3>
            <button type="button" class="btn-a" onclick="cmsGoto('themes')"><i class="fas fa-brush"></i> Design anpassen</button>
            <p class="hint">oder <a href="#" onclick="cmsGoto('branding');return false">Branding ändern</a></p></div>
          <div><h3>Nächste Schritte</h3>
            <a href="#" onclick="cmsGoto('news');return false"><i class="fas fa-pen"></i> Ersten Beitrag schreiben</a>
            <a href="#" onclick="cmsGoto('pages');return false"><i class="fas fa-file-circle-plus"></i> Seite anlegen</a>
            <a href="/" target="_blank" rel="noopener"><i class="fas fa-eye"></i> Website ansehen</a></div>
          <div><h3>Weitere Aktionen</h3>
            <a href="#" onclick="cmsGoto('widgets');return false"><i class="fas fa-puzzle-piece"></i> Widgets &amp; Menüs verwalten</a>
            <a href="#" onclick="cmsGoto('apps');return false"><i class="fas fa-mobile-screen"></i> Apps verwalten</a>
            <a href="#" onclick="cmsGoto('legal');return false"><i class="fas fa-scale-balanced"></i> Rechtstexte prüfen</a></div>
        </div>
      </div>
      <div class="card"><div class="th"><div class="tt"><i class="fas fa-chart-simple"></i>Auf einen Blick</div><button class="btn-g" onclick="loadDashboardStats()"><i class="fas fa-rotate"></i></button></div><div id="dashboardStats" class="grid"><div class="stat"><div class="l">Lädt…</div><div class="v">–</div></div></div></div>
      <div class="card"><div class="tt"><i class="fas fa-fire"></i>Meistgelesen</div><div id="dashboardTopViewed" style="margin-top:10px"><div class="hint">Lädt…</div></div></div>
      <div id="dashboardCommentsCard" class="card">
        <div class="th"><div class="tt"><i class="fas fa-comments"></i>Letzte Kommentare</div><a class="btn-g" href="#" onclick="cmsGoto('comments');return false"><i class="fas fa-arrow-up-right-from-square"></i> Alle ansehen</a></div>
        <div id="dashboardComments" style="margin-top:10px"><div class="hint">Lädt…</div></div>
      </div>
      <div class="card">
        <div class="tt"><i class="fas fa-pen-to-square"></i>Schnellentwurf</div>
        <div class="hint" style="margin-top:4px;margin-bottom:10px">Kurzer Gedanke ohne Editor – landet als Entwurf im Beitragsbestand.</div>
        <input id="quickDraftTitle" class="fc w-100" placeholder="Titel" style="margin-bottom:8px">
        <textarea id="quickDraftText" class="fc w-100" rows="3" placeholder="Was gibt's Neues?" style="margin-bottom:10px"></textarea>
        <button class="btn-a" onclick="quickDraftSave()"><i class="fas fa-floppy-disk"></i> Als Entwurf speichern</button>
        <span id="quickDraftMsg" style="margin-left:10px;font-size:.76rem;color:var(--muted)"></span>
      </div>
      <div id="dashboardActivityCard" class="card" style="display:none">
        <div class="th"><div class="tt"><i class="fas fa-clock-rotate-left"></i>Letzte Aktivität</div><a class="btn-g" href="#" onclick="cmsGoto('activity');return false"><i class="fas fa-arrow-up-right-from-square"></i> Alle ansehen</a></div>
        <div id="dashboardActivity"><div class="hint">Lädt…</div></div>
      </div>
      <details class="card dash-tech"><summary><i class="fas fa-screwdriver-wrench"></i> Technischer Zustand <span class="hint">für die Fehlersuche</span></summary>
      <div class="card"><div class="th"><div><div class="tt"><i class="fas fa-hard-drive"></i>Speicherort &amp; Veröffentlichung</div><div class="hint">Prüft, ob Änderungen gespeichert und veröffentlicht werden können.</div></div><button class="btn-g" onclick="checkCmsFilesystem()"><i class="fas fa-rotate"></i> Prüfen</button></div><div id="cmsHealthDetails" class="grid"></div></div>
      <div class="card"><div class="th"><div><div class="tt"><i class="fas fa-heart-pulse"></i>Website-Zustand</div><div class="hint">Selbstdiagnose von Server, Anmeldung, Daten und Dateisystem – wie „Website-Zustand“ in WordPress.</div></div><div style="display:flex;align-items:center;gap:8px"><span id="siteHealthBadge" class="publish-state"><i class="fas fa-spinner fa-spin"></i> Prüfung läuft</span><button class="btn-g" onclick="loadSiteHealth(true)"><i class="fas fa-rotate"></i> Prüfen</button></div></div><div id="siteHealthList"><div class="hint">Lädt…</div></div></div>
      </details>
    </section>
