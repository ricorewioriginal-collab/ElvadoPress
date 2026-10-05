// frontend/src/components/AiSettings.tsx
// Einstellungen des KI-Gateways (nur Administratoren): Schlüssel je Anbieter (nur schreibend – der Server meldet nur „gesetzt“), Modell, EvoLink-Basis-Adresse,
// Limit je Stunde und Standard-Anbieter; darunter Nutzung/Protokoll (ohne Prompt-Texte).

import { useCallback, useEffect, useState } from 'react';
import type { ReactElement } from 'react';
import { cmsApi, toast } from '../lib/api';
import type { AiAdminConfig, AiAdminProvider, AiLogRow, AiLogSummary } from '../lib/types';

const field = 'w-full rounded-lg border border-line bg-surface2 px-3 py-2 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60';
const btn = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:opacity-50';

interface Draft {
  api_key: string;
  model: string;
  base_url: string;
  enabled: boolean;
}

export default function AiSettings(): ReactElement {
  const [cfg, setCfg] = useState<AiAdminConfig | null>(null);
  const [drafts, setDrafts] = useState<Record<string, Draft>>({});
  const [rate, setRate] = useState(60);
  const [def, setDef] = useState('');
  const [msg, setMsg] = useState<{ text: string; bad: boolean } | null>(null);
  const [busy, setBusy] = useState(false);
  const [logs, setLogs] = useState<{ available: boolean; summary: AiLogSummary[]; recent: AiLogRow[] } | null>(null);

  const apply = useCallback((c: AiAdminConfig) => {
    setCfg(c);
    setRate(c.rate_limit);
    setDef(c.default_provider);
    const d: Record<string, Draft> = {};
    c.providers.forEach((p) => (d[p.id] = { api_key: '', model: p.model, base_url: p.base_url, enabled: p.enabled }));
    setDrafts(d);
  }, []);

  useEffect(() => {
    cmsApi<{ config: AiAdminConfig }>('ai_config_get').then((r) => apply(r.config)).catch((e: Error) => setMsg({ text: e.message, bad: true }));
    cmsApi<{ available: boolean; summary: AiLogSummary[]; recent: AiLogRow[] }>('ai_logs').then(setLogs).catch(() => setLogs({ available: false, summary: [], recent: [] }));
  }, [apply]);

  const set = (id: string, patch: Partial<Draft>) => setDrafts((d) => ({ ...d, [id]: { ...d[id], ...patch } }));

  const save = async () => {
    if (!cfg) return;
    setBusy(true);
    setMsg(null);
    const providers: Record<string, Partial<Draft>> = {};
    cfg.providers.forEach((p) => {
      const d = drafts[p.id];
      providers[p.id] = { api_key: d.api_key, model: d.model, enabled: d.enabled, ...(p.base_editable ? { base_url: d.base_url } : {}) };
    });
    try {
      const r = await cmsApi<{ config: AiAdminConfig }>('ai_config_save', { config: { providers, rate_limit: rate, default_provider: def } });
      apply(r.config);
      setMsg({ text: 'Gespeichert.', bad: false });
      toast('KI-Einstellungen gespeichert');
    } catch (e) {
      setMsg({ text: e instanceof Error ? e.message : 'Speichern fehlgeschlagen', bad: true });
    } finally {
      setBusy(false);
    }
  };

  const clearKey = async (p: AiAdminProvider) => {
    if (!window.confirm(`Schlüssel für ${p.label} entfernen?`)) return;
    try {
      const r = await cmsApi<{ config: AiAdminConfig }>('ai_config_save', { config: { providers: { [p.id]: { api_key: '__clear__' } } } });
      apply(r.config);
    } catch (e) {
      setMsg({ text: e instanceof Error ? e.message : 'Fehlgeschlagen', bad: true });
    }
  };

  if (!cfg) return <div className="text-sm text-muted">{msg?.text ?? 'Lade …'}</div>;

  return (
    <div className="space-y-4 text-ink">
      <p className="text-sm text-muted">
        Hier hinterlegst du die Zugänge zu den KI-Diensten. Schlüssel bleiben auf dem Server und werden nie an den Browser gesendet; ein leeres Feld lässt einen vorhandenen Schlüssel unverändert. Dienste, die du im KI-Assistenten der Website schon eingerichtet hast (OpenAI, OpenRouter, Gemini), werden mitgenutzt.
      </p>
      {cfg.providers.map((p) => {
        const d = drafts[p.id];
        if (!d) return null;
        return (
          <fieldset key={p.id} className="rounded-xl border border-line bg-surface p-4">
            <legend className="px-2 text-sm font-bold">
              {p.label} <span className="ml-1 text-xs font-normal text-muted">{p.group}</span>
            </legend>
            <div className="mb-2 flex flex-wrap items-center gap-3 text-xs">
              <label className="flex items-center gap-2 font-semibold">
                <input type="checkbox" checked={d.enabled} onChange={(e) => set(p.id, { enabled: e.target.checked })} /> aktiv
              </label>
              <span className={p.has_key ? 'text-good' : 'text-muted'}>{p.has_key ? (p.key_from_assistant ? 'Schlüssel vom KI-Assistenten mitgenutzt' : 'Schlüssel gesetzt') : 'kein Schlüssel'}</span>
              {p.has_key && !p.key_from_assistant && (
                <button type="button" className="font-semibold text-accent hover:underline" onClick={() => clearKey(p)}>
                  Schlüssel entfernen
                </button>
              )}
              {!p.verified && <span className="rounded bg-bad/15 px-2 py-0.5 font-semibold text-bad">bitte Anbieter-Doku prüfen</span>}
            </div>
            <p className="mb-3 text-xs text-muted">{p.note}</p>
            <div className="grid gap-3 sm:grid-cols-2">
              <label className="block text-xs font-semibold text-muted">
                API-Schlüssel
                <input className={`${field} mt-1`} type="password" autoComplete="new-password" value={d.api_key} onChange={(e) => set(p.id, { api_key: e.target.value })} placeholder={p.has_key ? '•••••••• (zum Ändern neu eingeben)' : 'Schlüssel einfügen'} />
              </label>
              <label className="block text-xs font-semibold text-muted">
                Standardmodell
                <input className={`${field} mt-1`} list={`models-${p.id}`} value={d.model} onChange={(e) => set(p.id, { model: e.target.value })} maxLength={120} />
                <datalist id={`models-${p.id}`}>
                  {p.models.map((m) => (
                    <option key={m} value={m} />
                  ))}
                </datalist>
              </label>
              {p.base_editable && (
                <label className="block text-xs font-semibold text-muted sm:col-span-2">
                  Basis-Adresse (https)
                  <input className={`${field} mt-1`} value={d.base_url} onChange={(e) => set(p.id, { base_url: e.target.value })} placeholder="https://…/v1" />
                </label>
              )}
            </div>
          </fieldset>
        );
      })}
      <div className="grid gap-3 sm:grid-cols-2">
        <label className="block text-xs font-semibold text-muted">
          Standard-Anbieter
          <select className={`${field} mt-1`} value={def} onChange={(e) => setDef(e.target.value)}>
            <option value="">(erster verfügbarer)</option>
            {cfg.providers.map((p) => (
              <option key={p.id} value={p.id}>
                {p.label}
              </option>
            ))}
          </select>
        </label>
        <label className="block text-xs font-semibold text-muted">
          Anfragen pro Stunde und Benutzer
          <input className={`${field} mt-1`} type="number" min={5} max={1000} value={rate} onChange={(e) => setRate(Number(e.target.value))} />
        </label>
      </div>
      <div className="flex items-center gap-3">
        <button type="button" className={`${btn} bg-accent text-black hover:brightness-110`} onClick={save} disabled={busy}>
          {busy ? 'Speichere …' : 'KI-Einstellungen speichern'}
        </button>
        {msg && <span className={`text-sm ${msg.bad ? 'text-bad' : 'text-good'}`}>{msg.text}</span>}
      </div>

      <div className="rounded-xl border border-line bg-surface p-4">
        <h4 className="mb-2 text-sm font-bold">Nutzung (letzte 30 Tage)</h4>
        {!logs ? (
          <p className="text-sm text-muted">Lade …</p>
        ) : !logs.available ? (
          <p className="text-sm text-muted">Das Protokoll ist nicht verfügbar (keine Datenbank/SQLite-Erweiterung).</p>
        ) : logs.summary.length === 0 ? (
          <p className="text-sm text-muted">Noch keine Aufrufe. Es werden nur Zähler gespeichert, nie Aufträge oder Texte.</p>
        ) : (
          <table className="w-full text-left text-sm">
            <thead className="text-xs text-muted">
              <tr>
                <th className="py-1">Anbieter</th>
                <th>Aufrufe</th>
                <th>Fehler</th>
                <th>Token</th>
                <th>Ø Dauer</th>
              </tr>
            </thead>
            <tbody>
              {logs.summary.map((s) => (
                <tr key={s.provider} className="border-t border-line">
                  <td className="py-1 font-semibold">{s.provider}</td>
                  <td>{s.calls}</td>
                  <td className={s.errors ? 'text-bad' : ''}>{s.errors}</td>
                  <td>{s.tokens}</td>
                  <td>{(s.avg_ms / 1000).toFixed(1)} s</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}
