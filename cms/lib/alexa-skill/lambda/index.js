'use strict';
// Alexa-Skill: spielt die Sender per AudioPlayer (Live-Streams von laut.fm) und nennt Titel, Sendung und Sendeplan.
// Einstellungen (Sender, Reihenfolge, Texte, Wartung) kommen aus dem CMS (alexa_config); fällt das CMS aus, gilt fallback.json.
// Es werden keine Nutzerdaten gespeichert; es wird auch nichts mit Geräte-/Nutzer-IDs protokolliert oder gemeldet.
const Alexa = require('ask-sdk-core');
const https = require('https');
const CMS = require('./cms.json');
const FALLBACK = require('./fallback.json');

// ---------- Netzwerk ----------

let fetchJson = (url) => new Promise((resolve, reject) => {
  const req = https.get(url, { timeout: 3000, headers: { Accept: 'application/json', 'User-Agent': 'radio-alexa-skill/1.1' } }, (res) => {
    if (res.statusCode !== 200) { res.resume(); reject(new Error(`HTTP ${res.statusCode}`)); return; }
    let body = '';
    res.setEncoding('utf8');
    res.on('data', (c) => { body += c; if (body.length > 400000) req.destroy(); });
    res.on('end', () => { try { resolve(JSON.parse(body)); } catch (e) { reject(e); } });
  });
  req.on('timeout', () => req.destroy(new Error('timeout')));
  req.on('error', reject);
});

let postJson = (url, data) => new Promise((resolve) => {
  const body = JSON.stringify(data);
  const u = new URL(url);
  const req = https.request({ method: 'POST', hostname: u.hostname, path: u.pathname + u.search, timeout: 800, headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) } }, (res) => { res.resume(); res.on('end', resolve); });
  req.on('timeout', () => req.destroy());
  req.on('error', () => resolve());
  req.end(body);
});

// ---------- Konfiguration (CMS, 5 Minuten zwischengespeichert) ----------

let cfgCache = { at: 0, value: null };
async function loadConfig() {
  const now = Date.now();
  if (cfgCache.value && now - cfgCache.at < 5 * 60 * 1000) return cfgCache.value;
  try {
    const c = await fetchJson(`${CMS.base}/cms/api.php?action=alexa_config`);
    if (c && c.status === 'ok' && Array.isArray(c.stations) && c.stations.length) { cfgCache = { at: now, value: c }; return c; }
  } catch (e) { /* weiter mit altem Stand oder Rückfall */ }
  if (cfgCache.value) { cfgCache.at = now - 4 * 60 * 1000; return cfgCache.value; } // in einer Minute erneut versuchen
  return FALLBACK;
}

const cfgOf = (h) => h.attributesManager.getRequestAttributes().cfg;
const enabledStations = (cfg) => cfg.stations.filter((s) => s.enabled);
const byId = (cfg, id) => cfg.stations.find((s) => s.id === id) || null;
const streamUrl = (cfg, id) => cfg.stream_url.split('{id}').join(id);

