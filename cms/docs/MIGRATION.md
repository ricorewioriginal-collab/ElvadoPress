# Migration ElvadoPress → echtes WordPress (Trockenlauf)

**Stand:** Der **Trockenlauf** schreibt nichts nach WordPress. Die **echte Migration** (`Migrator`) ist umgesetzt und getestet, startet aber nur mit ausdrücklicher Bestätigung (`MIGRIEREN`) durch Administratoren und **schaltet die Website nicht um**: Verwaltung und öffentliche Seiten lesen weiter aus den bisherigen Daten.

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

## Ablauf der echten Migration
1. Sicherung (`cms/data`, `cms/media`, Datenbank der WordPress-Tabellen) – ohne Sicherung beginnt nichts.
2. Benutzer (Spiegel, zufälliges unbenutztes Passwort).
3. Medien, 4. Begriffe, 5. Beiträge, 6. Seiten (Eltern zuerst), 7. Menüs.
8. Prüfung: Zahlen und Stichproben vergleichen; Abweichung stoppt.
Das **Umschalten** (Verwaltung/Website lesen aus WordPress) ist ein eigener, späterer Schritt und nicht Teil des Laufs.

Details: Sicherung = Kopie von `cms/data` (ohne Engine-Zustand/Sitzungen) + SQL-Auszug der WordPress-Tabellen unter `cms/data/.wp-engine/migration/backup-<Lauf>/`; Medien werden kopiert, nie verschoben. Papierkorb wird nicht übernommen; geplante Beiträge mit vergangenem Datum werden veröffentlicht; Inhalte bleiben HTML; Menüeinträge mit ungültigem Ziel werden ausgelassen und gemeldet. Jeder Lauf wird protokolliert (`run-<Lauf>.json`), Angelegtes trägt `_elvado_migration_run`. **Rückbau** (`migration_rollback`) entfernt genau diese Objekte. Zeitbudget 240 s: danach „partial“, ein neuer Lauf setzt fort.

Eigenschaften: **keine Big-Bang-Umstellung**, **wiederholbar** (Quellkennung `_elvado_source_id` verhindert Dubletten), **abbrechbar** (jeder Schritt einzeln, ursprüngliche Daten bleiben unverändert bis zum Umschalten).

## Bedienung
Verwaltung → WordPress-Engine → Schritt 5 „Migration (Trockenlauf)“ → *Trockenlauf starten*. Der Bericht lässt sich als JSON herunterladen.

API (nur Administratoren, nicht in der Demo): `migration_run` (POST, `{"confirm":"MIGRIEREN"}`), `migration_rollback` (POST, `{"run":"<Lauf>","confirm":true}`), `migration_runs` (GET); außerdem `POST engine-api.php?action=migration_plan` (erstellt und speichert den Bericht), `GET …?action=migration_report[&file=…]` (letzter/benannter Bericht). Berichte liegen geschützt (0600) in `cms/data/.wp-engine/migration/`, die letzten 10 bleiben erhalten – das ist die einzige Schreibstelle des Trockenlaufs.

## Tests
`php scripts/test-wp-engine-migration.php` (Planner, Berichtsspeicher, „nichts verändert“ per Datei-Prüfsumme). Mit `WPE_TEST_ZIP`/`WPE_TEST_DB` zusätzlich gegen echtes WordPress inkl. Prüfung, dass Beitrags-, Benutzer-, Begriffs-, Meta- und Optionstabellen nach dem Trockenlauf unverändert sind.
