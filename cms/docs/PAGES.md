# Seiten: Status, Planung, SEO, Versionen

CMS → Inhalte → Seiten. Zusätzlich zu Blöcken und Systeminhalt hat jede Seite:

- **Status**: Veröffentlicht · Entwurf (nicht sichtbar, nicht in der Sitemap) · Geplant (nur eigene Seiten; erscheint zum eingestellten Zeitpunkt, Serverzeit).
  - Geplante Seiten werden bereits erzeugt, aber von `brandpage.php` bis zum Zeitpunkt mit 404 ausgeblendet – ohne Cron. Mit dem Vorschau-Link aus *Werkzeuge → Wartungsmodus* sieht man sie vorab.
  - Die Sitemap wird beim nächsten Speichern/Deploy aktualisiert und enthält geplante Seiten erst, wenn sie fällig sind.
- **SEO**: Meta-Titel, Meta-Beschreibung, „noindex“ (setzt `robots: noindex,follow` und nimmt die Seite aus der Sitemap). Leer = Standard (Seitentitel – RicoReWi Radio / Einleitung).
- **Duplizieren** (eigene Seiten): legt eine Kopie als Entwurf an.
- **Versionen**: bei jedem Speichern wird die vorherige Fassung jeder geänderten Seite abgelegt (je Seite die letzten 10, `cms/data/.tools/page-revisions.json`). „Laden“ übernimmt eine Version in den Editor; sie gilt erst nach „Alle Seiten speichern“.

Code: `cms/lib/tools.php` (Planung/Versionen), `cms/lib/publish.php` (Bereinigung, Erzeugung), `cms/lib/seo.php`, `cms/assets/pages-manager.js`. Tests: `php scripts/test-pages.php`.
