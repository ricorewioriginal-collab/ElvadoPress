# Anleitung: Eigene Apps aus ElvadoPress

Diese Anleitung erklärt **wo** du etwas einstellst, **was** es bewirkt und **wie** du vorgehst – von der Einrichtung bis zum laufenden Betrieb.

## Wie das Ganze zusammenhängt

```
ElvadoPress (deine Website, CMS)             GitHub (dein App-Repository, aus dieser Vorlage)
  Apps → Eigene App bauen  ── Konfiguration, Icon, Bilder ──▶  android/brands.json, brands/<id>/…
                           ── Build starten ────────────────▶  Workflows bauen APK / Installer
                           ◀── fertige Pakete ───────────────  Pre-Releases „app-<id>-<nr>“
  Apps → Apps verwalten    ◀── die App fragt beim Start ───── Hinweis, Wartung, Funktionen, Layout
```

* **Gebaut** wird bei GitHub (kostenlos im Rahmen des GitHub-Kontingents), nicht auf deinem Webserver – Android-Apps brauchen Gradle und das Android-SDK.
* **Gesteuert** wird alles im CMS. Du musst keinen Quelltext anfassen.
* Was sich **ohne neuen Build** ändern lässt (Hinweise, Wartungsmodus, Funktionen, Startseite der Radio-App), liegt im CMS und wird von der App beim Start abgeholt.

## 1. Einmalige Einrichtung

### 1.1 Repository anlegen
Du brauchst ein eigenes GitHub-Repository mit dem Inhalt dieser Vorlage. Drei Wege:

1. **ZIP aus dem Release** (einfach): Im ElvadoPress-Release die Datei `app-template.zip` herunterladen, entpacken, auf GitHub ein **neues, leeres** Repository anlegen und den entpackten Inhalt hochladen (*Add file → Upload files*; den Ordner `.github` nicht vergessen – bei versteckten Ordnern hilft das Hochladen per Git).
2. **Per Git:** Ordner `app-template/` des ElvadoPress-Repositories in ein neues Repository kopieren und pushen (`git init`, `git add .`, `git commit`, `git remote add origin …`, `git push -u origin main`).
3. **Skript:** `scripts/export-app-template.sh [Zielordner]` im ElvadoPress-Repository erzeugt den Ordner bzw. mit `--zip=Datei.zip` das ZIP.

Das Repository darf **privat** sein. Wichtig: Die Datei `android/brands.json` und die beiden Workflows unter `.github/workflows/` müssen im **Standard-Branch** (meist `main`) liegen.

### 1.2 Token erzeugen
GitHub → *Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token*:

* **Repository access:** *Only select repositories* → genau dein App-Repository.
* **Permissions:** *Contents: Read and write*, *Actions: Read and write*, *Metadata: Read-only*.

Das Token bleibt auf deinem Webserver (`cms/data/.apps/build.json`, nicht öffentlich erreichbar) und wird nie an den Browser zurückgegeben.

### 1.3 Im CMS verbinden
CMS → **Apps → Eigene App bauen → 1 · Verbindung zu GitHub**: Repository (`besitzer/name`), Branch (Standard `app-builder`; das CMS legt ihn aus dem Standard-Branch an), Token eintragen → *Speichern* → *Verbindung prüfen*. Alle Zeilen müssen grün sein (Repository gefunden, Schreibrecht, `android/brands.json`, Workflows).

### 1.4 Android-Signatur (empfohlen, einmalig)
Ohne Schlüssel entsteht eine **Entwickler-APK mit Debug-Signatur**: Sie lässt sich installieren, aber nicht über eine bereits installierte Version aktualisieren, wenn sich der Schlüssel ändert. Für gleichbleibende Signatur:

1. `scripts/create-developer-keystore.sh` (Linux/macOS) oder `scripts/create-developer-keystore.ps1` (Windows/PowerShell) ausführen. Der Schlüssel entsteht **nur lokal**.
2. Die vier ausgegebenen Werte in GitHub als **Repository-Secrets** anlegen (*Settings → Secrets and variables → Actions*): `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`. (Mit dem GitHub-CLI geht es automatisch: `bash scripts/create-developer-keystore.sh besitzer/name --set-secrets`.)
3. Den Schlüssel-Ordner **zusätzlich sicher sichern** (Passwortmanager/Offline-Kopie). Geht er verloren, können installierte Apps nicht mehr aktualisiert werden.

