# App-Vorlage für den Build-Assistenten

Diese Vorlage enthält die Quellen der Android- und Windows-App und die Build-Workflows. Sie wird vom **Build-Assistenten im CMS** (Apps → Eigene App bauen) benutzt: Das CMS trägt deine Apps in `android/brands.json` ein, lädt das Icon nach `brands/<id>/app_logo.png` und startet den Workflow – gebaut wird kostenlos bei GitHub Actions.

## Einrichtung
1. Auf GitHub **„Use this template“** wählen (oder den Inhalt in ein neues, leeres Repository hochladen). Privat ist möglich.
2. Ein **Fine-grained Token** nur für dieses Repository erzeugen: *Contents: Read and write*, *Actions: Read and write*, *Metadata: Read*.
3. Im CMS unter **Apps → Eigene App bauen** Repository (`besitzer/name`) und Token eintragen, **Verbindung prüfen**, App anlegen, **bauen**.

## Inhalt
| Ordner | Zweck |
| --- | --- |
| `android/`, `android-app/` | Android-App (Radio-App und Website-App), Marken aus `android/brands.json` |
| `windows-native/` | Windows-App (WPF/WebView2) |
| `.github/workflows/android-custom-brand.yml` | baut die Android-APK einer Marke |
| `.github/workflows/windows-custom-brand.yml` | baut Installer und portable EXE einer Marke |
| `brands/<id>/` | Icon und Bilder je Marke (legt das CMS an) |

## Signatur (optional)
Ohne Schlüssel entstehen Entwickler-Pakete. Für gleichbleibende Android-Signatur: Repository-Secrets `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`.

Die Vorlage wird mit `scripts/export-app-template.sh` aus dem RicoReWi-Projekt erzeugt; Änderungen an den Apps bitte dort vornehmen und die Vorlage neu exportieren.
