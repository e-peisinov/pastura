// La lógica de sincronización de la app de campo (P11), sin navegador.
// Corre con `npm run test:app` (node --test). El archivo se evalúa en un
// contexto aislado, igual que lo carga el navegador con <script>.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const contexto = vm.createContext({ console, Date, Math, JSON, Promise, setTimeout, clearTimeout });
vm.runInContext(readFileSync(new URL('../../public/app/js/sincronizacion.js', import.meta.url), 'utf8'), contexto);
const Sincro = contexto.Sincro;

// JSON.parse(JSON.stringify()) pasa los objetos del contexto aislado a este,
// para que deepEqual compare solo valores.
const plano = v => JSON.parse(JSON.stringify(v));

const T0 = Date.UTC(2026, 9, 10, 12, 12, 0); // 09:12 en Buenos Aires

function recorrida(extra = {}) {
  return {
    uuid: '6f1c2a7e-0b1d-4c3e-9f20-1a2b3c4d5e6f',
    usuarioId: 2,
    potreroId: 3,
    iniciada: T0,
    dispositivo: 'iPhone · Safari',
    rev: 1,
    revSubida: 0,
    sync: 'pendiente',
    intentos: 0,
    proximo: 0,
    ...extra,
  };
}

function medicion(id, extra = {}) {
  return {
    id,
    recorrida: '6f1c2a7e-0b1d-4c3e-9f20-1a2b3c4d5e6f',
    creado: T0 + 65000,
    alturaCm: 12.345,
    distanciaCm: 41.04,
    kgMsHa: 2150.6,
    lat: -34.65123456,
    lon: -59.43012344,
    precisionM: 4.04,
    inclinacion: 3.54,
    imagenes: 8,
    dispersionCm: 0.4249,
    calibracion: { a: 1234.5, c: -2.1, r2: 0.998, grad: null, creado: T0 },
    ...extra,
  };
}

// ----- armado del JSON -----------------------------------------------------

test('arma el JSON de la API con los nombres de las columnas y los decimales de cada una', () => {
  const cuerpo = plano(Sincro.armarCuerpo(recorrida(), [medicion('a1')]));

  assert.deepEqual(cuerpo, {
    potrero_id: 3,
    iniciada_en: '2026-10-10T12:12:00.000Z',
    dispositivo: 'iPhone · Safari',
    observaciones: null,
    mediciones: [{
      uuid: 'a1',
      tomada_en: '2026-10-10T12:13:05.000Z',
      altura_cm: 12.3,
      distancia_cm: 41,
      kg_ms_ha: 2151,
      latitud: -34.6512346,
      longitud: -59.4301234,
      precision_m: 4,
      inclinacion_grados: 3.5,
      imagenes: 8,
      dispersion_cm: 0.42,
      calibracion: { a: 1234.5, c: -2.1, r2: 0.998, grad: null, creado: T0 },
    }],
  });
});

test('una medición sin GPS, sin biomasa ni nivel manda null', () => {
  const [m] = Sincro.armarCuerpo(recorrida(), [medicion('a1', {
    lat: null, lon: null, precisionM: null, kgMsHa: null, inclinacion: null,
  })]).mediciones;

  assert.equal(m.latitud, null);
  assert.equal(m.longitud, null);
  assert.equal(m.precision_m, null);
  assert.equal(m.kg_ms_ha, null);
  assert.equal(m.inclinacion_grados, null);
});

test('una altura apenas negativa (disco en el suelo) se manda como 0, no rechaza la recorrida', () => {
  const [m] = Sincro.armarCuerpo(recorrida(), [medicion('a1', { alturaCm: -0.34, distanciaCm: Number.NaN })]).mediciones;

  assert.equal(m.altura_cm, 0);
  assert.equal(m.distancia_cm, null);
});

test('ordena las mediciones por hora', () => {
  const cuerpo = Sincro.armarCuerpo(recorrida(), [medicion('b', { creado: T0 + 2000 }), medicion('a', { creado: T0 + 1000 })]);

  assert.deepEqual(plano(cuerpo.mediciones.map(m => m.uuid)), ['a', 'b']);
});

