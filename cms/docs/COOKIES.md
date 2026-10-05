# Cookie-Hinweis und externe Inhalte

Gilt für das Portal von **RicoReWi Radio** und **SenderWelt** (`index.html`, gleiche Datei für alle Domains).

## Was das Portal speichert
- **Notwendig (ohne Einwilligung):** Lautstärke, Favoriten, Sendereinstellungen und die Cookie-Auswahl selbst (`localStorage`, Schlüssel `rrw_consent_v1`). Es gibt **keine** Tracking-, Analyse- oder Werbe-Cookies.
- **Externe Inhalte (nur mit Einwilligung):** YouTube (nocookie), Vimeo, TikTok, Instagram und fremde Einbettungen (News-Videos, Social Wall, Einbettungs-Widgets mit fremder Adresse). Bis zur Zustimmung steht ein Platzhalter mit „Einmal laden“ und „Immer zulassen“; es geht keine Anfrage an den Anbieter.
- **Schriften/Icons:** Font Awesome liegt lokal (`assets/vendor/fontawesome/`), es gibt keine Verbindung zu cdnjs oder Google Fonts.

## Der Hinweis
- Erscheint beim ersten Besuch unten (über Player und Navigation, nie als Vollbild, die Seite bleibt bedienbar).
- „Nur notwendige“, „Einstellungen“ und „Alle akzeptieren“ sind gleich gestaltet.
- Die Auswahl gilt 12 Monate und ist jederzeit änderbar: Footer-Link **Cookie-Einstellungen** (auch im Mehr-Menü der mobilen Ansicht).
- Ändern sich die Zwecke, in `assets/js/consent.js` `VERSION` erhöhen: dann wird erneut gefragt.

## Für Entwicklung
- `RRWConsent.embed(anbieter, html)` liefert mit Einwilligung das HTML, sonst den Platzhalter.
- `RRWConsent.gate(element, anbieter, ladeFunktion)` lädt in ein vorhandenes Element oder zeigt den Platzhalter.
- `RRWConsent.has('external')`, `RRWConsent.onChange(fn)`, `RRWConsent.open()`.
- Neue externe Inhalte immer über `embed`/`gate` einbinden.
- Test: `node scripts/test-consent-e2e.js` (Chromium, Desktop und Handy).

## Offen / zu beachten
- Der Text der **Datenschutzerklärung** (im CMS unter Rechtliches) muss die externen Anbieter und die Cookie-Auswahl nennen. Der Hinweis ersetzt keine Rechtsberatung.
- Freies HTML in „HTML“-Widgets und Plugins wird nicht automatisch gesperrt: dort keine fremden Skripte oder iFrames ohne `RRWConsent` einbauen.
- Der Spreadshop (Shops-Seite) und laut.fm-Widgets laden erst nach einem ausdrücklichen Klick der Nutzer; sie sind nicht hinter dem Hinweis.
