# Plugins für ElvadoPress: Core vs. Plugin und offizielle Plugins entwickeln

Dieses Dokument beschreibt das **native Plugin-System** (Verzeichnis `cms/src/Plugin/`, Bedienung unter *Plugins › ElvadoPress-Plugins*) und die **offiziellen Essentials**. Drei Arten von Erweiterungen gibt es nebeneinander – sie sind bewusst getrennt:

| Art | Wo | Was es ist |
| --- | --- | --- |
| **Native offizielle Plugins** („Essentials“ und weitere) | `cms/official-plugins/<id>/` → installiert nach `cms/plugins/<id>/` | Server-PHP + Browser-Skripte, nur aus dem offiziellen Release, vom Server verifiziert. Diese Anleitung. |
| **Frontend-/Admin-Plugins** (JavaScript) | `cms/plugins/<id>/` per ZIP-Upload | Reine JS/CSS-Plugins ohne Server-PHP – siehe [PLUGINS.md](PLUGINS.md). |
| **WordPress-Plugins** (Kompatibilitätsschicht) | `cms/wp-content/plugins/` | Echte WordPress-Plugins, Verwaltung unter *Plugins › WordPress-Plugins*, siehe [WORDPRESS.md](WORDPRESS.md). Davon unberührt. |

## Core oder Plugin?

**Core bleibt** (nie in Plugins auslagern): Plugin-System, Themes und Live-Customizer, Website-/Block-Editor, Medienverwaltung, Benutzer, Rollen und Rechte, Update-System, API-Grundsystem, grundlegende Sicherheit (Login, Sitzungen, geschützte Datenordner, Eingabebereinigung), Installer, die KI-Infrastruktur (KI-Zentrale, Gateway, Modellkataloge, Bild-/Video-Erzeugung, Website-Generator), WordPress-Kompatibilitätsschicht, vorhandene Weiterleitungs-Engine (`cms/lib/tools.php`), einfacher Wartungsmodus.

**Plugin wird**, was optional ist oder neue Fähigkeiten zusätzlich bringt. Ein Plugin **erweitert** den Core über Hooks, es dupliziert ihn nicht:

| Plugin | Neu im Plugin | Nutzt den Core |
| --- | --- | --- |
| Elvado SEO | Meta-Beschreibung, OpenGraph/Twitter, Schema.org, robots-Regeln, echte XML-Sitemap, Editor-Vorschau | SEO-Felder von Beiträgen/Seiten, `rrw_seo_defaults` |
| Elvado Security | Login-Begrenzung, Login-Protokoll, Header, Sicherheitsübersicht, Dateiänderungs-Prüfung | bestehender Login (wird nicht ersetzt, nur vorher/nachher eingehängt) |
| Elvado Backup | Zeitplan, Rotation, Prüfsummen, Redaktion von Geheimnissen, Sicherheitskopie vor Wiederherstellung | bestehender Bereich „Backups“ (die Engine dahinter wird ersetzt) |
| Elvado Performance | Seiten-Cache, Lazy Loading, AVIF, .htaccess-Block, Kennzahlen | WebP-Varianten der Mediathek |
| Elvado Forms | Formular-Builder, SMTP, Spam-Schutz, Webhook, Einsendungen | WordPress-Shortcode-System, `rrw_send_mail` als Rückfall |
| Elvado Analytics | interne Statistik, optional Matomo/Google Analytics | – |
| Elvado Redirects | automatische Weiterleitung bei geändertem Slug, Schleifen-/Ketten-Schutz, 404-Monitor-Aktionen | vorhandene Regeln und 404-Protokoll (`cms/lib/tools.php`, Verwaltung unter Werkzeuge) |
| Elvado AI | Textwerkzeuge im Editor, Vorschläge für Tabs/Hinweise/Store-Texte der Apps | KI-Zentrale (Anbieter, Schlüssel, Modelle, Limits) |

