# Bekannte Probleme

## App-Vorlage und Alexa
- **Bereich:** App-Baukasten (`app-template/`) / Alexa
- **Status:** Vorlage umgesetzt (Ordner `app-template/`, zuletzt vor dem Entfernen des Radio-Players mit `scripts/verify-app-template.sh` gebaut; danach nur statisch geprüft, siehe „Native Clients“).
- **Einschränkungen der Vorlage:** Die Apps sind **nicht auf einem Gerät** getestet (nur gebaut/kompiliert und per Unit-Tests der CMS-Auswertung geprüft); die Windows-Pakete entstehen erst im Build-Workflow (Windows-Runner) und sind unsigniert.  Der interne Quelltext-Namensraum der Android-App (`app.elvadopress.client`) ist bewusst von der App-Kennung getrennt.
- **Nächste Untersuchung:** Erste echte App aus der Vorlage auf Gerät (Android) und unter Windows prüfen; Alexa-Paket für eigene Inhalte.

## ElvadoPress Essentials / Plugin-System
- **Bereich:** `cms/src/Plugin/`, `cms/official-plugins/`
- **Nicht live getestet (kein Zugang in der Entwicklungsumgebung):** SMTP mit STARTTLS/SSL gegen echte Server (getestet wurde die Anmeldung gegen einen lokalen Test-Mailserver ohne Verschlüsselung), Matomo und Google Analytics (nur Auslieferung des Skripts geprüft), alle KI-Anbieter (Gateway mit simulierten Antworten), Webhook-Zustellung an echte Ziele, AVIF-Erzeugung hängt von der GD-Bibliothek des Servers ab (hier ohne AVIF getestet, WebP vorhanden).
- **Bewusste Grenzen:** Updates offizieller Plugins kommen aus der mit ElvadoPress ausgelieferten Paketbibliothek (`update.source: bundled`), nicht aus separaten Downloads. Hochgeladene Plugins führen kein Server-PHP aus. Newsletter, Podcast, Radio/Audio, Events, Shop, Social, Consent, Maintenance und WordPress Compatibility Tools existieren noch nicht (nur Katalogeinträge).
- **Hinweise:** Bestehende Installationen bekommen die Essentials inaktiv (kein Verhaltenswechsel ohne Zustimmung). Das Plugin Elvado SEO ersetzt `sitemap.xml` (solange aktiv); die SEO-Felder von Beiträgen/Seiten sind die vorhandenen Felder des CMS, für reine WordPress-Beiträge gelten Beitrags-Metafelder `_elvado_seo_*` (ohne eigene Editor-Oberfläche). Der Seiten-Cache von Elvado Performance wirkt nur für Besucher ohne Cookies/Parameter; Seiten mit dem Attribut `data-elvado-nocache` werden nie gecacht. Elvado Backup exportiert keine Passwörter/Schlüssel; nach einer Wiederherstellung auf einem neuen Server müssen sie neu eingetragen werden. Die Sicherheits-Header (`frame-ancestors 'self'`) verhindern das Einbetten der Website in fremde Seiten (abschaltbar).
- **Control-Center-Anbindung:** vollständig entfernt (Anmeldung nur lokal; Header `X-ElvadoPress-Token`, Speicherschlüssel `elvadopress_session_token`). Bereits angemeldete Browser müssen sich einmal neu anmelden.

- **Behoben:** Die Demo (und jede Installation mit aktiven Plugins Elvado AI/SEO) fror beim Öffnen der Verwaltung ein (Endlosschleife im `MutationObserver` der Plugin-Oberflächen). Plugin-Skripte, die den DOM beobachten, müssen eigene Änderungen ausschließen und bündeln (siehe `cms/docs/PLUGIN-ENTWICKLUNG.md`).

- **WordPress-Kompatibilität, noch offen:** Die Verwaltung ist keine WordPress-Verwaltung; Hooks, die nur dort einen Platz haben, werden (noch) nicht ausgelöst: `admin_footer_text`, `in_admin_header`, `plugin_action_links` (Links in der Plugin-Zeile). `admin_notices`/`all_admin_notices` zeigt die Verwaltung seit dem Rahmen oben (`wp-notices.js`); Meldungen erscheinen einmal global, nicht je Seite.

