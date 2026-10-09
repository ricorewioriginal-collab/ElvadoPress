# Admin-Leiste auf der Website

Wer im CMS angemeldet ist, sieht auf der öffentlichen Website oben eine schmale Leiste (wie in WordPress): CMS-Dashboard, Design anpassen, Seite bearbeiten, „Neu“ (Beitrag/Seite/Medien) und „Hallo, Name“ mit Mein Profil und Abmelden. Abgemeldet ist sie nicht da.

- Skript: `assets/js/adminbar.js` (eingebunden in `index.html` und in die vom CMS erzeugten Seiten über `brandpage.php`).
- Das CMS speichert beim Anmelden einen Hinweis `elvado_cms_bar` im `localStorage` (Name, Rolle, Ablauf nach 12 Stunden) – **kein Token**. Die Leiste ist reine Navigation; jede Aktion verlangt weiterhin die echte CMS-Anmeldung. Abmelden (im CMS oder in der Leiste), eine fehlgeschlagene Anmeldeprüfung oder der Ablauf entfernen den Hinweis.
- Der Hinweis gilt je Domain (jede Domain getrennt) und je Browser.
- Nicht in Vorschau-Rahmen, nicht im CMS selbst, nicht beim Drucken; `?nobar=1` blendet sie aus.
- Die Links öffnen das CMS direkt im passenden Reiter (`/cms/#tab=themes`).