Geplante, noch nicht vorhandene Plugins (Newsletter, Podcast, Radio/Audio, Events, Shop, Social, Consent, Maintenance, WordPress Compatibility Tools) stehen im Katalog als „noch nicht verfügbar“ (`cms/official-plugins/planned.json`). Sie lassen sich nicht installieren und tun nichts.

## Vertrauensmodell (warum ein Plugin „offiziell“ ist)

* Offiziell ist ein Plugin **nur serverseitig**: Seine ID steht im Katalog `cms/official-plugins/catalog.json` **und** die SHA-256-Prüfsumme seines Ordners passt zum Katalog des Releases **und** der Ordner ist seit der Installation unverändert (Prüfsumme im Zustand `cms/data/.plugins/state.json`).
* Das Feld `"official": true` im Manifest ist nur eine Behauptung des Pakets und zählt nicht.
* **Server-PHP führt nur ein offizielles, unverändertes Plugin aus.** Verändert jemand Dateien in `cms/plugins/<id>/`, wird das Plugin nicht mehr geladen und erscheint als „Nicht verifiziert“.
* Hochgeladene ZIPs bleiben reine JS/CSS-Plugins (kein PHP). Ihre IDs dürfen die reservierten IDs des Katalogs nicht verwenden.
* Nach jeder Änderung an einem Plugin im Repository: `php scripts/build-official-plugins.php` (schreibt `catalog.json` neu). `php scripts/test-nplugins.php` prüft, dass der Katalog aktuell ist.

## Manifest (`plugin.json`)

Das Manifest erweitert das bestehende Format (id, name, version, author, license, description) um die Felder unten. Pflicht sind `id`, `name`, `version` (SemVer), `"type": "native"`.

```json
{
  "id": "elvado-seo",
  "name": "Elvado SEO",
  "description": "…",
  "version": "1.0.0",
  "author": "ElvadoPress",
  "homepage": "https://github.com/…",
  "license": "MIT",
  "type": "native",
  "official": true,
  "recommended": true,
  "category": "SEO & Inhalte",
  "icon": "fa-magnifying-glass-chart",
  "requires": { "elvadopress": ">=1.1.0", "php": ">=8.1", "plugins": { "andere-id": ">=1.0.0" } },
  "optional": { "plugins": { "elvado-ai": "*" } },
  "tested_up_to": "1.x",
  "capabilities": ["seo.manage"],
  "update": { "source": "bundled" },
  "entry": "plugin.php",
  "admin_js": "admin.js",
  "admin_global": true,
  "settings": [ { "key": "meta", "type": "toggle", "default": true, "label": "…", "help": "…", "group": "…", "show_if": "anderer_schalter" } ],
  "actions": [ { "id": "sitemap_now", "label": "…", "icon": "fa-sitemap", "confirm": "…" } ]
}
```

* **Bedingungen** (`requires`): `>=1.0.0`, `^1.2`, `~1.2.3`, `1.x`, `*`, kombinierbar mit Leerzeichen (`>=1.0 <2.0`).
* **Einstellungs-Typen**: `toggle`, `text`, `textarea`, `number` (`min`/`max`), `select` (`options`), `secret` (wird dem Browser nie geliefert; leer = unverändert, `__clear__` = löschen), `url` (nur https), `email`. Die Plugin-Seite erzeugt das Formular automatisch; der Server validiert jeden Wert.
* **Aktionen**: Knöpfe der Plugin-Seite; jede ruft die API-Aktion `action_<id>` des Plugins auf und zeigt `message` (und `details`).
* `update.source`: `bundled` (mit ElvadoPress-Updates), `github` (+ `repo`), `manual`. Aktuell werden Updates aus der mitgelieferten Paketbibliothek eingespielt.

## Aufbau und `plugin.php`

```
cms/official-plugins/mein-plugin/
  plugin.json
  plugin.php       Einstieg; bekommt $np (\Elvado\Plugin\Context)
  lib/Klasse.php   eigener Code (Namensraum ElvadoPlugin\<Name>)
  admin.js         optional: Verwaltungsseite oder Editor-Erweiterung
```

