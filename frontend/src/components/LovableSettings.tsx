// frontend/src/components/LovableSettings.tsx
// Verwaltung der Lovable-Anbindung (nur Administratoren): Beitrags-Provider (CORS, Datenquelle), Widget-Skripte, Widgets (Tabelle lovable_widgets) und das
// verknüpfte GitHub-Repository (Webhook, Token, Synchronisation in cms/frontend/lovable/). Geheimnisse (Token, Webhook-Geheimnis) werden nur geschrieben.

import { useCallback, useEffect, useState } from 'react';
import type { ReactElement, ReactNode } from 'react';
import { cmsApi, toast } from '../lib/api';
import type { LovableAdminState, LovableWidget } from '../lib/types';

const field = 'w-full rounded-lg border border-line bg-surface2 px-3 py-2 text-sm text-ink placeholder:text-muted focus:outline-none focus:ring-2 focus:ring-accent/60';
const btn = 'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold transition disabled:opacity-50';
const primary = `${btn} bg-accent text-black hover:brightness-110`;
const ghost = `${btn} border border-line bg-surface2 text-ink hover:bg-surface3`;

const lines = (s: string): string[] => s.split(/\r?\n/).map((x) => x.trim()).filter(Boolean);

function Card({ title, children, hint }: { title: string; children: ReactNode; hint?: string }): ReactElement {
  return (
    <section className="rounded-xl border border-line bg-surface p-4">
      <h4 className="text-sm font-bold">{title}</h4>
      {hint && <p className="mb-3 mt-1 text-xs text-muted">{hint}</p>}
      <div className="mt-2 space-y-3">{children}</div>
    </section>
  );
}

function Lbl({ children, text }: { children: ReactNode; text: string }): ReactElement {
  return (
    <label className="block text-xs font-semibold text-muted">
      {text}
      <div className="mt-1">{children}</div>
    </label>
  );
}

const emptyWidget = (): LovableWidget => ({ id: 0, project_id: '', component_name: '', label: '', enabled: true, config: { posts: { limit: 10, category: '', tag: '', search: '' }, attributes: {}, script_url: '' } });

