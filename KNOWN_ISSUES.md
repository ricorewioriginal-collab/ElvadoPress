# Bekannte Probleme

## Historische Angaben in INSTALL.md
- **Bereich:** Dokumentation / Installation / Betriebsmodi
- **Status:** offen; keine Anwendungscode-Änderung erforderlich.
- **Problem:** `INSTALL.md` beginnt mit der Aussage, der Standardbetrieb sei unverändert die Anbindung an das AnMaCha Control Center, und enthält weitere historische RicoReWi-Bezeichnungen. Das steht in Spannung zur aktuellen Repository-Dokumentation, die ElvadoPress als eigenständiges CMS/Hauptquelle beschreibt und bei frischer Installation `control_center: false` dokumentiert.
- **Bekannte Ursache:** Die Datei ist aus der früheren Projektstruktur historisch weitergewachsen.
- **Nächste Untersuchung:** Connected-/Standalone-Verhalten gezielt gegen `cms/install.php`, Auth-/Systemlogik und aktuelle Tests prüfen; danach nur eindeutig veraltete Formulierungen korrigieren. Weiterhin gültige Migrations-/Connected-Mode-Hinweise erhalten.

## App-Vorlage und Alexa
- **Bereich:** App-Baukasten (`app-template/`) / Alexa
- **Status:** Vorlage umgesetzt (Ordner `app-template/`, verifiziert mit `scripts/verify-app-template.sh`: Android baut, Windows kompiliert, Tests grün). Alexa-Erweiterung offen.
- **Einschränkungen der Vorlage:** Die Apps sind **nicht auf einem Gerät** getestet (nur gebaut/kompiliert und per Unit-Tests der CMS-Auswertung geprüft); die Windows-Pakete entstehen erst im Build-Workflow (Windows-Runner) und sind unsigniert. Die Radio-App enthält Funktionen mit Hersteller-Backend (Podcast, Community, Shops) – sie sind ausgeschaltet, solange nicht in `brands.json` unter `"radio"` eingetragen. Der interne Quelltext-Namensraum der Android-App (`app.elvadopress.client`) ist bewusst von der App-Kennung getrennt.
- **Nächste Untersuchung:** Erste echte App aus der Vorlage auf Gerät (Android) und unter Windows prüfen; Alexa-Paket für eigene Inhalte.
