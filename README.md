<p align="center"><img src="cms/assets/brand/elvadopress-logo.png" alt="ElvadoPress" width="560"></p>

<h1 align="center">ElvadoPress</h1>

<p align="center"><strong>Modernes, erweiterbares Content-Management-System für Websites, Themes, Plugins und eigene Web-Projekte.</strong></p>

<p align="center">
  <img alt="PHP 8.1+" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white">
  <img alt="License GPL-2.0-or-later" src="https://img.shields.io/badge/License-GPL--2.0--or--later-blue">
  <img alt="CI" src="https://github.com/ricorewioriginal-collab/ElvadoPress/actions/workflows/ci.yml/badge.svg">
  <img alt="No build step" src="https://img.shields.io/badge/Build%20step-not%20required-success">
</p>

<p align="center">
  <a href="https://elvadopress.ricorewi-radio.de">Live-Demo</a> ·
  <a href="INSTALL.md">Installation</a> ·
  <a href="cms/docs/">Dokumentation</a>
</p>

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
- **KI-Assistent** für Website und Apps

Alle Radio-Erweiterungen sind optional und arbeiten mit deinen eigenen Inhalten.

## Live-Demo

**<https://elvadopress.ricorewi-radio.de>** – Verwaltung: <https://elvadopress.ricorewi-radio.de/cms/?demo=1> (automatische Anmeldung; Benutzer `demo`, Passwort `ElvadoPress-Demo1`).

Die öffentliche Testinstanz mit Demo-Benutzer setzt ihre Daten alle 10 Minuten zurück; Uploads, Plugin-/Theme-Installation und Systemänderungen sind gesperrt. Eigene Demo: `php scripts/make-demo.php <Kopie>` – Details in `cms/docs/ELVADOPRESS.md`.

## Installation

1. Dateien auf einen Webserver legen (PHP 8.1 oder neuer mit `mbstring`; für den Datenbankspiegel zusätzlich `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`; `zip` und `curl` empfohlen). `cms/data/` muss beschreibbar sein.
2. `https://deine-domain/cms/` öffnen – der Einrichtungsassistent führt durch Website-Name, Sprache, Zeitzone, Administratorkonto und optional die Datenbank.
3. Fertig: Die Website wird sofort mit dem neutralen Standard-Theme ausgeliefert; weitere Themes und Plugins installierst du in der Verwaltung.

Ausführliche Hinweise stehen in [INSTALL.md](INSTALL.md) und in `cms/docs/`.

## Qualität

- `php scripts/smoke-test.php` richtet das CMS in einem temporären Ordner ein und prüft Einrichtung, Auslieferung, Verwaltung, Alexa-Baukasten und KI-Assistent (das Radioverzeichnis ist im eigenständigen CMS bewusst nicht enthalten); die übrigen Tests liegen in `scripts/test-*.php`.
- Der Workflow *CI* führt PHP-Syntaxprüfung, diesen Test und alle weiteren Tests bei jedem Push und Pull Request aus.

## Entwicklung

Dieses Repository ist die **Hauptquelle** von ElvadoPress: Änderungen per Pull Request, die CI führt alle Tests aus (siehe [DEVELOPMENT.md](DEVELOPMENT.md)). Marken-spezifische Teile eines Betreibers (z. B. das RicoReWi-Portal) liegen in eigenen Repositories und binden ElvadoPress ein.

## Lizenz

ElvadoPress steht unter der **GNU General Public License, Version 2 oder (nach deiner Wahl) jeder späteren Version** (GPL-2.0-or-later) – siehe [LICENSE](LICENSE). Das ist dieselbe Lizenz wie bei WordPress; WordPress-Themes und -Plugins bleiben damit lizenzkompatibel.
