<section id="panel-profile" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Mein Profil</div><div class="wp-subtitle">Anzeigename, E-Mail und eigenes Passwort ändern.</div></div></div>
        <div id="profileExternalNote" class="danger-note" style="display:none;margin-bottom:14px"><i class="fas fa-circle-info"></i> Dieses Profil wird über das <?=rrw_product_h(rrw_product_control_center())?> verwaltet. Anzeigename und Passwort können nur dort geändert werden.</div>
        <div id="profileForm" style="display:grid;gap:12px;max-width:440px">
          <div><label class="news-lbl">Benutzername</label><input id="profileUsername" class="fc w-100" disabled></div>
          <div><label class="news-lbl">Rolle</label><input id="profileRole" class="fc w-100" disabled></div>
          <div><label class="news-lbl">Anzeigename</label><input id="profileDisplayName" class="fc w-100"></div>
          <div><label class="news-lbl">E-Mail</label><input id="profileEmail" class="fc w-100" type="email" placeholder="für Kommentar-Benachrichtigungen"></div>
          <div><label class="news-lbl">Neues Passwort</label><input id="profilePassword" class="fc w-100" type="password" placeholder="leer lassen, um es nicht zu ändern" autocomplete="new-password"></div>
          <div><label class="news-lbl">Neues Passwort bestätigen</label><input id="profilePasswordConfirm" class="fc w-100" type="password" autocomplete="new-password"></div>
          <div><button class="btn-a" onclick="saveProfile()"><i class="fas fa-floppy-disk"></i> Profil speichern</button></div>
        </div>
      </div>
    </section>
