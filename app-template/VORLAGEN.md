# App-Vorlagen im Überblick

Alle Vorlagen teilen dieselbe Code-Basis (`android/`, `windows-native/`) und dieselben Build-Workflows. Welche Vorlage eine App nutzt, bestimmt das Feld **App-Typ** im CMS (*Apps → Eigene App bauen*), gespeichert als `type` in `android/brands.json`. Maschinenlesbar: [templates.json](templates.json).

| Vorlage | `type` | Wofür | Inhalte kommen aus | Ohne neuen Build änderbar |
| --- | --- | --- | --- | --- |
| **Website-App** | `web` | die Homepage 1:1 als App | die Website | Hinweis, Wartung, Pflicht-Update |
| **Baukasten-App** | `content` | eigene App mit eigenen Inhalten | Seiten/Beiträge/Shop der Website über eine Tab-Leiste | Tabs, Symbole, Reihenfolge, Hinweis, Wartung, Update |

Beide gibt es für Android und Windows.

## Welche Vorlage passt?

* *„Meine Seite soll genau so in der App erscheinen“* → **Website-App**.
* *„Ich will eine App mit eigenen Bereichen (Start, Shop, Termine, Kontakt) und pflege alles im CMS“* → **Baukasten-App** (mit Vorlage Verein, Shop, Magazin, Restaurant/Café oder Dienstleister).

## Eine neue Vorlage ergänzen

1. **CMS:** Typ in `RRW_AB_TYPES` (`cms/lib/appbuild.php`) und in der Typ-Auswahl (`cms/assets/app-build.js`) eintragen; `rrw_ab_merge_brands` schreibt den Typ nach `brands.json`.
2. **Android:** `APP_TYPE` in `android/app/build.gradle` und Start-Activity in `SplashActivity.java` (alle Typen = `WebShellActivity`).
3. **Windows:** Typ in `Brand.cs` (`IsWeb`/`IsContent`) und im Workflow `windows-custom-brand.yml`.
4. **Einstellungen im Betrieb:** Felder in `cms/lib/apps.php` (Bereinigung und `app_config`) und Oberfläche in `cms/assets/apps-manager.js`.
5. **Katalog und Doku:** `templates.json`, diese Datei, `ANLEITUNG.md`; `php scripts/test-app-template.php` prüft die Übereinstimmung von Katalog, CMS und Build.
6. **Prüfen:** `scripts/verify-app-template.sh` (baut je Typ eine Beispiel-App).

Noch nicht enthalten (denkbare weitere Vorlagen): reine Podcast-App, Community-App mit Konto und Push-Nachrichten. Sie sind **nicht** umgesetzt.
