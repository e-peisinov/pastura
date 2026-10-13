/*
 * Los kg MS/ha de un punto (P23). Cada potrero del catálogo trae la recta
 * `a × altura + b` de su curva de biomasa vigente por estado fenológico
 * (`curvas`), y el recorredor elige el estado al empezar la recorrida. Sin
 * curva para ese potrero y ese estado, no hay kilos. Lo que muestra la app
 * es lo que vio el recorredor; el panel vuelve a calcular con la curva
 * vigente.
 *
 * No toca la pantalla: se prueba en Node (tests/app/). `var` (no `const`)
 * para que el test lo vea al evaluar el archivo en un contexto aislado.
 */
var Biomasa = (() => {
  'use strict';

  const numero = v => (typeof v === 'number' && Number.isFinite(v) ? v : null);

  /** Los estados activos del panel, en su orden ([] en un catálogo de antes de P23). */
  function estadosDe(catalogo) {
    return (catalogo && catalogo.estados_fenologicos) || [];
  }

  function potreroDe(catalogo, potreroId) {
    for (const campo of (catalogo && catalogo.campos) || []) {
      const potrero = campo.potreros.find(p => p.id === potreroId);
      if (potrero) return potrero;
    }
    return null;
  }

  /** La recta del potrero para ese estado, o null. */
  function curvaDe(catalogo, potreroId, estadoId) {
    if (estadoId === null || estadoId === undefined) return null;
    const potrero = potreroDe(catalogo, potreroId);
    return (potrero && (potrero.curvas || []).find(c => c.estado_fenologico_id === estadoId)) || null;
  }

  /** El último estado usado en ese potrero (`ultimos`: { potreroId: estadoId }) si sigue en la lista; si no, el primero. */
  function estadoPropuesto(estados, ultimos, potreroId) {
    const ultimo = ultimos ? ultimos[potreroId] : undefined;
    if (estados.some(e => e.id === ultimo)) return ultimo;
    return estados.length ? estados[0].id : null;
  }

  /** max(0, a × altura + b); sin curva, null. */
  function kgMsHa(alturaCm, curva) {
    const a = curva ? numero(curva.a) : null;
    const b = curva ? numero(curva.b) : null;
    if (a === null || b === null) return null;
    return Math.max(0, a * alturaCm + b);
  }

  return { estadosDe, curvaDe, estadoPropuesto, kgMsHa };
})();
