<section id="panel-legal" class="panel">
      <div class="legal-grid">
        <div class="card"><div class="th"><div class="tt"><i class="fas fa-building"></i>Impressum</div></div>
          <label class="news-lbl">Modus</label><select id="legalImprintMode" class="fc w-100" onchange="renderLegalMode()"><option value="link">Externe Standard-Verlinkung</option><option value="custom">Eigenes Impressum</option></select>
          <div id="legalImprintLink" style="margin-top:10px"><label class="news-lbl">URL</label><input id="legalImprintUrl" class="fc w-100"></div>
          <div id="legalImprintCustom" style="margin-top:10px"><label class="news-lbl">Titel</label><input id="legalImprintTitle" class="fc w-100"><label class="news-lbl" style="margin-top:8px">Inhalt (HTML erlaubt)</label><textarea id="legalImprintContent" class="fc w-100" rows="12"></textarea></div>
        </div>
        <div class="card"><div class="th"><div class="tt"><i class="fas fa-user-shield"></i>Datenschutz</div></div>
          <label class="news-lbl">Modus</label><select id="legalPrivacyMode" class="fc w-100" onchange="renderLegalMode()"><option value="link">Externe Standard-Verlinkung</option><option value="custom">Eigener Datenschutz</option></select>
          <div id="legalPrivacyLink" style="margin-top:10px"><label class="news-lbl">URL</label><input id="legalPrivacyUrl" class="fc w-100"></div>
          <div id="legalPrivacyCustom" style="margin-top:10px"><label class="news-lbl">Titel</label><input id="legalPrivacyTitle" class="fc w-100"><label class="news-lbl" style="margin-top:8px">Inhalt (HTML erlaubt)</label><textarea id="legalPrivacyContent" class="fc w-100" rows="12"></textarea></div>
        </div>
      </div>
      <div style="text-align:right"><button class="btn-a" onclick="saveLegal()"><i class="fas fa-floppy-disk"></i> Rechtliches speichern</button></div>
    </section>
