/*
 * Calibración y cálculo de altura.
 *
 * Un marcador a distancia Z de la cámara se ve con tamaño t = k / Z (k junta
 * la focal del celular y el tamaño real del marcador). Como la altura del
 * disco es h = H0 − Z (H0 = altura de la cámara sobre el suelo), queda
 *
 *     h = c + a / t      con  c = H0  y  a = −k
 *
 * que es lineal en 1/t: con dos o más alturas conocidas se ajusta por mínimos
 * cuadrados. La calibración absorbe la focal, el tamaño real de los marcadores
 * y la posición del soporte, así que no hace falta conocer ninguno de esos
 * datos con exactitud.
 *
 * Depende de Vision (vision-core.js).
 */
const Calibracion = (() => {
  // Resume los cuadros de una captura: mediana del tamaño y su dispersión.
  function resumirCaptura(cuadros, grad) {
    const tams = cuadros
      .map(mk => Vision.tamDelCuadro(mk, grad))
      .filter(Boolean)
      .map(r => r.tam);
    if (!tams.length) return null;
    const med = Vision.mediana(tams);
    const mad = Vision.mediana(tams.map(t => Math.abs(t - med)));
    return { tam: med, mad, validos: tams.length };
  }

  /**
   * @param puntos [{ id, alturaCm, cuadros: [[marcador]] }]
   * @return null si no alcanza, o { grad, a, c, r2, puntos: [{id, alturaCm, tam, estimada, error}] }
   */
  function ajustar(puntos) {
    const alturas = new Set(puntos.map(p => p.alturaCm));
    if (puntos.length < 2 || alturas.size < 2) return null;

    const grad = Vision.aprenderGradiente(puntos.flatMap(p => p.cuadros));
    const datos = puntos
      .map(p => ({ p, r: resumirCaptura(p.cuadros, grad) }))
      .filter(d => d.r);
    if (datos.length < 2) return null;

    let n = 0, su = 0, sh = 0, suu = 0, suh = 0;
    for (const { p, r } of datos) {
      const u = 1 / r.tam;
      n++; su += u; sh += p.alturaCm; suu += u * u; suh += u * p.alturaCm;
    }
    const den = n * suu - su * su;
    if (Math.abs(den) < 1e-12) return null;
    const a = (n * suh - su * sh) / den;
    const c = (sh - a * su) / n;

    const media = sh / n;
    let ssRes = 0, ssTot = 0;
    const detalle = datos.map(({ p, r }) => {
      const estimada = c + a / r.tam;
      ssRes += (p.alturaCm - estimada) ** 2;
      ssTot += (p.alturaCm - media) ** 2;
      return { id: p.id, alturaCm: p.alturaCm, tam: r.tam, estimada, error: estimada - p.alturaCm };
    });
    const r2 = ssTot > 0 ? 1 - ssRes / ssTot : 1;
    return { grad, a, c, r2, puntos: detalle, creado: Date.now() };
  }

  const altura = (cal, tam) => cal.c + cal.a / tam;
  // Distancia cámara–disco: Z = k / t = −a / t
  const distancia = (cal, tam) => -cal.a / tam;
  // Cuánto cambia la altura por cada píxel de tamaño, para pasar la dispersión a cm
  const cmPorPixel = (cal, tam) => Math.abs(cal.a) / (tam * tam);

  return { resumirCaptura, ajustar, altura, distancia, cmPorPixel };
})();

if (typeof module !== 'undefined' && module.exports) module.exports = Calibracion;