Play-Store-Pakete (AAB mit Release-Schlüssel) baut der Assistent nicht; lokal geht es mit `gradle -p android bundle<Marke>Release` und den Umgebungsvariablen `ANDROID_KEYSTORE_PATH`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`.

## 2. Eine App anlegen

CMS → **Apps → Eigene App bauen → 2 · Meine Apps → Neue App**. Bis zu 8 Apps pro CMS.

| Feld im CMS | Was es bewirkt | Wo es landet |
| --- | --- | --- |
| **App-Typ** | *Website-App* oder *Radio-App* (siehe README) | `android/brands.json` → `type` (`web`; Radio ist der Standard) |
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

## 3. Bauen und herunterladen

Bei der App auf **Android bauen** bzw. **Windows bauen** klicken. Das CMS legt (falls nötig) den Branch an, schreibt `android/brands.json` und die Bilder und startet den Workflow. Das dauert etwa 5–10 Minuten; der Stand erscheint im CMS.

* **Android:** Pre-Release `app-<id>-<nr>` mit `<Dateiname-Anfang>-Developer.apk` – zum Testen auf dem Handy installieren („Unbekannte Quellen“ erlauben).
* **Windows:** Pre-Release `app-<id>-win-<nr>` mit Installer (`…-Setup-x64.exe`) und portabler EXE. Die Dateien sind **nicht signiert**; Windows SmartScreen kann warnen („Weitere Informationen → Trotzdem ausführen“).
* Download direkt im CMS bei der App.

## 4. Im Betrieb verwalten (ohne neuen Build)

CMS → **Apps → Apps verwalten**. Pro App und Plattform gibt es einen aufklappbaren Block. Die App holt die Einstellungen beim Start von deiner Website (`cms/api.php?action=app_config&brand=<Marken-ID>`); Änderungen gelten also ohne neues Paket, sobald „Speichern“ gedrückt ist.

| Einstellung | Gilt für | Wirkung in der App |
| --- | --- | --- |
| **Hinweis an alle Nutzer** (Überschrift, Text, Link, Art Info/Wichtig) | beide App-Typen | Hinweis in der App (Banner bzw. Dialog), einmal pro Gerät – bis du den Text änderst |
| **Wartungsmodus** | beide App-Typen | Die App zeigt nur die Wartungsmeldung („Erneut prüfen“), bis du ihn wieder ausschaltest |
| **Funktionen** (KI-Assistent, Cast, Sender melden) | Radio-App | schaltet die Funktion in der App aus bzw. an |
| **App-Builder: Aussehen & Startseite** (Farben, Karten, Kacheln, Senderreihenfolge, eigene Sender, „Mehr“-Menü) | Radio-App | gestaltet die Startseite und Listen der Radio-App |
| **Datenschutz & Statistik** (anonyme Nutzungszahlen, Fehlerberichte, Hörstatistik) | beide | standardmäßig **aus**; gespeichert werden keine Namen, IP-Adressen oder Hörverläufe |

Bei einer fehlgeschlagenen Abfrage (kein Netz, Server nicht erreichbar) **startet die App immer normal** – sie sperrt nie aus.

## 5. Die Radio-App im Detail

* **Sender:** Trage sie unter **Apps → Apps verwalten → (App) → App-Builder → *Eigene Sender*** ein (Name, https-Stream-Adresse oder laut.fm-Kennung, optional Logo; bis zu 20) und schalte **„Builder aktiv“** ein – ohne aktiven Builder liefert das CMS keine Sender und kein Layout an die App. Hat dein CMS zusätzlich ein Core-Netzwerk (laut.fm-Kennungen), erscheinen diese Sender vor den eigenen. Eigene Sender mit beliebigem Stream haben keine Titel- und Sendeplan-Auskunft (die kommt von laut.fm); eigene Sender mit laut.fm-Kennung schon. Reihenfolge und Sichtbarkeit stellst du im selben Block ein.
* **Weitere Funktionen** (Podcast, Voting/Community, Shops) hängen an Backends, die nur die Website bereitstellen kann, und sind standardmäßig **aus**. Wer sie hat, ergänzt sie im Eintrag der App in `android/brands.json` (gilt für Android und Windows; das CMS behält solche Zusätze beim Speichern bei):

  ```json
  { "id": "meinradio", …, "radio": {
      "podcast": true,
      "communityBase": "https://www.meinradio.de/community/",
      "shops": [ { "title": "Fanshop", "desc": "Shirts und mehr", "url": "https://shop.meinradio.de/" } ] } }
  ```
  `communityBase` verweist auf ein Verzeichnis mit den Seiten `voting.html`, `wunsch.html`, `studiomail/widget-form.html` und `voicemsg.html`; der Podcast wird von `https://<website>/podcast.php` als JSON geladen. Beliebige Links lassen sich auch ohne Backend als Kachel/Menüeintrag im App-Builder anlegen.
* **Radioverzeichnis** (Suche in laut.fm und aller Welt) gehört zum Hersteller-Paket und ist in dieser Vorlage nicht freigeschaltet.

## 6. Die Website-App im Detail

Die App lädt `https://<deine-website>/` im Vollbild:

* Interne Adressen (deine Domain, https) bleiben in der App; fremde Adressen, `mailto:`, `tel:` und Downloads öffnen im Standardbrowser/-programm.
* Ohne Verbindung erscheint eine Offline-Seite mit „Erneut versuchen“.
* Farbe, Name, Icon und Startbild kommen aus den App-Feldern; Hinweise und Wartung aus *Apps verwalten* (Abschnitt 4).
* Die Website selbst gestaltest du wie gewohnt im CMS (Themes, Baukasten, Widgets) – die App zeigt immer den aktuellen Stand.

## 7. Die Baukasten-App im Detail

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

