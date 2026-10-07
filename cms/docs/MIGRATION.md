# Migration ElvadoPress → echtes WordPress (Trockenlauf)

**Stand:** Es gibt bisher **nur den Trockenlauf**. Er schreibt nichts nach WordPress und verändert weder Beiträge, Seiten, Medien, Benutzer, Menüs noch Einstellungen. Die echte Migration wird erst gebaut und ausgeführt, **nachdem sie ausdrücklich freigegeben wurde**.

## Was der Trockenlauf macht
Er liest die bisherigen Daten (über die Native-Adapter, nur lesend) und – wenn die WordPress-Engine aktiv ist – das Ziel (über `WordPressProbe`, nur `SELECT`/Lesefunktionen). Daraus entsteht ein Bericht (`Elvado\Wp\Migration\Planner`):

| Bereich | Geprüft |
|---|---|
| Beiträge, Seiten | Anzahl, Papierkorb/Entwürfe/geplant; bereits übernommen (`_elvado_source_id`, z. B. `post:12`); belegte Adressen → Umbenennung `-2`, `-3` … mit geplanter Weiterleitung; leere Titel; unbekannte Besitzer; Blöcke (`ep:` → `wp:`, HTML-Rückfall); Medienverweise auf fehlende Dateien |
| Medien | Endung/Größe wie in der Mediathek erlaubt (kein SVG/ZIP, ≤ 32 MB), fehlende/leere Dateien, inhaltsgleiche Duplikate, gleichnamige Medien im Ziel |
| Benutzer | gültiger Anmeldename, doppelte (Groß-/Kleinschreibung), fehlende E-Mail, bereits vorhanden. Passwörter/Hashes werden nie gelesen oder in den Bericht geschrieben |
| Begriffe | neu anzulegende bzw. wiederverwendete Kategorien/Schlagwörter |
| Menüs | gültige Ziele, Tiefe > 4 |
| Widgets | haben kein 1:1-Gegenstück → Liste zum manuellen Einrichten |
| Sicherung | geschätzter Platzbedarf (`cms/data` + Medien, doppelt + Puffer) gegen freien Speicher |

Urteil: `ready`, `ready_with_warnings` oder `blocked` (z. B. Engine nicht aktiv, zu wenig Speicher). Mit `NullProbe` (Engine aus) werden Konflikte im Ziel nicht geprüft – der Bericht gilt dann für ein leeres Ziel und ist als blockiert markiert, weil die echte Migration eine aktive Engine voraussetzt.

## Geplanter Ablauf der echten Migration (noch nicht implementiert)
1. Sicherung (`cms/data`, `cms/media`, Datenbank der WordPress-Tabellen) – ohne Sicherung beginnt nichts.
2. Benutzer (Spiegel, zufälliges unbenutztes Passwort).
3. Medien, 4. Begriffe, 5. Beiträge, 6. Seiten (Eltern zuerst), 7. Menüs.
8. Prüfung: Zahlen und Stichproben vergleichen; Abweichung stoppt.
9. Umschalten erst nach Freigabe; Rückweg über Sicherung bzw. Umschalter.

Eigenschaften: **keine Big-Bang-Umstellung**, **wiederholbar** (Quellkennung `_elvado_source_id` verhindert Dubletten), **abbrechbar** (jeder Schritt einzeln, ursprüngliche Daten bleiben unverändert bis zum Umschalten).

## Bedienung
Verwaltung → WordPress-Engine → Schritt 5 „Migration (Trockenlauf)“ → *Trockenlauf starten*. Der Bericht lässt sich als JSON herunterladen.

API (nur Administratoren, nicht in der Demo): `POST engine-api.php?action=migration_plan` (erstellt und speichert den Bericht), `GET …?action=migration_report[&file=…]` (letzter/benannter Bericht). Berichte liegen geschützt (0600) in `cms/data/.wp-engine/migration/`, die letzten 10 bleiben erhalten – das ist die einzige Schreibstelle des Trockenlaufs.

## Tests
`php scripts/test-wp-engine-migration.php` (Planner, Berichtsspeicher, „nichts verändert“ per Datei-Prüfsumme). Mit `WPE_TEST_ZIP`/`WPE_TEST_DB` zusätzlich gegen echtes WordPress inkl. Prüfung, dass Beitrags-, Benutzer-, Begriffs-, Meta- und Optionstabellen nach dem Trockenlauf unverändert sind.
