# Projektregeln – ElvadoPress (Hauptquelle des CMS)

Hier wird das CMS **entwickelt**. Das Entwicklungsprojekt *ricorewi-radio* (RicoReWi-Portal, Marken-Inhalte, Produktion von ricorewi-radio.de) übernimmt `cms/` von hier per Sync-Workflow und enthält nur die RicoReWi-spezifischen Teile.

## Was wohin gehört
| Art | Beispiele | Repository |
|---|---|---|
| **CMS** | Beiträge, Seiten, Medien, Kommentare, Einstellungen, WordPress-Schicht, Themes/Plugins, Benutzer, Formulare, Community, Demo-Betrieb | **hier** |
| **Radio-Erweiterungen** (neutral) | App-Baukasten, Alexa-Skill-Baukasten, Radioverzeichnis, KI-Assistent – mit eigenen Inhalten des Betreibers | **hier** |
| **RicoReWi** | Portal (`index.html`, `assets/`), Portal-Themes (`"pack":"ricorewi-radio"`), Radio-Widgets des Portals, Core-Netzwerk, Partnerseite, Rechtstexte-Vorlagen, Apps/Alexa-Katalog des Herstellers, SenderWelt, Studiomail | **nur ricorewi-radio** |

## Regeln
- **Keine RicoReWi-Inhalte** in diesem Repository (Texte, Namen, Adressen, Sender, Themes). Vorgaben sind neutral. Wo das RicoReWi-Paket abweichen soll, gibt es Haken: `rrw_pack_available()` / `rrw_pack_active()` (`cms/lib/pack.php`), in der Oberfläche `data-pack` (nur mit Paket sichtbar) und `data-pack-app` (Radio-Erweiterung, bleibt sichtbar). In JavaScript: `window.CMS_PACKS['ricorewi-radio']`.
- **Bestandsschutz:** Mit dem RicoReWi-Paket (Produktion) darf sich Bestehendes nicht ändern. Der Sync-Pull-Request in ricorewi-radio führt dessen Paket-Tests (`test-pack.php` u. a.) aus; schlägt er fehl, hier korrigieren.
- **Dateien, die nicht synchronisiert werden** (in ricorewi-radio gibt es eigene Fassungen): `cms/lib/product.default.json`, `cms/assets/brand/**`, `cms/lib/alexa-skill/{catalog.json,skill.json,README.md,listing-de.md}`. Alles andere unter `cms/` und die geteilten Tests (`scripts/test-*.php`, `scripts/_testdb.php`, `scripts/smoke-test.php`) wird 1:1 übernommen.
- Neue Dateien unter `cms/` landen automatisch in ricorewi-radio – nichts Markenspezifisches hinzufügen.

## Entwickeln und testen
- Alle Tests: `for t in scripts/test-*.php; do php $t | tail -1; done`; Einrichtung/Verwaltung: `php scripts/smoke-test.php`.
- Demo-Instanz lokal: Repository kopieren, `php scripts/make-demo.php <Kopie>` (siehe `cms/lib/demo.php`); Test `php scripts/test-demo.php`.
- Arbeitsweise: Branch → Pull Request → CI grün → mergen. Antworten und Texte auf Deutsch; ressourcenschonend und fehlerfrei bauen.

## Veröffentlichung
- Release: Tag `v<Version>` erzeugt das ZIP (Workflow *Release*).
- Live-Demo: <https://elvadopress.ricorewi-radio.de> – wird aus ricorewi-radio bereitgestellt (Workflow *Deploy ElvadoPress Demo*), nachdem dort der Sync übernommen wurde.
