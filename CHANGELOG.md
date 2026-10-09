# Änderungen

Neueste Änderungen zuerst. Die Versionsnummer (`cms/VERSION`) wird beim Veröffentlichen eines Releases (Tag `v<Version>`) angehoben.

## Nicht veröffentlicht (nach 1.1.0)

### Projektseite
- Statische Projektseite unter `docs/` (ohne Skripte und externe Ressourcen, `scripts/test-site.php`); nicht im Installations-ZIP. Aktivieren: GitHub → Settings → Pages → *Deploy from a branch* → `main` / `/docs`.

### Update-Hinweise für bestehende Installationen
- **Neu anmelden:** Der Sitzungs-Header heißt jetzt `X-ElvadoPress-Token`, der Browser-Speicherschlüssel `elvadopress_session_token`. Eigene Clients müssen den Header anpassen (`cms/docs/API.md`).
- **Neu veröffentlichen:** Marker in generierten Seiten und interne Namen wurden von `rrw`/`RRW` auf `elvado`/`ELVADO` umgestellt. Nach dem Update einmal veröffentlichen (Verwaltung → Speichern/Veröffentlichen); ältere generierte Seiten können von Hand gelöscht werden.
- **Theme:** Das Standard-Theme heißt `elvado-classic`. Fehlt das aktive Theme, fällt die Website auf das Standard-Theme zurück (statt einer leeren Seite); danach das gewünschte Theme unter Design neu wählen.
- **Zeitzone:** Die bei der Einrichtung gewählte Zeitzone gilt jetzt auch für Website und Feed; neue Beiträge erscheinen ohne Verzögerung.
- **KI-Assistent:** Er ist ab Werk ausgeschaltet. Wer ihn nutzt, schaltet ihn unter Einstellungen → KI-Assistent ein.
- **Datenbank-Spiegel:** Die Tabellen heißen `elvado_cms_state` und `elvado_cms_news`; der Spiegel wird beim nächsten Speichern neu aufgebaut.

### Neu
- Optionales Plugin **Elvado Radio / Audio**: Player (`[elvado_radio]`), Sendeplan (`[elvado_radio_schedule]`) und „Jetzt läuft“ (`[elvado_radio_now]`) aus einer eigenen Liste; nicht Standard.
- App-Vorlage (`app-template/`) ist leer und baut nichts von allein; ausführliche Anleitung mit drei Wegen und Schnittstellen-Referenz (`app-template/ANLEITUNG.md`).
- Der KI-Assistent nutzt die aktiven Themen des Alexa-Skills als Wissen.
- Mediathek-Auswahl in Widget-Feldern (Bild und Audio).
- Integrationstest `scripts/test-integration.php` über eine echte Installation.

### Entfernt
- Radio-Funktionen im Kern (Player, Sendeplan, Radioverzeichnis, Radio-Widgets, Radio-Theme), der Radio-Player in den nativen App-Clients, Control-Center-Anbindung, Partnerseite, Rechtstext-Vorlagen und alle Marken-Inhalte fremder Projekte.

### Behoben
- Zeitzone galt nur in der Verwaltung, nicht auf der Website.
- Beiträge ohne `image_mode` lösten eine PHP-Warnung aus.
- Leere Website, wenn das aktive Theme nicht (mehr) existiert.
