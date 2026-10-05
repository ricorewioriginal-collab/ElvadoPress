# Alexa-Skill „{{NAME}}“

Dieses Paket wurde aus deinem CMS erzeugt (Apps → Alexa-Skill).

| Datei | Zweck |
|---|---|
| `skill-package/skill.json` | Skill-Angaben für die Amazon Developer Console |
| `skill-package/interactionModels/custom/de-DE.json` | Sprachmodell: deine Sender mit Aussprachen, Befehle |
| `lambda/index.js`, `lambda/package.json` | Backend des Skills (Node.js) |
| `lambda/fallback.json` | Rückfall-Konfiguration, falls dein CMS einmal nicht erreichbar ist |
| `lambda/cms.json` | Adresse deines CMS und geheimes Zähler-Token – nicht weitergeben |
| `listing-de.md` | Texte für den Store-Eintrag |

## Einreichen

1. Konto auf developer.amazon.com/alexa/console/ask → *Create Skill* → Name `{{NAME}}` → Sprache *German (DE)* → *Custom* → Hosting **Alexa-hosted (Node.js)**, Region *EU*.
2. *Build → Interaction Model → JSON Editor*: Inhalt von `de-DE.json` einfügen, speichern, *Build Skill*. Der Aufrufname ist `{{INVOCATION}}`.
3. *Build → Interfaces → Audio Player* einschalten.
4. *Code*: `index.js`, `package.json`, `fallback.json` und `cms.json` aus `lambda/` einfügen, *Deploy*.
5. *Test*: „Alexa, öffne {{INVOCATION}}“. Danach *Distribution* mit den Texten aus `skill.json` ausfüllen, Icons (108 und 512 Pixel) hochladen und *Submit for review*.

Einstellungen wie Sender, Reihenfolge, Texte, Standardsender und Wartung änderst du im CMS; der Skill holt sie sich automatisch. **Neu einspielen** musst du nur das Sprachmodell (bei neuen Sendern oder Aussprachen).

Hinweis: Der Aufrufname darf keine Ziffern enthalten und ist pro Skill genau einer.
