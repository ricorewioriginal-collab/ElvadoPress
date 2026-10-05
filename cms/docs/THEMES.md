# RicoReWi Radio CMS – Themes

## Eigenes Theme-System

Ein natives Theme besteht aus:

```
mein-theme/
  theme.json
  theme.css
  screenshot.png   optional
```

`theme.json` beschreibt Name, Version, Varianten, Standardwerte und Customizer-Regler.

## WordPress-Kompatibilität

Ein WordPress-Theme-ZIP kann importiert werden, wenn eine `style.css` vorhanden ist.

Das CMS übernimmt sicher:
- Theme-Metadaten aus `style.css`
- CSS
- Bilder
- Webfonts
- Screenshots
- statische Assets

Nicht ausgeführt werden:
- `functions.php`
- WordPress-PHP-Templates
- WordPress-Hooks
- serverseitige WordPress-Plugins

Warum: Ein vollständiges WordPress-PHP-Theme benötigt den WordPress-Core und dessen Laufzeit-API. Das RicoReWi CMS bleibt bewusst eigenständig und führt fremden PHP-Code nicht ungeprüft aus.

Das Ergebnis ist deshalb eine **WordPress-Kompatibilitätsschicht für Design und Assets**, nicht eine versteckte WordPress-Installation.

## Bootstrap-Kompatibilität

Themes mit Bootstrap-Struktur oder Bootstrap-CSS werden automatisch markiert.

Unterstützt:
- Bootstrap-CSS
- Grid-Klassen
- Buttons
- Karten
- Utilities
- eigene CSS-Varianten

Nicht automatisch geladen wird fremdes JavaScript aus einem ZIP. JavaScript-Erweiterungen gehören in das Plugin-System.

## Homepage-Baukasten (Theme „ElvadoPress Baukasten“)

Das mitgelieferte Theme `cms/themes/elvado-baukasten` ist ein WordPress-Theme (läuft in der WordPress-Schicht, Einstellungen als `theme_mods`) und dient als freier Baukasten für ein eigenes Design:

- **Startseite aus Abschnitten:** Im Live-Customizer (Design → Themes → Baukasten → *Anpassen*) lassen sich bis zu acht Positionen mit Abschnitten belegen: Hero, Textabschnitt, drei Vorteile, Bild + Text, Neueste Beiträge, Aufruf (Call to Action), eigenes HTML/Shortcodes. Jeder Typ erscheint höchstens einmal; die Reihenfolge ist frei.
- **Visueller Editor im CMS:** Design → **Homepage-Baukasten** (`cms/views/panel-baukasten.php`, `cms/assets/home-builder.js`, API `wp_bk_get`/`wp_bk_save`). Abschnitte hinzufügen, per Drag & Drop oder Pfeilen sortieren, duplizieren, ein-/ausblenden, löschen; gleiche Typen beliebig oft (zusätzlich „Abstand“, Hintergrundvarianten, Spaltenzahl, Kategorie-Filter bei Beiträgen, Karten-Liste bei Vorteilen). Bilder aus der Mediathek oder per Upload; Vorschau (gespeicherter Stand) daneben. Das Layout liegt in der WordPress-Option `elvado_bk_layout`; ohne gespeichertes Layout gelten die acht Positionen aus dem Customizer, „Zurücksetzen“ kehrt dahin zurück. Schema und Bereinigung stehen in `cms/themes/elvado-baukasten/inc/layout.php` (eine Quelle für Editor, API und Theme).
- **Design:** Akzent-, Hintergrund-, Karten-, Text- und Hero-Farben, Schriftart für Überschriften/Fließtext (Systemschriften, kein Fremd-Server), Schriftgröße, Eckenradius, Inhalts- und Seitenleistenbreite, Abschnittsabstand, Kopfbereich (links/zentriert, fixiert), Seitenleiste links/rechts, Fußzeilentext, zusätzliches CSS.
- **Unterseiten** (Beiträge, Seiten, Archive, Suche, 404) nutzen das normale Layout mit Seitenleiste, Menüs (`primary`, `footer`) und Widgets (`sidebar-1`).
- Alle Werte werden serverseitig begrenzt/bereinigt (Farben als Hex, Zahlen mit Grenzen, HTML über `wp_kses_post`, URLs über `esc_url`); eigenes CSS kann den Style-Tag nicht verlassen.
- Test: `php scripts/test-baukasten.php`.

## Radio-Theme „ElvadoPress Radio“ (mitgeliefert)