// Dynamische Texte gehören in SSML: Sonderzeichen entschärfen, Zahlen im Sendernamen ausschreiben
const tidy = (s) => s.replace(/\s+/g, ' ').trim();
const esc = (s) => tidy(String(s == null ? '' : s).replace(/\s*&\s*/g, ' und ').replace(/[<>]/g, ' ').replace(/["']/g, ''));
const spoken = (title) => esc(title).replace(/\b24\b/g, 'vierundzwanzig');
const fill = (tpl, vars) => esc0(tpl).replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
const esc0 = (s) => tidy(String(s).replace(/\s*&\s*/g, ' und ').replace(/[<>]/g, ' '));
const examples = (cfg) => {
  const list = enabledStations(cfg);
  return list.slice(1, 4).concat(list.slice(0, 1)).slice(0, 3).map((s) => spoken(s.title)).join(', ');
};

// ---------- Hilfen ----------

/** Slot-Wert über die Entity Resolution auf die ID abbilden; null, wenn nichts Passendes erkannt wurde. */
function slotId(handlerInput, name) {
  const slot = Alexa.getSlot(handlerInput.requestEnvelope, name);
  const per = (slot && slot.resolutions && slot.resolutions.resolutionsPerAuthority) || [];
  for (const a of per) {
    if (a.status && a.status.code === 'ER_SUCCESS_MATCH' && a.values && a.values.length) return a.values[0].value.id;
  }
  return null;
}

/** Zuletzt gespielter Sender (Token des AudioPlayers). */
function currentStationId(handlerInput) {
  const ctx = handlerInput.requestEnvelope.context;
  const token = ctx && ctx.AudioPlayer && ctx.AudioPlayer.token;
  return byId(cfgOf(handlerInput), token) ? token : null;
}

function neighbour(cfg, id, step) {
  const list = enabledStations(cfg);
  const i = Math.max(0, list.findIndex((s) => s.id === id));
  return list[(i + step + list.length) % list.length].id;
}

function play(handlerInput, id, speech) {
  const cfg = cfgOf(handlerInput);
  const st = byId(cfg, id) || byId(cfg, cfg.default);
  const meta = { title: st.title, subtitle: cfg.name };
  if (cfg.art) { meta.art = { sources: [{ url: cfg.art }] }; meta.backgroundImage = { sources: [{ url: cfg.art }] }; }
  const rb = handlerInput.responseBuilder;
  if (speech) rb.speak(speech);
  return rb
    .addAudioPlayerPlayDirective('REPLACE_ALL', streamUrl(cfg, st.id), st.id, 0, undefined, meta)
    .withShouldEndSession(true)
    .getResponse();
}

function stop(handlerInput, speech) {
  const rb = handlerInput.responseBuilder.addAudioPlayerStopDirective();
  if (speech) rb.speak(speech);
  return rb.withShouldEndSession(true).getResponse();
}

/** Sender wählen: Slot > laufender Sender > null */
const pickStation = (h) => {
  const cfg = cfgOf(h);
  const id = slotId(h, 'sender') || currentStationId(h);
  return id && byId(cfg, id) ? id : null;
};

const is = (type) => (h) => Alexa.getRequestType(h.requestEnvelope) === type;
const isIntent = (...names) => (h) => Alexa.getRequestType(h.requestEnvelope) === 'IntentRequest' && names.includes(Alexa.getIntentName(h.requestEnvelope));

// ---------- Sendeplan (laut.fm), Berlin-Zeit ----------

const DAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
const DAY_DE = { mon: 'Montag', tue: 'Dienstag', wed: 'Mittwoch', thu: 'Donnerstag', fri: 'Freitag', sat: 'Samstag', sun: 'Sonntag' };
const WD = { Mon: 0, Tue: 1, Wed: 2, Thu: 3, Fri: 4, Sat: 5, Sun: 6 };

let nowFn = () => new Date();
function berlinNow(date) {
  const p = {};
  for (const x of new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Berlin', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(date || nowFn())) p[x.type] = x.value;
  return { day: WD[p.weekday], hour: parseInt(p.hour, 10) % 24, minute: parseInt(p.minute, 10) };
}

let scheduleCache = new Map();
async function loadSchedule(cfg, id) {
  const hit = scheduleCache.get(id);
  if (hit && Date.now() - hit.at < 10 * 60 * 1000) return hit.list;
  // Zuerst der Sendeplan-Dienst des Portals (gemeinsamer Zwischenspeicher, nur eigene Sender); sonst direkt laut.fm
  let raw = null;
  try {
    const own = await fetchJson(`${CMS.base}/cms/api.php?action=schedule&station=${encodeURIComponent(id)}`);
    const st = own && own.status === 'ok' && own.stations && own.stations[id];
    if (st && Array.isArray(st.playlists)) {
      raw = [];
      for (const p of st.playlists) for (const a of (p.airtimes || [])) raw.push({ name: p.name, day: a.day, hour: a.hour, end_time: a.end_time });
    }
  } catch (e) { /* kein eigener Sender oder Dienst nicht erreichbar: weiter mit laut.fm */ }
  if (raw === null) raw = await fetchJson(`${cfg.api_base}${id}/schedule`);
  const list = (Array.isArray(raw) ? raw : []).map((e) => {
    const d = DAYS.indexOf(e.day);
    const start = Number(e.hour), end = Number(e.end_time);
    if (d < 0 || !isFinite(start) || !isFinite(end)) return null;
    return { name: String(e.name || '').trim(), day: d, start: d * 24 + start, end: d * 24 + start + ((end - start + 24) % 24 || 24), sh: start, eh: end % 24 === 0 && end !== 0 ? 24 : end };
  }).filter((e) => e && e.name).sort((a, b) => a.start - b.start);
  scheduleCache.set(id, { at: Date.now(), list });
  return list;
}

/** Aktuelle und nächste Sendung (Position in der Woche in Stunden). */
function showsAt(list, now) {
  const pos = now.day * 24 + now.hour + now.minute / 60;
  const WEEK = 168;
  let current = list.find((e) => e.start <= pos && pos < e.end) || list.find((e) => e.end > WEEK && pos + WEEK < e.end && pos + WEEK >= e.start) || null;
  let next = list.find((e) => e.start > pos && (!current || e.name !== current.name)) || null;
  if (!next && list.length) next = list.find((e) => !current || e.name !== current.name) || null;
  return { current, next };
}

const hourText = (h) => (h === 24 ? '24' : String(h)) + ' Uhr';

/** Sendungen eines Wochentags, benachbarte gleiche Sendungen zusammengefasst. */
function dayShows(list, dayIdx) {
  const out = [];
  for (const e of list.filter((x) => x.day === dayIdx)) {
    const last = out[out.length - 1];
    if (last && last.name === e.name && last.eh === e.sh) last.eh = e.eh; else out.push({ name: e.name, sh: e.sh, eh: e.eh });
  }
  return out;
}

function dayFromSlot(h, now) {
  const id = slotId(h, 'tag');
  if (!id || id === 'today') return { idx: now.day, label: 'heute' };
  if (id === 'tomorrow') return { idx: (now.day + 1) % 7, label: 'morgen' };
  if (id === 'dayafter') return { idx: (now.day + 2) % 7, label: 'übermorgen' };
  const i = DAYS.indexOf(id);
  return i >= 0 ? { idx: i, label: 'am ' + DAY_DE[id] } : { idx: now.day, label: 'heute' };
}

// ---------- Handler ----------

const MaintenanceHandler = {
  canHandle(h) {
    const cfg = cfgOf(h);
    // Auch wenn im CMS alle Sender ausgeschaltet sind, gibt es nichts zu spielen
    if (cfg.enabled && !cfg.maintenance && enabledStations(cfg).length) return false;
    const t = Alexa.getRequestType(h.requestEnvelope);
    if (t === 'IntentRequest') return !['AMAZON.StopIntent', 'AMAZON.CancelIntent', 'AMAZON.PauseIntent', 'AMAZON.NavigateHomeIntent'].includes(Alexa.getIntentName(h.requestEnvelope));
    return t === 'LaunchRequest' || t.startsWith('PlaybackController.');
  },
  handle(h) {
    const cfg = cfgOf(h);
    return stop(h, esc0(cfg.maintenance || 'Der Skill ist gerade nicht verfügbar. Bitte versuche es später noch einmal.'));
  }
};

const LaunchHandler = {
  canHandle: is('LaunchRequest'),
  handle(h) {
    const cfg = cfgOf(h);
    const st = byId(cfg, cfg.default) || enabledStations(cfg)[0];
    const hint = examples(cfg) ? ` Sag zum Beispiel: Spiele ${examples(cfg)}.` : '';
    return play(h, st.id, `${fill(cfg.texts.welcome, {})} ${fill(cfg.texts.play, { sender: spoken(st.title) })}${hint}`);
  }
};

const PlayStationHandler = {
  canHandle: isIntent('PlayStationIntent'),
  handle(h) {
    const cfg = cfgOf(h);
    const id = slotId(h, 'sender');
    if (!id || !byId(cfg, id)) {
      const said = Alexa.getSlotValue(h.requestEnvelope, 'sender');
      const msg = said
        ? `Den Sender ${esc(said)} kenne ich leider nicht. Sag zum Beispiel: Spiele ${examples(cfg)}.`
        : `Welchen Sender möchtest du hören? Zum Beispiel ${examples(cfg)}.`;
      return h.responseBuilder.speak(msg).reprompt('Welchen Sender möchtest du hören? Sag Sender, um alle zu hören.').getResponse();
    }
    const st = byId(cfg, id);
    if (!st.enabled) {
      return h.responseBuilder.speak(`${fill(cfg.texts.unavailable, { sender: spoken(st.title) })} Sag zum Beispiel: Spiele ${examples(cfg)}.`).reprompt('Welchen Sender möchtest du hören?').getResponse();
    }
    return play(h, id, fill(cfg.texts.play, { sender: spoken(st.title) }));
  }
};

const PlayBrandHandler = {
  canHandle: isIntent('PlayBrandIntent'),
  handle(h) {
    const cfg = cfgOf(h);
    const brandId = slotId(h, 'marke');
    const map = cfg.brand_map || {};
    let id = map[brandId] || cfg.default;
    if (!byId(cfg, id) || !byId(cfg, id).enabled) id = (enabledStations(cfg)[0] || {}).id;
    return play(h, id, fill(cfg.texts.play, { sender: spoken(byId(cfg, id).title) }));
  }
};

const NowPlayingHandler = {
  canHandle: isIntent('NowPlayingIntent'),
  async handle(h) {
    const cfg = cfgOf(h);
    const id = pickStation(h);
    if (!id) return h.responseBuilder.speak(`Gerade läuft nichts. Sag zum Beispiel: Spiele ${examples(cfg)}.`).getResponse();
    try {
      const d = await fetchJson(`${cfg.api_base}${id}/current_song`);
      const title = (d && d.title) || '';
      const artist = (d && d.artist && d.artist.name) || '';
      if (title) return h.responseBuilder.speak(`Auf ${spoken(byId(cfg, id).title)} läuft gerade ${esc(title)}${artist ? ' von ' + esc(artist) : ''}.`).getResponse();
    } catch (e) { /* fällt unten auf die allgemeine Antwort zurück */ }
    return h.responseBuilder.speak(`Das aktuelle Lied von ${spoken(byId(cfg, id).title)} kann ich gerade nicht abrufen.`).getResponse();
  }
};

const noSchedule = (h) => h.responseBuilder.speak('Den Sendeplan gibt es in diesem Skill gerade nicht.').getResponse();

const CurrentShowHandler = {
  canHandle: isIntent('CurrentShowIntent'),
  async handle(h) {
    const cfg = cfgOf(h);
    if (cfg.schedule === false) return noSchedule(h);
    const id = pickStation(h);
    if (!id) return h.responseBuilder.speak(`Für welchen Sender? Sag zum Beispiel: Welche Sendung läuft auf ${examples(cfg).split(',')[0]}?`).reprompt('Für welchen Sender?').getResponse();
    const name = spoken(byId(cfg, id).title);
    try {
      const { current } = showsAt(await loadSchedule(cfg, id), berlinNow());
      if (!current) return h.responseBuilder.speak(`Bei ${name} gibt es gerade keine feste Sendung.`).getResponse();
      return h.responseBuilder.speak(`Auf ${name} läuft gerade ${esc(current.name)}, bis ${hourText(current.eh)}.`).getResponse();
    } catch (e) {
      return h.responseBuilder.speak(`Den Sendeplan von ${name} kann ich gerade nicht abrufen.`).getResponse();
    }
  }
};

const NextShowHandler = {
  canHandle: isIntent('NextShowIntent'),
  async handle(h) {
    const cfg = cfgOf(h);
    if (cfg.schedule === false) return noSchedule(h);
    const id = pickStation(h);
    if (!id) return h.responseBuilder.speak(`Für welchen Sender? Sag zum Beispiel: Was kommt als Nächstes auf ${examples(cfg).split(',')[0]}?`).reprompt('Für welchen Sender?').getResponse();
    const name = spoken(byId(cfg, id).title);
    try {
      const { next } = showsAt(await loadSchedule(cfg, id), berlinNow());
      if (!next) return h.responseBuilder.speak(`Bei ${name} gibt es keine weitere feste Sendung.`).getResponse();
      return h.responseBuilder.speak(`Als Nächstes kommt auf ${name} ${esc(next.name)}, ab ${hourText(next.sh)}.`).getResponse();
    } catch (e) {
      return h.responseBuilder.speak(`Den Sendeplan von ${name} kann ich gerade nicht abrufen.`).getResponse();
    }
  }
};

const ScheduleHandler = {
  canHandle: isIntent('ScheduleIntent'),
  async handle(h) {
    const cfg = cfgOf(h);
    if (cfg.schedule === false) return noSchedule(h);
    const id = pickStation(h) || cfg.default;
    const name = spoken(byId(cfg, id).title);
    const day = dayFromSlot(h, berlinNow());
    try {
      const shows = dayShows(await loadSchedule(cfg, id), day.idx);
      if (!shows.length) return h.responseBuilder.speak(`Bei ${name} gibt es ${day.label} keinen festen Sendeplan.`).getResponse();
      const max = 6;
      const parts = shows.slice(0, max).map((s) => `${hourText(s.sh)} bis ${hourText(s.eh)}: ${esc(s.name)}`);
      const more = shows.length > max ? ` Dazu kommen noch ${shows.length - max} weitere Sendungen.` : '';
      return h.responseBuilder.speak(`Der Sendeplan von ${name} ${day.label}: ${parts.join('; ')}.${more}`).getResponse();
    } catch (e) {
      return h.responseBuilder.speak(`Den Sendeplan von ${name} kann ich gerade nicht abrufen.`).getResponse();
    }
  }
};

const ListStationsHandler = {
  canHandle: isIntent('ListStationsIntent'),
  handle(h) {
    const names = enabledStations(cfgOf(h)).map((s) => spoken(s.title));
    const list = names.length > 1 ? names.slice(0, -1).join(', ') + ' und ' + names[names.length - 1] : names.join('');
    return h.responseBuilder.speak(`Ich kann ${list} spielen. Welchen möchtest du hören?`).reprompt('Welchen Sender möchtest du hören?').getResponse();
  }
};

const PauseHandler = {
  canHandle: (h) => isIntent('AMAZON.PauseIntent')(h) || is('PlaybackController.PauseCommandIssued')(h),
  handle: (h) => stop(h)
};

const ResumeHandler = {
  canHandle: (h) => isIntent('AMAZON.ResumeIntent')(h) || is('PlaybackController.PlayCommandIssued')(h),
  // Live-Radio: "Weiter" startet den Stream des zuletzt gespielten Sender neu
  handle: (h) => { const cfg = cfgOf(h); return play(h, currentStationId(h) || cfg.default); }
};

const NextHandler = {
  canHandle: (h) => isIntent('AMAZON.NextIntent')(h) || is('PlaybackController.NextCommandIssued')(h),
  handle(h) {
    const cfg = cfgOf(h);
    const id = neighbour(cfg, currentStationId(h) || cfg.default, 1);
    return play(h, id, isIntent('AMAZON.NextIntent')(h) ? `Weiter mit ${spoken(byId(cfg, id).title)}.` : undefined);
  }
};

const PreviousHandler = {
  canHandle: (h) => isIntent('AMAZON.PreviousIntent')(h) || is('PlaybackController.PreviousCommandIssued')(h),
  handle(h) {
    const cfg = cfgOf(h);
    const id = neighbour(cfg, currentStationId(h) || cfg.default, -1);
    return play(h, id, isIntent('AMAZON.PreviousIntent')(h) ? `Zurück zu ${spoken(byId(cfg, id).title)}.` : undefined);
  }
};

const StartOverHandler = {
  canHandle: isIntent('AMAZON.StartOverIntent', 'AMAZON.RepeatIntent'),
  handle: (h) => { const cfg = cfgOf(h); return play(h, currentStationId(h) || cfg.default); }
};

const UnsupportedHandler = {
  canHandle: isIntent('AMAZON.LoopOffIntent', 'AMAZON.LoopOnIntent', 'AMAZON.ShuffleOffIntent', 'AMAZON.ShuffleOnIntent'),
  handle: (h) => h.responseBuilder.speak('Das geht bei einem Live-Radio leider nicht.').getResponse()
};

const HelpHandler = {
  canHandle: isIntent('AMAZON.HelpIntent'),
  handle(h) {
    const cfg = cfgOf(h);
    return h.responseBuilder.speak(fill(cfg.texts.help, { beispiele: examples(cfg) })).reprompt('Welchen Sender möchtest du hören?').getResponse();
  }
};

const StopHandler = {
  canHandle: isIntent('AMAZON.StopIntent', 'AMAZON.CancelIntent', 'AMAZON.NavigateHomeIntent'),
  handle: (h) => stop(h, fill(cfgOf(h).texts.goodbye, {}))
};

const FallbackHandler = {
  canHandle: isIntent('AMAZON.FallbackIntent'),
  handle: (h) => h.responseBuilder
    .speak(`Das habe ich nicht verstanden. Sag zum Beispiel: Spiele ${examples(cfgOf(h))}.`)
    .reprompt('Welchen Sender möchtest du hören?')
    .getResponse()
};

// AudioPlayer-Ereignisse und Sitzungsende: nur bestätigen (hier darf nichts gesprochen werden)
const SilentHandler = {
  canHandle: (h) => {
    const t = Alexa.getRequestType(h.requestEnvelope);
    return t === 'SessionEndedRequest' || t.startsWith('AudioPlayer.') || t === 'PlaybackController.JumpToCommandIssued' || t.startsWith('System.');
  },
  handle: (h) => {
    const t = Alexa.getRequestType(h.requestEnvelope);
    if (t === 'AudioPlayer.PlaybackFailed') {
      const err = h.requestEnvelope.request.error || {};
      console.error('PlaybackFailed', err.type || '', err.message || '');
    }
    return h.responseBuilder.getResponse();
  }
};

const ErrorHandler = {
  canHandle: () => true,
  handle(h, error) {
    console.error('Fehler', error && error.message);
    return h.responseBuilder.speak('Entschuldigung, da ist etwas schiefgelaufen. Bitte versuche es noch einmal.').reprompt('Welchen Sender möchtest du hören?').getResponse();
  }
};

// ---------- Interceptors ----------

const ConfigInterceptor = {
  async process(h) { h.attributesManager.setRequestAttributes({ cfg: await loadConfig() }); }
};

// Anonyme Zähler (nur Sender und Befehlsname) – nur wenn im CMS eingeschaltet; wartet höchstens 0,8 s
const StatsInterceptor = {
  async process(h, response) {
    const cfg = cfgOf(h);
    if (!cfg.stats || !CMS.token) return;
    const url = `${CMS.base}/cms/api.php?action=alexa_stat`;
    const jobs = [];
    const req = h.requestEnvelope.request;
    if (req.type === 'IntentRequest') jobs.push(postJson(url, { token: CMS.token, event: 'intent', intent: req.intent.name }));
    const d = ((response && response.directives) || []).find((x) => x.type === 'AudioPlayer.Play');
    if (d) jobs.push(postJson(url, { token: CMS.token, event: 'play', station: d.audioItem.stream.token }));
    if (jobs.length) await Promise.all(jobs);
  }
};

exports.handler = Alexa.SkillBuilders.custom()
  .addRequestHandlers(
    MaintenanceHandler, LaunchHandler, PlayStationHandler, PlayBrandHandler, NowPlayingHandler, CurrentShowHandler, NextShowHandler, ScheduleHandler,
    ListStationsHandler, PauseHandler, ResumeHandler, NextHandler, PreviousHandler, StartOverHandler, UnsupportedHandler,
    HelpHandler, StopHandler, FallbackHandler, SilentHandler
  )
  .addRequestInterceptors(ConfigInterceptor)
  .addResponseInterceptors(StatsInterceptor)
  .addErrorHandlers(ErrorHandler)
  .withCustomUserAgent('radio-alexa-skill/1.1')
  .lambda();

// nur für Tests
exports.__setFetch = (fn) => { fetchJson = fn; };
exports.__setPost = (fn) => { postJson = fn; };
exports.__setNow = (fn) => { nowFn = fn; };
exports.__reset = () => { cfgCache = { at: 0, value: null }; scheduleCache = new Map(); };
exports.__internals = { berlinNow, showsAt, dayShows };
