# Eigene Apps bauen (Build-Assistent)

Im CMS unter **Apps → Eigene App bauen** erstellst du aus deiner Website installierbare Apps für **Android** und **Windows** mit eigenem Namen, Icon und Paketnamen – ohne Entwicklungsumgebung auf dem eigenen Rechner. Dazu kommt auf Wunsch der **Alexa-Skill** (Sprachsteuerung für deine Website).

## App-Typen
* **Baukasten-App** – eigene App mit eigenen Inhalten: Tab-Leiste unten (bis 5 Tabs), jeder Tab zeigt eine Seite, einen Beitrag oder den Shop deiner Website. Tabs, Symbole und Reihenfolge pflegst du unter *Apps verwalten → Inhalte der App* (mit Vorlagen für Verein, Shop, Magazin, Restaurant/Café, Dienstleister) – ohne neuen Build. Die Leiste liefert `app_config` als `tabs`; Android und Windows merken sich die letzte Leiste auch offline. Überblick aller Vorlagen: `app-template/VORLAGEN.md`. **App-Modus:** Die App meldet sich mit einem User-Agent-Zusatz (`ElvadoPressApp/1.0 (brand=…; platform=…)`), die Website blendet dann Kopf und Fuß aus (Einstellung je App: automatisch / ausblenden / anzeigen; `?rrw_app=<id>` zum Testen im Browser). Der Tab-Editor hat eine Seitenauswahl (Aktion `wp_link_targets`: Seiten, Beiträge, Kategorien) und eine Live-Vorschau im Handy-Rahmen.
* **Website-App** – deine Website als eigene App, **für jedes Thema** (Shop, Verein, Magazin, Portfolio …): Vollbild, interne Links bleiben in der App, fremde Adressen/E-Mail/Telefon öffnen extern, Offline-Seite mit „Erneut versuchen“, eigene Farbe für Statusleiste/Fenster. Android: WebView; Windows: WebView2 (die Microsoft-Edge-WebView2-Runtime ist unter Windows 11 und aktuellem Windows 10 vorhanden).

Beides gibt es für **Android** und **Windows**; pro App wählst du die Plattformen.

## Warum GitHub?
Android-Apps werden mit Gradle und dem Android-SDK gebaut. Das läuft nicht auf normalem Webhosting. Der Build läuft deshalb kostenlos bei **GitHub Actions**. Das CMS übernimmt alles Drumherum: Konfiguration schreiben, Build starten, Stand anzeigen, fertige APK ausliefern.

## Einrichtung (einmalig)
1. **Repository anlegen** – aus der **App-Vorlage**: Sie liegt im ElvadoPress-Repository im Ordner `app-template/` und als `app-template.zip` im Release. Sie enthält die Android- und Windows-Quellen, die beiden Build-Workflows und eine leere Marken-Liste (kein CMS, keine fremden Marken). Den Inhalt in ein neues, leeres (auch privates) GitHub-Repository legen; `scripts/export-app-template.sh` erzeugt Ordner oder ZIP. Die ausführliche Schritt-für-Schritt-Anleitung steht in `app-template/ANLEITUNG.md` (Wo/Was/Wie, Fehlersuche).
2. **Token erzeugen** – GitHub → Settings → Developer settings → *Fine-grained personal access tokens*. Zugriff nur auf dieses Repository, Rechte: *Contents: Read and write*, *Actions: Read and write*, *Metadata: Read*.
3. Im CMS **Repository** (`besitzer/name`) und **Token** eintragen, speichern und **Verbindung prüfen**. Das Token bleibt auf deinem Server (`cms/data/.apps/build.json`, nicht öffentlich) und wird nie an den Browser zurückgegeben.

## Eine App anlegen und bauen
1. **Neue App**: App-Name, Paketname (`de.meinefirma.app` – später nicht mehr ändern, wenn die App im Store ist), Website (`https://…`, ohne Pfad), Icon aus der Mediathek.
2. **Android bauen / Windows bauen**: Das CMS legt (falls nötig) den Branch `app-builder` aus dem Standard-Branch an, ergänzt die App in `android/brands.json`, lädt das Icon als PNG nach `brands/<id>/app_logo.png` und startet den Workflow. Das dauert etwa 5–10 Minuten.
3. Die fertigen Pakete erscheinen im CMS zum Download: Android als **APK** (Pre-Release `app-<id>-<nr>`), Windows als **Installer und portable EXE** (Pre-Release `app-<id>-win-<nr>`). Android zum Testen auf dem Handy installieren („Unbekannte Quellen“ erlauben). Die Windows-Dateien sind nicht signiert – SmartScreen kann eine Warnung zeigen („Weitere Informationen → Trotzdem ausführen“).

