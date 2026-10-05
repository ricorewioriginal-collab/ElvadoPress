# ElvadoPress – Veröffentlichung

ElvadoPress ist das eigenständige CMS ohne RicoReWi-Abhängigkeit. Es wird aus diesem Projekt gebaut und in <https://github.com/ricorewioriginal-collab/ElvadoPress> veröffentlicht.

- **Funktionsarten:** CMS, Radio, RicoReWi – siehe `CLAUDE.md` im Wurzelverzeichnis. Nur *RicoReWi* bleibt hier.
- **Bauen:** `php scripts/build-standalone.php <Ordner> --product=ElvadoPress`
- **Branding:** Logo und Icons liegen in `cms/standalone/brand/`; der Produktname kommt aus `cms/lib/product.default.json` (wird beim Bauen erzeugt, kann in der Verwaltung unter *Betrieb & Produktname* überschrieben werden).
- **Prüfen:** `php scripts/test-standalone-build.php`.
- **Veröffentlichen:** Workflow „Publish ElvadoPress“ (manuell oder bei einem Tag `cms-v<Version>`); Secret `ELVADOPRESS_TOKEN` nötig.
