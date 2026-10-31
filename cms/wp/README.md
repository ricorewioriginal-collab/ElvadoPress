# cms/wp – Verwaltungsbausteine für echtes WordPress

Die frühere WordPress-Nachbildung ist entfernt. ElvadoPress arbeitet ausschließlich mit echtem WordPress (WordPress-Engine, `cms/src/Wp/`).
Hier liegen nur noch Bausteine der Verwaltung, die mit den Funktionen des echten Cores arbeiten (geladen von `cms/api.php` nach dem Start von WordPress):

- `installer.php` – Plugins/Themes aus dem WordPress-Verzeichnis suchen, laden, sicher entpacken, Sprachpakete
- `settings-api.php` – Einstellungen (Allgemein, Schreiben, Lesen, Diskussion, Medien)
- `links-api.php` – Link-Struktur und Startseite
- `customizer-api.php` – Live-Customizer (Entwürfe, geplante Änderungen, Vorschau)
- `autoupdate.php` – automatische Updates von Plugins, Themes und Sprachpaketen

Helfer für echtes WordPress: `cms/lib/wp-real-admin.php`, `cms/lib/wp-real-compat.php`, Sandbox `cms/lib/wp-real-sandbox.php`.
