# CMS-Aktualisierung (Menü System → Version & Update)

Ein ElvadoPress-CMS kann sich selbst aus einem GitHub-Repository aktualisieren – mit Sicherung, Gesundheitsprüfung und **automatischem Rückschritt**. Gedacht für mehrere Web-Projekte: jedes Projekt hat eigene Einstellungen (Repository, Kanal), der Betreiber-Inhalt bleibt unangetastet.

Code: `cms/src/Update/` (`UpdateService`, `GitHubSource`, `TreeInstaller`, `UpdateSettings`, `Semver`), Verwaltung `cms/assets/update-manager.js`, API `update_*` in `cms/api.php`, Tests `scripts/test-update.php`.

## Was wird angezeigt?
Im Kopf der Verwaltung steht immer die installierte Version (`cms/VERSION`), bei einem verfügbaren Update mit Hinweis. Unter *System → Version & Update*: installiert / neueste / zuletzt geprüft, Hinweise zur neuen Version, Sicherungen, Verlauf.

## Kanäle (Einstellung „Kanal“)
| Kanal | Quelle | „Neueste“ |
|---|---|---|
| `release` (Standard) | GitHub-Releases, ersatzweise Tags `v1.2.3` | **höchste Version nach SemVer** (nicht „zuletzt veröffentlicht“); Entwürfe und Vorabversionen ausgeschlossen |
| `beta` | wie release | zählt auch Vorabversionen (`1.3.0-beta.1`) |
| `main` | neuester Commit eines Branches | Stand des Branches; Version aus `<ordner>/VERSION` dieses Commits |

Standard-Repository: `update_repo` in `cms/lib/product.default.json`; pro Installation überschreibbar (Repository `besitzer/name`, „CMS-Ordner im Repository“ = Unterordner mit dem CMS, Standard `cms`, leer = Wurzel). Private Repositories: GitHub-Token mit Leserecht „Contents“ (nur serverseitig gespeichert, nie angezeigt).

## Neue Version veröffentlichen (Entwicklerseite)
`cms/VERSION` auf die neue Version setzen und nach `main` mergen → der Workflow *Release* legt Tag `v<Version>`, Release, ZIP und `.sha256` an (oder manuell Tag `v<Version>` pushen; Tag und `cms/VERSION` müssen übereinstimmen). Das ZIP wird beim Update bevorzugt verwendet und per Prüfsumme geprüft.

## Suche – automatisch
1. **Beim Öffnen der Verwaltung**: alle *n* Stunden (Standard 6, einstellbar, 0 = aus).
2. **GitHub-Webhook** (sofort): Repository → Settings → Webhooks → Payload URL `https://<domain>/cms/update-webhook.php`, `application/json`, Geheimnis aus dem CMS („Neu erzeugen“), Ereignisse *Releases* (und im Kanal `main` *Pushes*, optional *Branch or tag creation*). HMAC-SHA256-Signatur wird geprüft.
3. **Cron**: `0 * * * * php /pfad/cms/update-cron.php` (`--check` nur suchen, `--apply` einspielen).

Webhook und Cron spielen neue Versionen nur ein, wenn **„automatisch einspielen“** aktiv ist (Standard aus – dann gibt es nur den Hinweis und den Knopf „Aktualisieren“).

## Einspielen – Ablauf
1. Paket laden (nur `api.github.com`, Weiterleitung auf GitHub-Hosts), Prüfsumme prüfen.
2. Sicher entpacken: keine Pfadtricks/Symlinks/versteckten Dateien (außer `.htaccess`), Endungs-Allowlist, Mengen-/Größenlimits; **alle PHP-Dateien werden geparst** (Syntaxfehler → Abbruch, nichts verändert).
3. Schreibrechte prüfen; **Sicherung** des aktuellen Code-Stands (`cms/data/.update/snapshots/`, die letzten *n* bleiben).
4. Dateien einspielen (atomar je Datei), veraltete Dateien entfernen.
5. **Gesundheitsprüfung**: Dateien/Version stimmen; das neue CMS startet in einem eigenen PHP-Prozess (`cms/update-health.php` lädt `api.php`); Ping der Website (`api.php?action=update_ping`, unklare Ergebnisse wie Zugangsschutz zählen nicht als Fehler).
6. **Fehler → sofort automatischer Rückschritt** auf die Sicherung, Fehlermeldung mit Grund.
7. **Überwachungsfenster** (Standard 10 Min.): `watchdog()` prüft die Gesundheit erneut (bei Aufrufen der CMS-API, per Webhook/Cron); krank → automatisch zurück; gesund → nach der Frist bestätigt (nie stilles Zurückrollen eines gesunden Updates). „Als gut bestätigen“ beendet es früher.

**Nie angefasst:** `data/`, `backups/`, `media/`, `content/`, `plugins/`, `generated/`, `wp-content/`, `frontend/` (u. a. Lovable-Sync), `demo-state/`, `*.local.php/json` und eigene Themes (Themes, die das Paket nicht mitbringt). Geändert wird nur der Code im CMS-Ordner (Wurzeldateien außerhalb von `cms/`, z. B. eine `index.php` im Webroot, werden nicht aktualisiert).

## Downgrade und Rückschritt
- *Bestimmte Version installieren*: beliebige veröffentlichte Version (auch ältere) wählen – mit Sicherung und denselben Prüfungen.
- *Sicherungen*: jede Sicherung (vor jedem Update) lässt sich zurückspielen.
- **Notfall**, falls die Verwaltung nach einem Update gar nicht mehr startet: `https://<domain>/cms/update-rescue.php?token=<Notfall-Token>` (Token steht in der Verwaltung und in `cms/data/.update/rescue.token`) – ohne Anmeldung, nur mit Token; spielt die gewählte Sicherung zurück.

## Voraussetzungen
PHP-Erweiterungen `zip` und `curl`; der CMS-Ordner muss für den Webserver-Benutzer beschreibbar sein (die Verwaltung zeigt fehlende Voraussetzungen an). Der Neustart-Test braucht `proc_open` und eine PHP-Kommandozeile (sonst wird er übersprungen, Dateiprüfung und Ping bleiben). Auf Hosts mit nur einem PHP-Worker kann der Ping der eigenen Website nicht antworten – er wird dann als „übersprungen“ gewertet.

## Zustand
`cms/data/.update/` (gesperrt per `.htaccess`): `settings.json`, `state.json` (Verlauf, Überwachung), `snapshots/`, `rescue.token`, `lock`.