// ----- respuestas ----------------------------------------------------------

test('clasifica cada respuesta del servidor', () => {
  assert.equal(Sincro.clasificar(201).estado, 'ok');
  assert.equal(Sincro.clasificar(200).estado, 'ok');
  assert.equal(Sincro.clasificar(401).estado, 'sin_sesion');
  assert.equal(Sincro.clasificar(429).estado, 'reintentar');
  assert.equal(Sincro.clasificar(500).estado, 'reintentar');
  assert.equal(Sincro.clasificar(503).estado, 'reintentar');
  assert.equal(Sincro.clasificar(0).estado, 'reintentar'); // sin red

  const prohibido = Sincro.clasificar(403, { message: 'Esta recorrida pertenece a otro usuario.' });
  assert.equal(prohibido.estado, 'rechazada');
  assert.equal(prohibido.mensaje, 'Esta recorrida pertenece a otro usuario.');

  // En un 422 se muestra el primer error de dato, que es más claro que el resumen.
  const invalido = Sincro.clasificar(422, {
    message: 'El potrero no existe… (and 1 more error)',
    errors: { potrero_id: ['El potrero no existe o está dado de baja (o lo está su campo).'] },
  });
  assert.equal(invalido.estado, 'rechazada');
  assert.equal(invalido.mensaje, 'El potrero no existe o está dado de baja (o lo está su campo).');
});

test('la espera entre reintentos crece y tiene techo', () => {
  assert.equal(Sincro.esperaReintento(1), 30000);
  assert.equal(Sincro.esperaReintento(2), 60000);
  assert.equal(Sincro.esperaReintento(3), 120000);
  assert.equal(Sincro.esperaReintento(20), 30 * 60000);
});

// ----- cola ----------------------------------------------------------------

/** Un almacén en memoria con la misma interfaz que usa la app sobre IndexedDB. */
function almacen(recorridas, mediciones) {
  const r = new Map(recorridas.map(x => [x.uuid, { ...x }]));
  const m = new Map(mediciones.map(x => [x.id, { ...x }]));
  return {
    r, m,
    recorridas: async () => [...r.values()].map(x => ({ ...x })),
    medicionesDe: async uuid => [...m.values()].filter(x => x.recorrida === uuid).map(x => ({ ...x })),
    guardarRecorrida: async x => { r.set(x.uuid, { ...x }); },
    marcarSubidas: async ids => { ids.forEach(id => { if (m.has(id)) m.get(id).subida = true; }); },
  };
}

/** Una API falsa: responde con lo que diga `respuestas` y anota los pedidos. */
function api(...respuestas) {
  const pedidos = [];
  return {
    pedidos,
    subirRecorrida: async (uuid, cuerpo) => {
      pedidos.push({ uuid, cuerpo: plano(cuerpo) });
      const r = respuestas.length > 1 ? respuestas.shift() : respuestas[0];
      if (r instanceof Error) throw r;
      return r;
    },
  };
}

const sinRed = Object.assign(new Error('Failed to fetch'), { estado: 0 });
const http = (estado, cuerpo = {}) => Object.assign(new Error(`HTTP ${estado}`), { estado, cuerpo });

test('sube las recorridas pendientes del usuario y las marca subidas', async () => {
  const a = almacen([recorrida()], [medicion('a1'), medicion('a2')]);
  const servidor = api({ creada: true });
  const cola = Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 });

  const resultado = await cola.procesar(2);

  assert.deepEqual(plano(resultado), { subidas: 1, rechazadas: 0, pendientes: 0, sinSesion: false });
  assert.equal(servidor.pedidos.length, 1);
  assert.equal(servidor.pedidos[0].cuerpo.mediciones.length, 2);
  const r = a.r.get(recorrida().uuid);
  assert.equal(r.sync, 'sincronizada');
  assert.equal(r.revSubida, 1);
  assert.equal(r.intentos, 0);
  assert.ok(a.m.get('a1').subida && a.m.get('a2').subida);
});

test('no vuelve a subir lo que ya está sincronizado', async () => {
  const a = almacen([recorrida({ sync: 'sincronizada', revSubida: 1 })], [medicion('a1')]);
  const servidor = api({});

  await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2);

  assert.equal(servidor.pedidos.length, 0);
});

