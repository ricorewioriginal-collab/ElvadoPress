// frontend/src/components/LovableWidgetBridge.tsx
//
// Brücke zwischen einem Lovable-Projekt (Web Component) und dem CMS:
//  1. lädt das Skript des Projekts asynchron (nur https, nur erlaubte Hosts) – höchstens einmal pro Seite –,
//  2. holt die Daten per fetch von unserem PHP-Backend (cms/api-lovable-provider.php, siehe dataSourceUrl),
//  3. rendert das Custom Element (componentName) und übergibt die Daten als JSON-Attribut (und zusätzlich als Eigenschaft "data").
//
// Hinweis zur Skript-Adresse: Wie ein Lovable-Projekt als Skript bereitgestellt wird, hängt von deinem Projekt/Hosting ab. Darum gibt es KEINE
// fest verdrahtete Adresse: übergib scriptUrl oder scriptUrlTemplate (z. B. "https://dein-hosting.example/{projectId}.js") und trage den Host in
// "allowedHosts" ein (im CMS: Menü „KI & Lovable“ → Lovable). Ohne beides wird kein Fremdskript geladen (die Komponente zeigt dann nur die Daten-Hülle).

import { createElement, useEffect, useMemo, useRef, useState } from 'react';
import type { ReactElement, ReactNode } from 'react';
import type { ProviderPayload } from '../lib/types';

export interface LovableWidgetBridgeProps {
  /** Lovable-Projekt-ID (Buchstaben, Ziffern, _ und -). */
  projectId: string;
  /** Tag-Name der Web Component (Kleinbuchstaben mit Bindestrich, z. B. "news-grid"). */
  componentName: string;
  /** Adresse der JSON-Daten, z. B. "/cms/api-lovable-provider.php?widget=news-grid". */
  dataSourceUrl: string;
  /** Vollständige Skript-Adresse (https). Hat Vorrang vor scriptUrlTemplate. */
  scriptUrl?: string;
  /** Vorlage mit {projectId}, z. B. "https://dein-hosting.example/{projectId}.js". */
  scriptUrlTemplate?: string;
  /** Hosts, von denen Skripte geladen werden dürfen (Pflicht, sobald ein Skript geladen werden soll). */
  allowedHosts?: readonly string[];
  /** Name des Attributs für die Daten (Standard "data"; ergibt data-Attribut mit JSON). */
  dataAttribute?: string;
  /** Zusätzliche Attribute (nur data-* und aria-*). */
  attributes?: Readonly<Record<string, string>>;
  /** Wartezeit auf das Skript in Millisekunden. */
  scriptTimeoutMs?: number;
  /** Inhalt während des Ladens / bei Fehlern. */
  loading?: ReactNode;
  renderError?: (message: string) => ReactNode;
  onData?: (payload: ProviderPayload) => void;
  onError?: (message: string) => void;
}

type Phase = 'idle' | 'loading' | 'ready' | 'error';

const COMPONENT_RE = /^[a-z][a-z0-9]*(-[a-z0-9]+)+$/;
const PROJECT_RE = /^[A-Za-z0-9][A-Za-z0-9_-]{0,119}$/;
const ATTR_RE = /^(data|aria)-[a-z0-9-]{1,40}$/;

/** Schon eingefügte Skripte (Adresse → Promise), damit dasselbe Projekt auch bei mehreren Widgets nur einmal geladen wird. */
const scriptCache = new Map<string, Promise<void>>();

function resolveScriptUrl(p: LovableWidgetBridgeProps): string {
  const url = p.scriptUrl ?? (p.scriptUrlTemplate ? p.scriptUrlTemplate.replace('{projectId}', encodeURIComponent(p.projectId)) : '');
  if (!url) return '';
  try {
    const u = new URL(url);
    const hosts = (p.allowedHosts ?? []).map((h) => h.toLowerCase());
    return u.protocol === 'https:' && hosts.includes(u.hostname.toLowerCase()) ? u.href : '';
  } catch {
    return '';
  }
}

