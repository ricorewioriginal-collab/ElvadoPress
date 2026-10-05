# Entwicklung

ElvadoPress wird in diesem Repository als Hauptquelle entwickelt. Verbindliche Arbeitsregeln stehen in [CLAUDE.md](CLAUDE.md); Orientierung in [PROJECT_MAP.md](PROJECT_MAP.md).

## Voraussetzungen
- PHP **8.1 oder neuer**; CI verwendet PHP 8.2.
- Erforderlich: `mbstring`.
- Für Datenbankfunktionen je nach Treiber: `pdo_sqlite`, `pdo_mysql` oder `pdo_pgsql`.
- Empfohlen bzw. in CI genutzt: `curl`, `zip`, `gd`.
- Das CMS selbst benötigt keinen Node-/Frontend-Build-Schritt.

## Lokales Setup
1. Repository auschecken.
2. Für eine echte Installation muss `cms/data/` durch den Webserver beschreibbar sein.
3. Repository über einen PHP-fähigen Webserver bereitstellen und `/cms/` öffnen; eine frische Installation führt zum Einrichtungsassistenten.
4. Laufzeit-, Zugangsdaten und Secrets niemals committen. Details: [INSTALL.md](INSTALL.md) und `cms/docs/ELVADOPRESS.md`.

## Entwicklung
Bevorzugter Ablauf: Branch anlegen → gezielt ändern → betroffene Tests → Pull Request → CI grün → Merge. Nach dem Merge übernimmt *ricorewi-radio* die CMS-Dateien per Sync-Workflow und führt zusätzliche Paket-Tests aus.

## Syntaxprüfung und Tests
Gezielte PHP-Syntaxprüfung:
```sh
find cms scripts -name '*.php' -print0 | xargs -0 -n1 php -l
```

Smoke-Test:
```sh
php scripts/smoke-test.php
```

Alle vorhandenen Funktionstests:
```sh
fail=0
for t in scripts/test-*.php; do
  echo "== $t"
  php "$t" || fail=1
done
exit $fail
```

Für eine konkrete Änderung bevorzugt nur die betroffenen `scripts/test-*.php` plus ggf. Smoke-Test ausführen. CI führt Syntaxprüfung, Smoke-Test und alle Tests aus.

## React-Bündel
Quellen in `frontend/`; nach Änderungen `cd frontend && npm install && npm run build` (schreibt `cms/assets/react/`, wird eingecheckt). Das CMS selbst braucht keinen Build.

## Demo
Demo-Kopie vorbereiten:
```sh
php scripts/make-demo.php <Kopie>
```

Demo testen:
```sh
php scripts/test-demo.php
```

Details stehen in `cms/docs/ELVADOPRESS.md`.

## Standalone-Paket
```sh
php scripts/build-standalone.php <Zielordner> [--zip=<Datei.zip>]
```

Die Standalone-Logik besitzt eigene Tests unter `scripts/`; Details stehen in [INSTALL.md](INSTALL.md).

## Build und Release
Für die normale Entwicklung gibt es keinen Build-Schritt. Ein Git-Tag `v<Version>` startet `.github/workflows/release.yml` und erzeugt per `git archive` ein Installations-ZIP.

## Datenbanken / Test-Umgebung
Das CMS arbeitet primär dateibasiert. Datenbankdetails: `cms/docs/DATABASE.md`.

`scripts/test-db-config.php` kann zusätzliche Datenbankserver über Test-Environment-Variablen ansprechen. In Dokumentation oder Commits niemals echte Zugangsdaten hinterlegen.

## Environment-Variablen
Es gibt keine allgemeine verpflichtende `.env`-Datei für den normalen CMS-Betrieb. Tests können optionale Variablen verwenden, z. B. für externe Test-Datenbanken. Werte mit Passwörtern/Tokens bleiben ausschließlich lokal bzw. in CI-Secrets.

## Secrets
Niemals Passwörter, Tokens, API-Keys, private SSH-Keys oder andere Secrets in Repository-Dokumentation oder Beispielwerte übernehmen. Laufzeitkonfigurationen wie `*.local.*` und Inhalte unter `cms/data/` sind entsprechend zu behandeln.

Lizenz: GPL-2.0-or-later, siehe [LICENSE](LICENSE).
