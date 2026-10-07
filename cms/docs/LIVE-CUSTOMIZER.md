# Live Builder (Website → Live Builder)

Drei Spalten: **Seitenstruktur** (Baum mit verschachtelten Komponenten, Drag & Drop, ein-/ausblenden, duplizieren, löschen, „Hier hinein“ bei Containern/Spalten), **Einstellungen** des gewählten Bereichs (Reiter Inhalt, Design mit Tablet-/Mobil-Werten, Sichtbarkeit) und **Live-Vorschau** (Desktop/Tablet/Mobil) mit Vorschau-Brücke. Schema, Kategorien und Regeln kommen aus der Komponenten-Registry (`COMPONENTS.md`); die Palette ist nach Kategorien gegliedert und zeigt alles, was das Theme ausgeben kann (die acht Baukasten-Abschnitte, native Komponenten wie Button, Bild, Galerie, Spalten, Container, Audio, Video, Navigation sowie WordPress-Shortcode, -Block und Widget-Bereich).

## Vorschau anklicken (Preview Bridge)
In der Vorschau lassen sich Komponenten direkt anklicken: Hervorhebung beim Überfahren, Auswahl mit Rahmen, Werkzeugleiste (nach oben/unten, Kopie, ausblenden, löschen). Der Klick wählt dieselbe Komponente im Builder aus und öffnet ihre Einstellungen; umgekehrt scrollt die Vorschau zur Auswahl im Baum. Teile des Themes, die keine Komponenten sind (Header, Footer, Navigation), meldet die Vorschau als „Region“ – der Builder verweist dafür auf den Customizer. Im Modus „Vorschau“ ist die Auswahl aus und Links funktionieren. Die Scroll-Position bleibt beim Neuladen erhalten.

**Sicherheit:** Die Brücke (`cms/assets/preview-bridge.js`) wird nur bei gültigem, signiertem Vorschau-Schlüssel eingebunden (`wp-front.php`). Nachrichten gelten nur vom Eltern-Fenster **und** derselben Herkunft; Befehle tragen zusätzlich einen zufälligen Sitzungsschlüssel, den der Builder beim Handshake („hello“ → „init“) vergibt. Nachrichten anderer Frames oder mit falschem Schlüssel werden ignoriert; es wird nie an „*“ gesendet. Die Überlagerung läuft in einem geschlossenen Shadow-DOM und kollidiert nicht mit den Styles des Themes.

## Entwurf, Veröffentlichen, Verlauf, Termin
- Jede Änderung wird nach kurzer Pause als **Entwurf** gespeichert (`wp_bk_draft`); der Entwurf ist nur in der geprüften Vorschau sichtbar, die öffentliche Seite bleibt unverändert.
- **Veröffentlichen** (`wp_bk_publish`) macht den Entwurf zur neuen Fassung; **Entwurf verwerfen** (`wp_bk_discard`); **Verlauf** zeigt die Fassungen, **Wiederherstellen** (`wp_bk_rollback`) veröffentlicht eine alte Fassung als neue (nichts geht verloren); **Veröffentlichung planen** speichert einen Termin, zu dem der Entwurf automatisch veröffentlicht wird (beim nächsten Aufruf der Website).
- „Auf Customizer-Positionen zurücksetzen“ nimmt die Veröffentlichung zurück (die bisherige Fassung bleibt im Verlauf).
- Speicher: `LayoutStore`, Bereich `home`, Dateien unter `cms/data/layouts/` (siehe `COMPONENTS.md`). Ältere Installationen (Option `elvado_bk_layout`) werden weiter gelesen, bis zum ersten Veröffentlichen über den Speicher.

## Grenzen (Stand jetzt)
Der Live Builder bearbeitet die Startseite des Themes „ElvadoPress Baukasten“. Header, Footer und Navigation des Themes stellt weiter der Customizer ein; Layouts je Seite/Beitrag (Bereiche `page:*`/`post:*` des Speichers) bekommen ihre Oberfläche mit der Migration (Phase 9).
