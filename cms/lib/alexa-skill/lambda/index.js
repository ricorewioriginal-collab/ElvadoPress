'use strict';
// Alexa-Skill (Website-Skill): liest die neuesten Beiträge der Website vor und beantwortet Fragen zu den Themen, die der Betreiber im CMS pflegt.
// Einstellungen (Themen, Texte, Neuigkeiten, Wartung) kommen aus dem CMS (alexa_config); fällt das CMS aus, gilt fallback.json.
// Es werden keine Nutzerdaten gespeichert; es wird auch nichts mit Geräte-/Nutzer-IDs protokolliert oder gemeldet.
const Alexa = require('ask-sdk-core');
const https = require('https');
const CMS = require('./cms.json');
const FALLBACK = require('./fallback.json');

// ---------- Netzwerk ----------

let fetchJson = (url) => new Promise((resolve, reject) => {
  const req = https.get(url, { timeout: 3000, headers: { Accept: 'application/json', 'User-Agent': 'website-alexa-skill/1.0' } }, (res) => {
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
    if (c && c.status === 'ok' && Array.isArray(c.topics)) { cfgCache = { at: now, value: c }; return c; }
  } catch (e) { /* weiter mit altem Stand oder Rückfall */ }
  if (cfgCache.value) { cfgCache.at = now - 4 * 60 * 1000; return cfgCache.value; } // in einer Minute erneut versuchen
  return FALLBACK;
}

const cfgOf = (h) => h.attributesManager.getRequestAttributes().cfg;
const topicsOf = (cfg) => (cfg.topics || []).filter((t) => t.enabled && t.text);
const newsOf = (cfg) => (cfg.news || []).filter((n) => n && n.title);

// ---------- Texte (SSML-sicher) ----------

const tidy = (s) => String(s == null ? '' : s).replace(/\s+/g, ' ').trim();
// Dynamische Texte gehören in SSML: Sonderzeichen entschärfen
const esc = (s) => tidy(String(s == null ? '' : s).replace(/\s*&\s*/g, ' und ').replace(/[<>]/g, ' ').replace(/["']/g, ''));
const fill = (tpl, vars) => esc(tpl).replace(/\{(\w+)\}/g, (m, k) => (k in vars ? vars[k] : m));
const examples = (cfg) => {
  const list = topicsOf(cfg).slice(0, 3).map((t) => esc(t.title));
  return list.length ? list.join(', ') : 'die Neuigkeiten';
};
const join = (list) => list.length > 1 ? `${list.slice(0, -1).join(', ')} und ${list[list.length - 1]}` : (list[0] || '');

/** Thema aus dem Slot „thema“: bevorzugt die Auflösung von Amazon (Kennung), sonst Vergleich mit dem gesprochenen Text. */
function topicFromSlot(cfg, slot) {
  if (!slot) return null;
  const list = topicsOf(cfg);
  const per = slot.resolutions && slot.resolutions.resolutionsPerAuthority;
  if (Array.isArray(per)) {
    for (const a of per) {
      if (a.status && a.status.code === 'ER_SUCCESS_MATCH' && a.values && a.values[0]) {
        const t = list.find((x) => x.id === a.values[0].value.id);
        if (t) return t;
      }
    }
  }
  const said = tidy(slot.value).toLowerCase();
  return said ? (list.find((x) => tidy(x.title).toLowerCase() === said) || null) : null;
}

// Antwort samt „Wiederholen“ merken
function say(h, speech, reprompt, end) {
  h.attributesManager.setSessionAttributes(Object.assign({}, h.attributesManager.getSessionAttributes(), { last: speech }));
  const b = h.responseBuilder.speak(speech);
  if (!end) b.reprompt(reprompt || speech);
  return b.getResponse();
}

// ---------- Handler ----------

const is = (h, type, name) => {
  const r = h.requestEnvelope.request;
  return r.type === type && (!name || (r.intent && (Array.isArray(name) ? name.includes(r.intent.name) : r.intent.name === name)));
};

const MaintenanceHandler = {
  canHandle: (h) => { const cfg = cfgOf(h); return h.requestEnvelope.request.type !== 'SessionEndedRequest' && (cfg.enabled === false || !!cfg.maintenance); },
  handle: (h) => { const cfg = cfgOf(h); return say(h, esc(cfg.maintenance || 'Der Skill ist gerade nicht verfügbar. Bitte versuche es später noch einmal.'), '', true); }
};

const LaunchHandler = {
  canHandle: (h) => is(h, 'LaunchRequest') || is(h, 'IntentRequest', 'AMAZON.NavigateHomeIntent'),
  handle: (h) => { const cfg = cfgOf(h); return say(h, fill(cfg.texts.welcome, {}), fill(cfg.texts.help, { beispiele: examples(cfg) })); }
};

const NewsHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'NewsIntent'),
  handle: (h) => {
    const cfg = cfgOf(h);
    const news = newsOf(cfg);
    if (!news.length) return say(h, fill(cfg.texts.nonews, {}), fill(cfg.texts.help, { beispiele: examples(cfg) }));
    const parts = news.map((n, i) => `Beitrag ${i + 1}: ${esc(n.title)}.`);
    h.attributesManager.setSessionAttributes(Object.assign({}, h.attributesManager.getSessionAttributes(), { news: true }));
    return say(h, `${parts.join(' ')} Sag zum Beispiel: Lies Beitrag eins, um mehr zu hören.`, 'Sag zum Beispiel: Lies Beitrag eins.');
  }
};

const ReadNewsHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'ReadNewsIntent'),
  handle: (h) => {
    const cfg = cfgOf(h);
    const news = newsOf(cfg);
    const slot = h.requestEnvelope.request.intent.slots && h.requestEnvelope.request.intent.slots.nummer;
    const n = parseInt(slot && slot.value, 10);
    if (!news.length) return say(h, fill(cfg.texts.nonews, {}), '');
    if (!n || n < 1 || n > news.length) return say(h, `Diesen Beitrag gibt es nicht. Wähle eine Nummer von eins bis ${news.length}.`, 'Welchen Beitrag möchtest du hören?');
    const item = news[n - 1];
    return say(h, `${esc(item.title)}. ${esc(item.text) || 'Dazu gibt es keinen weiteren Text.'}`, 'Möchtest du einen weiteren Beitrag hören?');
  }
};

const TopicHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'TopicIntent'),
  handle: (h) => {
    const cfg = cfgOf(h);
    const slot = h.requestEnvelope.request.intent.slots && h.requestEnvelope.request.intent.slots.thema;
    const t = topicFromSlot(cfg, slot);
    h.attributesManager.setRequestAttributes(Object.assign({}, h.attributesManager.getRequestAttributes(), { topic: t ? t.id : '' }));
    if (!t) return say(h, fill(cfg.texts.unknown, {}), 'Frag zum Beispiel: Welche Themen gibt es?');
    return say(h, esc(t.text), 'Möchtest du noch etwas wissen?');
  }
};

const ListTopicsHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'ListTopicsIntent'),
  handle: (h) => {
    const cfg = cfgOf(h);
    const titles = topicsOf(cfg).map((t) => esc(t.title));
    if (!titles.length) return say(h, 'Dazu gibt es aktuell keine Themen. Frag nach den Neuigkeiten.', 'Frag nach den Neuigkeiten.');
    return say(h, `Ich kenne diese Themen: ${join(titles)}.`, 'Worüber möchtest du mehr wissen?');
  }
};

const HelpHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'AMAZON.HelpIntent'),
  handle: (h) => { const cfg = cfgOf(h); const t = fill(cfg.texts.help, { beispiele: examples(cfg) }); return say(h, t, t); }
};

const RepeatHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'AMAZON.RepeatIntent'),
  handle: (h) => { const last = h.attributesManager.getSessionAttributes().last; const cfg = cfgOf(h); return say(h, last || fill(cfg.texts.welcome, {}), ''); }
};

const StopHandler = {
  canHandle: (h) => is(h, 'IntentRequest', ['AMAZON.StopIntent', 'AMAZON.CancelIntent']),
  handle: (h) => { const cfg = cfgOf(h); return say(h, fill(cfg.texts.goodbye, {}), '', true); }
};

const FallbackHandler = {
  canHandle: (h) => is(h, 'IntentRequest', 'AMAZON.FallbackIntent') || h.requestEnvelope.request.type === 'IntentRequest',
  handle: (h) => { const cfg = cfgOf(h); return say(h, fill(cfg.texts.unknown, {}), fill(cfg.texts.help, { beispiele: examples(cfg) })); }
};

const SessionEndedHandler = {
  canHandle: (h) => h.requestEnvelope.request.type === 'SessionEndedRequest',
  handle: (h) => h.responseBuilder.getResponse()
};

const ErrorHandler = {
  canHandle: () => true,
  handle(h, error) {
    console.error('Fehler', error && error.message);
    return h.responseBuilder.speak('Entschuldigung, da ist etwas schiefgelaufen. Bitte versuche es noch einmal.').reprompt('Was möchtest du wissen?').getResponse();
  }
};

// ---------- Interceptors ----------

const ConfigInterceptor = {
  async process(h) { h.attributesManager.setRequestAttributes({ cfg: await loadConfig() }); }
};

// Anonyme Zähler (nur Befehlsname und Thema) – nur wenn im CMS eingeschaltet; wartet höchstens 0,8 s
const StatsInterceptor = {
  async process(h) {
    const cfg = cfgOf(h);
    if (!cfg.stats || !CMS.token) return;
    const req = h.requestEnvelope.request;
    if (req.type !== 'IntentRequest') return;
    const url = `${CMS.base}/cms/api.php?action=alexa_stat`;
    const jobs = [postJson(url, { token: CMS.token, event: 'intent', intent: req.intent.name })];
    const topic = h.attributesManager.getRequestAttributes().topic;
    if (topic) jobs.push(postJson(url, { token: CMS.token, event: 'topic', topic }));
    await Promise.all(jobs);
  }
};

exports.handler = Alexa.SkillBuilders.custom()
  .addRequestHandlers(
    MaintenanceHandler, LaunchHandler, NewsHandler, ReadNewsHandler, TopicHandler, ListTopicsHandler,
    HelpHandler, RepeatHandler, StopHandler, SessionEndedHandler, FallbackHandler
  )
  .addRequestInterceptors(ConfigInterceptor)
  .addResponseInterceptors(StatsInterceptor)
  .addErrorHandlers(ErrorHandler)
  .withCustomUserAgent('website-alexa-skill/1.0')
  .lambda();

// nur für Tests
exports.__internals = { esc, fill, join, examples, topicFromSlot };
