# Allgemeine Widgets und Formulare

Unabhängig vom Radio nutzbare Widgets (Design → Widgets): **FAQ/Akkordeon**, **Countdown**, **Video** (YouTube, Vimeo, MP4/WebM), **Karte** (OpenStreetMap), **Social-Links**, **Kontaktformular**, **Newsletter-Anmeldung**. Externe Inhalte (Video, Karte) erscheinen erst nach Zustimmung im Cookie-Hinweis.

## Formulare
- Einsendungen landen unter **Inhalte → Einsendungen** (ungelesen markiert, Zähler am Menüpunkt); optional zusätzlich per E-Mail an die im Widget eingetragene Adresse (nicht öffentlich sichtbar).
- Export als CSV (nur Administratoren), Löschen einzeln/markiert. Die Datenschutz-Werkzeuge finden Einsendungen ebenfalls.
- Missbrauchsschutz: nur für vorhandene Formular-Widgets, Einwilligung Pflicht, Honeypot, Mindest-Ausfüllzeit, höchstens 5 Einsendungen pro Besucher und Stunde (20 am Tag; gespeichert wird nur ein täglich wechselnder Hash der IP), doppelte Newsletter-Adressen werden nicht erneut angelegt.
- Daten: `cms/data/.tools/forms.json` (per `.htaccess` gesperrt). Maximal 5000 Einsendungen.

API: `POST ?action=form_submit` (öffentlich), `forms_list`, `forms_update` (`op`: read/unread/delete), `forms_export` (Admin). Tests: `php scripts/test-forms.php`.
