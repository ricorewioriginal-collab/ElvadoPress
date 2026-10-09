# Anleitung: Eigene Apps aus ElvadoPress

Diese Anleitung erklärt **wo** du etwas einstellst, **was** es bewirkt und **wie** du vorgehst – von der Einrichtung bis zum laufenden Betrieb. Sie gilt für **Android** (APK) und **Windows** (Installer + portable EXE).

> **Diese Vorlage ist leer.** ElvadoPress liefert keine fertigen Apps und baut oder veröffentlicht nichts. `android/brands.json` enthält keine Apps (`[]`), es gibt keine Marken-Bilder, und die Workflows laufen nur **von Hand** (`workflow_dispatch`), nie automatisch bei einem Push. Erst wenn du in **deinem eigenen** App-Repository eine App einträgst und einen Build startest, entsteht ein Paket.

## Inhalt

1. [Überblick: drei Wege zur App](#überblick-drei-wege-zur-app)
2. [Weg A – Build-Assistent im CMS (empfohlen)](#weg-a--build-assistent-im-cms-empfohlen)
3. [Weg B – GitHub Actions von Hand (ohne Assistent)](#weg-b--github-actions-von-hand-ohne-assistent)
4. [Weg C – lokal selbst bauen](#weg-c--lokal-selbst-bauen)
5. [Im Betrieb verwalten (ohne neuen Build)](#im-betrieb-verwalten-ohne-neuen-build)
6. [Die App-Typen im Detail](#die-app-typen-im-detail)
7. [Schnittstellen-Referenz](#schnittstellen-referenz)
8. [Vorlage aktualisieren](#vorlage-aktualisieren)
9. [Fehlersuche](#fehlersuche)
10. [Technische Hinweise und Grenzen](#technische-hinweise-und-grenzen)

## Überblick: drei Wege zur App

```
ElvadoPress (deine Website, CMS)             GitHub (dein App-Repository, aus dieser Vorlage)
  Apps → Eigene App bauen  ── Konfiguration, Icon, Bilder ──▶  android/brands.json, brands/<id>/…
                           ── Build starten ────────────────▶  Workflows bauen APK / Installer
                           ◀── fertige Pakete ───────────────  Pre-Releases „app-<id>-<nr>“
  Apps → Apps verwalten    ◀── die App fragt beim Start ───── Hinweis, Wartung, Update, Tab-Leiste
```

| Weg | Für wen | Du brauchst | Aufwand |
| --- | --- | --- | --- |
| **A – Assistent im CMS** | die meisten | GitHub-Konto, Token, ZIP der Vorlage | einmal einrichten, danach Klicks im CMS |
| **B – GitHub Actions von Hand** | wer dem CMS kein Token geben will | GitHub-Konto | `brands.json` und Bilder selbst ins Repository legen, Workflow in GitHub starten |
| **C – lokal bauen** | Entwickler, Tests | JDK 17, Android-SDK, Gradle bzw. .NET 8 | Befehle im Terminal |

In allen Wegen wird **nicht auf deinem Webserver** gebaut (Android braucht Gradle und das Android-SDK), sondern bei GitHub (kostenlos im Rahmen des GitHub-Kontingents) oder auf deinem Rechner. **Gesteuert** wird danach alles im CMS, ohne neuen Build.

## Weg A – Build-Assistent im CMS (empfohlen)

Die Schritte 1–4 machst du einmal pro CMS, die Schritte 5–6 pro App.

### Schritt 1 – App-Repository anlegen
Du brauchst ein eigenes GitHub-Repository mit dem Inhalt dieser Vorlage. Drei Wege:

1. **ZIP aus dem Release** (einfach): Im ElvadoPress-Release die Datei `app-template.zip` herunterladen, entpacken, auf GitHub ein **neues, leeres** Repository anlegen und den entpackten Inhalt hochladen (*Add file → Upload files*; den Ordner `.github` nicht vergessen – bei versteckten Ordnern hilft das Hochladen per Git).
2. **Per Git:** Ordner `app-template/` des ElvadoPress-Repositories in ein neues Repository kopieren und pushen (`git init`, `git add .`, `git commit`, `git remote add origin …`, `git push -u origin main`).
3. **Skript:** `scripts/export-app-template.sh [Zielordner]` im ElvadoPress-Repository erzeugt den Ordner bzw. mit `--zip=Datei.zip` das ZIP.

Das Repository darf **privat** sein. Wichtig: Die Datei `android/brands.json` und die beiden Workflows unter `.github/workflows/` müssen im **Standard-Branch** (meist `main`) liegen.

### Schritt 2 – Token erzeugen
GitHub → *Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token*:

* **Repository access:** *Only select repositories* → genau dein App-Repository.
* **Permissions:** *Contents: Read and write*, *Actions: Read and write*, *Metadata: Read-only*.

Das Token bleibt auf deinem Webserver (`cms/data/.apps/build.json`, nicht öffentlich erreichbar) und wird nie an den Browser zurückgegeben.

### Schritt 3 – Im CMS verbinden
CMS → **Apps → Eigene App bauen → 1 · Verbindung zu GitHub**: Repository (`besitzer/name`), Branch (Standard `app-builder`; das CMS legt ihn aus dem Standard-Branch an), Token eintragen → *Speichern* → *Verbindung prüfen*. Alle Zeilen müssen grün sein (Repository gefunden, Schreibrecht, `android/brands.json`, Workflows).

### Schritt 4 – Android-Signatur (empfohlen, einmalig)
Ohne Schlüssel entsteht eine **Entwickler-APK mit Debug-Signatur**: Sie lässt sich installieren, aber nicht über eine bereits installierte Version aktualisieren, wenn sich der Schlüssel ändert. Für gleichbleibende Signatur:

1. `scripts/create-developer-keystore.sh` (Linux/macOS) oder `scripts/create-developer-keystore.ps1` (Windows/PowerShell) ausführen. Der Schlüssel entsteht **nur lokal**.
2. Die vier ausgegebenen Werte in GitHub als **Repository-Secrets** anlegen (*Settings → Secrets and variables → Actions*): `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`. (Mit dem GitHub-CLI geht es automatisch: `bash scripts/create-developer-keystore.sh besitzer/name --set-secrets`.)
3. Den Schlüssel-Ordner **zusätzlich sicher sichern** (Passwortmanager/Offline-Kopie). Geht er verloren, können installierte Apps nicht mehr aktualisiert werden.

Play-Store-Pakete (AAB mit Release-Schlüssel) baut der Assistent nicht; lokal geht es mit `gradle -p android bundle<Marke>Release` und den Umgebungsvariablen `ANDROID_KEYSTORE_PATH`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`.

### Schritt 5 – Eine App anlegen

CMS → **Apps → Eigene App bauen → 2 · Meine Apps → Neue App**. Bis zu 8 Apps pro CMS.

| Feld im CMS | Was es bewirkt | Wo es landet |
| --- | --- | --- |
| **App-Typ** | *Website-App* oder *Baukasten-App* (siehe README) | `android/brands.json` → `type` (`web` oder `content`) |
| **Plattformen** | Android und/oder Windows | nur im CMS; bestimmt, welche Workflows gestartet werden |
| **App-Name** | Name unter dem Symbol, Fenstertitel, Dateiname-Anfang | `appName` |
| **Marken-ID** | interne Kennung (3–20 Kleinbuchstaben/Ziffern, nicht änderbar). Darf **nicht** mit `test` oder `androidtest` beginnen (Android-Regel) und nicht `debug`, `release`, `developer`, `main` heißen | `id` |
| **Paketname** | eindeutige Android-Kennung, z. B. `de.meinefirma.app`; nach Veröffentlichung im Store **nie mehr ändern** | `applicationId` |
| **Website** | Adresse deiner Website (`https://…`, ohne Pfad); die App zeigt sie bzw. holt von dort ihre Daten | `site`, `launchUrl` |
| **Dateiname-Anfang** | Anfang der Dateinamen der Pakete (`<Anfang>-Developer.apk`, `<Anfang>-Setup-x64.exe`) | `filePrefix` |
| **Farbe** | Statusleiste (Android) bzw. Fensterhintergrund (Windows) | `themeColor` |
| **App-Icon** | quadratisch, mindestens 96 px, besser 512 px; *Icon-Hintergrund* füllt transparente Icons | `brands/<id>/app_logo.png` |
| **Startbild** | wird beim Öffnen gezeigt | `brands/<id>/startscreen.png` |
| **Logo in der Kopfzeile** | Wortmarke in der App | `brands/<id>/logo-lockup.png` |
| **Store-Screenshots / Beschreibungen** | bis zu 8 Screenshots, Kurz- (80 Zeichen) und Langbeschreibung für Play Store und Microsoft Store | `brands/<id>/store/screenshot-<n>.png`, `listing-de.md` |

Nur ausgefüllte Angaben werden geschrieben; leere Felder lassen die Standardwerte (Platzhalter-Icon und -Bilder aus `icon-512.png` bzw. `android-app/assets/config/`).

### Schritt 6 – Bauen und herunterladen

Bei der App auf **Android bauen** bzw. **Windows bauen** klicken. Das CMS legt (falls nötig) den Branch an, schreibt `android/brands.json` und die Bilder und startet den Workflow. Das dauert etwa 5–10 Minuten; der Stand erscheint im CMS.

* **Android:** Pre-Release `app-<id>-<nr>` mit `<Dateiname-Anfang>-Developer.apk` – zum Testen auf dem Handy installieren („Unbekannte Quellen“ erlauben).
* **Windows:** Pre-Release `app-<id>-win-<nr>` mit Installer (`…-Setup-x64.exe`) und portabler EXE. Die Dateien sind **nicht signiert**; Windows SmartScreen kann warnen („Weitere Informationen → Trotzdem ausführen“).
* Download direkt im CMS bei der App.

## Weg B – GitHub Actions von Hand (ohne Assistent)

Du gibst dem CMS kein Token; die Konfiguration pflegst du selbst im App-Repository. Das Repository legst du wie in Weg A, Schritt 1 an (Standard-Branch `main`).

1. **Marke eintragen:** `android/brands.json` bearbeiten. Beispiel mit einer Website-App für beide Plattformen:
   ```json
   [
     {
       "id": "meinshop",
       "applicationId": "de.meinefirma.shop",
       "appName": "Mein Shop",
       "launchUrl": "https://www.meinefirma.de/",
       "site": "https://www.meinefirma.de",
       "filePrefix": "Mein-Shop",
       "type": "web",
       "themeColor": "#112233"
     }
   ]
   ```
   Alle Felder und ihre Regeln stehen in der [Schnittstellen-Referenz](#schnittstellen-referenz). Mehrere Apps = mehrere Einträge.
2. **Bilder ablegen (optional):** `brands/meinshop/app_logo.png` (quadratisch, 512 px empfohlen), `brands/meinshop/startscreen.png`, `brands/meinshop/logo-lockup.png`. Fehlt eine Datei, nimmt der Build das Standardbild aus der Vorlage.
3. **Signatur (nur Android, empfohlen):** Secrets anlegen wie in Weg A, Schritt 4.
4. **Build starten:** Im GitHub-Repository → Reiter **Actions** → Workflow **Android custom brand** bzw. **Windows custom brand** → **Run workflow** → Feld *brand* mit der Marken-ID (`meinshop`) füllen → starten. Dauer etwa 5–10 Minuten. Mit dem GitHub-CLI: `gh workflow run android-custom-brand.yml -f brand=meinshop` (Windows: `windows-custom-brand.yml`).
5. **Herunterladen:** Reiter **Releases** → Pre-Release `app-meinshop-<nr>` (Android, `Mein-Shop-Developer.apk`) bzw. `app-meinshop-win-<nr>` (Windows, `Mein-Shop-Setup-x64.exe` und `Mein-Shop-Portable-x64.exe`).
6. **Weitergeben:** Die Dateien auf deine Website legen (z. B. in den Ordner `downloads/`, Download-Zähler über `/cms/api.php?action=app_download&file=<Datei>`) oder direkt aus dem Release verteilen.

Im Betrieb (Hinweise, Wartung, Updates, Tab-Leiste) verwaltest du die App wie in Weg A im CMS unter *Apps → Apps verwalten*; dafür braucht das CMS **keine** Verbindung zu GitHub.

## Weg C – lokal selbst bauen

Voraussetzungen: JDK 17, Android-SDK (Plattform 36, Build-Tools 36.0.0, `ANDROID_HOME`), Gradle 9.6 oder neuer.

```bash
# Marke in android/brands.json eintragen (id, applicationId, appName, launchUrl, site, filePrefix, optional type "web" bzw. "content" und themeColor),
# Icon nach android/app/src/<id>/res/drawable-nodpi/app_logo.png kopieren (z. B. icon-512.png), dann:
gradle -p android assemble<Id>Developer        # <Id> mit großem Anfangsbuchstaben, z. B. assembleMeinshopDeveloper
# APK: android/app/build/outputs/apk/<id>/developer/app-<id>-developer.apk
```

Windows (nur unter Windows mit .NET-SDK 8): `dotnet publish -c Release -r win-x64 -p:BrandAppName="Meine App"` im Ordner `windows-native/`, Installer mit Inno Setup (`installer.iss`). Zum reinen Prüfen reicht `scripts/verify-app-template.sh` im ElvadoPress-Repository (baut zwei Beispiel-Apps und führt die Tests aus).

Die Signatur lokal: Entwickler-Schlüssel mit `scripts/create-developer-keystore.sh` erzeugen und die Umgebungsvariablen `ANDROID_DEVELOPER_KEYSTORE_PATH`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD` setzen; ohne sie wird mit dem Debug-Schlüssel signiert.

## Im Betrieb verwalten (ohne neuen Build)

CMS → **Apps → Apps verwalten**. Pro App und Plattform gibt es einen aufklappbaren Block. Die App holt die Einstellungen beim Start von deiner Website (`cms/api.php?action=app_config&brand=<Marken-ID>`); Änderungen gelten also ohne neues Paket, sobald „Speichern“ gedrückt ist.

| Einstellung | Gilt für | Wirkung in der App |
| --- | --- | --- |
| **Hinweis an alle Nutzer** (Überschrift, Text, Link, Art Info/Wichtig) | beide App-Typen | Hinweis in der App (Banner bzw. Dialog), einmal pro Gerät – bis du den Text änderst |
| **Wartungsmodus** | beide App-Typen | Die App zeigt nur die Wartungsmeldung („Erneut prüfen“), bis du ihn wieder ausschaltest |
| **Datenschutz & Statistik** (anonyme Nutzungszahlen; Fehlerberichte nur Windows) | Nutzungszahlen beide, Fehlerberichte Windows | standardmäßig **aus**; gespeichert werden keine Namen oder IP-Adressen |

Bei einer fehlgeschlagenen Abfrage (kein Netz, Server nicht erreichbar) **startet die App immer normal** – sie sperrt nie aus.

## Die App-Typen im Detail

### Die Website-App

Die App lädt `https://<deine-website>/` im Vollbild:

* Interne Adressen (deine Domain, https) bleiben in der App; fremde Adressen, `mailto:`, `tel:` und Downloads öffnen im Standardbrowser/-programm.
* Ohne Verbindung erscheint eine Offline-Seite mit „Erneut versuchen“.
* Farbe, Name, Icon und Startbild kommen aus den App-Feldern; Hinweise und Wartung aus *Apps verwalten* (siehe „Im Betrieb verwalten“).
* Die Website selbst gestaltest du wie gewohnt im CMS (Themes, Baukasten, Widgets) – die App zeigt immer den aktuellen Stand.

### Die Baukasten-App

Die **Baukasten-App** (`content`) ist die Vorlage für eine **eigene App mit eigenen Inhalten**, ohne dass du Programmcode anfasst: Die App zeigt unten eine **Tab-Leiste**, jeder Tab öffnet eine Seite deiner Website. Was dort steht, pflegst du wie gewohnt im CMS (Seiten, Beiträge, Shop, Formulare, Community …) – die App spiegelt es.

**Einrichten:** *Apps → Eigene App bauen* → App-Typ **Baukasten-App** → bauen. Danach unter *Apps → Apps verwalten → (deine App) → Inhalte der App*:

| Feld | Bedeutung |
| --- | --- |
| Symbol | eines von zwölf mitgelieferten Symbolen (Start, Neuigkeiten, Info, Shop, Termine, Kontakt, Karte, Nachricht, Profil, Favoriten, Medien, Menü) |
| Titel | Beschriftung des Tabs (bis 16 Zeichen) |
| Adresse | Pfad auf deiner Website (`/kontakt/`) oder volle `https://`-Adresse (z. B. ein Shop) |
| ‹ › 🗑 | Reihenfolge ändern, Tab entfernen |
| Vorlage laden | fertige Tab-Leiste für Verein, Shop, Magazin, Restaurant/Café oder Dienstleister – danach Pfade an deine Seiten anpassen |

Mit **Speichern** gilt die neue Leiste sofort in allen installierten Apps (beim nächsten Start; die zuletzt bekannte Leiste bleibt auch offline erhalten). Ein **neuer Build ist nicht nötig**. Weniger als zwei Tabs blenden die Leiste aus.

| Seite wählen … | Auswahlliste mit Startseite, Seiten, Beiträgen und Kategorien deiner Website – trägt den Pfad (und, wenn leer, den Titel) ein |
| Kopf und Fuß der Website | *Automatisch* (Baukasten-App: in der App ausblenden), *In der App ausblenden* oder *anzeigen* |

Rechts neben dem Editor zeigt die **Live-Vorschau** die Website im Handy-Rahmen im App-Modus samt Tab-Leiste; Tabs lassen sich dort antippen. Für die Vorschau muss die Website unter der eingetragenen Adresse erreichbar sein.

**App-Modus:** Die Apps hängen ihrem User-Agent `ElvadoPressApp/1.0 (brand=<id>; platform=android|windows)` an. Daran erkennt die Website die App und blendet – je nach Einstellung – Kopf und Fuß (`.site-header`, `#masthead`, `.site-footer`, `#colophon`) aus; das `<body>` bekommt die Klasse `elvado-app`. Eigene Elemente steuerst du mit den Klassen `elvado-hide-in-app` (nur im normalen Browser sichtbar) und `elvado-only-app` (nur in der App sichtbar, per CSS `display:none` für den Browser vorbelegen). Im Browser zum Ausprobieren: `?elvado_app=<id>` an die Adresse hängen (`?elvado_app=off` beendet das).

**Gut zu wissen**

* Der App-Modus gilt für Themes, die Kopf und Fuß mit den üblichen Klassen ausgeben (alle mitgelieferten Elvado-Themes); bei fremden Themes ergänzt du die Selektoren im CSS deines Themes mit `body.elvado-app`.
* Hinweis, Wartungsmodus und Pflicht-Update funktionieren wie bei der Website-App (siehe „Im Betrieb verwalten“).
* Fremde Adressen, E-Mail und Telefon öffnen außerhalb der App; ein Tab mit fremder `https://`-Adresse lädt sie ausnahmsweise in der App.
* Technisch: Die Leiste steht als `tabs` in der Antwort von `cms/api.php?action=app_config&brand=<id>`; Android und Windows lesen sie beim Start (`WebRuntime`).

### Weitere Vorlagen

Der Katalog der Vorlagen steht in [`templates.json`](templates.json). Eine neue Vorlage (z. B. Podcast-App, Community-App) besteht immer aus: einem Typ-Eintrag in `ELVADO_AB_TYPES` (`cms/lib/appbuild.php`), der Auswertung des Typs in `android/app/build.gradle` und `windows-native/Brand.cs` sowie den Einstellungen unter *Apps verwalten*. Siehe [VORLAGEN.md](VORLAGEN.md).

## Schnittstellen-Referenz

Alle Schnittstellen sind ohne Zusatzdienste nutzbar: Apps sprechen nur mit **deiner Website**, das CMS nur mit **GitHub**.

### 1. `android/brands.json` (App-Repository)

Liste der Apps. Das CMS schreibt sie im Weg A, im Weg B pflegst du sie selbst. Unbekannte Zusatzfelder bleiben erhalten.

| Feld | Pflicht | Regel | Wirkung |
| --- | --- | --- | --- |
| `id` | ja | 3–20 Kleinbuchstaben/Ziffern, beginnt mit Buchstaben; nicht `test…`, `androidtest…`, `debug`, `release`, `developer`, `main`, `android`, `app` | Marken-ID: Product Flavor, Workflow-Eingabe, Release-Tag, `brand=` der App-Abfrage |
| `applicationId` | ja | wie `de.meinefirma.app` (Kleinbuchstaben, mindestens zwei Teile, keine Java-Schlüsselwörter) | Android-Paketname; nach Store-Veröffentlichung nie mehr ändern |
| `appName` | ja | 1–30 Zeichen: Buchstaben, Ziffern, Leerzeichen, `. _ -` | Name unter dem Symbol, Fenstertitel |
| `site` | ja | `https://host[:port]`, **ohne Pfad** | Website; hierhin fragt die App `app_config` |
| `launchUrl` | ja | volle `https://`-Adresse (üblich: `site` + `/`) | Startseite der App |
| `filePrefix` | ja | 2–30 Zeichen: Buchstaben, Ziffern, `-` | Anfang der Dateinamen der Pakete |
| `type` | nein | `web` (Standard) oder `content` | Website-App bzw. Baukasten-App |
| `themeColor` | nein | `#rrggbb` | Statusleiste (Android), Fensterhintergrund (Windows) |

Bilder je Marke unter `brands/<id>/`: `app_logo.png` (Icon), `startscreen.png`, `logo-lockup.png`, `store/screenshot-<n>.png` (bis 8), `store/listing-de.md` (Store-Texte).

### 2. Workflows (GitHub Actions)

| Workflow | Datei | Eingabe | Ergebnis |
| --- | --- | --- | --- |
| Android | `.github/workflows/android-custom-brand.yml` | `brand` (Marken-ID) | Pre-Release `app-<id>-<nr>` mit `<filePrefix>-Developer.apk` |
| Windows | `.github/workflows/windows-custom-brand.yml` | `brand` (Marken-ID) | Pre-Release `app-<id>-win-<nr>` mit `<filePrefix>-Setup-x64.exe` und `<filePrefix>-Portable-x64.exe` |

Auslöser ist ausschließlich `workflow_dispatch` (von Hand oder per API). Der Workflow bricht ab, wenn die Marken-ID ungültig ist oder nicht in `android/brands.json` steht. Optionale Secrets (Android-Signatur): `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`.

### 3. Windows: `assets/config/brand.json`

Der Windows-Workflow schreibt sie aus `brands.json`: `{"id","name","website","type","themeColor"}`. Fehlt die Datei (lokaler Lauf), gelten neutrale Standardwerte (`ElvadoPress App`, `https://example.org/`, Typ `web`).

### 4. CMS → GitHub (nur Weg A)

Der Assistent nutzt ausschließlich die GitHub-REST-API mit deinem Token (Header `Authorization: Bearer …`, feste Domain `api.github.com`):

| Zweck | Aufruf |
| --- | --- |
| Verbindung prüfen | `GET /repos/{repo}`, `GET /repos/{repo}/contents/{pfad}` |
| Branch anlegen | `GET /repos/{repo}/git/ref/heads/{branch}`, `POST /repos/{repo}/git/refs` |
| Konfiguration und Bilder ablegen | `GET`/`PUT /repos/{repo}/contents/{pfad}` (`android/brands.json`, `brands/<id>/…`) |
| Build starten | `POST /repos/{repo}/actions/workflows/{datei}/dispatches` mit `{"ref":"<branch>","inputs":{"brand":"<id>"}}` |
| Stand anzeigen | `GET /repos/{repo}/actions/workflows/{datei}/runs` |
| Pakete finden/laden | `GET /repos/{repo}/releases`, `GET /repos/{repo}/releases/assets/{id}` |

Das Token liegt nur serverseitig (`cms/data/.apps/build.json`) und wird nie an den Browser geliefert. Die Verwaltungs-Aktionen des CMS (`apps_overview`, `app_build_*`) verlangen eine Administrator-Anmeldung (Header `X-ElvadoPress-Token`, siehe `cms/docs/API.md`).

### 5. App → Website (alle Wege)

**`GET {site}/cms/api.php?action=app_config`** – öffentlich, wird beim Start der App abgefragt.

| Parameter | Bedeutung |
| --- | --- |
| `brand` | Marken-ID der App (`id` aus `brands.json`) |
| `platform` | `android` oder `windows` |
| `version` | Versionsname der App (z. B. `3.0.0`) |
| `code` | Android: Versionsnummer (`versionCode`); nur für Entwickler-Builds |
| `did` | zufällige Installations-Kennung (16–64 Zeichen `a–f 0–9 -`); der Server hasht sie gesalzen |

Antwort (`status: "ok"`):

```json
{
  "status": "ok", "brand": "meinshop", "platform": "android",
  "features": { "assistant": true },
  "notice": { "id": "ab12cd34ef", "level": "info", "title": "…", "text": "…", "url": "", "url_label": "" },
  "update": { "latest": "3.1.0", "min": "", "available": true, "required": false, "url": "https://…/downloads/….apk",
              "package": "…", "latest_code": 12, "notes": "…", "sha256": "…", "size": 12345678, "page": "https://…/#apps" },
  "maintenance": { "title": "Wartungsarbeiten", "text": "…" },
  "telemetry": { "usage": false, "errors": false },
  "tabs": [ { "title": "Start", "icon": "home", "url": "/" } ]
}
```

`notice` und `maintenance` sind `null`, wenn nicht aktiv; `tabs` ist nur bei Baukasten-Apps gefüllt. **Bei jedem Fehler (kein Netz, Server nicht erreichbar, ungültige Antwort) startet die App normal** – sie sperrt nie aus.

**`POST {site}/cms/api.php?action=app_error`** (nur Windows, nur wenn *Fehlerberichte* im CMS eingeschaltet sind) – JSON `{"platform","brand","kind":"crash|network|other","message","where","version","os","stack"}`; Antwort `{"status":"ok","stored":true|false}`; höchstens 30 Berichte pro Zeitraum (sonst HTTP 429).

**`GET {site}/cms/api.php?action=app_download&file=<Dateiname>`** – zählt den Download und leitet (HTTP 302) auf `/downloads/<Datei>` weiter.

**User-Agent:** Die Apps hängen `ElvadoPressApp/1.0 (brand=<id>; platform=android|windows)` an. Die Website erkennt daran den App-Modus (`<body class="elvado-app">`).

## Vorlage aktualisieren

Neue Funktionen und Korrekturen der Apps kommen mit neuen ElvadoPress-Versionen (Ordner `app-template/`). So übernimmst du sie: neues `app-template.zip` laden, entpacken und in deinem App-Repository **alle Dateien außer** `android/brands.json` und dem Ordner `brands/` ersetzen (die gehören deinen Apps). Danach die Apps neu bauen.

## Fehlersuche

| Meldung / Problem | Ursache und Lösung |
| --- | --- |
| „Repository gefunden“ rot | Schreibweise `besitzer/name`; Token hat keinen Zugriff auf genau dieses Repository |
| „Das Token darf nicht schreiben“ | Beim Token *Contents: Read and write* wählen |
| „`android/brands.json` fehlt“ / „Workflow fehlt“ | Die Dateien liegen nicht im **Standard-Branch** oder das Repository stammt nicht aus dieser Vorlage |
| „Windows-Quellen fehlen“ | `windows-native/ElvadoPress.App.Windows.csproj` fehlt im Repository |
| Build bricht mit „ProductFlavor names cannot start with 'test'“ ab | Marken-ID beginnt mit `test`/`androidtest` – App mit anderer ID neu anlegen (das CMS lehnt solche IDs inzwischen ab) |
| Update lässt sich nicht installieren | Signaturschlüssel wechselte (Weg A, Schritt 4) – App einmal deinstallieren und neu installieren |
| Hinweis erscheint nicht | Er wird einmal pro Gerät gezeigt; zum erneuten Zeigen den Text im CMS ändern |
| App zeigt trotz Wartungsmodus die Website | Wartungsmodus nur beim App-Start abgefragt; App neu starten |

## Technische Hinweise und Grenzen

* **Paketnamen:** `applicationId` (die Kennung der App) vergibst du pro App. Der Quelltext-Namensraum `app.elvadopress.client` (Android) bzw. `ElvadoPress.App.Windows` ist intern und für Nutzer unsichtbar.
* **Tests:** `android/app/src/test` (JUnit, Auswertung der CMS-Antwort) und `windows-native/tests/RuntimeCheck` (dasselbe für Windows). Gesammelt über `scripts/verify-app-template.sh` im ElvadoPress-Repository (braucht Android-SDK bzw. .NET).
* **Datenschutz:** Die App sendet an deine Website nur die Abfrage `app_config` (Marke, Version, zufällige Installations-Kennung); die Kennung wird auf dem Server gesalzen gehasht, gezählt wird nur, wenn du die Nutzungszahlen eingeschaltet hast. Es gibt keine Dienste Dritter in den Apps.
* **Grenzen:** Die Apps sind **Hüllen um deine Website** (Vollbild-WebView); es gibt keinen eingebauten Audio-/Radio-Player, keine Push-Nachrichten und keine Konten. Die Pakete sind Entwickler-Builds: Android ohne Play-Store-Signatur, Windows unsigniert (SmartScreen kann warnen). iOS/macOS gibt es nicht. Die Apps sind nicht auf einem Gerät getestet, solange du sie nicht selbst prüfst.
* **Store-Veröffentlichung:** Play-Store-Pakete (AAB mit Release-Schlüssel) und Microsoft-Store-Pakete baut der Assistent nicht; Android lokal mit `gradle -p android bundle<Marke>Release` und `ANDROID_KEYSTORE_PATH`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`. Die Store-Texte und Screenshots aus `brands/<id>/store/` sind Vorlagen für die Store-Einträge.
