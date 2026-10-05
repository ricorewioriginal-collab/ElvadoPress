// frontend/src/components/AiContentAssistant.tsx
//
// KI-Assistent für den CMS-Editor (Tailwind-UI): Anbieter per Dropdown wählen (z. B. „EvoLink Smart Route“, „DeepSeek“, „OpenRouter“),
// per Prompt Texte schreiben/überarbeiten/übersetzen/zusammenfassen oder strukturierte JSON-Layouts erzeugen. Das Ergebnis lässt sich direkt in den
// Editor (Titel, Teaser, Artikeltext) oder als Layout in den Homepage-Baukasten übernehmen. Alle Aufrufe laufen über das PHP-Backend
// (cms/src/Ai/AiGatewayService.php) – API-Schlüssel sehen Browser und Komponente nie.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactElement } from 'react';
import { cmsApi } from '../lib/api';
import type { AiGenerateRequest, AiGenerateResult, AiProviderInfo, AiStatus, AiTask, LayoutSection } from '../lib/types';

export interface InsertTarget {
  /** Beschriftung des Knopfs, z. B. „In Artikeltext“. */
  label: string;
  /** Übernimmt den Text in den Editor. */
  apply: (text: string) => void;
}

export interface AiContentAssistantProps {
  /** Wohin das Ergebnis übernommen werden kann (Titel, Teaser, Artikeltext …). */
  targets?: readonly InsertTarget[];
  /** Liefert den markierten bzw. vorhandenen Text des Editors (für „Überarbeiten“, „Übersetzen“ …). */
  getEditorText?: () => string;
  /** Übernimmt ein vom Modell erzeugtes Layout in den Homepage-Baukasten. Ohne diese Funktion fehlt der Layout-Knopf. */
  onApplyLayout?: (sections: LayoutSection[]) => void;
  /** Aufgabe beim Öffnen. */
  initialTask?: AiTask;
  className?: string;
}

const TASK_HINTS: Record<AiTask, { placeholder: string; needsText: boolean }> = {
  text: { placeholder: 'z. B. „Schreibe einen kurzen Beitrag über unser Sommerkonzert (120 Wörter), freundlicher Ton.“', needsText: false },
  rewrite: { placeholder: 'Anweisung, z. B. „kürzer, lockerer, ohne Fachwörter“', needsText: true },
  translate: { placeholder: 'Optionaler Hinweis, z. B. „förmliche Anrede beibehalten“', needsText: true },
  summarize: { placeholder: 'Optional: „3 Stichpunkte“ oder „für Social Media“', needsText: true },
  json: { placeholder: 'Beschreibe die gewünschte Struktur, z. B. „Liste von 5 FAQ-Einträgen mit frage und antwort“', needsText: false },
  layout: { placeholder: 'z. B. „Startseite für eine Bäckerei: Hero, drei Vorteile, aktuelle Beiträge, Kontakt-Aufruf“', needsText: false },
};

const LANGUAGES = ['Deutsch', 'English', 'Français', 'Español', 'Italiano', 'Türkçe', 'Polski', 'Nederlands', 'Português', 'Русский'] as const;

const field =
  'w-full rounded-lg border border-line bg-surface2 px-3 py-2 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60';
const btn = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50';
const btnPrimary = `${btn} bg-accent text-black hover:brightness-110`;
const btnGhost = `${btn} border border-line bg-surface2 text-ink hover:bg-surface3`;

function groupProviders(list: readonly AiProviderInfo[]): Array<[string, AiProviderInfo[]]> {
  const map = new Map<string, AiProviderInfo[]>();
  for (const p of list) map.set(p.group, [...(map.get(p.group) ?? []), p]);
  return Array.from(map.entries());
}

