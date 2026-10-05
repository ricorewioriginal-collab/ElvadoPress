# Eigene Apps bauen (Build-Assistent)

Im CMS unter **Apps → Eigene App bauen** erstellst du aus deiner Website installierbare Apps für **Android** und **Windows** mit eigenem Namen, Icon und Paketnamen – ohne Control Center und ohne Entwicklungsumgebung auf dem eigenen Rechner. Dazu kommt auf Wunsch der **Alexa-Skill** (Sprachsteuerung für Radio-Apps).

## App-Typen
* **Radio-App** – die volle Radio-App: Sender, Player, Sendeplan, Podcast, News, Community, KI-Assistent, optional Radioverzeichnis.
* **Website-App** – deine Website als eigene App, **für jedes Thema** (Shop, Verein, Magazin, Portfolio …): Vollbild, interne Links bleiben in der App, fremde Adressen/E-Mail/Telefon öffnen extern, Offline-Seite mit „Erneut versuchen“, eigene Farbe für Statusleiste/Fenster. Android: WebView; Windows: WebView2 (die Microsoft-Edge-WebView2-Runtime ist unter Windows 11 und aktuellem Windows 10 vorhanden).

Beides gibt es für **Android** und **Windows**; pro App wählst du die Plattformen.

## Warum GitHub?
Android-Apps werden mit Gradle und dem Android-SDK gebaut. Das läuft nicht auf normalem Webhosting. Der Build läuft deshalb kostenlos bei **GitHub Actions**. Das CMS übernimmt alles Drumherum: Konfiguration schreiben, Build starten, Stand anzeigen, fertige APK ausliefern.

## Einrichtung (einmalig)
1. **Repository anlegen** – aus der **App-Vorlage**: Sie enthält nur die Android- und Windows-Quellen, die beiden Build-Workflows und eine leere Marken-Liste (kein CMS, keine fremden Marken). Die Vorlage liegt als `app-template.zip` im Release „app-template“ des RicoReWi-Repositories und wird bei jeder Änderung an den Apps automatisch aktualisiert (`scripts/export-app-template.sh`). ZIP entpacken, in ein neues, leeres GitHub-Repository hochladen (privat ist möglich) und dort optional unter *Settings → Template repository* markieren.
2. **Token erzeugen** – GitHub → Settings → Developer settings → *Fine-grained personal access tokens*. Zugriff nur auf dieses Repository, Rechte: *Contents: Read and write*, *Actions: Read and write*, *Metadata: Read*.
3. Im CMS **Repository** (`besitzer/name`) und **Token** eintragen, speichern und **Verbindung prüfen**. Das Token bleibt auf deinem Server (`cms/data/.apps/build.json`, nicht öffentlich) und wird nie an den Browser zurückgegeben.

## Eine App anlegen und bauen
1. **Neue App**: App-Name, Paketname (`de.meinradio.app` – später nicht mehr ändern, wenn die App im Store ist), Website (`https://…`, ohne Pfad), Icon aus der Mediathek, optional Radioverzeichnis.
2. **Android bauen / Windows bauen**: Das CMS legt (falls nötig) den Branch `app-builder` aus dem Standard-Branch an, ergänzt die App in `android/brands.json`, lädt das Icon als PNG nach `brands/<id>/app_logo.png` und startet den Workflow. Das dauert etwa 5–10 Minuten.
3. Die fertigen Pakete erscheinen im CMS zum Download: Android als **APK** (Pre-Release `app-<id>-<nr>`), Windows als **Installer und portable EXE** (Pre-Release `app-<id>-win-<nr>`). Android zum Testen auf dem Handy installieren („Unbekannte Quellen“ erlauben). Die Windows-Dateien sind nicht signiert – SmartScreen kann eine Warnung zeigen („Weitere Informationen → Trotzdem ausführen“).

Die App fragt beim Start deine Website (`app_config`) nach Funktionen, Startseite und Hinweisen – die weitere Gestaltung (Kacheln, Farben, Wartungsmeldungen) erfolgt unverändert im CMS unter **Apps**.

