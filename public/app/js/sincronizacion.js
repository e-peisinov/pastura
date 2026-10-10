/*
 * Sincronización con el panel (P4, P11), sin tocar la pantalla ni IndexedDB:
 * arma el JSON de la API, interpreta las respuestas y procesa la cola de
 * recorridas pendientes. Se prueba en Node (tests/app/).
 *
 * Estado de sincronización de cada recorrida guardada en el celular:
 *   pendiente     falta subirla (o cambió desde la última subida)
 *   sincronizada  el panel tiene la misma versión (rev === revSubida)
 *   rechazada     el panel la rechazó (403/422); no se reintenta sola
 */
var Sincro = (() => {
  'use strict';

  const ESPERA_INICIAL_MS = 30000;
  const ESPERA_MAXIMA_MS = 30 * 60000;

  const finito = v => (typeof v === 'number' && Number.isFinite(v) ? v : null);
  const redondear = (v, decimales) => {
    const n = finito(v);
    if (n === null) return null;
    const f = 10 ** decimales;
    return Math.round(n * f) / f;
  };
  const acotar = (v, min, max) => (v === null ? null : Math.min(max, Math.max(min, v)));

  /** El cuerpo de `PUT /api/v1/recorridas/{uuid}`, con los nombres y decimales de las columnas. */
  function armarCuerpo(recorrida, mediciones) {
    return {
      potrero_id: recorrida.potreroId,
      iniciada_en: new Date(recorrida.iniciada).toISOString(),
      dispositivo: recorrida.dispositivo || null,
      observaciones: recorrida.observaciones || null,
      mediciones: [...mediciones].sort((a, b) => a.creado - b.creado).map(m => ({
        uuid: m.id,
        tomada_en: new Date(m.creado).toISOString(),
        // Con el disco en el suelo la calibración puede dar -0,3 cm: es 0.
        altura_cm: acotar(redondear(m.alturaCm, 1), 0, 999.9) ?? 0,
        distancia_cm: acotar(redondear(m.distanciaCm, 1), 0, 999.9),
        kg_ms_ha: m.kgMsHa === null || m.kgMsHa === undefined ? null : Math.max(0, Math.round(m.kgMsHa)),
        latitud: redondear(m.lat, 7),
        longitud: redondear(m.lon, 7),
        precision_m: redondear(m.precisionM, 1),
        inclinacion_grados: redondear(m.inclinacion, 1),
        imagenes: Math.max(0, Math.min(255, Math.round(m.imagenes || 0))),
        dispersion_cm: acotar(redondear(m.dispersionCm, 2), 0, 999.99),
        calibracion: m.calibracion || null,
      })),
    };
  }

  /** Qué hacer con una respuesta del servidor. `estado` 0 = no hubo respuesta (sin red). */
  function clasificar(estado, cuerpo) {
    if (estado >= 200 && estado < 300) return { estado: 'ok' };
    if (estado === 401) return { estado: 'sin_sesion' };
    if (estado === 0 || estado === 408 || estado === 429 || estado >= 500) return { estado: 'reintentar' };

    const errores = cuerpo && cuerpo.errors ? Object.values(cuerpo.errors).flat() : [];
    const mensaje = errores[0] || (cuerpo && cuerpo.message) || `El servidor respondió ${estado}.`;
    return { estado: 'rechazada', mensaje };
  }

  /** 30 s, 1 min, 2 min, 4 min… hasta 30 min. */
  function esperaReintento(intentos) {
    return Math.min(ESPERA_MAXIMA_MS, ESPERA_INICIAL_MS * 2 ** Math.max(0, intentos - 1));
  }

  /**
   * La cola: sube las recorridas pendientes del usuario, una por una.
   *
   *   almacen.recorridas()            todas las recorridas guardadas
   *   almacen.medicionesDe(uuid)      las mediciones de una recorrida
   *   almacen.guardarRecorrida(r)
   *   almacen.marcarSubidas(ids)      mediciones que el panel ya tiene
   *   api.subirRecorrida(uuid, cuerpo)  lanza un Error con `estado` y `cuerpo`
   */
  function crearCola({ almacen, api, ahora = () => Date.now() }) {
    let enCurso = null;

    async function fresca(uuid) {
      return (await almacen.recorridas()).find(r => r.uuid === uuid);
    }

    async function correr(usuarioId, forzar) {
      const resultado = { subidas: 0, rechazadas: 0, pendientes: 0, sinSesion: false };
      const cola = (await almacen.recorridas())
        .filter(r => r.usuarioId === usuarioId)
        .filter(r => (r.sync === 'pendiente' && (forzar || (r.proximo || 0) <= ahora()))
          || (r.sync === 'rechazada' && forzar))
        .sort((a, b) => a.iniciada - b.iniciada);

      for (const r of cola) {
        const mediciones = await almacen.medicionesDe(r.uuid);
        const revEnviada = r.rev;
        try {
          await api.subirRecorrida(r.uuid, armarCuerpo(r, mediciones));
          const actual = (await fresca(r.uuid)) || r;
          await almacen.guardarRecorrida({
            ...actual,
            revSubida: revEnviada,
            // Si se agregó un punto mientras viajaba el pedido, queda pendiente.
            sync: actual.rev === revEnviada ? 'sincronizada' : 'pendiente',
            intentos: 0,
            proximo: 0,
            error: null,
          });
          await almacen.marcarSubidas(mediciones.map(m => m.id));
          resultado.subidas++;
        } catch (e) {
          const c = clasificar(typeof e.estado === 'number' ? e.estado : 0, e.cuerpo);
          if (c.estado === 'sin_sesion') { resultado.sinSesion = true; break; }
          const actual = (await fresca(r.uuid)) || r;
          if (c.estado === 'rechazada') {
            await almacen.guardarRecorrida({ ...actual, sync: 'rechazada', error: c.mensaje, intentos: 0, proximo: 0 });
            resultado.rechazadas++;
          } else {
            const intentos = (actual.intentos || 0) + 1;
            await almacen.guardarRecorrida({ ...actual, sync: 'pendiente', intentos, proximo: ahora() + esperaReintento(intentos) });
            resultado.pendientes++;
          }
        }
      }
      return resultado;
    }

    return {
      /** Una sola corrida a la vez: si ya hay una, devuelve esa. */
      procesar(usuarioId, { forzar = false } = {}) {
        if (!enCurso) enCurso = correr(usuarioId, forzar).finally(() => { enCurso = null; });
        return enCurso;
      },
    };
  }

  /** "iPhone · Safari", "Android · Chrome": el nombre del token y de la recorrida. */
  function describirDispositivo(ua) {
    const texto = ua || '';
    const sistema = [[/iPhone/, 'iPhone'], [/iPad/, 'iPad'], [/Android/, 'Android'], [/Windows/, 'Windows'], [/Macintosh/, 'Mac'], [/Linux/, 'Linux']]
      .find(([re]) => re.test(texto));
    const navegador = [[/Edg(e|iOS|A)?\//, 'Edge'], [/SamsungBrowser/, 'Samsung Internet'], [/CriOS|Chrome\//, 'Chrome'], [/FxiOS|Firefox\//, 'Firefox'], [/Safari\//, 'Safari']]
      .find(([re]) => re.test(texto));
    const partes = [sistema && sistema[1], navegador && navegador[1]].filter(Boolean);
    return partes.length ? partes.join(' · ').slice(0, 150) : 'Celular';
  }

  /** Para pintar en innerHTML los nombres que vienen del panel. */
  function escapar(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  }

  return { armarCuerpo, clasificar, esperaReintento, crearCola, describirDispositivo, escapar };
})();
