# RicoReWi Radio CMS – Plugin API

Plugins erweitern das Portal ohne beliebigen serverseitigen PHP-Code auszuführen.

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

Aktive Plugins werden auf ricorewi-radio.de geladen.

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

Nicht erlaubt:
- PHP-Dateien
- Server-Shell-Code
- automatische Ausführung von WordPress-Plugins
- `eval()`
- `new Function()`
- `document.write()`

Die Plugin-ZIP-Installation akzeptiert nur bekannte Dateien.

## Widgets

Ein Plugin kann über JavaScript auf Portal-Hooks reagieren und eigene DOM-Komponenten einfügen. Für echte CMS-Widgets sollte ein Plugin einen eindeutigen Widget-Namen verwenden und mit der Widget-Registry arbeiten, sobald diese verfügbar ist.

## Kompatibilität

Plugins sollten:
- keine globalen Portal-Funktionen überschreiben,
- ihre CSS-Klassen mit einem eigenen Präfix versehen,
- DOMContentLoaded nicht voraussetzen,
- auf `portal:ready` reagieren.
