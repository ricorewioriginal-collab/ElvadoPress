# Community (Mitglieder)

Optionales Modul, **standardmäßig aus**. Solange es ausgeschaltet ist, liefern alle `member_*`-Aktionen 404 und auf der Website erscheint nichts davon. Für RicoReWi Radio bleibt es aus; gedacht für das später eigenständig veröffentlichte CMS.

## Einschalten
CMS → Benutzer → **Community**: aktivieren, Registrierung wählen (offen / nach Freigabe / geschlossen), Mindest-Passwortlänge, Regeln (werden bei der Registrierung angezeigt). Danach unter Design → Widgets das Widget **Mitgliederbereich** platzieren (Anmelden, Registrieren, Passwort vergessen, Profil bearbeiten, Konto löschen).

## Sicherheit
- Passwörter nur als `password_hash`; Sitzungs-Token werden nur als SHA-256 gespeichert (Header `X-Member-Token`).
- Anmelde-Sperre: 8 Fehlversuche je Konto und 20 je Anschluss in 15 Minuten; IPs nur als gesalzener HMAC.
- Registrierung mit Honeypot, Zeitprüfung, Ratenbegrenzung und Einwilligung.
- Passwort-Reset-Link nutzt die Basis-URL aus den SEO-Einstellungen (kein Host-Header).
- Admin-Aktionen (`community_*`) nur für Superadmins. Daten liegen in `cms/data/.community/` (per `.htaccess` gesperrt).
- Konto löschen entfernt Daten und Sitzungen; die Datenschutz-Suche findet Mitglieder ebenfalls.

## Grenzen
Dateibasierte JSON-Speicherung – geeignet für kleine bis mittlere Communities. Forum und soziales Netzwerk sind eigene Module mit je einem Schalter in den Einstellungen.

## Forum
Schalter **Forum** in den Community-Einstellungen, Widget **Forum** (Community) im gewünschten Bereich platzieren. Kategorien legst du unter Benutzer → Community an.
- Lesen ist öffentlich, Themen/Antworten nur für angemeldete Mitglieder. Beiträge sind **reiner Text** (kein HTML), max. 5000 Zeichen.
- Mitglieder können eigene Beiträge 30 Minuten lang bearbeiten und jederzeit löschen (ein Thema mit Antworten nur die Moderation) und fremde Beiträge melden.
- Moderation: Mitglieder mit Rolle **Moderator** (direkt im Forum: anheften, schließen, löschen) und CMS-Administratoren (Meldungen unter Community → „Gemeldete Beiträge“).
- Schutz: Ratenbegrenzung (3 Themen / 10 Antworten / 5 Meldungen je 10 Minuten und Mitglied), Doppelpost-Sperre, Größenlimits.
- Konto löschen anonymisiert die Beiträge („Gelöschtes Mitglied“); Meldungen des Kontos werden entfernt.
- Daten: `cms/data/.community/forum.json` und `forum-<Thema>.json` (gesperrt, nicht im Repo).

## Soziales Netzwerk
Schalter **Soziales Netzwerk** in den Community-Einstellungen, Widget **Soziales Netzwerk** platzieren.
- Statusbeiträge (reiner Text, max. 500 Zeichen), Likes (je Mitglied einmal, an/aus), Folgen/Entfolgen, öffentliche Profile mit Zähler (Beiträge, Follower, folgt) und Feed-Reiter „Alle“ / „Folge ich“. Lesen ist öffentlich, Mitmachen nur für Mitglieder.
- Moderatoren können Beiträge löschen. Schutz: Ratenbegrenzung (10 Beiträge / 60 Likes / 40 Folgen-Aktionen je 10 Minuten), Doppelpost-Sperre, höchstens 500 gefolgte Mitglieder, 5000 Beiträge insgesamt (ältere fallen weg).
- Konto löschen entfernt alle Beiträge, Likes und Folgen des Mitglieds.
- Daten: `cms/data/.community/social.json` (gesperrt, nicht im Repo). Dateibasiert – für kleine bis mittlere Netzwerke.