Die App fragt beim Start deine Website (`app_config&brand=<Marken-ID>`) nach Funktionen, Startseite, Hinweisen und Wartungsmodus – die weitere Gestaltung (Kacheln, Farben, Wartungsmeldungen) erfolgt unverändert im CMS unter **Apps**.

## Eigene Apps im Betrieb verwalten
CMS → **Apps → Apps verwalten** zeigt auch im eigenständigen CMS alle Apps aus dem Build-Assistenten (je Plattform ein Block). Ohne neuen Build einstellbar:

* **Hinweis an alle Nutzer** (Überschrift, Text, Link, Art) und **Wartungsmodus** – für Website- und Baukasten-Apps (Android und Windows).
* **Anonyme Nutzungszahlen und Fehlerberichte** (standardmäßig aus).

Die App meldet sich mit ihrer Marken-ID (`brand=`); das CMS erkennt sie an der Liste des Build-Assistenten (`cms/data/.apps/build.json`). Update-Steuerung (Mindestversion, stufenweises Ausrollen, Prüfsummen) gibt es nur für Apps, die über diese Website verteilt werden (Hersteller-Paket). Schlägt die Abfrage fehl, startet die App immer normal.

## Signatur und Play Store
Ohne Schlüssel entsteht eine **Entwickler-APK** (Debug-Signatur). Für gleichbleibende Signatur und Updates lege diese Repository-Secrets an: `ANDROID_DEVELOPER_KEYSTORE_BASE64`, `ANDROID_DEVELOPER_KEYSTORE_PASSWORD`, `ANDROID_DEVELOPER_KEY_ALIAS`, `ANDROID_DEVELOPER_KEY_PASSWORD`. Pakete für den Play Store (AAB, Release-Schlüssel) werden mit dem Workflow „Android APK / AAB“ im Repository auf Knopfdruck erzeugt.

## Branding (für alle App-Typen)
Beim Anlegen oder Bearbeiten einer App gibt es – für jeden App-Typ – diese optionalen Angaben aus der Mediathek. Das CMS schreibt sie in die Dateien, die die App-Vorlage (Android und Windows) schon einliest:
* **App-Icon** → `brands/<id>/app_logo.png` (quadratisch, höchstens 512 px). Mit **Icon-Hintergrund** (Farbe) wird das Icon beim Hochladen auf eine deckende Fläche dieser Farbe gesetzt, praktisch für Icons mit transparentem Hintergrund.
* **Farbe** der Statusleiste/des Fensters (`themeColor` in `android/brands.json`).
* **Startbild** → `brands/<id>/startscreen.png` (höchstens 1080 px), erscheint beim Öffnen der App.
* **Logo in der Kopfzeile** → `brands/<id>/logo-lockup.png` (höchstens 1000 px breit); ohne Angabe wird das App-Icon verwendet.
* **Store-Screenshots** (bis zu 8, `brands/<id>/store/screenshot-<n>.png`) sowie **Kurz- und Langbeschreibung** (`brands/<id>/store/listing-de.md`) für Play Store und Microsoft Store.

Nur angegebene Werte werden ins Repository geschrieben; Apps ohne diese Angaben bauen wie bisher.

## Eigene Sender und beliebige Streams
* **App:** Im Builder (Apps → Layout) trägst du die Sender selbst ein – auch laut.fm-Sender: unter **Eigene Sender** bis zu 20 Stück mit **https-Stream-Adresse oder laut.fm-Kennung** (Name, Stream, optional Logo-Adresse). Eine laut.fm-Kennung (oder `laut.fm/<kennung>`) wird zur Stream-Adresse `https://<kennung>.stream.laut.fm/<kennung>` und zusätzlich als `laut` mitgegeben. Sie stehen in der Konfiguration `layout.stations.custom` (`id`, `title`, `stream`, optional `logo`) und erscheinen zusätzlich zu den Sendern des Core-Netzwerks; Reihenfolge und Sichtbarkeit gelten über `order`/`hidden` auch für sie. Ohne eigene Sender fehlt der Schlüssel `custom`, die Ausgabe bleibt wie bisher.

## Grenzen
* Die Windows-App wird von GitHub Actions gebaut (Windows-Runner, ebenfalls kostenlos im Rahmen des Kontingents).
* iOS/macOS gibt es nicht (Apple verlangt eigene Konten und Signatur).
* Für den Build braucht es ein GitHub-Konto; ohne GitHub kann das Repository auch lokal mit Gradle gebaut werden (`gradle -p android assemble<Marke>Developer`).
* Pro CMS sind bis zu 8 eigene Apps möglich.
