<section id="panel-users" class="panel">
      <div class="card">
        <div class="th"><div><div class="wp-page-title">Redakteure</div><div class="wp-subtitle">Mehrere lokale CMS-Zugänge mit Rollen: Administratoren dürfen alles, Autoren nur ihre eigenen Beiträge verwalten.</div></div></div>
        <div class="danger-note" style="margin-bottom:14px"><i class="fas fa-circle-info"></i> Gilt nur für den lokalen CMS-Zugang. Dies ist der einzige Zugang zur Verwaltung.</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:14px">
          <input id="userNewName" class="fc" placeholder="Benutzername" autocomplete="off">
          <input id="userNewDisplay" class="fc" placeholder="Anzeigename (optional)" autocomplete="off">
          <input id="userNewEmail" class="fc" type="email" placeholder="E-Mail (optional, für Benachrichtigungen)" autocomplete="off">
          <input id="userNewPass" class="fc" type="password" placeholder="Passwort (mind. 8 Zeichen)" autocomplete="new-password">
          <select id="userNewRole" class="fc">
            <option value="autor">Autor (nur eigene Beiträge)</option>
            <option value="admin">Administrator (voller Zugriff)</option>
          </select>
          <button class="btn-a" onclick="UsersManager.add()"><i class="fas fa-user-plus"></i> Anlegen</button>
        </div>
        <div id="usersList"><div class="empty">Redakteure werden geladen …</div></div>
      </div>
    </section>