/** Skript asynchron einfügen, falls es noch nicht im DOM ist. Löst auf, sobald es geladen ist. */
export function injectScript(src: string, timeoutMs: number, projectId: string): Promise<void> {
  const cached = scriptCache.get(src);
  if (cached) return cached;
  const existing = Array.from(document.scripts).find((s) => s.src === src);
  const p = new Promise<void>((resolve, reject) => {
    const timer = window.setTimeout(() => reject(new Error('Das Lovable-Skript antwortet nicht (Zeitüberschreitung).')), timeoutMs);
    const done = () => {
      window.clearTimeout(timer);
      resolve();
    };
    const fail = () => {
      window.clearTimeout(timer);
      scriptCache.delete(src);
      reject(new Error('Das Lovable-Skript konnte nicht geladen werden.'));
    };
    if (existing) {
      // schon im DOM (z. B. vom Theme eingebunden): als geladen betrachten, wenn es nicht mehr lädt
      if ((existing as HTMLScriptElement & { readyState?: string }).readyState === 'loading') {
        existing.addEventListener('load', done, { once: true });
        existing.addEventListener('error', fail, { once: true });
      } else {
        done();
      }
      return;
    }
    const s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.crossOrigin = 'anonymous';
    s.referrerPolicy = 'no-referrer';
    s.dataset.elvadoLovable = projectId;
    s.addEventListener('load', done, { once: true });
    s.addEventListener('error', fail, { once: true });
    document.head.appendChild(s);
  });
  scriptCache.set(src, p);
  return p;
}

export default function LovableWidgetBridge(props: LovableWidgetBridgeProps): ReactElement {
  const { projectId, componentName, dataSourceUrl, dataAttribute = 'data', attributes, scriptTimeoutMs = 10000, onData, onError } = props;
  const [phase, setPhase] = useState<Phase>('idle');
  const [error, setError] = useState('');
  const [payload, setPayload] = useState<ProviderPayload | null>(null);
  const elRef = useRef<HTMLElement | null>(null);

  const valid = COMPONENT_RE.test(componentName) && PROJECT_RE.test(projectId);
  const scriptUrl = useMemo(() => resolveScriptUrl(props), [props.scriptUrl, props.scriptUrlTemplate, props.allowedHosts, props.projectId]); // eslint-disable-line react-hooks/exhaustive-deps
  const safeAttrs = useMemo(() => {
    const out: Record<string, string> = {};
    for (const [k, v] of Object.entries(attributes ?? {})) if (ATTR_RE.test(k)) out[k] = String(v).slice(0, 200);
    return out;
  }, [attributes]);

  useEffect(() => {
    if (!valid) {
      setPhase('error');
      setError('Ungültige Projekt-ID oder Komponentenname.');
      return;
    }
    const ctrl = new AbortController();
    let alive = true;
    setPhase('loading');
    setError('');

    const loadScript = scriptUrl ? injectScript(scriptUrl, scriptTimeoutMs, projectId) : Promise.resolve();
    const loadData = fetch(dataSourceUrl, { signal: ctrl.signal, credentials: 'omit', headers: { Accept: 'application/json' } }).then(async (r) => {
      if (!r.ok) throw new Error(r.status === 404 ? 'Das Widget ist im CMS nicht (mehr) eingerichtet.' : `Daten konnten nicht geladen werden (HTTP ${r.status}).`);
      const j = (await r.json()) as ProviderPayload | { status: string; message?: string };
      if (j.status !== 'ok') throw new Error((j as { message?: string }).message ?? 'Unerwartete Antwort des Servers.');
      return j as ProviderPayload;
    });

    Promise.all([loadScript, loadData])
      .then(([, data]) => {
        if (!alive) return;
        setPayload(data);
        setPhase('ready');
        onData?.(data);
      })
      .catch((e: unknown) => {
        if (!alive || (e instanceof DOMException && e.name === 'AbortError')) return;
        const m = e instanceof Error ? e.message : 'Unbekannter Fehler';
        setError(m);
        setPhase('error');
        onError?.(m);
      });
    return () => {
      alive = false;
      ctrl.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [valid, componentName, projectId, dataSourceUrl, scriptUrl, scriptTimeoutMs]);

  // Daten zusätzlich als Eigenschaft setzen (Web Components lesen oft element.data statt des Attributs)
  useEffect(() => {
    if (elRef.current && payload) (elRef.current as unknown as { data?: unknown }).data = payload;
  }, [payload]);

  if (phase === 'error') {
    return <>{props.renderError ? props.renderError(error) : <div role="alert" className="text-sm text-bad">{error}</div>}</>;
  }
  if (phase !== 'ready' || !payload) {
    return <>{props.loading ?? <div aria-busy="true" className="text-sm text-muted">Lade …</div>}</>;
  }
  return createElement(componentName, {
    ref: elRef,
    'data-project': projectId,
    [`data-${dataAttribute.replace(/^data-/, '')}`]: JSON.stringify(payload),
    ...safeAttrs,
  });
}
