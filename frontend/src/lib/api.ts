// frontend/src/lib/api.ts
// Zugriff auf cms/api.php. Im Verwaltungsbereich gibt es die globale Funktion window.cmsApi (Sitzung, Konflikt- und Fehlerbehandlung des CMS);
// außerhalb (Tests, eigenständige Nutzung) fällt der Client auf fetch mit dem gespeicherten Sitzungs-Token zurück.

declare global {
  interface Window {
    cmsApi?: (action: string, body?: unknown) => Promise<Record<string, unknown>>;
    cmsToast?: (message: string, isError?: boolean) => void;
  }
}

export class ApiError extends Error {
  constructor(message: string, public readonly status = 0) {
    super(message);
    this.name = 'ApiError';
  }
}

function token(): string {
  try {
    return sessionStorage.getItem('anmacha_session_token') || localStorage.getItem('anmacha_session_token') || '';
  } catch {
    return '';
  }
}

/** POST/GET an cms/api.php?action=… und die Antwort als Objekt zurückgeben (wirft ApiError mit verständlicher Meldung). */
export async function cmsApi<T = Record<string, unknown>>(action: string, body?: unknown): Promise<T> {
  if (typeof window.cmsApi === 'function') {
    try {
      return (await window.cmsApi(action, body)) as T;
    } catch (e) {
      throw new ApiError(e instanceof Error ? e.message : 'Anfrage fehlgeschlagen', (e as { httpStatus?: number }).httpStatus ?? 0);
    }
  }
  const res = await fetch(`/cms/api.php?action=${encodeURIComponent(action)}`, {
    method: body === undefined ? 'GET' : 'POST',
    headers: { 'X-AnMaCha-Token': token(), ...(body === undefined ? {} : { 'Content-Type': 'application/json' }) },
    body: body === undefined ? undefined : JSON.stringify(body),
    credentials: 'same-origin',
  });
  let data: Record<string, unknown> | null = null;
  try {
    data = (await res.json()) as Record<string, unknown>;
  } catch {
    data = null;
  }
  if (!res.ok || !data || data.status === 'error') {
    throw new ApiError(typeof data?.message === 'string' ? data.message : 'Ungültige Serverantwort', res.status);
  }
  return data as T;
}

export function toast(message: string, isError = false): void {
  window.cmsToast?.(message, isError);
}
