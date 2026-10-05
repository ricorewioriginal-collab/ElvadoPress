# Entwicklung

ElvadoPress wird in diesem Repository entwickelt (Hauptquelle). Die Regeln stehen in [CLAUDE.md](CLAUDE.md).

1. Branch anlegen, ändern, `php scripts/smoke-test.php` und die betroffenen `scripts/test-*.php` ausführen.
2. Pull Request öffnen – die CI führt Syntaxprüfung, Testinstallation und alle Tests aus.
3. Nach dem Merge holt das Entwicklungsprojekt *ricorewi-radio* die Änderungen per Sync-Workflow (Pull Request dort, mit den Paket-Tests der RicoReWi-Seite).

Lizenz: GPL-2.0-or-later (siehe [LICENSE](LICENSE)).
