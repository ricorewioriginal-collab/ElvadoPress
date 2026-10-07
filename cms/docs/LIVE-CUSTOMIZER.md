# Live Builder (Website → Live Builder)

Drei Spalten: **Seitenstruktur** (Bereiche der Startseite, Drag & Drop, ein-/ausblenden, duplizieren, löschen), **Einstellungen** des gewählten Bereichs (Reiter Inhalt, Design mit Tablet-/Mobil-Werten und Sichtbarkeit; Schema und Gruppen aus der Komponenten-Registry, siehe `COMPONENTS.md`) und **Live-Vorschau** (Desktop/Tablet/Mobil). Auf schmalen Fenstern schaltet die Leiste oben zwischen den Spalten um.

## Entwurf und Veröffentlichen
- Jede Änderung wird nach kurzer Pause als **Entwurf** gespeichert (`wp_bk_draft`, Option `elvado_bk_layout_draft`).
- Der Entwurf ist nur in der **geprüften Vorschau** sichtbar (signierter, 15 Minuten gültiger Schlüssel, `$GLOBALS['rrw_wp_preview_theme']`); die öffentliche Seite bleibt unverändert.
- **Veröffentlichen** (`wp_bk_publish`) übernimmt den Entwurf in `elvado_bk_layout`; **Entwurf verwerfen** (`wp_bk_discard`) setzt auf den veröffentlichten Stand zurück; „Auf Customizer-Positionen zurücksetzen“ nutzt `wp_bk_save` mit `reset`.
- Abschnittstypen und Felder kommen aus `elvado_bk_schema()` (`cms/themes/elvado-baukasten/inc/layout.php`); die Bereinigung erfolgt serverseitig.

## Grenzen (Stand jetzt)
Der Live Builder bearbeitet die Startseite des Themes „ElvadoPress Baukasten“. Ein allgemeines Komponenten-Register für beliebige Seiten, Header/Footer und Navigation folgt in späteren Phasen (siehe `ARCHITECTURE-WORDPRESS.md`).
