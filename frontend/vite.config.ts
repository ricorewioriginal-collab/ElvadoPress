// frontend/vite.config.ts – baut ein einziges Skript + Stylesheet für das CMS (keine Module, kein Laufzeit-Build im CMS):
//   npm run build  →  ../cms/assets/react/elvado-react.js  und  elvado-react.css  (global: window.ElvadoReact)
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';

export default defineConfig({
  plugins: [react()],
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: resolve(__dirname, '../cms/assets/react'),
    emptyOutDir: true,
    sourcemap: false,
    cssCodeSplit: false,
    lib: {
      entry: resolve(__dirname, 'src/mount.tsx'),
      name: 'ElvadoReact',
      formats: ['iife'],
      fileName: () => 'elvado-react.js',
    },
    rollupOptions: {
      output: { assetFileNames: (a) => (a.name?.endsWith('.css') ? 'elvado-react.css' : '[name][extname]') },
    },
  },
});
