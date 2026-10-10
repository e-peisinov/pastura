// Si la app quedó lista para abrir sin señal, y el aviso del inicio mientras
// no lo está. Corre con `npm run test:app` (node --test), con un service
// worker y una caché falsos.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const contexto = vm.createContext({ console, Promise });
vm.runInContext(readFileSync(new URL('../../public/app/js/sin-senal.js', import.meta.url), 'utf8'), contexto);
const SinSenal = contexto.SinSenal;

const PAGINA = 'https://192.168.1.76/pastura/public/app/';

// Un service worker falso: cambia de estado cuando el test lo pide.
function trabajador(estado) {
  const oyentes = [];
  return {
    state: estado,
    addEventListener: (evento, f) => { if (evento === 'statechange') oyentes.push(f); },
    pasarA(nuevo) { this.state = nuevo; oyentes.forEach(f => f()); },
  };
}

function entorno({ registro, guardadas = [PAGINA], falla = null } = {}) {
  return {
    sw: { register: async () => { if (falla) throw new Error(falla); return registro; } },
    cajas: { match: async url => (guardadas.includes(url) ? { ok: true } : undefined) },
    pagina: PAGINA,
  };
}

test('ya preparada: queda lista sin pasar por "preparando"', async () => {
  const esperas = [];
  const r = await SinSenal.preparar({ ...entorno({ registro: { active: trabajador('activated') } }), alEsperar: () => esperas.push(1) });
  assert.equal(r.estado, 'lista');
  assert.equal(esperas.length, 0);
  assert.equal(SinSenal.aviso(r), null);
});

test('la primera vez avisa que se está preparando y termina lista', async () => {
  const instalando = trabajador('installing');
  const esperas = [];
  const promesa = SinSenal.preparar({ ...entorno({ registro: { active: null, installing: instalando } }), alEsperar: () => esperas.push(1) });
  await Promise.resolve();
  instalando.pasarA('installed');
  instalando.pasarA('activated');
  const r = await promesa;
  assert.equal(esperas.length, 1);
  assert.equal(r.estado, 'lista');
  assert.match(SinSenal.aviso({ estado: 'preparando' }), /Dejala abierta con señal/);
});

test('si la preparación se corta, no está lista', async () => {
  const instalando = trabajador('installing');
  const promesa = SinSenal.preparar({ ...entorno({ registro: { active: null, installing: instalando } }), alEsperar: () => {} });
  await Promise.resolve();
  instalando.pasarA('redundant');
  const r = await promesa;
  assert.equal(r.estado, 'incompleta');
  assert.match(SinSenal.aviso(r), /todavía no está lista para usar sin señal/);
});

test('con el service worker activo pero sin la página guardada, no está lista', async () => {
  const r = await SinSenal.preparar({ ...entorno({ registro: { active: trabajador('activated') }, guardadas: [] }), alEsperar: () => {} });
  assert.equal(r.estado, 'incompleta');
});

test('si no se puede registrar, avisa con el motivo', async () => {
  const r = await SinSenal.preparar({ ...entorno({ falla: 'Sin espacio' }), alEsperar: () => {} });
  assert.equal(r.estado, 'error');
  assert.match(SinSenal.aviso(r), /No se pudo preparar la app para usar sin señal \(Sin espacio\)/);
});

test('un navegador sin service worker lo dice', async () => {
  const r = await SinSenal.preparar({ sw: undefined, cajas: undefined, pagina: PAGINA, alEsperar: () => {} });
  assert.equal(r.estado, 'sin-soporte');
  assert.match(SinSenal.aviso(r), /no permite abrir la app sin señal/);
});

test('el texto de Ajustes confirma cuando está lista', () => {
  assert.equal(SinSenal.textoAjustes({ estado: 'lista' }), '✓ Lista para usar sin señal.');
  assert.equal(SinSenal.textoAjustes({ estado: 'incompleta' }), SinSenal.aviso({ estado: 'incompleta' }));
});
