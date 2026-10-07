# Komponenten-Registry

Die Registry (`cms/src/Components/`) ist die zentrale Beschreibung aller editierbaren Bausteine einer Website: Header, Footer, Hero, Navigation, Text, Bild, Galerie, Button, Container, Spalten, Beiträge, Audio, Video, Formular, Newsletter, Widget-Bereich, WordPress-Block, Shortcode, Plugin-Widget, eigenes HTML u. a. Sie ersetzt nicht das Frontend: **bestehendes HTML/CSS/JS bleibt** (Baukasten-Komponenten werden weiter vom Theme gerendert), die Registry liefert Schema, Regeln, Bereinigung und – wo möglich – eine native Darstellung.

## Eine Komponente
```php
$registry->register([
    'id' => 'partner_wall', 'name' => 'Partner', 'category' => 'extension', 'icon' => 'handshake',
    'fields' => [['k' => 'items', 'type' => 'items', 'max_items' => 8, 'item' => [['k' => 'name', 'type' => 'text'], ['k' => 'logo', 'type' => 'image']]]],
    'rules' => ['droppable' => false, 'repeatable' => true],
], 'mein-plugin', fn(array $props, array $instance) => '<ul>…</ul>');   // Quelle + native Darstellung
```
| Teil | Bedeutung |
|---|---|
| `id`, `name`, `category`, `icon`, `description` | Kennung (`a-z0-9_`), Anzeige, Kategorie (structure, content, media, data, navigation, widgets, radio, advanced, extension) |
| `fields` | Schema: `k`, `type` (text, textarea, html, url, image, checkbox, number, select, color, items), `label`, `default`, `group` (content/design/behavior), `responsive` (geräteabhängig), `options`, `min`/`max`, `max_items`/`item` (Listen), `css` (Zuordnung für geräteabhängiges CSS: `prop`, `unit` oder `tpl`, `target`, `skip_zero`) |
| `renderer` | `native` (PHP-Funktion der Registry, überall nutzbar), `theme` (vom Theme gerendert, z. B. Baukasten), `wp` (nur mit WordPress-Laufzeit), `runtime` (ElvadoPress-Modul, z. B. Formulare) |
| `rules` | `locked` (fest verankert, nur Administratoren ändern), `sortable`, `droppable` + `accepts` (Typen oder `category:<name>`), `parents` (nur in diesen Elternelementen, `root` erlaubt Oberste Ebene), `repeatable`, `max_instances`, `slot` (`first`/`last` für Header/Footer), `use` (`autor` oder `admin`) |
| `data` | Datenquelle (`none`, `posts`, `menu`, `forms`, `widgets` …; Hinweis für die Oberfläche) |
| `preview` | Selektor, mit dem die Vorschau die Komponente findet (`[data-ep-id="{id}"]`) |
| `feature` | Komponente erscheint im Katalog nur, wenn das Merkmal aktiv ist (z. B. `radio`) |

**Neutral bleibt neutral:** Der Kern registriert nur allgemeine Komponenten. Produkt-/Markenspezifisches (z. B. Partner, Social Wall, Sender) registriert eine Erweiterung über den Hook `components_register` (`rrw_np_do('components_register', $registry)`), nicht der Kern. Eine Erweiterung kann Kern-Komponenten nicht überschreiben.

## Instanzen und Layouts
`{ id, type, hidden, locked, props{…}, responsive{tablet{…},mobile{…}}, visibility{devices[…],audience,from,until}, children[…] }`
- `Layout::clean()` bereinigt alles serverseitig nach dem Schema und setzt die Regeln durch (feste Plätze, Wiederholbarkeit, Höchstzahl, erlaubte Kinder/Eltern, Tiefe ≤ 3, höchstens 120 Instanzen, eindeutige IDs). Komponenten ohne Berechtigung werden abgelehnt.
- **Unbekannte Typen** (z. B. Plugin abgeschaltet) bleiben mit ihren Werten als `missing` erhalten und werden nicht ausgegeben – mit dem Plugin kommen sie zurück.
- **Responsive:** nur Felder mit `responsive`; Tablet und Mobil überschreiben die Grundwerte (Mobil erbt von Tablet). Ausgabe als `@media`-Regeln (Tablet ≤ 1024 px, Mobil ≤ 640 px).
- **Sichtbarkeit:** je Gerät (CSS-Klassen `ep-hide-desktop|tablet|mobile`), Zielgruppe (alle/Besucher/Angemeldete) und Zeitfenster (von/bis).
- **HTML-Felder:** durch `rrw_html_sanitize` bereinigt; Roh-HTML-Blöcke nur für Administratoren.

