// frontend/tailwind.config.js
// Die Komponenten laufen im Verwaltungsbereich des CMS, der bereits eigene Stile (Bootstrap + cms.css) mitbringt. Darum:
//  - kein Preflight (keine globalen Resets),
//  - alle Utilities nur unterhalb von ".ep-react" wirksam (important-Selektor), eigene Klassen kollidieren so nicht mit dem CMS.
// Farben kommen aus den CSS-Variablen des CMS (--surface, --text, --border, --accent …), damit das Aussehen zum Verwaltungsthema passt.
// Farbe aus einer CSS-Variablen, auch mit Deckkraft-Stufen (bg-good/20 → color-mix), damit Utilities wie ring-accent/60 funktionieren
const cv = (name, fallback) => ({ opacityValue }) => {
  const n = Number(opacityValue);   // ohne Stufe liefert Tailwind "var(--tw-bg-opacity)" (keine Zahl) bzw. undefined
  return opacityValue === undefined || Number.isNaN(n) || n === 1
    ? `var(${name}, ${fallback})`
    : `color-mix(in srgb, var(${name}, ${fallback}) ${Math.round(n * 100)}%, transparent)`;
};

/** @type {import('tailwindcss').Config} */
export default {
  content: ['./src/**/*.{ts,tsx}'],
  important: '.ep-react',
  corePlugins: { preflight: false },
  theme: {
    extend: {
      colors: {
        surface: cv('--surface', '#0e0e14'),
        surface2: cv('--surface2', '#151520'),
        surface3: cv('--surface3', '#1c1c28'),
        line: cv('--border', 'rgba(255,255,255,.12)'),
        ink: cv('--text', '#e8ecf5'),
        muted: cv('--muted', '#93a0b8'),
        accent: cv('--accent', '#2fb8ff'),
        good: cv('--good', '#34d399'),
        bad: cv('--bad', '#f87171'),
      },
    },
  },
  plugins: [],
};