## Signatur und Play Store
Ohne Schlüssel entsteht eine **Entwickler-APK** (Debug-Signatur). Für gleichbleibende Signatur und Updates lege diese Repository-Secrets an: `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`. Pakete für den Play Store (AAB, Release-Schlüssel) werden mit dem Workflow „Android APK / AAB“ im Repository auf Knopfdruck erzeugt.

## Branding (für alle App-Typen)
Beim Anlegen oder Bearbeiten einer App gibt es – für Radio-Apps genauso wie für Website-Apps – diese optionalen Angaben aus der Mediathek:
* **App-Icon** und **Icon-Hintergrund** (Farbe für runde/adaptive Icons, `iconBg` in `android/brands.json`).
* **Farbe** der Statusleiste/des Fensters (`themeColor`).
* **Startbild** (`brands/<id>/app_splash.png`, höchstens 1080 px, Eintrag `splash: true`).
* **Store-Screenshots** (bis zu 8, `brands/<id>/store/screenshot-<n>.png`) sowie **Kurz- und Langbeschreibung** (`brands/<id>/store/listing-de.md`) für Play Store und Microsoft Store.

Nur angegebene Werte werden ins Repository geschrieben; Apps ohne diese Angaben bauen wie bisher. Die App-Vorlage muss `iconBg`, `splash` und die Store-Dateien auswerten bzw. verwenden.

## Eigene Sender und beliebige Streams
* **App:** Im Builder (Apps → Layout) trägst du die Sender selbst ein – auch laut.fm-Sender: unter **Eigene Sender** bis zu 20 Stück mit **https-Stream-Adresse oder laut.fm-Kennung** (Name, Stream, optional Logo-Adresse). Eine laut.fm-Kennung (oder `laut.fm/<kennung>`) wird zur Stream-Adresse `https://<kennung>.stream.laut.fm/<kennung>` und zusätzlich als `laut` mitgegeben. Sie stehen in der Konfiguration `layout.stations.custom` (`id`, `title`, `stream`, optional `logo`) und erscheinen zusätzlich zu den Sendern des Core-Netzwerks; Reihenfolge und Sichtbarkeit gelten über `order`/`hidden` auch für sie. Ohne eigene Sender fehlt der Schlüssel `custom`, die Ausgabe bleibt wie bisher.
* **Alexa:** Im Bereich Alexa-Skill kann jeder Sender eine eigene https-Stream-Adresse bekommen (leer = laut.fm). Alexa spielt nur https. Bei eigenen Streams gibt es keine Titel- und Sendeplan-Auskunft (die kommt von laut.fm).

## Alexa-Skill
Für Radio-Apps erzeugt das CMS im Bereich **Alexa-Skill** das einreichfertige Paket (Sprachmodell, Skill-Angaben, Backend, Anleitung) passend zu deinen Sendern. Einreichen musst du es selbst bei Amazon (Developer-Konto, Zertifizierung); der Skill selbst braucht kein Control Center. Für Website-Apps ist kein Skill vorgesehen.

## Grenzen
* Die Windows-App wird von GitHub Actions gebaut (Windows-Runner, ebenfalls kostenlos im Rahmen des Kontingents).
* iOS/macOS gibt es nicht (Apple verlangt eigene Konten und Signatur).
* Für den Build braucht es ein GitHub-Konto; ohne GitHub kann das Repository auch lokal mit Gradle gebaut werden (`gradle -p android assemble<Marke>Developer`).
* Pro CMS sind bis zu 8 eigene Apps möglich.

## Eigenständiger Betrieb (CMS-Release)

Im eigenständigen Betrieb (ohne Control Center) zeigt der Bereich **Apps** nur den Build-Assistenten für deine eigenen Apps.
Die mitgelieferten Apps des Herstellers (RicoReWi Radio, SenderWelt), ihre Funktionsschalter und Downloads, der Alexa-Skill des Herstellers
und das SenderWelt-Radioverzeichnis erscheinen nicht; die Marke „SenderWelt“ taucht unter Domains & Branding nicht auf.
