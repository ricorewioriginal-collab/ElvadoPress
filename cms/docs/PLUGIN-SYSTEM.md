# Plugin-System – Überblick

ElvadoPress kennt **drei getrennte Arten** von Erweiterungen. Sie werden nie vermischt:

| Art | Was ist das | Läuft wo | Verwaltung | Details |
|---|---|---|---|---|
| **Native Plugins (Essentials)** | Offizielle ElvadoPress-Plugins (PHP) unter `cms/official-plugins/<id>/`, Hooks in `cms/lib/nplugins.php` | ElvadoPress selbst; nur offizielle, unveränderte Plugins (Prüfsummen-Katalog) | *Plugins → ElvadoPress-Plugins / ElvadoPress Store* | [PLUGIN-ENTWICKLUNG.md](PLUGIN-ENTWICKLUNG.md) |
| **JavaScript-Plugins** | Hochgeladene ZIP-Plugins, nur JavaScript | Browser der Website, kein Server-PHP | *Plugins → Plugins & Themes / Plugin hinzufügen* | [PLUGINS.md](PLUGINS.md) |
| **WordPress-Plugins (echt)** | Beliebige Plugins aus dem WordPress-Verzeichnis oder als ZIP | **Echter WordPress-Core** (Engine aktiv), nicht die Emulation | *Plugins → Plugins & Themes (Engine)* | [ARCHITECTURE-WORDPRESS.md](ARCHITECTURE-WORDPRESS.md), `ExtensionService` |

## Regeln
- Der Server führt nur **offizielle, unveränderte** native Plugins aus. Hochgeladene Plugins bleiben JavaScript-only.
- WordPress-Plugins laufen **nur mit aktiver Engine** und werden von WordPress ausgeführt; ElvadoPress zeigt sie in der eigenen Oberfläche (Liste, Suche, Installation, Aktivieren/Deaktivieren, Löschen).
- Keine Telemetrie, keine automatisch aktivierten externen Dienste, keine Schlüssel in Repository oder Protokollen.
- Nur Administratoren verwalten Plugins; Eingaben (Kennung, ZIP-Inhalt, Größe, Dateitypen) werden serverseitig geprüft.

## WordPress-Plugins mit Absturzschutz (Engine)
Installation prüft Quelle (feste Hosts), Größe, Dateitypen und PHP-Syntax. Aktivieren läuft über einen **Wächter**: Vor dem Aktivieren wird ein Wächter geschrieben, ein Probelauf startet WordPress einmal unter Sperre; stürzt es ab, wird die Änderung rückgängig gemacht und der **abgesicherte Modus** (nur Kern) eingeschaltet. Die Verwaltung bleibt erreichbar (`ext_safe`). Ein Absturz beim Aktivieren liefert eine verständliche JSON-Meldung statt einer leeren Seite.

## API (nur Administratoren)
`ext_list`, `ext_search`, `ext_install`, `ext_upload`, `ext_activate`, `ext_deactivate`, `ext_delete`, `ext_safe` in `cms/engine-api.php`. Tests: `scripts/test-wp-engine-extensions.php` (mit echtem WordPress), `scripts/test-wp-engine-api.php` (Rechte, Methoden, Eingaben).

## Eigene Erweiterung eines Projekts („Pakete“)
Projekte liefern Zusatzfunktionen als **Paket** (`cms/packs/<paket>/components.php`): bestehende Bereiche der eigenen Website werden als steuerbare Komponenten im Live Builder angebunden – siehe [COMPONENTS.md](COMPONENTS.md). Projektinhalte gehören nie in den neutralen Kern.
