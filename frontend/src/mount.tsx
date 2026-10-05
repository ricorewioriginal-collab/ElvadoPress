// frontend/src/mount.tsx
// Einstiegspunkt des Bündels (cms/assets/react/elvado-react.js): stellt window.ElvadoReact bereit, damit die klassischen CMS-Seiten (PHP/Vanilla-JS)
// die React-Komponenten einhängen können – ohne Build-Schritt im CMS selbst.
//   ElvadoReact.mountAdminHub(el)                         Menü „KI & Lovable“
//   ElvadoReact.mountAiAssistant(el, { targets, getEditorText, onApplyLayout })   Assistent im Editor / Homepage-Baukasten
//   ElvadoReact.mountLovableBridge(el, props)             Lovable-Widget (auch auf der öffentlichen Website)
// Auf der öffentlichen Website hängen sich zusätzlich alle Elemente mit data-elvado-lovable='{"projectId":…}' automatisch ein.

import { createRoot } from 'react-dom/client';
import type { Root } from 'react-dom/client';
import AdminHub from './components/AdminHub';
import AiContentAssistant from './components/AiContentAssistant';
import type { AiContentAssistantProps } from './components/AiContentAssistant';
import LovableWidgetBridge from './components/LovableWidgetBridge';
import type { LovableWidgetBridgeProps } from './components/LovableWidgetBridge';
import './styles.css';

const roots = new WeakMap<Element, Root>();

function rootFor(el: Element): Root {
  let r = roots.get(el);
  if (!r) {
    r = createRoot(el);
    roots.set(el, r);
  }
  return r;
}

function unmount(el: Element): void {
  roots.get(el)?.unmount();
  roots.delete(el);
}

function mountAdminHub(el: Element): void {
  rootFor(el).render(<AdminHub />);
}

function mountAiAssistant(el: Element, props: AiContentAssistantProps = {}): void {
  rootFor(el).render(<AiContentAssistant {...props} />);
}

function mountLovableBridge(el: Element, props: LovableWidgetBridgeProps): void {
  rootFor(el).render(<LovableWidgetBridge {...props} />);
}

/** <div data-elvado-lovable='{"projectId":"…","componentName":"news-grid","dataSourceUrl":"/cms/api-lovable-provider.php?widget=news-grid","scriptUrl":"https://…"}'> */
function autoMount(): void {
  document.querySelectorAll<HTMLElement>('[data-elvado-lovable]').forEach((el) => {
    if (roots.has(el)) return;
    try {
      const p = JSON.parse(el.dataset.elvadoLovable ?? '{}') as Partial<LovableWidgetBridgeProps>;
      if (p.projectId && p.componentName && p.dataSourceUrl) mountLovableBridge(el, p as LovableWidgetBridgeProps);
    } catch {
      /* ungültige Konfiguration: Element bleibt leer */
    }
  });
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', autoMount);
else autoMount();

export { mountAdminHub, mountAiAssistant, mountLovableBridge, unmount, autoMount };
