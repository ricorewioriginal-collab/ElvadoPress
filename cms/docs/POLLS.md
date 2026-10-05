# Umfragen (CMS)

CMS → Inhalte → Umfragen. Eigene Umfragen für die Website, unabhängig von den Radio-Umfragen.

- **Anlegen**: Frage, 2–12 Antworten (eine pro Zeile), Einfach- oder Mehrfachauswahl, optional Beginn/Ende, manuell beenden.
- **Ergebnisse zeigen**: immer · nach der Abstimmung · erst nach Ende. Das wird serverseitig durchgesetzt – vorher gibt die öffentliche Schnittstelle keine Zahlen heraus.
- **Anzeigen**: Design → Widgets → „Umfrage (CMS)“ hinzufügen; entweder eine bestimmte Umfrage wählen oder „Neueste offene Umfrage“.
- **Doppelte Stimmen** werden verhindert über eine zufällige Browser-Kennung (eine Stimme je Umfrage) und – gröber, damit Haushalte/Schulen nicht ausgesperrt werden – höchstens 5 Stimmen je Anschluss. Gespeichert werden nur gesalzene Hashes (nie IP oder Kennung im Klartext); sie verschwinden, wenn die Umfrage gelöscht oder zurückgesetzt wird.
- Stimmen bleiben beim Bearbeiten erhalten, solange die Antwort gleich bleibt. Ergebnis als CSV exportierbar, Stimmen lassen sich zurücksetzen.

API: öffentlich `poll_get` (`id`, `client`), `poll_vote`; im CMS `polls_list`, `poll_save`, `poll_delete`, `poll_reset`, `polls_export`. Daten: `cms/data/.tools/polls.json` (gesperrter Ordner). Tests: `php scripts/test-polls.php`.