## Darstellung
`Renderer::render($layout, $ctx)` liefert `html` und `css`. Native Komponenten werden selbst ausgegeben (alle Werte maskiert, nur harmlose Adressen); andere über `ctx['renderers'][typ]` (Host) oder als Platzhalter.

## Layouts speichern (`LayoutStore`)
Bereich (`scope`): `home`, `site:<name>` (nur Administratoren), `page:<slug>`, `post:<id>` (auch Autoren; geschützte Komponenten – fest verankerte und Admin-Komponenten – bleiben dabei unverändert). Entwurf → Veröffentlichen, Verwerfen, **Revisionen** (25) und **Rollback** (alte Fassung wird als neue veröffentlicht), **zeitgesteuert** (`publish_at`, wird beim Lesen fällig veröffentlicht). Dateien unter `cms/data/layouts/` (gesperrt, atomar, mit Dateisperre).

## API (`cms/components-api.php`)
`components_catalog`, `layout_get`, `layout_revisions`, `layout_render` (lesen); `layout_save_draft`, `layout_publish`, `layout_discard`, `layout_rollback` (POST). In der Demo ist Schreiben gesperrt.

## Baukasten-Theme
Das Schema der acht Baukasten-Abschnitte kommt aus der Registry (`elvado_bk_schema()`), das Theme rendert sie unverändert und gibt zusätzlich alle nativen Komponenten (ohne festen Platz wie Header/Footer) sowie Shortcode, Block und Widget-Bereich aus; Layout und Entwurf liegen im `LayoutStore` (Bereich `home`). Neu: Sichtbarkeit je Gerät/Zeit/Zielgruppe und geräteabhängige Höhe/Spalten – nur wenn gesetzt, sonst bleibt die Ausgabe identisch. Der Live Builder zeigt Komponenten nach Kategorien und hat die Reiter Inhalt, Design (mit Tablet/Mobil-Werten) und Sichtbarkeit.

Tests: `scripts/test-components.php`, `scripts/test-baukasten.php`.

## Pakete: Bereiche der bestehenden Website als Komponenten („bind“)
Pakete (z. B. ein Projekt-Paket mit eigenem Portal) können ihre **bestehenden** Seitenbereiche im Live Builder steuerbar machen, ohne sie neu zu rendern.

- Eine Komponente mit `'bind' => '#selektor'` (Kennung oder Klasse, z. B. `#main-header`) hat die Rendering-Art **`bound`**: die Website behält ihr HTML, ElvadoPress steuert nur **Gestaltung** (Felder mit `css`-Zuordnung, optional `target` für Teilbereiche), **Teile ein-/ausblenden** (`'css' => ['hide' => '#teil', 'when' => 'off'|'on']` an einem Schalterfeld) und **Sichtbarkeit** (ausgeblendet, Zeitplan, Geräte). Vorgaben erzeugen kein CSS – ohne Änderung bleibt die Seite bytegleich.
- Das Paket liegt unter `cms/packs/<paket>/components.php` und liefert `['register' => fn(Registry $r, string $source): void, 'target' => ['id','label','scope' => 'site:<name>','preview' => '/']]` (oder `'targets' => [ … ]` mit mehreren Zielen, z. B. je Marke; die Vorschau-Adresse darf Parameter tragen, z. B. `/?rrw_brand=<marke>`). `rrw_components_packs()` lädt es nur, wenn `rrw_pack_available('<paket>')` gilt; im eigenständigen CMS gibt es den Ordner nicht. Quelle der Komponenten: `pack:<paket>`.
- Der Live Builder zeigt für Ziele eine Auswahl „Bearbeiten“; dort sind Bereiche nur ausblendbar/gestaltbar (nicht verschieb- oder löschbar). Entwurf, Veröffentlichen, Verlauf, Planung und Rollback laufen über dieselben `layout_*`-Aktionen (Bereich `site:<name>`, nur Administratoren). Die Vorschau holt `layout_preview` (signierter, 15 Minuten gültiger Schlüssel `?rrw_ep_preview=…`) und setzt die Vorschau-Brücke ein; Klicks auf Bereiche der Website wählen die Komponente aus.
- Die Website bindet die Gestaltung mit `rrw_components_inject($html, $scope, $_GET)` ein (`<style id="ep-bound-css">` vor `</head>`; mit gültigem Schlüssel Entwurf + Brücke + `noindex`). Ohne veröffentlichtes Layout und ohne Schlüssel bleibt `$html` unverändert; Fehler lassen es ebenfalls unverändert.
- Test: `php scripts/test-components-packs.php`.