export default function AiContentAssistant({ targets = [], getEditorText, onApplyLayout, initialTask = 'text', className = '' }: AiContentAssistantProps): ReactElement {
  const [status, setStatus] = useState<AiStatus | null>(null);
  const [loadError, setLoadError] = useState('');
  const [provider, setProvider] = useState('');
  const [model, setModel] = useState('');
  const [task, setTask] = useState<AiTask>(initialTask);
  const [language, setLanguage] = useState<string>('English');
  const [prompt, setPrompt] = useState('');
  const [text, setText] = useState('');
  const [temperature, setTemperature] = useState(0.7);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [result, setResult] = useState<AiGenerateResult | null>(null);
  const [copied, setCopied] = useState(false);
  const alive = useRef(true);

  useEffect(() => {
    alive.current = true;
    cmsApi<AiStatus & { status: string }>('ai_status')
      .then((s) => {
        if (!alive.current) return;
        setStatus(s);
        const first = s.providers.find((p) => p.id === s.default) ?? s.providers[0];
        if (first) {
          setProvider(first.id);
          setModel(first.model);
        }
      })
      .catch((e: unknown) => alive.current && setLoadError(e instanceof Error ? e.message : 'Status konnte nicht geladen werden'));
    return () => {
      alive.current = false;
    };
  }, []);

  const current = useMemo(() => status?.providers.find((p) => p.id === provider), [status, provider]);
  const hint = TASK_HINTS[task];
  const structured = task === 'json' || task === 'layout';

  const onProvider = useCallback(
    (id: string) => {
      setProvider(id);
      setModel(status?.providers.find((p) => p.id === id)?.model ?? '');
    },
    [status],
  );

  const onTask = (t: AiTask) => {
    setTask(t);
    setTemperature(t === 'json' || t === 'layout' ? 0.2 : 0.7);
    setResult(null);
    setError('');
  };

  const useEditorText = () => setText(getEditorText?.() ?? '');

  const canRun = !busy && provider !== '' && (prompt.trim() !== '' || text.trim() !== '') && (!hint.needsText || text.trim() !== '');

  const run = async () => {
    setBusy(true);
    setError('');
    setResult(null);
    const req: AiGenerateRequest = { provider, model: model || undefined, task, prompt, text: text || undefined, language: task === 'translate' ? language : undefined, temperature, max_tokens: structured ? 3000 : 1500 };
    try {
      const r = await cmsApi<AiGenerateResult & { status: string }>('ai_generate', req);
      if (alive.current) setResult(r);
    } catch (e) {
      if (alive.current) setError(e instanceof Error ? e.message : 'Die KI-Anfrage ist fehlgeschlagen.');
    } finally {
      if (alive.current) setBusy(false);
    }
  };

  const output = useMemo(() => {
    if (!result) return '';
    return structured && result.data !== null && result.data !== undefined ? JSON.stringify(result.data, null, 2) : result.text;
  }, [result, structured]);

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(output);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      setError('Kopieren wird von diesem Browser blockiert.');
    }
  };

  const layout = task === 'layout' && Array.isArray(result?.data) ? (result?.data as LayoutSection[]) : null;

  if (loadError) {
    return <div className={`ep-react rounded-xl border border-line bg-surface p-4 text-sm text-bad ${className}`}>{loadError}</div>;
  }
  if (!status) {
    return <div className={`ep-react rounded-xl border border-line bg-surface p-4 text-sm text-muted ${className}`}>Lade KI-Assistent …</div>;
  }
  if (status.providers.length === 0) {
    return (
      <div className={`ep-react rounded-xl border border-line bg-surface p-5 ${className}`}>
        <h3 className="mb-1 text-base font-bold text-ink">KI-Assistent</h3>
        <p className="text-sm text-muted">Noch kein KI-Anbieter eingerichtet. Hinterlege im Menü „KI &amp; Lovable“ → „KI-Anbieter“ einen API-Schlüssel (z. B. EvoLink, OpenAI, Anthropic, Google, OpenRouter oder DeepSeek).</p>
      </div>
    );
  }

  return (
    <section className={`ep-react rounded-xl border border-line bg-surface p-4 text-ink shadow-lg ${className}`} aria-label="KI-Assistent">
      <header className="mb-3 flex flex-wrap items-center justify-between gap-2">
        <h3 className="text-base font-bold">KI-Assistent</h3>
        {current && (
          <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${current.free ? 'bg-good/20 text-good' : 'bg-surface3 text-muted'}`}>{current.free ? 'kostenloses Kontingent' : 'kostenpflichtig'}</span>
        )}
      </header>

      <div className="mb-3 grid gap-3 sm:grid-cols-2">
        <label className="block text-xs font-semibold text-muted">
          Anbieter
          <select className={`${field} mt-1`} value={provider} onChange={(e) => onProvider(e.target.value)}>
            {groupProviders(status.providers).map(([group, list]) => (
              <optgroup key={group} label={group}>
                {list.map((p) => (
                  <option key={p.id} value={p.id}>
                    {p.label}
                  </option>
                ))}
              </optgroup>
            ))}
          </select>
        </label>
        <label className="block text-xs font-semibold text-muted">
          Modell
          <input className={`${field} mt-1`} list="ep-ai-models" value={model} onChange={(e) => setModel(e.target.value)} placeholder="Standardmodell" maxLength={120} />
          <datalist id="ep-ai-models">
            {(current?.models ?? []).map((m) => (
              <option key={m} value={m} />
            ))}
          </datalist>
        </label>
      </div>

      <div className="mb-3 flex flex-wrap gap-2" role="tablist" aria-label="Aufgabe">
        {(Object.keys(status.tasks) as AiTask[])
          .filter((t) => t !== 'layout' || onApplyLayout)
          .map((t) => (
            <button key={t} type="button" role="tab" aria-selected={task === t} onClick={() => onTask(t)} className={`${btn} ${task === t ? 'bg-accent text-black' : 'border border-line bg-surface2 text-ink hover:bg-surface3'}`}>
              {status.tasks[t].replace(/ \(.*\)$/, '')}
            </button>
          ))}
      </div>

      <label className="mb-3 block text-xs font-semibold text-muted">
        Auftrag
        <textarea className={`${field} mt-1 min-h-[88px]`} value={prompt} onChange={(e) => setPrompt(e.target.value)} placeholder={hint.placeholder} maxLength={8000} />
      </label>

      {(hint.needsText || text !== '' || getEditorText) && (
        <div className="mb-3">
          <div className="mb-1 flex items-center justify-between gap-2">
            <span className="text-xs font-semibold text-muted">{hint.needsText ? 'Text' : 'Kontext (optional)'}</span>
            {getEditorText && (
              <button type="button" className="text-xs font-semibold text-accent hover:underline" onClick={useEditorText}>
                Text aus dem Editor übernehmen
              </button>
            )}
          </div>
          <textarea className={`${field} min-h-[88px]`} value={text} onChange={(e) => setText(e.target.value)} maxLength={20000} aria-label="Text" />
        </div>
      )}

      <div className="mb-4 flex flex-wrap items-end gap-3">
        {task === 'translate' && (
          <label className="text-xs font-semibold text-muted">
            Zielsprache
            <select className={`${field} mt-1`} value={language} onChange={(e) => setLanguage(e.target.value)}>
              {LANGUAGES.map((l) => (
                <option key={l}>{l}</option>
              ))}
            </select>
          </label>
        )}
        <label className="min-w-[160px] flex-1 text-xs font-semibold text-muted">
          Kreativität: {temperature.toFixed(1)}
          <input type="range" min={0} max={1.5} step={0.1} value={temperature} onChange={(e) => setTemperature(Number(e.target.value))} className="mt-2 block w-full accent-[var(--accent)]" />
        </label>
        <button type="button" className={btnPrimary} onClick={run} disabled={!canRun}>
          {busy ? (
            <>
              <span className="inline-block h-4 w-4 animate-spin rounded-full border-2 border-black/30 border-t-black" aria-hidden="true" /> Erzeuge …
            </>
          ) : (
            'Generieren'
          )}
        </button>
      </div>

      {error && (
        <div role="alert" className="mb-3 rounded-lg border border-bad/40 bg-bad/10 px-3 py-2 text-sm text-bad">
          {error}
        </div>
      )}

      {result && (
        <div className="rounded-lg border border-line bg-surface2 p-3">
          <pre className="max-h-80 overflow-auto whitespace-pre-wrap break-words font-sans text-sm leading-relaxed text-ink">{output}</pre>
          <div className="mt-3 flex flex-wrap items-center gap-2">
            {!structured &&
              targets.map((t) => (
                <button key={t.label} type="button" className={btnPrimary} onClick={() => t.apply(result.text)}>
                  {t.label}
                </button>
              ))}
            {layout && onApplyLayout && (
              <button type="button" className={btnPrimary} onClick={() => onApplyLayout(layout)}>
                Layout übernehmen ({layout.length} Abschnitte)
              </button>
            )}
            <button type="button" className={btnGhost} onClick={copy}>
              {copied ? 'Kopiert ✓' : 'Kopieren'}
            </button>
            <button type="button" className={btnGhost} onClick={run} disabled={busy}>
              Neu erzeugen
            </button>
            <span className="ml-auto text-xs text-muted">
              {result.provider} · {result.model}
              {result.usage.prompt_tokens !== null && result.usage.completion_tokens !== null ? ` · ${result.usage.prompt_tokens + result.usage.completion_tokens} Token` : ''} · {(result.latency_ms / 1000).toFixed(1)} s
            </span>
          </div>
        </div>
      )}
      <p className="mt-3 text-xs text-muted">Hinweis: Auftrag und Text werden an den gewählten KI-Dienst übertragen. Bitte keine personenbezogenen oder vertraulichen Daten eingeben und Ergebnisse vor dem Veröffentlichen prüfen.</p>
    </section>
  );
}