```php
<?php
require_once __DIR__ . '/lib/Mein.php';          // Klassen in lib/ einbinden (keine globalen Funktionen deklarieren)
$m = new ElvadoPlugin\Mein\Mein($np);
$np->on('front_output', [$m, 'inject'], 30);    // Hook (Priorität optional)
$np->api('overview', fn() => ['blocks' => [...]]);   // API-Aktion für die Plugin-Seite
```

`$np` bietet: `on($hook, $cb, $prio)`, `setting($key)` / `settings()`, `api($name, $cb, $access)` (`admin`, `editor`, `public`), `dataDir($sub)` (geschützter Datenordner), `log($zeile)` (keine Geheimnisse!), `cmsDir()`, `rootDir()`, `cmsVersion()`, `pluginActive($id)`.

### Hooks

| Hook | Art | Wann | Argumente |
| --- | --- | --- | --- |
| `boot` | Aktion | beim Start jeder API-/Website-Anfrage | – |
| `wp_ready` | Aktion | WordPress-Schicht samt Theme geladen – hier `add_action`/`add_filter`/`add_shortcode` registrieren | – |
| `front_request` | Aktion | Website-Anfrage, vor WordPress (darf `exit`, z. B. Cache) | `$uri, $path, $method` |
| `front_output` | Filter | fertige HTML-Seite (GET, nicht Vorschau) | `$html, $status` |
| `front_response` | Aktion | vor der Ausgabe; auch bei Cache-Treffern | `$status, $path, $contentType` |
| `tick` | Aktion | höchstens einmal pro Minute (Website/Verwaltung), für Zeitpläne | `$now` |
| `login_check` | Filter | vor der Passwortprüfung; Rückgabe Text = abweisen | `$null, $user, $ip` |
| `login_result` | Aktion | nach der Passwortprüfung | `$user, $ok, $ip` |
| `content_saved` | Aktion | Veröffentlichen (`'site'`) bzw. Beitrag geändert (`'news'`) | `$was` |
| `slug_changed` | Aktion | Adresse eines Beitrags geändert | `$art, $alt, $neu` |
| `media_uploaded` | Aktion | neues Bild in der Mediathek | `$meta, $ordner` |
| `redirects_clean` | Filter | Weiterleitungs-Regeln werden gespeichert | `$regeln` |
| `backup_create` / `backup_restore` | Filter | Engine des Bereichs „Backups“; Rückgabe Array/true = erledigt, Text = Fehler (Core bricht ab) | `$prev, …` |
| `plugin_settings_saved` | Aktion | Einstellungen gespeichert | `$id, $einstellungen` |

Ein Fehler (auch Exception) in einem Hook wird protokolliert und bricht die Seite nie ab; ein fataler Fehler schaltet das Plugin automatisch ab.

### Plugin-Seite

Die Seite eines Plugins besteht aus bis zu vier Reitern: **Übersicht** (Rückgabe der API-Aktion `overview`: `blocks`), **Einstellungen** (aus dem Manifest), **Aktionen** (aus dem Manifest) und **Verwalten** (eigenes `admin.js`).

Blocktypen der Übersicht: `stats` (`items: [{label,value,level}]`), `checks` (`items: [{label,status ok|warn|bad|info,text}]`), `table` (`columns`, `rows` mit Zellen oder `{cells, actions:[{label, call, args, confirm, prompt, promptKey, download}]}`), `bars`, `text`, `notice` (`level`).

`admin.js` registriert sich: `ElvadoPluginPages.register('plugin-id', (container, api) => {...})` mit `api.call(name, args)`, `api.toast(text, istFehler)`, `api.download(name, args)`. Mit `"admin_global": true` wird das Skript in der ganzen Verwaltung geladen (z. B. Editor-Erweiterungen).

