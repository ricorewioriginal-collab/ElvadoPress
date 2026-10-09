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
Bevorzugter Ablauf: Branch anlegen → gezielt ändern → betroffene Tests → Pull Request → CI grün → Merge.

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

Plugin-System: Nach jeder Änderung in `cms/official-plugins/<id>/` zuerst `php scripts/build-official-plugins.php` (schreibt `catalog.json` mit den Prüfsummen neu; `--check` prüft nur). Tests: `scripts/test-nplugins.php` (Framework), `scripts/test-essentials.php` (die acht Essentials inkl. SMTP-Testserver), `scripts/test-install-modes.php` (Installer-Modi und Upgrade über einen echten PHP-Server, braucht freie lokale Ports).

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

## Installations-Paket
Ein Tag `v<Version>` löst den Workflow *Release* aus (ZIP per `git archive`, siehe `.github/workflows/release.yml`).

Details stehen in [INSTALL.md](INSTALL.md).

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

## App-Vorlage (`app-template/`)
- Statische Prüfung (läuft mit allen Tests): `php scripts/test-app-template.php`.
- Bauen und Testen der Beispiel-Apps: `scripts/verify-app-template.sh [--android-only | --windows-only]` – braucht JDK 17, Android-SDK (Plattform 36, Build-Tools 36.0.0, `ANDROID_HOME`), Gradle ≥ 9.6 (`GRADLE=…`) und für den Windows-Teil das .NET-SDK 8 (kompiliert mit `EnableWindowsTargeting`, ohne Paket). Nicht Teil der CI.
- Export für ein App-Repository: `scripts/export-app-template.sh <Ordner> [--zip=<Datei.zip>]`.
