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

**ElvadoPress Essentials (offizielle Plugins)**
- **SEO** (Meta, OpenGraph, Schema.org, Sitemap, Editor-Vorschau), **Security** (Login-Schutz, Header, Prüfungen), **Backup** (Zeitplan, Rotation, Prüfsummen), **Performance** (Seiten-Cache, Lazy Loading, AVIF), **Forms** (Formular-Builder mit SMTP, Spam-Schutz, Webhook), **Analytics** (interne, cookiefreie Statistik; Matomo/Google nur nach bewusster Einrichtung), **Redirects** (automatische Weiterleitungen, Schleifenschutz), **AI** (KI-Werkzeuge für Texte und Apps auf Basis der KI-Zentrale)
- Die Installationsart wählst du im Installer: *Empfohlen*, *Minimal* oder *Benutzerdefiniert*. Weitere offizielle Plugins (Newsletter, Podcast, Shop …) sind im Katalog als „noch nicht verfügbar“ geführt. Entwicklung eigener Plugins: [cms/docs/PLUGIN-ENTWICKLUNG.md](cms/docs/PLUGIN-ENTWICKLUNG.md).

**Erweiterungen (optional, mit deinen eigenen Inhalten)**
- **App-Baukasten:** eigene Android- und Windows-Apps (Website-App oder Baukasten-App mit Tab-Leiste) über GitHub Actions bauen; die Vorlage ist leer und baut nichts von allein, Anleitung: [app-template/ANLEITUNG.md](app-template/ANLEITUNG.md)
- **Alexa-Skill:** Sprachmodell, Skill-Angaben und Backend aus deinen Themen und Beiträgen erzeugen (liest Neuigkeiten vor, beantwortet Fragen zur Website)
- **KI-Assistent** für deine Website
- **Elvado Radio / Audio** (optionales Plugin, nicht Standard): Webradio- und Audio-Streams per Shortcode `[elvado_radio]` als Player einbinden, dazu Sendeplan und „Jetzt läuft“ (`[elvado_radio_schedule]`, `[elvado_radio_now]`)

## Live-Demo

**<https://elvadopress.ricorewi-radio.de>** – Verwaltung: <https://elvadopress.ricorewi-radio.de/cms/?demo=1> (automatische Anmeldung; Benutzer `demo`, Passwort `ElvadoPress-Demo1`).

Die öffentliche Testinstanz mit Demo-Benutzer setzt ihre Daten alle 10 Minuten zurück; Uploads, Plugin-/Theme-Installation und Systemänderungen sind gesperrt. Eigene Demo: `php scripts/make-demo.php <Kopie>` – Details in `cms/docs/ELVADOPRESS.md`.

## Installation

1. Dateien auf einen Webserver legen (PHP 8.1 oder neuer mit `mbstring`; für den Datenbankspiegel zusätzlich `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`; `zip` und `curl` empfohlen). `cms/data/` muss beschreibbar sein.
2. `https://deine-domain/cms/` öffnen – der Einrichtungsassistent führt durch Website-Name, Sprache, Zeitzone, Administratorkonto, optional die Datenbank und die **Installationsart** (Empfohlen mit allen Essentials, Minimal nur Core, Benutzerdefiniert).
3. Fertig: Die Website wird sofort mit dem neutralen Standard-Theme ausgeliefert; weitere Themes und Plugins installierst du in der Verwaltung.

Ausführliche Hinweise stehen in [INSTALL.md](INSTALL.md) und in `cms/docs/`.

## Qualität

- `php scripts/smoke-test.php` richtet das CMS in einem temporären Ordner ein und prüft Einrichtung, Auslieferung, Verwaltung, Alexa-Baukasten und KI-Assistent; die übrigen Tests liegen in `scripts/test-*.php`.
- Der Workflow *CI* führt PHP-Syntaxprüfung, diesen Test und alle weiteren Tests bei jedem Push und Pull Request aus.

## Entwicklung

Dieses Repository ist die **Hauptquelle** von ElvadoPress: Änderungen per Pull Request, die CI führt alle Tests aus (siehe [DEVELOPMENT.md](DEVELOPMENT.md)). Betreiber-spezifische Teile (Marken, Portale) gehören in eigene Repositories.

## Lizenz

ElvadoPress steht unter der **GNU General Public License, Version 2 oder (nach deiner Wahl) jeder späteren Version** (GPL-2.0-or-later) – siehe [LICENSE](LICENSE). Das ist dieselbe Lizenz wie bei WordPress; WordPress-Themes und -Plugins bleiben damit lizenzkompatibel.