export default function LovableSettings(): ReactElement {
  const [st, setSt] = useState<LovableAdminState | null>(null);
  const [msg, setMsg] = useState<{ text: string; bad: boolean } | null>(null);
  const [busy, setBusy] = useState('');
  // Formularzustand
  const [origins, setOrigins] = useState('');
  const [hosts, setHosts] = useState('');
  const [tpl, setTpl] = useState('');
  const [source, setSource] = useState<'auto' | 'file' | 'db'>('auto');
  const [repo, setRepo] = useState('');
  const [branch, setBranch] = useState('main');
  const [project, setProject] = useState('app');
  const [include, setInclude] = useState('');
  const [auto, setAuto] = useState(true);
  const [token, setToken] = useState('');
  const [secret, setSecret] = useState('');
  const [widget, setWidget] = useState<LovableWidget>(emptyWidget());
  const [attrText, setAttrText] = useState('');

  const apply = useCallback((s: LovableAdminState) => {
    setSt(s);
    setOrigins(s.settings.bridge.allowed_origins.join('\n'));
    setHosts(s.settings.bridge.script_hosts.join('\n'));
    setTpl(s.settings.bridge.script_url_template);
    setSource(s.settings.bridge.post_source);
    setRepo(s.settings.github.repo);
    setBranch(s.settings.github.branch);
    setProject(s.settings.github.project);
    setInclude(s.settings.github.include.join('\n'));
    setAuto(s.settings.github.auto_sync);
    setToken('');
  }, []);

  useEffect(() => {
    cmsApi<LovableAdminState>('lovable_get').then(apply).catch((e: Error) => setMsg({ text: e.message, bad: true }));
  }, [apply]);

  const run = async (key: string, fn: () => Promise<void>) => {
    setBusy(key);
    setMsg(null);
    try {
      await fn();
    } catch (e) {
      setMsg({ text: e instanceof Error ? e.message : 'Fehlgeschlagen', bad: true });
    } finally {
      setBusy('');
    }
  };

  const saveSettings = () =>
    run('save', async () => {
      const s = await cmsApi<LovableAdminState>('lovable_save', {
        settings: {
          bridge: { allowed_origins: lines(origins), script_hosts: lines(hosts), script_url_template: tpl.trim(), post_source: source },
          github: { repo: repo.trim(), branch: branch.trim() || 'main', project: project.trim() || 'app', include: lines(include), auto_sync: auto, token },
        },
      });
      apply(s);
      setMsg({ text: 'Gespeichert.', bad: false });
      toast('Lovable-Einstellungen gespeichert');
    });

  const rotate = () =>
    run('rotate', async () => {
      if (st?.settings.github.secret_set && !window.confirm('Ein neues Geheimnis macht das alte ungültig – es muss danach auch in GitHub (Webhook) geändert werden. Fortfahren?')) return;
      const r = await cmsApi<LovableAdminState & { secret: string }>('lovable_secret_rotate', {});
      apply(r);
      setSecret(r.secret);
    });

  const sync = (force: boolean) =>
    run('sync', async () => {
      const r = await cmsApi<LovableAdminState & { result: { status: string; added: number; updated: number; removed: number; skipped: number } }>('lovable_sync', { force });
      apply(r);
      setMsg({ text: r.result.status === 'unchanged' ? 'Bereits auf dem neuesten Stand.' : `Synchronisiert: ${r.result.added} neu, ${r.result.updated} aktualisiert, ${r.result.removed} entfernt, ${r.result.skipped} übersprungen.`, bad: false });
    });

  const mirror = () =>
    run('mirror', async () => {
      const r = await cmsApi<{ posts: number }>('core_posts_mirror', {});
      setMsg({ text: `${r.posts} Beiträge in die Kern-Datenbank gespiegelt.`, bad: false });
    });

  const editWidget = (w: LovableWidget) => {
    const attrs: Record<string, string> = Array.isArray(w.config.attributes) ? {} : w.config.attributes;   // leere PHP-Arrays kommen als [] an
    setWidget({ ...w, config: { ...w.config, attributes: attrs } });
    setAttrText(Object.entries(attrs).map(([k, v]) => `${k}=${v}`).join('\n'));
  };

  const saveWidget = () =>
    run('widget', async () => {
      const attributes: Record<string, string> = {};
      lines(attrText).forEach((l) => {
        const i = l.indexOf('=');
        if (i > 0) attributes[l.slice(0, i).trim()] = l.slice(i + 1).trim();
      });
      const r = await cmsApi<{ widgets: LovableWidget[] }>('lovable_widget_save', { widget: { ...widget, config: { ...widget.config, attributes } } });
      setSt((s) => (s ? { ...s, widgets: r.widgets } : s));
      setWidget(emptyWidget());
      setAttrText('');
      setMsg({ text: 'Widget gespeichert.', bad: false });
    });

  const delWidget = (w: LovableWidget) =>
    run('widget', async () => {
      if (!window.confirm(`Widget „${w.component_name}“ löschen?`)) return;
      const r = await cmsApi<{ widgets: LovableWidget[] }>('lovable_widget_delete', { id: w.id });
      setSt((s) => (s ? { ...s, widgets: r.widgets } : s));
    });

  if (!st) return <div className="text-sm text-muted">{msg?.text ?? 'Lade …'}</div>;
  const gh = st.settings.github;
  const sync_ = st.sync;

  return (
    <div className="space-y-4 text-ink">
      {msg && (
        <div role="status" className={`rounded-lg border px-3 py-2 text-sm ${msg.bad ? 'border-bad/40 bg-bad/10 text-bad' : 'border-good/40 bg-good/10 text-good'}`}>
          {msg.text}
        </div>
      )}

      <Card title="Beitrags-Provider" hint="Öffentlicher, nur lesender JSON-Endpunkt mit den veröffentlichten Beiträgen – die Datenquelle für deine Lovable-Komponenten.">
        <Lbl text="Adresse">
          <input className={field} readOnly value={st.provider_url} onFocus={(e) => e.currentTarget.select()} />
        </Lbl>
        <Lbl text="Erlaubte Herkunft (CORS) – eine Adresse je Zeile, z. B. https://mein-projekt.lovable.app">
          <textarea className={`${field} min-h-[72px]`} value={origins} onChange={(e) => setOrigins(e.target.value)} placeholder="https://…" />
        </Lbl>
        <Lbl text="Datenquelle der Beiträge">
          <select className={field} value={source} onChange={(e) => setSource(e.target.value as 'auto' | 'file' | 'db')}>
            <option value="auto">Automatisch (Datenbank, sobald gespiegelt, sonst Dateien)</option>
            <option value="file">Dateien (cms/data/news.json)</option>
            <option value="db">Kern-Datenbank (Tabelle posts)</option>
          </select>
        </Lbl>
        <button type="button" className={ghost} onClick={mirror} disabled={busy !== ''}>
          {busy === 'mirror' ? 'Spiegele …' : 'Beiträge jetzt in die Kern-Datenbank spiegeln'}
        </button>
      </Card>

      <Card title="Widget-Skripte" hint="Wenn dein Lovable-Projekt als Skript bereitsteht, lädt die Bridge es von hier erlaubten Hosts. Ohne Eintrag wird nie ein Fremdskript geladen. Die genaue Adresse bestimmt dein Projekt/Hosting – sie ist bewusst nicht fest vorgegeben.">
        <Lbl text="Erlaubte Skript-Hosts – einer je Zeile (z. B. cdn.example.com)">
          <textarea className={`${field} min-h-[56px]`} value={hosts} onChange={(e) => setHosts(e.target.value)} />
        </Lbl>
        <Lbl text="Adressvorlage mit {projectId} (https, Host muss oben erlaubt sein)">
          <input className={field} value={tpl} onChange={(e) => setTpl(e.target.value)} placeholder="https://cdn.example.com/{projectId}.js" />
        </Lbl>
      </Card>

      <Card title="GitHub-Synchronisation" hint="Lovable schreibt jede Veröffentlichung in ein GitHub-Repository. Der Webhook holt neue Dateien automatisch nach cms/frontend/lovable/<Ordner>/. Es werden nur Quelltext und Assets übernommen – nie PHP, nie versteckte Dateien – und nichts ausgeführt oder gebaut.">
        <div className="grid gap-3 sm:grid-cols-3">
          <Lbl text="Repository (Besitzer/Name)">
            <input className={field} value={repo} onChange={(e) => setRepo(e.target.value)} placeholder="mein-konto/mein-projekt" />
          </Lbl>
          <Lbl text="Branch">
            <input className={field} value={branch} onChange={(e) => setBranch(e.target.value)} />
          </Lbl>
          <Lbl text="Zielordner (unter cms/frontend/lovable/)">
            <input className={field} value={project} onChange={(e) => setProject(e.target.value)} />
          </Lbl>
        </div>
        <Lbl text={`GitHub-Token (nur Leserecht „Contents“; für private Repositories nötig) – ${gh.token_set ? 'gesetzt, leer lassen = behalten' : 'nicht gesetzt'}`}>
          <input className={field} type="password" autoComplete="new-password" value={token} onChange={(e) => setToken(e.target.value)} placeholder={gh.token_set ? '••••••••' : 'ghp_… / github_pat_…'} />
        </Lbl>
        <Lbl text="Zu übernehmende Pfade – einer je Zeile (Ordner mit / am Ende, sonst Datei oder Muster)">
          <textarea className={`${field} min-h-[72px] font-mono text-xs`} value={include} onChange={(e) => setInclude(e.target.value)} />
        </Lbl>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={auto} onChange={(e) => setAuto(e.target.checked)} /> Bei jedem Push automatisch synchronisieren (Webhook)
        </label>
        <div className="rounded-lg border border-line bg-surface2 p-3 text-xs">
          <div className="mb-2 font-semibold">Webhook in GitHub eintragen (Repository → Settings → Webhooks)</div>
          <div className="mb-1 text-muted">Payload URL</div>
          <input className={field} readOnly value={st.webhook_url} onFocus={(e) => e.currentTarget.select()} />
          <div className="mb-1 mt-2 text-muted">Content type: application/json · Ereignis: „Just the push event“ · Secret:</div>
          {secret ? (
            <div className="rounded border border-good/40 bg-good/10 p-2">
              <code className="break-all text-good">{secret}</code>
              <div className="mt-1 text-muted">Dieses Geheimnis wird nur jetzt angezeigt – bitte in GitHub einfügen.</div>
            </div>
          ) : (
            <div className="text-muted">{gh.secret_set ? 'Ein Geheimnis ist gespeichert (wird nie erneut angezeigt).' : 'Noch kein Geheimnis erzeugt.'}</div>
          )}
          <button type="button" className={`${ghost} mt-2`} onClick={rotate} disabled={busy !== ''}>
            {gh.secret_set ? 'Neues Geheimnis erzeugen' : 'Geheimnis erzeugen'}
          </button>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button type="button" className={primary} onClick={saveSettings} disabled={busy !== ''}>
            {busy === 'save' ? 'Speichere …' : 'Einstellungen speichern'}
          </button>
          <button type="button" className={ghost} onClick={() => sync(false)} disabled={busy !== '' || !gh.repo}>
            {busy === 'sync' ? 'Synchronisiere …' : 'Jetzt synchronisieren'}
          </button>
          <button type="button" className={ghost} onClick={() => sync(true)} disabled={busy !== '' || !gh.repo} title="Lädt den Stand auch bei unverändertem Commit neu">
            Erzwingen
          </button>
        </div>
        <div className="text-xs text-muted">
          {sync_.last_sha ? (
            <>
              Letzter Stand: <code>{sync_.last_sha.slice(0, 7)}</code> {sync_.last_message ? `„${sync_.last_message}“` : ''} · {sync_.last_sync} · {sync_.files.length} Dateien
              {sync_.last_result ? ` (+${sync_.last_result.added} ~${sync_.last_result.updated} −${sync_.last_result.removed}, ${sync_.last_result.skipped} übersprungen)` : ''}
            </>
          ) : (
            'Noch nicht synchronisiert.'
          )}
          {sync_.last_error && <div className="mt-1 text-bad">Letzter Fehler ({sync_.last_error.at}): {sync_.last_error.message}</div>}
        </div>
      </Card>

      <Card title="Widgets" hint="Je Widget: Projekt-ID, Name der Web Component (Kleinbuchstaben mit Bindestrich) und die Filter für die Beitragsdaten. Die Komponente ruft danach …?widget=<name> auf.">
        {st.widgets === null ? (
          <p className="text-sm text-muted">Die Widget-Tabelle ist nicht verfügbar (keine Datenbank/SQLite-Erweiterung).</p>
        ) : (
          <>
            {st.widgets.length > 0 && (
              <table className="w-full text-left text-sm">
                <thead className="text-xs text-muted">
                  <tr>
                    <th className="py-1">Komponente</th>
                    <th>Projekt</th>
                    <th>Filter</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {st.widgets.map((w) => (
                    <tr key={w.id} className={`border-t border-line ${w.enabled ? '' : 'opacity-50'}`}>
                      <td className="py-1 font-mono text-xs">&lt;{w.component_name}&gt;</td>
                      <td>{w.project_id}</td>
                      <td className="text-xs text-muted">{[`max. ${w.config.posts.limit}`, w.config.posts.category, w.config.posts.tag && `#${w.config.posts.tag}`].filter(Boolean).join(' · ')}</td>
                      <td className="text-right">
                        <button type="button" className="mr-2 text-xs font-semibold text-accent hover:underline" onClick={() => editWidget(w)}>
                          Bearbeiten
                        </button>
                        <button type="button" className="text-xs font-semibold text-bad hover:underline" onClick={() => delWidget(w)}>
                          Löschen
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <div className="grid gap-3 rounded-lg border border-line bg-surface2 p-3 sm:grid-cols-3">
              <Lbl text="Projekt-ID">
                <input className={field} value={widget.project_id} onChange={(e) => setWidget({ ...widget, project_id: e.target.value })} />
              </Lbl>
              <Lbl text="Komponente (z. B. news-grid)">
                <input className={field} value={widget.component_name} onChange={(e) => setWidget({ ...widget, component_name: e.target.value.toLowerCase() })} />
              </Lbl>
              <Lbl text="Bezeichnung">
                <input className={field} value={widget.label} onChange={(e) => setWidget({ ...widget, label: e.target.value })} />
              </Lbl>
              <Lbl text="Anzahl Beiträge (1–50)">
                <input className={field} type="number" min={1} max={50} value={widget.config.posts.limit} onChange={(e) => setWidget({ ...widget, config: { ...widget.config, posts: { ...widget.config.posts, limit: Number(e.target.value) } } })} />
              </Lbl>
              <Lbl text="Kategorie">
                <input className={field} value={widget.config.posts.category} onChange={(e) => setWidget({ ...widget, config: { ...widget.config, posts: { ...widget.config.posts, category: e.target.value } } })} />
              </Lbl>
              <Lbl text="Schlagwort">
                <input className={field} value={widget.config.posts.tag} onChange={(e) => setWidget({ ...widget, config: { ...widget.config, posts: { ...widget.config.posts, tag: e.target.value } } })} />
              </Lbl>
              <div className="sm:col-span-2">
                <Lbl text="Feste Attribute – eine Zeile je data-…=Wert / aria-…=Wert">
                  <textarea className={`${field} min-h-[56px] font-mono text-xs`} value={attrText} onChange={(e) => setAttrText(e.target.value)} placeholder="data-theme=dark" />
                </Lbl>
              </div>
              <Lbl text="Eigene Skript-Adresse (https, Host erlaubt)">
                <input className={field} value={widget.config.script_url} onChange={(e) => setWidget({ ...widget, config: { ...widget.config, script_url: e.target.value } })} />
              </Lbl>
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={widget.enabled} onChange={(e) => setWidget({ ...widget, enabled: e.target.checked })} /> aktiv
              </label>
              <div className="sm:col-span-3">
                <button type="button" className={primary} onClick={saveWidget} disabled={busy !== '' || !widget.project_id || !widget.component_name}>
                  Widget speichern
                </button>
                {widget.component_name && <span className="ml-3 text-xs text-muted">Daten: {st.provider_url}?widget={widget.component_name}</span>}
              </div>
            </div>
          </>
        )}
      </Card>
    </div>
  );
}