test('solo sube las recorridas del usuario que ingresó', async () => {
  const a = almacen([recorrida({ usuarioId: 7 })], [medicion('a1')]);
  const servidor = api({});

  const resultado = await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2);

  assert.equal(servidor.pedidos.length, 0);
  assert.equal(resultado.pendientes, 0);
  assert.equal(a.r.get(recorrida().uuid).sync, 'pendiente');
});

test('sin red deja la recorrida pendiente y programa el reintento', async () => {
  const a = almacen([recorrida()], [medicion('a1')]);
  const cola = Sincro.crearCola({ almacen: a, api: api(sinRed), ahora: () => T0 });

  const resultado = await cola.procesar(2);

  assert.equal(resultado.pendientes, 1);
  const r = a.r.get(recorrida().uuid);
  assert.equal(r.sync, 'pendiente');
  assert.equal(r.intentos, 1);
  assert.equal(r.proximo, T0 + 30000);
  assert.ok(!a.m.get('a1').subida);
});

test('respeta la espera: antes de tiempo no reintenta; después, sí, y sin duplicar', async () => {
  const a = almacen([recorrida()], [medicion('a1')]);
  let reloj = T0;
  const servidor = api(sinRed, { creada: true });
  const cola = Sincro.crearCola({ almacen: a, api: servidor, ahora: () => reloj });

  await cola.procesar(2);
  reloj = T0 + 10000;
  await cola.procesar(2);
  assert.equal(servidor.pedidos.length, 1);

  reloj = T0 + 30000;
  await cola.procesar(2);
  assert.equal(servidor.pedidos.length, 2);
  // El reintento manda el mismo uuid: el servidor hace upsert (P4).
  assert.equal(servidor.pedidos[0].uuid, servidor.pedidos[1].uuid);
  assert.equal(a.r.get(recorrida().uuid).sync, 'sincronizada');
});

test('forzar sube ya, sin esperar el reintento programado', async () => {
  const a = almacen([recorrida({ intentos: 3, proximo: T0 + 999999 })], [medicion('a1')]);
  const servidor = api({});

  await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2, { forzar: true });

  assert.equal(servidor.pedidos.length, 1);
});

test('un 401 corta la cola y avisa que falta ingresar, sin gastar reintentos', async () => {
  const otra = recorrida({ uuid: '00000000-0000-4000-8000-000000000002' });
  const a = almacen([recorrida(), otra], [medicion('a1')]);
  const servidor = api(http(401));

  const resultado = await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2);

  assert.equal(resultado.sinSesion, true);
  assert.equal(servidor.pedidos.length, 1);
  assert.equal(a.r.get(recorrida().uuid).intentos, 0);
  assert.equal(a.r.get(recorrida().uuid).sync, 'pendiente');
});

test('un 422 la marca rechazada con el motivo y no la reintenta sola', async () => {
  const a = almacen([recorrida()], [medicion('a1')]);
  const servidor = api(http(422, { errors: { potrero_id: ['El potrero no existe o está dado de baja (o lo está su campo).'] } }));
  const cola = Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 });

  const resultado = await cola.procesar(2);
  await cola.procesar(2);

  assert.equal(resultado.rechazadas, 1);
  assert.equal(servidor.pedidos.length, 1);
  const r = a.r.get(recorrida().uuid);
  assert.equal(r.sync, 'rechazada');
  assert.equal(r.error, 'El potrero no existe o está dado de baja (o lo está su campo).');
});

test('forzar reintenta también una rechazada', async () => {
  const a = almacen([recorrida({ sync: 'rechazada', error: 'algo' })], [medicion('a1')]);
  const servidor = api({});

  await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2, { forzar: true });

  const r = a.r.get(recorrida().uuid);
  assert.equal(r.sync, 'sincronizada');
  assert.equal(r.error, null);
});

// ----- cambiar el potrero de una rechazada ---------------------------------

const campoNuevo = { id: 7, nombre: 'La Esperanza' };
const potreroNuevo = { id: 12, nombre: 'Lote 4' };

