// Los kg MS/ha en la app de campo (P23): cada potrero trae la recta de su
// curva de biomasa vigente por estado fenológico, y el recorredor elige el
// estado al empezar. Corre con `npm run test:app` (node --test).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const contexto = vm.createContext({ Math, Number });
vm.runInContext(readFileSync(new URL('../../public/app/js/biomasa.js', import.meta.url), 'utf8'), contexto);
const Biomasa = contexto.Biomasa;

const plano = v => JSON.parse(JSON.stringify(v));

const catalogo = {
  estados_fenologicos: [{ id: 1, nombre: 'Recién pastoreada' }, { id: 2, nombre: 'Rebrote' }, { id: 3, nombre: 'Pleno crecimiento' }],
  campos: [
    { id: 1, nombre: 'La Esperanza', potreros: [
      { id: 3, nombre: 'Lote 4', curvas: [{ estado_fenologico_id: 2, a: 25.5, b: -100 }, { estado_fenologico_id: 3, a: 40, b: 200 }] },
      { id: 4, nombre: 'El Bajo', curvas: [] },
    ] },
    // Un catálogo bajado antes de P23 no trae `curvas` ni los estados.
    { id: 2, nombre: 'San José', potreros: [{ id: 7, nombre: 'Norte', formula_kg_ms_ha: { a: 30, b: 0 } }] },
  ],
};

test('los estados vienen del catálogo, en su orden', () => {
  assert.deepEqual(plano(Biomasa.estadosDe(catalogo)).map(e => e.nombre), ['Recién pastoreada', 'Rebrote', 'Pleno crecimiento']);
  assert.deepEqual(plano(Biomasa.estadosDe({ campos: [] })), []);
  assert.deepEqual(plano(Biomasa.estadosDe(null)), []);
});

test('busca la curva del potrero para el estado elegido', () => {
  assert.deepEqual(plano(Biomasa.curvaDe(catalogo, 3, 2)), { estado_fenologico_id: 2, a: 25.5, b: -100 });
  assert.deepEqual(plano(Biomasa.curvaDe(catalogo, 3, 3)), { estado_fenologico_id: 3, a: 40, b: 200 });
  // Sin curva para ese estado, sin curvas, sin estado o de antes de P23: nada.
  assert.equal(Biomasa.curvaDe(catalogo, 3, 1), null);
  assert.equal(Biomasa.curvaDe(catalogo, 4, 2), null);
  assert.equal(Biomasa.curvaDe(catalogo, 3, null), null);
  assert.equal(Biomasa.curvaDe(catalogo, 7, 2), null);
  assert.equal(Biomasa.curvaDe(catalogo, 99, 2), null);
  assert.equal(Biomasa.curvaDe(null, 3, 2), null);
});

test('propone el último estado usado en ese potrero, si sigue en la lista', () => {
  const estados = Biomasa.estadosDe(catalogo);
  assert.equal(Biomasa.estadoPropuesto(estados, { 3: 3 }, 3), 3);
  // Otro potrero sin historia: el primero de la lista.
  assert.equal(Biomasa.estadoPropuesto(estados, { 3: 3 }, 4), 1);
  // El que usó se dio de baja: el primero.
  assert.equal(Biomasa.estadoPropuesto(estados, { 3: 9 }, 3), 1);
  assert.equal(Biomasa.estadoPropuesto(estados, null, 3), 1);
  assert.equal(Biomasa.estadoPropuesto([], { 3: 3 }, 3), null);
});

test('kg MS/ha = max(0, a × altura + b)', () => {
  const curva = { a: 25.5, b: -100 };
  // 25,5 × 12 − 100 = 206
  assert.equal(Biomasa.kgMsHa(12, curva), 206);
  // 25,5 × 3 − 100 = −23,5 → 0
  assert.equal(Biomasa.kgMsHa(3, curva), 0);
});

test('sin curva no hay kg MS/ha', () => {
  assert.equal(Biomasa.kgMsHa(12, null), null);
  assert.equal(Biomasa.kgMsHa(12, { a: null, b: 3 }), null);
});
