// Los kg MS/ha en la app de campo (P16): la fórmula la pone el panel, por
// tipo de pastura, y llega con cada potrero del catálogo. Corre con
// `npm run test:app` (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const contexto = vm.createContext({ Math, Number });
vm.runInContext(readFileSync(new URL('../../public/app/js/biomasa.js', import.meta.url), 'utf8'), contexto);
const Biomasa = contexto.Biomasa;

const catalogo = {
  campos: [
    { id: 1, nombre: 'La Esperanza', potreros: [
      { id: 3, nombre: 'Lote 4', formula_kg_ms_ha: { a: 25.5, b: -100 } },
      { id: 4, nombre: 'El Bajo', formula_kg_ms_ha: null },
    ] },
    { id: 2, nombre: 'San José', potreros: [{ id: 7, nombre: 'Norte' }] },
  ],
};

test('busca la fórmula del potrero en el catálogo', () => {
  assert.deepEqual(JSON.parse(JSON.stringify(Biomasa.formulaDe(catalogo, 3))), { a: 25.5, b: -100 });
  assert.equal(Biomasa.formulaDe(catalogo, 4), null);
  // Un catálogo bajado antes de P16 no trae la clave.
  assert.equal(Biomasa.formulaDe(catalogo, 7), null);
  assert.equal(Biomasa.formulaDe(catalogo, 99), null);
  assert.equal(Biomasa.formulaDe(null, 3), null);
});

test('kg MS/ha = max(0, a × altura + b)', () => {
  const formula = { a: 25.5, b: -100 };
  // 25,5 × 12 − 100 = 206
  assert.equal(Biomasa.kgMsHa(12, formula), 206);
  // 25,5 × 3 − 100 = −23,5 → 0
  assert.equal(Biomasa.kgMsHa(3, formula), 0);
});

test('sin fórmula no hay kg MS/ha', () => {
  assert.equal(Biomasa.kgMsHa(12, null), null);
  assert.equal(Biomasa.kgMsHa(12, { a: null, b: 3 }), null);
});