test('cambiar el potrero deja la recorrida pendiente, con otra versión y sin el error', () => {
  const original = recorrida({ sync: 'rechazada', error: 'El potrero está dado de baja.', rev: 3, revSubida: 2, intentos: 2, proximo: T0 + 60000 });

  const r = plano(Sincro.cambiarPotrero(original, campoNuevo, potreroNuevo));

  assert.equal(r.campoId, 7);
  assert.equal(r.campoNombre, 'La Esperanza');
  assert.equal(r.potreroId, 12);
  assert.equal(r.potreroNombre, 'Lote 4');
  assert.equal(r.rev, 4);
  assert.equal(r.revSubida, 2);
  assert.equal(r.sync, 'pendiente');
  assert.equal(r.error, null);
  assert.equal(r.intentos, 0);
  assert.equal(r.proximo, 0);
  assert.equal(r.uuid, original.uuid, 'es la misma recorrida: el panel la actualiza, no crea otra');
  assert.equal(original.potreroId, 3, 'no modifica la recorrida que recibe');
});

test('después de cambiar el potrero la cola la sube sola, con el potrero nuevo', async () => {
  const rechazada = recorrida({ sync: 'rechazada', error: 'El potrero está dado de baja.' });
  const a = almacen([Sincro.cambiarPotrero(rechazada, campoNuevo, potreroNuevo)], [medicion('a1')]);
  const servidor = api({});

  const resultado = await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2);

  assert.equal(resultado.subidas, 1);
  assert.equal(servidor.pedidos[0].cuerpo.potrero_id, 12);
  assert.equal(a.r.get(rechazada.uuid).sync, 'sincronizada');
});

test('solo se puede cambiar el potrero de una recorrida rechazada', () => {
  assert.equal(Sincro.puedeCambiarPotrero(recorrida({ sync: 'rechazada' })), true);
  for (const sync of ['nueva', 'pendiente', 'sincronizada']) {
    assert.equal(Sincro.puedeCambiarPotrero(recorrida({ sync })), false, sync);
  }
});

test('un punto que se agrega mientras se sube deja la recorrida pendiente', async () => {
  const a = almacen([recorrida()], [medicion('a1')]);
  const servidor = {
    pedidos: [],
    subirRecorrida: async (uuid, cuerpo) => {
      servidor.pedidos.push(cuerpo);
      // Mientras viaja el pedido, la app guarda otro punto (sube `rev`).
      const r = a.r.get(uuid);
      a.r.set(uuid, { ...r, rev: r.rev + 1 });
      a.m.set('a2', medicion('a2'));
      return {};
    },
  };

  await Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 }).procesar(2);

  const r = a.r.get(recorrida().uuid);
  assert.equal(r.sync, 'pendiente');
  assert.equal(r.rev, 2);
  assert.equal(r.revSubida, 1);
  assert.ok(a.m.get('a1').subida);
  assert.ok(!a.m.get('a2').subida);
});

test('dos llamadas a la vez no suben dos veces', async () => {
  const a = almacen([recorrida()], [medicion('a1')]);
  const servidor = api({});
  const cola = Sincro.crearCola({ almacen: a, api: servidor, ahora: () => T0 });

  await Promise.all([cola.procesar(2), cola.procesar(2)]);

  assert.equal(servidor.pedidos.length, 1);
});

// ----- utilidades ----------------------------------------------------------

test('describe el celular a partir del user agent', () => {
  const iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
  const android = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';
  const windows = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36 Edg/129.0.0.0';

  assert.equal(Sincro.describirDispositivo(iphone), 'iPhone · Safari');
  assert.equal(Sincro.describirDispositivo(android), 'Android · Chrome');
  assert.equal(Sincro.describirDispositivo(windows), 'Windows · Edge');
  assert.equal(Sincro.describirDispositivo(''), 'Celular');
});

test('escapa el texto que viene del servidor antes de pintarlo', () => {
  assert.equal(Sincro.escapar('<b>"Lote" & 4\'</b>'), '&lt;b&gt;&quot;Lote&quot; &amp; 4&#39;&lt;/b&gt;');
  assert.equal(Sincro.escapar(null), '');
});
