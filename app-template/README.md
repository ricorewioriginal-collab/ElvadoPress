# ElvadoPress App-Vorlage

Diese Vorlage macht aus einer ElvadoPress-Website installierbare **Android-** und **Windows-Apps**. Sie gehört zum **Build-Assistenten im CMS** (Apps → *Eigene App bauen*): Das CMS trägt deine Apps in `android/brands.json` ein, lädt Icon und Bilder hoch, startet die Build-Workflows und liefert die fertigen Pakete wieder aus. Alles Weitere (Hinweise, Wartung, Tab-Leiste der Baukasten-App …) steuerst du später **ohne neuen Build** im CMS unter *Apps → Apps verwalten*.

**Ausführliche Anleitung (Wo/Was/Wie): [ANLEITUNG.md](ANLEITUNG.md)** · **Übersicht der Vorlagen: [VORLAGEN.md](VORLAGEN.md)** (`templates.json`)

## App-Typen

| Typ | Wofür | Was die App tut |
| --- | --- | --- |
| **Website-App** (`web`) | jede Website: Shop, Verein, Magazin, Portfolio, Firma … | Deine Website im Vollbild, interne Links bleiben in der App, fremde Adressen/E-Mail/Telefon öffnen extern, Offline-Seite, eigene Farbe. Hinweise, Wartungsmodus und Pflicht-Update kommen aus dem CMS. |
| **Baukasten-App** (`content`) | eigene App mit eigenen Inhalten: Verein, Shop, Gastro, Dienstleister, Magazin | Tab-Leiste unten (bis 5 Tabs); jeder Tab zeigt eine Seite deiner Website. Tabs, Symbole und Reihenfolge pflegst du im CMS (mit fertigen Vorlagen) – ohne neuen Build. |

Beides gibt es für **Android** (APK) und **Windows** (Installer + portable EXE); pro App wählst du die Plattformen.

> **Hier werden keine Apps gebaut.** Dieser Ordner ist eine Vorlage: Im ElvadoPress-Repository laufen keine App-Builds, die Workflows unter `app-template/.github/` sind dort nicht aktiv. Gebaut wird erst im eigenen App-Repository, das du aus dieser Vorlage anlegst (Klon des Repositorys oder `app-template.zip` aus dem Release; eine über den Installer eingerichtete ElvadoPress-Website bringt den Build-Assistenten im CMS mit).

## Schnellstart

1. Aus diesem Ordner ein eigenes GitHub-Repository machen (privat genügt) – siehe [ANLEITUNG.md](ANLEITUNG.md), „Weg A“ (Assistent im CMS), „Weg B“ (GitHub Actions von Hand) oder „Weg C“ (lokal).
2. (Weg A) Fine-grained Token für genau dieses Repository erzeugen (*Contents*, *Actions*: Read and write, *Metadata*: Read).
3. (Weg A) Im CMS unter **Apps → Eigene App bauen** Repository und Token eintragen, **Verbindung prüfen**, App anlegen, **bauen**. Ohne CMS-Verbindung: `android/brands.json` selbst ausfüllen und den Workflow in GitHub → Actions starten (Weg B).

## Inhalt

| Pfad | Zweck |
| --- | --- |
| `templates.json`, `VORLAGEN.md` | Katalog der App-Vorlagen |
| `android/`, `android-app/` | Android-App (Website- und Baukasten-App), Marken aus `android/brands.json` |
| `windows-native/` | Windows-App (WPF/WebView2) |
| `.github/workflows/android-custom-brand.yml` | baut die Android-APK einer Marke |
| `.github/workflows/windows-custom-brand.yml` | baut Installer und portable EXE einer Marke |
| `brands/<id>/` | Icon, Startbild, Logo, Store-Texte je App (legt das CMS an) |
| `scripts/create-developer-keystore.*` | einmalig den Android-Signaturschlüssel erzeugen |
| `icon-512.png`, `android-app/assets/config/` | Standard-Icon und Standardbilder (Platzhalter, werden pro App überschrieben) |

## Hinweise

* Die Quellen enthalten keine Marken- oder Zugangsdaten. Der interne Quelltext-Paketname `app.elvadopress.client` ist **nicht** die App-Kennung – die vergibst du pro App im CMS (z. B. `de.meinefirma.app`).
* Änderungen an dieser Vorlage gehören in das ElvadoPress-Repository (Ordner `app-template/`); danach das eigene App-Repository aktualisieren (ANLEITUNG, „Vorlage aktualisieren“).
