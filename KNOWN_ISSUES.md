# Bekannte Probleme

## Historische Angaben in INSTALL.md
- **Bereich:** Dokumentation / Installation / Betriebsmodi
- **Status:** offen; keine Anwendungscode-Änderung erforderlich.
- **Problem:** `INSTALL.md` beginnt mit der Aussage, der Standardbetrieb sei unverändert die Anbindung an das AnMaCha Control Center, und enthält weitere historische RicoReWi-Bezeichnungen. Das steht in Spannung zur aktuellen Repository-Dokumentation, die ElvadoPress als eigenständiges CMS/Hauptquelle beschreibt und bei frischer Installation `control_center: false` dokumentiert.
- **Bekannte Ursache:** Die Datei ist aus der früheren Projektstruktur historisch weitergewachsen.
- **Nächste Untersuchung:** Connected-/Standalone-Verhalten gezielt gegen `cms/install.php`, Auth-/Systemlogik und aktuelle Tests prüfen; danach nur eindeutig veraltete Formulierungen korrigieren. Weiterhin gültige Migrations-/Connected-Mode-Hinweise erhalten.

## Offene App-/Alexa-Arbeiten aus STATUS.md
- **Bereich:** App-Baukasten / Alexa
- **Status:** laut bestehender `STATUS.md` offen.
- **Problem:** Dort sind das App-Vorlagen-Repository `elvadopress-app-template` sowie Erweiterungen des App-Baukastens/Alexa als offen aufgeführt.
- **Ursache:** Aus vorhandener Dokumentation nicht sicher ableitbar.
- **Nächste Untersuchung:** Vor Bearbeitung zuerst aktuellen Code, Fach-Doku und ggf. zugehöriges Template-Repository prüfen; keine fehlenden Funktionen unterstellen.
