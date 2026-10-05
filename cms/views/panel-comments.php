<section id="panel-comments" class="panel">
      <div class="card">
        <div class="th"><div><div class="tt"><i class="fas fa-comments"></i>Kommentare</div><div class="hint">Alle Kommentare deiner Beiträge: freigeben, beantworten oder löschen. Ob Kommentare erlaubt sind und ob sie freigegeben werden müssen, stellst du unter Einstellungen → Diskussion ein.</div></div><button class="btn-g" onclick="CommentsManager.load()"><i class="fas fa-rotate"></i> Aktualisieren</button></div>
        <div class="cm-bar">
          <div class="cm-filter" id="cmFilter"><button type="button" class="btn-g on" data-f="all" onclick="CommentsManager.filter('all')">Alle <span id="cmNAll"></span></button><button type="button" class="btn-g" data-f="pending" onclick="CommentsManager.filter('pending')">Ausstehend <span id="cmNPending"></span></button><button type="button" class="btn-g" data-f="approved" onclick="CommentsManager.filter('approved')">Freigegeben <span id="cmNApproved"></span></button></div>
          <input id="cmQuery" class="fc" type="search" placeholder="Kommentare durchsuchen …" oninput="CommentsManager.render()" style="max-width:280px">
        </div>
        <div class="cm-bulk"><label><input type="checkbox" id="cmAll" onchange="CommentsManager.toggleAll(this.checked)"> alle</label>
          <button type="button" class="btn-g" onclick="CommentsManager.bulk('approve')"><i class="fas fa-check"></i> Freigeben</button>
          <button type="button" class="btn-g" onclick="CommentsManager.bulk('delete')"><i class="fas fa-trash"></i> Löschen</button></div>
        <div id="cmList"><div class="hint">Lädt …</div></div>
      </div>
    </section>
