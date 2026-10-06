# ElvadoPress – native Plugin API

Native ElvadoPress-Plugins dieser API erweitern die Website und Verwaltung in einer bewusst eingeschränkten Laufzeit, ohne beliebigen serverseitigen PHP-Code auszuführen.

> **Wichtig:** Diese API ist nicht mit WordPress-Plugins gleichzusetzen. Die WordPress-Kompatibilitätsschicht besitzt einen eigenen Plugin-Laufzeitpfad. Ebenso dürfen zukünftige, von ElvadoPress selbst ausgelieferte serverseitige Essentials nicht einfach als ungeprüfte Drittanbieter-ZIPs in diese eingeschränkte API eingeordnet werden. Für vertrauenswürdige serverseitige Erweiterungen muss zuerst ein eigenes Berechtigungs-, Signatur-/Vertrauens- und Update-Modell festgelegt werden.

## ZIP-Struktur

```
mein-plugin/
  plugin.json
  frontend.js       optional
  frontend.css      optional
  admin.js          optional
  README.md         optional
```

## plugin.json

```json
{
  "id": "mein-plugin",
  "name": "Mein Plugin",
  "version": "1.0.0",
  "author": "Name",
  "license": "MIT",
  "description": "Beschreibung",
  "hooks": ["portal:ready", "page:rendered"]
}
```

## Frontend Runtime

Aktive native Plugins werden auf der Website der jeweiligen ElvadoPress-Installation geladen.

Globale Schnittstelle:

```js
RRWPluginAPI.on("portal:ready", payload => {
  console.log(payload);
});

RRWPluginAPI.emit("mein-plugin:event", { value: 1 });

const config = RRWPluginAPI.getConfig();
```

## Verfügbare Hooks

- `portal:ready`
- `cms:config-applied`
- `page:rendered`
- `plugin:loaded`

Plugins können eigene Events mit einem eindeutigen Namespace verwenden.

## Sicherheit

Für über diese native ZIP-Schnittstelle installierte Drittanbieter-Plugins gilt weiterhin ausdrücklich:

Nicht erlaubt:
- PHP-Dateien
- Server-Shell-Code
- automatische Ausführung von WordPress-Plugins
- `eval()`
- `new Function()`
- `document.write()`

Die Plugin-ZIP-Installation akzeptiert nur bekannte Dateien. Diese Einschränkung darf nicht gelockert werden, nur um serverseitige Essentials zu ermöglichen; dafür ist eine getrennte, vertrauenswürdige Erweiterungsschicht erforderlich.

## Widgets

Ein Plugin kann über JavaScript auf Portal-Hooks reagieren und eigene DOM-Komponenten einfügen. Für echte CMS-Widgets sollte ein Plugin einen eindeutigen Widget-Namen verwenden und mit der Widget-Registry arbeiten, sobald diese verfügbar ist.

## Kompatibilität

Plugins sollten:
- keine globalen Portal-Funktionen überschreiben,
- ihre CSS-Klassen mit einem eigenen Präfix versehen,
- DOMContentLoaded nicht voraussetzen,
- auf `portal:ready` reagieren.
