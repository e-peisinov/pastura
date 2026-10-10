// La app instalada abre el panel adentro, sin pasar al navegador (P14): el
// alcance (`scope`) del manifest tiene que cubrir la app y el panel, que está
// una carpeta más arriba, en la raíz del dominio o en una subcarpeta.
// Corre con `npm run test:app`.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

const manifest = JSON.parse(readFileSync(new URL('../../public/app/manifest.webmanifest', import.meta.url), 'utf8'));

for (const raiz of ['https://pastura.example', 'https://192.168.1.76/pastura/public']) {
  test(`en ${raiz} el panel queda dentro de la app instalada`, () => {
    const direccionManifest = `${raiz}/app/manifest.webmanifest`;
    const alcance = new URL(manifest.scope, direccionManifest).href;
    const inicio = new URL(manifest.start_url, direccionManifest).href;

    assert.ok(inicio.startsWith(alcance), `la app (${inicio}) fuera del alcance ${alcance}`);
    for (const pantalla of ['/dashboard', '/recorridas', '/entrar-desde-app/pase']) {
      assert.ok(`${raiz}${pantalla}`.startsWith(alcance), `${pantalla} fuera del alcance ${alcance}`);
    }
  });
}

test('la app instalada sigue arrancando en la app, no en el panel', () => {
  assert.equal(manifest.start_url, './');
});
