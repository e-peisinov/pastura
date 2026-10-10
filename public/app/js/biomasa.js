/*
 * Los kg MS/ha de un punto (P16). La fórmula `a × altura + b` la pone el
 * panel, por tipo de pastura, y llega con cada potrero del catálogo
 * (`formula_kg_ms_ha`). Lo que muestra la app es lo que vio el recorredor;
 * el panel vuelve a calcular con la fórmula vigente.
 *
 * No toca la pantalla: se prueba en Node (tests/app/). `var` (no `const`)
 * para que el test lo vea al evaluar el archivo en un contexto aislado.
 */
var Biomasa = (() => {
  'use strict';

  const numero = v => (typeof v === 'number' && Number.isFinite(v) ? v : null);

  /** La fórmula del potrero, o null si no tiene (o el catálogo es de antes de P16). */
  function formulaDe(catalogo, potreroId) {
    for (const campo of (catalogo && catalogo.campos) || []) {
      const potrero = campo.potreros.find(p => p.id === potreroId);
      if (potrero) return potrero.formula_kg_ms_ha || null;
    }
    return null;
  }

  /** max(0, a × altura + b); sin fórmula, null. */
  function kgMsHa(alturaCm, formula) {
    const a = formula ? numero(formula.a) : null;
    const b = formula ? numero(formula.b) : null;
    if (a === null || b === null) return null;
    return Math.max(0, a * alturaCm + b);
  }

  return { formulaDe, kgMsHa };
})();
