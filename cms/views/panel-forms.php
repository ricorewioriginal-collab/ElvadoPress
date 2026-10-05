<section id="panel-forms" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-inbox"></i>Einsendungen</div><div class="hint">Nachrichten aus Kontaktformularen und Newsletter-Anmeldungen. Die Widgets legst du unter Design → Widgets an („Kontaktformular“, „Newsletter-Anmeldung“). Es werden nur Angaben aus dem Formular gespeichert – keine IP-Adressen.</div></div><button class="btn-g" onclick="FormsManager.load()"><i class="fas fa-rotate"></i> Aktualisieren</button></div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center">
          <button id="fmTabContact" class="btn-g on" onclick="FormsManager.setKind('contact')"><i class="fas fa-envelope"></i> Kontakt <span id="fmCntContact"></span></button>
          <button id="fmTabNewsletter" class="btn-g" onclick="FormsManager.setKind('newsletter')"><i class="fas fa-paper-plane"></i> Newsletter <span id="fmCntNewsletter"></span></button>
          <span style="flex:1"></span>
          <a id="fmExport" class="btn-g" href="#"><i class="fas fa-file-csv"></i> Als CSV</a>
          <button class="btn-g" onclick="FormsManager.bulk('read')"><i class="fas fa-envelope-open"></i> Als gelesen</button>
          <button class="btn-g" onclick="FormsManager.bulk('delete')"><i class="fas fa-trash"></i> Markierte löschen</button>
        </div>
        <div id="fmList" style="margin-top:12px"></div>
        <div id="fmMsg" class="hint" style="margin-top:8px"></div>
      </div>
    </section>
