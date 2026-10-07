# Bekannte Probleme

## Historische Angaben in INSTALL.md
- **Bereich:** Dokumentation / Installation / Betriebsmodi
- **Status:** offen; keine Anwendungscode-Änderung erforderlich.
- **Problem:** `INSTALL.md` beginnt mit der Aussage, der Standardbetrieb sei unverändert die Anbindung an das AnMaCha Control Center, und enthält weitere historische RicoReWi-Bezeichnungen. Das steht in Spannung zur aktuellen Repository-Dokumentation, die ElvadoPress als eigenständiges CMS/Hauptquelle beschreibt und bei frischer Installation `control_center: false` dokumentiert.
- **Bekannte Ursache:** Die Datei ist aus der früheren Projektstruktur historisch weitergewachsen.
- **Nächste Untersuchung:** Connected-/Standalone-Verhalten gezielt gegen `cms/install.php`, Auth-/Systemlogik und aktuelle Tests prüfen; danach nur eindeutig veraltete Formulierungen korrigieren. Weiterhin gültige Migrations-/Connected-Mode-Hinweise erhalten.

## App-Vorlage und Alexa
- **Bereich:** App-Baukasten (`app-template/`) / Alexa
- **Status:** Vorlage umgesetzt (Ordner `app-template/`, verifiziert mit `scripts/verify-app-template.sh`: Android baut, Windows kompiliert, Tests grün). Alexa-Erweiterung offen.
- **Einschränkungen der Vorlage:** Die Apps sind **nicht auf einem Gerät** getestet (nur gebaut/kompiliert und per Unit-Tests der CMS-Auswertung geprüft); die Windows-Pakete entstehen erst im Build-Workflow (Windows-Runner) und sind unsigniert. Die Radio-App enthält Funktionen mit Hersteller-Backend (Podcast, Community, Shops) – sie sind ausgeschaltet, solange nicht in `brands.json` unter `"radio"` eingetragen. Der interne Quelltext-Namensraum der Android-App (`app.elvadopress.client`) ist bewusst von der App-Kennung getrennt.
- **Nächste Untersuchung:** Erste echte App aus der Vorlage auf Gerät (Android) und unter Windows prüfen; Alexa-Paket für eigene Inhalte.

## ElvadoPress Essentials / Plugin-System
- **Bereich:** `cms/src/Plugin/`, `cms/official-plugins/`
- **Nicht live getestet (kein Zugang in der Entwicklungsumgebung):** SMTP mit STARTTLS/SSL gegen echte Server (getestet wurde die Anmeldung gegen einen lokalen Test-Mailserver ohne Verschlüsselung), Matomo und Google Analytics (nur Auslieferung des Skripts geprüft), alle KI-Anbieter (Gateway mit simulierten Antworten), Webhook-Zustellung an echte Ziele, AVIF-Erzeugung hängt von der GD-Bibliothek des Servers ab (hier ohne AVIF getestet, WebP vorhanden).
- **Bewusste Grenzen:** Updates offizieller Plugins kommen aus der mit ElvadoPress ausgelieferten Paketbibliothek (`update.source: bundled`), nicht aus separaten Downloads. Hochgeladene Plugins führen kein Server-PHP aus. Newsletter, Podcast, Radio/Audio, Events, Shop, Social, Consent, Maintenance und WordPress Compatibility Tools existieren noch nicht (nur Katalogeinträge).
- **Hinweise:** Bestehende Installationen bekommen die Essentials inaktiv (kein Verhaltenswechsel ohne Zustimmung). Das Plugin Elvado SEO ersetzt `sitemap.xml` (solange aktiv); die SEO-Felder von Beiträgen/Seiten sind die vorhandenen Felder des CMS, für reine WordPress-Beiträge gelten Beitrags-Metafelder `_elvado_seo_*` (ohne eigene Editor-Oberfläche). Der Seiten-Cache von Elvado Performance wirkt nur für Besucher ohne Cookies/Parameter; Seiten mit dem Attribut `data-elvado-nocache` werden nie gecacht. Elvado Backup exportiert keine Passwörter/Schlüssel; nach einer Wiederherstellung auf einem neuen Server müssen sie neu eingetragen werden. Die Sicherheits-Header (`frame-ancestors 'self'`) verhindern das Einbetten der Website in fremde Seiten (abschaltbar).
- **Sync nach ricorewi-radio:** Dateien unter `cms/` werden mitsynchronisiert; mit dem RicoReWi-Paket (`rrw_pack_available()`) ist Installer-/Upgrade-Aktivierung der Essentials ausgeschaltet. Der Test `test-install-modes.php` braucht lokale Ports und einen echten PHP-Server.

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
- **Nächste Untersuchung:** Panels auf die Engine umstellen (mit Golden-Test in ricorewi-radio), danach die Emulation entfernen.