**Gut zu wissen**

* Die App zeigt deine Website im Vollbild – ein eigenes App-Aussehen (ohne Kopf/Fuß der Website) erreichst du über ein schlankes Theme oder eine Seitenvorlage für die App-Seiten.
* Hinweis, Wartungsmodus und Pflicht-Update funktionieren wie bei der Website-App (Abschnitt 4).
* Fremde Adressen, E-Mail und Telefon öffnen außerhalb der App; ein Tab mit fremder `https://`-Adresse lädt sie ausnahmsweise in der App.
* Technisch: Die Leiste steht als `tabs` in der Antwort von `cms/api.php?action=app_config&brand=<id>`; Android und Windows lesen sie beim Start (`WebRuntime`).

### Weitere Vorlagen

Der Katalog der Vorlagen steht in [`templates.json`](templates.json). Eine neue Vorlage (z. B. Podcast-App, Community-App) besteht immer aus: einem Typ-Eintrag in `RRW_AB_TYPES` (`cms/lib/appbuild.php`), der Auswertung des Typs in `android/app/build.gradle` und `windows-native/Brand.cs` sowie den Einstellungen unter *Apps verwalten*. Siehe [VORLAGEN.md](VORLAGEN.md).

## 8. Selbst bauen (ohne GitHub)

Voraussetzungen: JDK 17, Android-SDK (Plattform 36, Build-Tools 36.0.0, `ANDROID_HOME`), Gradle 9.6 oder neuer.

```bash
# Marke in android/brands.json eintragen (id, applicationId, appName, launchUrl, site, filePrefix, optional type "web" bzw. "content" und themeColor),
# Icon nach android/app/src/<id>/res/drawable-nodpi/app_logo.png kopieren (z. B. icon-512.png), dann:
gradle -p android assemble<Id>Developer        # <Id> mit großem Anfangsbuchstaben, z. B. assembleMeinshopDeveloper
# APK: android/app/build/outputs/apk/<id>/developer/app-<id>-developer.apk
```

Windows (nur unter Windows mit .NET-SDK 8): `dotnet publish -c Release -r win-x64 -p:BrandAppName="Meine App"` im Ordner `windows-native/`, Installer mit Inno Setup (`installer.iss`). Zum reinen Prüfen reicht `scripts/verify-app-template.sh` im ElvadoPress-Repository (baut zwei Beispiel-Apps und führt die Tests aus).

## 9. Die Vorlage aktualisieren

Neue Funktionen und Korrekturen der Apps kommen mit neuen ElvadoPress-Versionen (Ordner `app-template/`). So übernimmst du sie: neues `app-template.zip` laden, entpacken und in deinem App-Repository **alle Dateien außer** `android/brands.json` und dem Ordner `brands/` ersetzen (die gehören deinen Apps). Danach die Apps neu bauen.

## 10. Fehlersuche

| Meldung / Problem | Ursache und Lösung |
| --- | --- |
| „Repository gefunden“ rot | Schreibweise `besitzer/name`; Token hat keinen Zugriff auf genau dieses Repository |
| „Das Token darf nicht schreiben“ | Beim Token *Contents: Read and write* wählen |
| „`android/brands.json` fehlt“ / „Workflow fehlt“ | Die Dateien liegen nicht im **Standard-Branch** oder das Repository stammt nicht aus dieser Vorlage |
| „Windows-Quellen fehlen“ | `windows-native/ElvadoPress.App.Windows.csproj` fehlt im Repository |
| Build bricht mit „ProductFlavor names cannot start with 'test'“ ab | Marken-ID beginnt mit `test`/`androidtest` – App mit anderer ID neu anlegen (das CMS lehnt solche IDs inzwischen ab) |
| Update lässt sich nicht installieren | Signaturschlüssel wechselte (Abschnitt 1.4) – App einmal deinstallieren und neu installieren |
| Hinweis erscheint nicht | Er wird einmal pro Gerät gezeigt; zum erneuten Zeigen den Text im CMS ändern |
| App zeigt trotz Wartungsmodus die Website | Wartungsmodus nur beim App-Start abgefragt; App neu starten |
| Radio-App ohne Sender | Keine eigenen Sender eingetragen oder „Builder aktiv“ ausgeschaltet (Abschnitt 5) |

## 11. Technische Hinweise

* **Paketnamen:** `applicationId` (die Kennung der App) vergibst du pro App. Der Quelltext-Namensraum `app.elvadopress.client` (Android) bzw. `ElvadoPress.App.Windows` ist intern und für Nutzer unsichtbar.
* **Tests:** `android/app/src/test` (JUnit, Auswertung der CMS-Antwort der Website-App) und `windows-native/tests/RuntimeCheck` (dasselbe für Windows). Aufruf gesammelt über `scripts/verify-app-template.sh` im ElvadoPress-Repository.
* **Datenschutz:** Die App sendet an deine Website nur die Abfrage `app_config` (Marke, Version, zufällige Installations-Kennung); die Kennung wird auf dem Server gesalzen gehasht, gezählt wird nur, wenn du die Nutzungszahlen eingeschaltet hast.