### Rechte und Datenschutz

* API-Aktionen sind standardmäßig nur für Administratoren; `editor` auch für Redakteure; `public` ohne Anmeldung (nur über `np_public`, z. B. Formular absenden – dort selbst validieren, Spam und Missbrauch begrenzen).
* Keine Telemetrie, keine automatisch aktivierten externen Dienste, keine Schlüssel im Repository oder in Logs. Externe Dienste (KI, Matomo, Google) müssen bewusst eingerichtet werden und sind in der Oberfläche als extern gekennzeichnet.

## Lebenszyklus

| Aktion | Verhalten |
| --- | --- |
| Installieren | Paket verifizieren (Prüfsumme), Manifest und PHP-Syntax prüfen, Kompatibilität prüfen, Abhängigkeiten anbieten, atomar nach `cms/plugins/` kopieren. Fehler hinterlassen nichts. |
| Aktivieren | Abhängigkeiten müssen aktiv sein (oder werden mit aktiviert); Probelauf von `plugin.php` – bei Fehler bleibt es inaktiv, der Fehler steht an der Karte. |
| Deaktivieren | Abhängige aktive Plugins werden genannt; mit Bestätigung mit deaktiviert. |
| Aktualisieren | Sicherung nach `cms/data/.plugins/backup/`, neue Fassung daneben aufbauen, Kompatibilität/Abhängigkeiten (auch der Abhängigen) prüfen, Probelauf; bei Fehler wird die alte Fassung zurückgesetzt. |
| Deinstallieren | nur inaktiv und ohne installierte Abhängige; Sicherung bleibt; Einstellungen/Daten bleiben, außer man wählt „löschen“. |

Zustand: `cms/data/.plugins/` (`state.json`, `settings/`, `data/<id>/`, `backup/`, `log.txt`) – per `.htaccess` gesperrt.

## Installation und Upgrade

* **Neuinstallation** (Installer, Schritt „Installationsart“): *Empfohlen* (alle Essentials installiert und aktiviert), *Minimal* (nur Core – die grundlegende Sicherheit ist Core, kein Pflichtplugin), *Benutzerdefiniert* (Auswahl mit automatischer Abhängigkeitserkennung). Fehler bei Plugins machen die Einrichtung nie ungültig.
* **Bestehende Installationen**: Beim ersten Aufruf der Plugin-Verwaltung werden die Essentials **installiert, aber nicht aktiviert** – die Website verhält sich unverändert, bis der Administrator „Empfohlene aktivieren“ wählt. Inhalte, Medien, Themes, KI-Konfiguration und vorhandene Plugins werden nicht angefasst.

## Tests

`php scripts/test-nplugins.php` (Framework), `php scripts/test-essentials.php` (alle Essentials), `php scripts/test-install-modes.php` (Installer-Modi und Upgrade über HTTP). Vor jedem Commit nach Plugin-Änderungen `php scripts/build-official-plugins.php`.


## Hinweis zu Oberflächen-Skripten (`admin.js`)
Wer in einem Plugin-Skript einen `MutationObserver` auf `document.body` setzt, darf dort keine Änderungen am DOM oder Netzwerkanfragen ungebündelt auslösen: Die Verwaltung ändert den DOM bei jeder Anfrage selbst (Ladeanzeige), das ergibt sonst eine Endlosschleife und die Seite friert ein. Regeln: Ergebnis-Promise statt Ergebnis zwischenspeichern, Callback mit `setTimeout` bündeln, eigene Änderungen ausschließen, nur bei echter Änderung schreiben. Vorbild: `official-plugins/elvado-ai/admin.js` und `elvado-seo/admin.js`.

## Kopplung mit der KI-Zentrale
Sobald in der KI-Zentrale ein nutzbarer Anbieter gespeichert wird, aktiviert `rrw_np_ai_autoactivate()` das Plugin *Elvado AI* einmalig. Wer es danach abschaltet, behält es aus.
