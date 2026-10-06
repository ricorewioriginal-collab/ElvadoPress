// frontend/src/components/AdminHub.tsx – Menü „KI & Lovable“ im CMS: Reiter für den KI-Assistenten, die KI-Anbieter und die Lovable-Anbindung.

import { useState } from 'react';
import type { ReactElement } from 'react';
import AiContentAssistant from './AiContentAssistant';
import LovableSettings from './LovableSettings';

type Tab = 'assistant' | 'providers' | 'lovable';

const TABS: Array<[Tab, string]> = [
  ['assistant', 'KI-Assistent'],
  ['providers', 'KI-Anbieter'],
  ['lovable', 'Lovable & GitHub'],
];

export default function AdminHub(): ReactElement {
  const [tab, setTab] = useState<Tab>('assistant');
  return (
    <div className="ep-react">
      <div className="mb-4 flex flex-wrap gap-2" role="tablist">
        {TABS.map(([id, label]) => (
          <button key={id} type="button" role="tab" aria-selected={tab === id} onClick={() => setTab(id)} className={`rounded-lg px-4 py-2 text-sm font-semibold transition ${tab === id ? 'bg-accent text-black' : 'border border-line bg-surface2 text-ink hover:bg-surface3'}`}>
            {label}
          </button>
        ))}
      </div>
      {tab === 'assistant' && (
        <div className="space-y-2">
          <p className="text-sm text-muted">Texte, Übersetzungen und JSON-Strukturen erzeugen. Im Beitrags-Editor und im Homepage-Baukasten steht derselbe Assistent mit direkter Übernahme zur Verfügung.</p>
          <AiContentAssistant />
        </div>
      )}
      {tab === 'providers' && (
        <div className="space-y-3 text-sm">
          <p className="text-muted">Anbieter, API-Schlüssel, Modelle und Einsatzzwecke werden jetzt zentral in der <b>KI-Zentrale</b> verwaltet – dort gelten sie für den KI-Assistenten der Website, die Texthilfen, den Website-Generator und den KI-Entwickler.</p>
          <button type="button" className="rounded-lg bg-accent px-4 py-2 font-semibold" onClick={() => { const w = window as unknown as { cmsTab?: (id: string, el: Element | null) => void; AiCenter?: { open: () => void } }; w.cmsTab?.('aicenter', document.querySelector('[data-tab="aicenter"]')); w.AiCenter?.open(); }}>Zur KI-Zentrale</button>
        </div>
      )}
      {tab === 'lovable' && <LovableSettings />}
    </div>
  );
}