## WordPress-Engine / Umstrukturierung (Stand Phase 11)
- **Bereich:** `cms/src/Wp/`, `cms/engine-api.php`, Live Builder, Migration
- **Status:** Engine, Dienste/Adapter, Komponenten-Registry, Live Builder, Migration (Trockenlauf, echter Lauf, Rückbau) und Pakete sind umgesetzt und getestet (auch mit echtem WordPress 7.1.3 + MariaDB). Die Engine ist **standardmäßig aus**.
- **Noch offen (bewusst nicht als fertig deklariert):**
  - Die bisherigen Panels (Beiträge, Seiten, Medien, Benutzer, Menüs, Widgets) arbeiten weiter mit den nativen Daten bzw. der Emulation `cms/wp`; das **Umschalten auf die Engine** und danach das **Entfernen der Emulation** (Ziel: nur noch echter Core) steht aus. Die Migration schaltet nicht um; sie wurde in einer Produktivumgebung noch nie ausgeführt.
  - Die REST-API (`cms/rest.php`, Version 1) und Systemstatus/Update-Center (System → Systemstatus, Updates) sind seit Phase 11b vorhanden; offen: Schreiben von Medien-Metadaten/Menüs/Begriffen per REST, Core-/Plugin-Updates direkt aus dem Update-Center einspielen (derzeit nur Anzeige bzw. über die vorhandenen Verwaltungen).
  - Layouts je Seite/Beitrag (`page:*`, `post:*`) haben noch keine Oberfläche; Header/Footer/Navigation bestehender Frontends sind nur über Pakete (gebundene Bereiche) steuerbar.
  - Widget-Bereiche lassen sich nur mit klassischen Themes sinnvoll testen; Werkzeugleisten-Aktionen der Vorschau-Brücke sind nicht per Klick getestet.
  - Migration: Inhalte werden als HTML übernommen (kein Umbau in Blöcke), Widgets müssen von Hand eingerichtet werden, Papierkorb wird nicht übernommen.
  - Verwaltungs-Oberfläche: Zähler-Abzeichen an „Plugins“/„Updates“ und ein Menüpunkt „Automatisierung“ fehlen mangels Funktion.
- **Nächste Untersuchung:** Panels auf die Engine umstellen, danach die Emulation entfernen.

## Mehrere eigenständige Websites (Multisite)
- Grundlage steht (`cms/lib/sites.php`, `scripts/test-sites.php`, `cms/docs/MULTISITE.md`), ist aber noch **nicht eingebunden**: Auslieferung, Verwaltung (Website-Umschalter, Bereich „Websites“), WordPress-Emulation und -Engine je Website folgen in Stufen (siehe Dokument). Bis dahin ändert sich am Betrieb nichts.

## Demo-Bereitstellung
- **Stand:** Die Demo (`elvadopress.ricorewi-radio.de`) wird serverseitig per Deploy (Projekt `elvadopress`, bei Änderung in `main`) bereitgestellt; es laufen keine GitHub-Actions-Deploys. Ob die Domain/Subdomain künftig wechselt, entscheidet der Betreiber.

## Radio-Reste in den nativen App-Clients
- **Stand:** Die Radio-Erweiterung (Theme, Menü, `radio.php`, Sendeplan-API, Radio-Widgets), der Radio-Modus des KI-Assistenten und der Radio-App-Typ im App-Baukasten (Builder, Hörstatistik, Radio-Funktionen, Vorlagenkatalog) sind entfernt. Neue Apps sind Website- oder Baukasten-Apps; der frühere Typ „radio“ gilt als Website-App.
- **Alexa:** Ist ein Website-Skill (Themen des Betreibers und Neuigkeiten aus den Beiträgen); die frühere Sender-/Stream-Logik ist entfernt.
- **Native Clients:** Der Radio-Player ist aus `app-template/` entfernt (Android `MainActivity`, `PlaybackService`, Cast/DLNA, Konto/OIDC, Selbst-Update; Windows `MainWindow`, `RadioApi`, `NativePlayer`). Übrig bleibt die Web-Shell (`WebShellActivity` bzw. `WebShellWindow`) für Website- und Baukasten-Apps. **Nicht kompiliert:** Hier gibt es weder Android-SDK noch .NET; die Änderung wurde per Abhängigkeitsanalyse und `scripts/test-app-template.php` geprüft. Vor dem ersten Einsatz `scripts/verify-app-template.sh` mit SDK ausführen. Selbst-Update und Fehlermeldung (`ErrorReporter`) fehlen in der Android-Web-Shell.
- **Assistent:** Nutzt zusätzlich die aktiven Themen des Alexa-Skills als Wissen. Eigenes Wissen über die Apps der Website hat er nicht (Betreiber können es im Feld „Wissen“ ergänzen).
