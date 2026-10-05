# Werkzeuge: Wartungsmodus, Weiterleitungen, 404

Nur für Administratoren (CMS → Werkzeuge). Code: `cms/lib/tools.php`, Oberfläche: `cms/assets/tools-manager.js`, `cms/views/panel-tools.php`.
Die Daten liegen in `cms/data/.tools/` (Punkt-Ordner, per Webserver gesperrt, nicht im Repo).

## Wartungsmodus
- Besucher bekommen die Wartungsseite mit Status **503** und `Retry-After` (Suchmaschinen merken sich nichts).
- Greift auf `index.php` (Startseite) und `brandpage.php` (alle `.html`-Seiten, außer `offline.html`).
- Nicht betroffen: Apps, Alexa, `cms/api.php`, das CMS selbst, Bilder/Skripte.
- Der **Vorschau-Link** (`/?vorschau=<Schlüssel>`) setzt ein Cookie für 12 Stunden; so sieht man die echte Website. „Neuer Schlüssel“ macht alte Links ungültig.
- Fehler beim Lesen der Einstellung führen nie zu einer Sperre: im Zweifel wird die Seite normal ausgeliefert.

## Weiterleitungen
- `.htaccess` setzt `ErrorDocument 404 /web/notfound.php`. Der Handler prüft zuerst die Regeln, sonst zeigt er die 404-Seite und protokolliert die Adresse.
- Regeln: „Von“ als Pfad (Groß-/Kleinschreibung und Schrägstrich am Ende egal), `*` am Ende = alles darunter; Ziel ein Pfad oder `https://…`; Code 301, 302, 307 oder 410 (entfernt).
- Verworfen werden: Pfade ohne `/` am Anfang, `//…`, andere Schemata als http/https, Schleifen auf sich selbst, Doppelte.
- Das 404-Protokoll ignoriert Bilder, Skripte, Schriften, Audio/Video; es behält maximal 300 Adressen.

Tests: `php scripts/test-tools.php`

## Theme-Verzeichnis (Themes → „Neue Themes entdecken“)
Kostenlose Themes suchen und mit einem Klick installieren. Quellen sind fest hinterlegt: **Bootswatch** (Bootstrap 5, MIT) und das **WordPress.org-Theme-Verzeichnis** (GPL). Pro Treffer: Autor, Lizenz, Bewertung, Installationen und unterstützte Merkmale (Spalten, Sidebar, Farben, Block-Theme …). Nach „Installieren & Vorschau“ öffnet sich der Live-Customizer; aktiv wird das Theme erst mit „Aktivieren & Veröffentlichen“.

**Grenzen (bewusst):** Übernommen werden nur Design-Dateien (CSS, Bilder). WordPress-PHP, Plugins, Skripte und Webfonts werden nie ausgeführt/übernommen; Aufbau und Funktionen des Portals bleiben. Wie stark sich das Aussehen ändert, hängt vom Theme ab. Installieren darf nur ein Superadmin. Nur HTTPS-Adressen der festen Quellen, Downloads max. 15 MB.
