<p align="center"><img src="cms/assets/brand/elvadopress-logo.png" alt="ElvadoPress" width="560"></p>

# ElvadoPress

ElvadoPress ist ein modernes, erweiterbares CMS zur Erstellung und Verwaltung von Websites – mit Themes, Plugins, visueller Bearbeitung, Medienverwaltung und entwicklerfreundlicher API. Es läuft auf normalem PHP-Webhosting, speichert Inhalte in Dateien (optional zusätzlich in SQLite, MySQL, MariaDB oder PostgreSQL) und braucht keinen Build-Schritt.

## Das steckt drin

**CMS**
- Beiträge, Seiten, Kategorien, Schlagwörter, Kommentare mit Moderation, Medienbibliothek
- Menüs, Widgets, Customizer mit Live-Vorschau, Sandbox zum Ausprobieren von Themes vor dem Live-Gang
- **WordPress-kompatible Schicht:** WordPress-Themes (klassisch und Block-Themes) und -Plugins aus dem offiziellen Verzeichnis installieren und ausführen; Permalinks, Startseite/Beitragsseite wie im Original
- Einstellungen wie in WordPress (Allgemein, Schreiben, Lesen, Diskussion, Medien, Permalinks), Werkzeuge (Import, Export, Website-Zustand, Datenschutz, Backup), Benutzer und Rollen
- Formulare, Umfragen, Community (Mitglieder, Forum, soziales Netzwerk)
- SEO, Weiterleitungen, Wartungsmodus, Aktivitätsprotokoll

**Radio-Erweiterungen (für eigene Radio-Apps und -Seiten)**
- **App-Baukasten:** eigene Android- und Windows-Apps (Radio-App oder Website-App) über GitHub-Actions bauen
- **Alexa-Skill:** Sprachmodell, Skill-Angaben und Backend aus deinen eigenen Sendern erzeugen
- **Radioverzeichnis** und **KI-Assistent** für Website und Apps

Alle Radio-Erweiterungen sind optional und arbeiten mit deinen eigenen Inhalten.

## Live-Demo

**<https://elvadopress.ricorewi-radio.de>** – Verwaltung: <https://elvadopress.ricorewi-radio.de/cms/?demo=1> (automatische Anmeldung; Benutzer `demo`, Passwort `ElvadoPress-Demo1`).

Eine öffentliche Testinstanz mit Demo-Benutzer läuft als Demo-Build (`--demo`): Daten werden alle 10 Minuten zurückgesetzt, Uploads, Plugin-/Theme-Installation und Systemänderungen sind gesperrt. Einrichtung und Betrieb: `cms/docs/ELVADOPRESS.md` im Entwicklungsprojekt.

## Installation

1. Dateien auf einen Webserver legen (PHP 8.1 oder neuer mit `mbstring`; für den Datenbankspiegel zusätzlich `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`; `zip` und `curl` empfohlen). `cms/data/` muss beschreibbar sein.
2. `https://deine-domain/cms/` öffnen – der Einrichtungsassistent führt durch Website-Name, Sprache, Zeitzone, Administratorkonto und optional die Datenbank.
3. Fertig: Die Website wird sofort mit dem neutralen Standard-Theme ausgeliefert; weitere Themes und Plugins installierst du in der Verwaltung.

Ausführliche Hinweise stehen in [INSTALL.md](INSTALL.md) und in `cms/docs/`.

## Qualität

- `php scripts/smoke-test.php` richtet das CMS in einem temporären Ordner ein und prüft Einrichtung, Auslieferung, Verwaltung, Alexa-Baukasten, Radioverzeichnis und KI-Assistent.
- Der Workflow *CI* führt PHP-Syntaxprüfung und diesen Test bei jedem Push aus.

## Herkunft und Entwicklung

ElvadoPress wird aus dem Entwicklungsprojekt *ricorewi-radio* veröffentlicht. Dort werden CMS-, Radio- und RicoReWi-Funktionen getrennt entwickelt; diese Veröffentlichung enthält **keine** RicoReWi-Inhalte. Änderungen an diesem Repository werden bei der nächsten Veröffentlichung überschrieben – Beiträge bitte im Entwicklungsprojekt einreichen.

## Lizenz

ElvadoPress steht unter der **GNU General Public License, Version 2 oder (nach deiner Wahl) jeder späteren Version** (GPL-2.0-or-later) – siehe [LICENSE](LICENSE). Das ist dieselbe Lizenz wie bei WordPress; WordPress-Themes und -Plugins bleiben damit lizenzkompatibel.