`cms/themes/elvado-radio` ist ein WordPress-Theme für Webradios. Es wird **mitgeliefert** (nicht separat installiert), weil Verwaltungsmenü, Datenquellen-Abruf und Endpunkt zum CMS gehören und das Theme nur die Darstellung liefert. Es ist neutral: keine Marken, Sender oder Adressen vorgegeben.

- **Aussehen:** dunkles Neon-Design (Violett/Cyan), Glas-Kopfzeile, Hero mit Live-Player, „Jetzt läuft“ mit Cover, Titelverlauf, Sendeplan (heute/Woche), Senderliste, News, feste Player-Leiste am unteren Rand (Lautstärke, Media-Session für Sperrbildschirm). Farben, Überschrift und News-Anzahl im Customizer („Radio: …“).
- **Menü „Radio“ im CMS:** erscheint automatisch (Panel `cms/views/panel-radio.php`, `cms/assets/radio-manager.js`), sobald dieses Theme die Website ausliefert (`radio_state` prüft Flag `front-on` + Option `stylesheet`), und verschwindet beim Wechsel zu einem anderen Theme. Eingerichtet werden Sender (bis 12), Datenquelle, Anzeige-Schalter, Links und ein eigener Sendeplan. „Verbindung testen“ ruft die Quelle ab.
- **Datenquellen** (`cms/lib/radio.php`): **laut.fm** (`api.laut.fm`: aktueller Titel, Verlauf, Playlists → Sendeplan, Stream `stream.laut.fm/<name>` automatisch), **Icecast** (`status-json.xsl` + Mount), **Shoutcast** (v2 `stats?json=1`, v1 `7.html`), **Nur Stream**. Cover optional über die iTunes-Suche. Abrufe laufen serverseitig (SSRF-geschützt: nur öffentliche Adressen), werden kurz zwischengespeichert (Standard 15 s, Cover 7 Tage, Sendeplan 15 min) und fallen bei Ausfall auf den letzten Stand zurück. Beim Seitenaufbau wartet das Theme nie auf externe Server; der Browser holt den Stand danach.
- **Öffentlicher Endpunkt** `cms/radio.php`: `?a=now&station=<id>`, `?a=schedule`, `?a=stations` (nur lesend, keine Zugangsdaten).
- **Shortcodes:** `[radio_player]`, `[radio_nowplaying]`, `[radio_history]`, `[radio_schedule today="1"]`, `[radio_stations]` (jeweils optional `station="<id>"`), nutzbar in Beiträgen, Seiten und im Homepage-Baukasten (HTML-Abschnitt).
- **Grenzen:** Beim Seitenwechsel startet der Browser den Stream neu (kein durchgehender Player über Seiten hinweg). Eine Streamadresse im Heimnetz/auf `localhost` wird nicht abgefragt (Titelanzeige); der Stream selbst spielt trotzdem im Browser.
- **Alexa-Skill (verdrahtet):** Mit aktivem Radio-Theme übernimmt der Alexa-Skill-Baukasten die Sender aus dem Menü „Radio“ automatisch (Schalter `radio_sync` im Alexa-Menü, Block „Radio-Theme“): laut.fm-Sender über ihre Kennung (wie bisher), Icecast/Shoutcast/Nur-Stream als eigener Stream. Alexa spielt nur **https**-Streams; andere Sender werden übersprungen und im Alexa-Menü gemeldet. „Was läuft gerade?“ und der Sendeplan eigener Streams kommen über `cms/radio.php` (`radio_api` in der Skill-Konfiguration, nur wenn die Website über https erreichbar ist; Skill-Backend `cms/lib/alexa-skill/lambda/index.js` neu bei Amazon einspielen). Das Radio-Theme zeigt auf der Startseite „Alexa, öffne …“, sobald der Skill aktiv ist und seine Einstellungen abgerufen hat. Sender-Änderungen ändern das Sprachmodell (Hinweis „neu einspielen“).
- Config: `cms/data/.tools/radio.json`. Tests: `php scripts/test-radio.php`, `php scripts/test-alexa-radio.php`.

## Live-Customizer

Der Customizer unterstützt unter anderem:
- Farben
- Hintergründe
- Content-Breite
- Header-Höhe
- Sidebar-Breite
- Typografie-Skalierung
- Kartenstil
- Navigation
- Buttons
- Footer
- eigenes zusätzliches CSS
- Theme-Varianten
- Desktop / Tablet / Mobile Preview

## Mitgelieferte freie Themes

- RicoReWi Neon
- Broadcast Glass
- Clean Air
- Midnight Magazine
- Bootstrap Wave
- Signal Paper

Alle Theme-Pakete im Repository sind für dieses CMS erstellt und nicht aus fremden Themes kopiert.
