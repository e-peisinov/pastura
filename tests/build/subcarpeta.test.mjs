// El CSS compilado del panel tiene que andar también en una subcarpeta (P13;
// en XAMPP, /pastura/public). Las fuentes de Font Awesome y las imágenes de
// Leaflet van con dirección relativa al CSS, nunca desde la raíz del dominio
// (`/build/...`), que en una subcarpeta da 404.
// Corre con `npm run test:build`: compila el CSS en una carpeta temporal.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, readdirSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { build } from 'vite';

test('el CSS del panel no pide archivos desde la raíz del dominio', async () => {
  const salida = mkdtempSync(path.join(tmpdir(), 'pastura-build-'));
  try {
    await build({
      configFile: new URL('../../vite.config.js', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1'),
      logLevel: 'silent',
      build: { outDir: salida, emptyOutDir: true },
    });

    const assets = path.join(salida, 'assets');
    const css = readdirSync(assets).filter(f => f.endsWith('.css'))
      .map(f => readFileSync(path.join(assets, f), 'utf8')).join('\n');
    const direcciones = [...css.matchAll(/url\(\s*["']?([^"')]+)/g)].map(m => m[1]).filter(u => !u.startsWith('data:'));

    assert.ok(direcciones.some(u => u.includes('fa-solid-900')), 'el CSS tendría que incluir la fuente de Font Awesome');
    assert.deepEqual(direcciones.filter(u => u.startsWith('/')), [], 'direcciones desde la raíz del dominio');
  } finally {
    rmSync(salida, { recursive: true, force: true });
  }
});
