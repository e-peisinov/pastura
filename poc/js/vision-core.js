/*
 * Núcleo de visión del pasturómetro: detecta los marcadores ArUco del disco,
 * refina sus esquinas a subpíxel sobre la imagen completa y devuelve el
 * tamaño aparente de cada marcador (raíz del área, en píxeles).
 *
 * Corre en el Web Worker (importScripts) y en Node (tests) sin cambios.
 * Depende de js-aruco2 (AR, CV) cargado antes.
 */
(function (global) {
  'use strict';

  var AR = global.AR;

  // DICT_4X4_50 de OpenCV = los primeros 50 códigos de 4X4_1000.
  if (!AR.DICTIONARIES.ARUCO_4X4_50) {
    AR.DICTIONARIES.ARUCO_4X4_50 = {
      nBits: 16,
      tau: null,
      codeList: AR.DICTIONARIES.ARUCO_4X4_1000.codeList.slice(0, 50),
    };
  }

  var detectores = {};
  function detector(nombre) {
    if (!detectores[nombre]) detectores[nombre] = new AR.Detector({ dictionaryName: nombre });
    return detectores[nombre];
  }

  /* ---------- imagen ---------- */

  function aGris(rgba, w, h) {
    var g = new Float32Array(w * h);
    for (var i = 0, j = 0; i < g.length; i++, j += 4) {
      g[i] = 0.299 * rgba[j] + 0.587 * rgba[j + 1] + 0.114 * rgba[j + 2];
    }
    return g;
  }

  // Reduce RGBA por promedio de bloques (factor entero) para la detección gruesa.
  function reducir(rgba, w, h, f) {
    if (f <= 1) return { data: rgba, width: w, height: h };
    var w2 = Math.floor(w / f), h2 = Math.floor(h / f);
    var out = new Uint8ClampedArray(w2 * h2 * 4);
    var n = f * f;
    for (var y = 0; y < h2; y++) {
      for (var x = 0; x < w2; x++) {
        var r = 0, g = 0, b = 0;
        for (var dy = 0; dy < f; dy++) {
          var fila = ((y * f + dy) * w + x * f) * 4;
          for (var dx = 0; dx < f; dx++) {
            var k = fila + dx * 4;
            r += rgba[k]; g += rgba[k + 1]; b += rgba[k + 2];
          }
        }
        var o = (y * w2 + x) * 4;
        out[o] = r / n; out[o + 1] = g / n; out[o + 2] = b / n; out[o + 3] = 255;
      }
    }
    return { data: out, width: w2, height: h2 };
  }

  function bilineal(g, w, h, x, y) {
    if (x < 0 || y < 0 || x >= w - 1 || y >= h - 1) return NaN;
    var x0 = x | 0, y0 = y | 0, fx = x - x0, fy = y - y0, i = y0 * w + x0;
    return (g[i] * (1 - fx) + g[i + 1] * fx) * (1 - fy) + (g[i + w] * (1 - fx) + g[i + w + 1] * fx) * fy;
  }

  /* ---------- refinamiento subpíxel ---------- */

  // Recta por mínimos cuadrados totales: punto medio + dirección principal.
  function ajustarRecta(pts) {
    var n = pts.length, mx = 0, my = 0, sxx = 0, syy = 0, sxy = 0, i;
    for (i = 0; i < n; i++) { mx += pts[i].x; my += pts[i].y; }
    mx /= n; my /= n;
    for (i = 0; i < n; i++) {
      var dx = pts[i].x - mx, dy = pts[i].y - my;
      sxx += dx * dx; syy += dy * dy; sxy += dx * dy;
    }
    var ang = 0.5 * Math.atan2(2 * sxy, sxx - syy);
    return { x: mx, y: my, dx: Math.cos(ang), dy: Math.sin(ang) };
  }

  function intersectar(a, b) {
    var det = a.dx * b.dy - a.dy * b.dx;
    if (Math.abs(det) < 1e-9) return null;
    var t = ((b.x - a.x) * b.dy - (b.y - a.y) * b.dx) / det;
    return { x: a.x + t * a.dx, y: a.y + t * a.dy };
  }

  // Para cada lado del cuadrilátero busca el borde negro/blanco a lo largo de
  // la normal (máximo del gradiente con interpolación parabólica), ajusta una
  // recta y recalcula las esquinas como intersección de rectas vecinas.
  function refinarEsquinas(g, w, h, c) {
    var rectas = [], i, k;
    for (i = 0; i < 4; i++) {
      var p = c[i], q = c[(i + 1) % 4];
      var ex = q.x - p.x, ey = q.y - p.y, largo = Math.hypot(ex, ey);
      if (largo < 8) return null;
      var nx = -ey / largo, ny = ex / largo;
      var r = Math.max(2.5, Math.min(12, largo * 0.07));
      var paso = 0.25, nPasos = Math.round((2 * r) / paso);
      var puntos = [];
      var muestras = Math.max(8, Math.min(40, Math.round(largo / 3)));
      for (k = 0; k < muestras; k++) {
        var t = 0.15 + (0.7 * k) / (muestras - 1);
        var bx = p.x + ex * t, by = p.y + ey * t;
        var perfil = new Float32Array(nPasos + 1), ok = true;
        for (var s = 0; s <= nPasos; s++) {
          var d = -r + s * paso;
          var v = bilineal(g, w, h, bx + nx * d, by + ny * d);
          if (v !== v) { ok = false; break; }
          perfil[s] = v;
        }
        if (!ok) continue;
        var mejor = -1, mejorG = 0;
        for (s = 1; s < nPasos; s++) {
          var gr = Math.abs(perfil[s + 1] - perfil[s - 1]);
          if (gr > mejorG) { mejorG = gr; mejor = s; }
        }
        if (mejor < 2 || mejor > nPasos - 2 || mejorG < 8) continue;
        var g0 = Math.abs(perfil[mejor] - perfil[mejor - 2]);
        var g2 = Math.abs(perfil[mejor + 2] - perfil[mejor]);
        var den = g0 - 2 * mejorG + g2;
        var off = den !== 0 ? (0.5 * (g0 - g2)) / den : 0;
        if (off > 1 || off < -1) off = 0;
        var dd = -r + (mejor + off) * paso;
        puntos.push({ x: bx + nx * dd, y: by + ny * dd });
      }
      if (puntos.length < Math.max(5, muestras * 0.7)) return null;
      var recta = ajustarRecta(puntos), sq = 0;
      for (k = 0; k < puntos.length; k++) {
        var dist = (puntos[k].x - recta.x) * recta.dy - (puntos[k].y - recta.y) * recta.dx;
        sq += dist * dist;
      }
      if (Math.sqrt(sq / puntos.length) > Math.max(0.6, largo * 0.006)) return null;
      rectas.push(recta);
    }
    var res = [];
    for (i = 0; i < 4; i++) {
      var e = intersectar(rectas[(i + 3) % 4], rectas[i]);
      if (!e) return null;
      var tol = Math.max(3, Math.hypot(c[(i + 1) % 4].x - c[i].x, c[(i + 1) % 4].y - c[i].y) * 0.1);
      if (Math.hypot(e.x - c[i].x, e.y - c[i].y) > tol) return null;
      res.push(e);
    }
    return res;
  }

  function area(c) {
    var a = 0;
    for (var i = 0; i < 4; i++) {
      var p = c[i], q = c[(i + 1) % 4];
      a += p.x * q.y - q.x * p.y;
    }
    return Math.abs(a) / 2;
  }

  /* ---------- detección de un cuadro ---------- */

  /**
   * @param rgba  Uint8ClampedArray del cuadro completo
   * @param opts  { diccionario, ids: [0,1,2,3], ladoDeteccion: 960 }
   * @return { marcadores: [{id, esquinas, tam, refinado}], tam, ms }
   */
  function analizarCuadro(rgba, w, h, opts) {
    var t0 = Date.now();
    opts = opts || {};
    var dic = opts.diccionario || 'ARUCO_4X4_50';
    var ids = opts.ids || [0, 1, 2, 3];
    var ladoDet = opts.ladoDeteccion || 960;
    var det = detector(dic);

    // Detección gruesa en dos escalas: una base y otra más chica, que ayuda
    // cuando el umbral adaptativo (ventana fija) no cierra bordes borrosos.
    var largo = Math.max(w, h);
    var factores = [Math.max(1, Math.round(largo / ladoDet)), Math.max(1, Math.round(largo / (ladoDet * 0.6)))];
    var porId = {};
    for (var fi = 0; fi < factores.length; fi++) {
      var f = factores[fi];
      if (fi > 0 && (f === factores[0] || Object.keys(porId).length >= ids.length)) break;
      var img = reducir(rgba, w, h, f);
      var encontrados = det.detect(img);
      for (var m = 0; m < encontrados.length; m++) {
        var mk = encontrados[m];
        if (ids.indexOf(mk.id) < 0 || porId[mk.id]) continue;
        porId[mk.id] = mk.corners.map(function (p) {
          // centro del píxel reducido → coordenadas de la imagen completa
          return { x: (p.x + 0.5) * f - 0.5, y: (p.y + 0.5) * f - 0.5 };
        });
      }
    }

    var gris = null, marcadores = [];
    Object.keys(porId).forEach(function (id) {
      gris = gris || aGris(rgba, w, h);
      var gruesas = porId[id];
      var finas = refinarEsquinas(gris, w, h, gruesas);
      var esq = finas || gruesas;
      var cx = 0, cy = 0;
      esq.forEach(function (p) { cx += p.x / 4; cy += p.y / 4; });
      marcadores.push({
        id: Number(id), esquinas: esq, refinado: !!finas,
        tam: Math.sqrt(area(esq)),
        // centro normalizado por el ancho, con origen en el centro de la imagen
        qx: (cx - w / 2) / w, qy: (cy - h / 2) / w,
      });
    });
    marcadores.sort(function (a, b) { return a.id - b.id; });

    return { marcadores: marcadores, ancho: w, alto: h, ms: Date.now() - t0 };
  }

  /* ---------- combinación y gradiente ---------- */

  function mediana(v) {
    var o = v.slice().sort(function (a, b) { return a - b; }), n = o.length;
    return n ? (n % 2 ? o[(n - 1) / 2] : (o[n / 2 - 1] + o[n / 2]) / 2) : null;
  }

  /**
   * Tamaño aparente del disco en un cuadro: promedio del tamaño de cada
   * marcador corregido por el gradiente de perspectiva (cámara no paralela al
   * disco), descartando marcadores sin refinar o que no coinciden con el resto
   * (típicamente tapados en parte por el caño).
   * @return { tam, usados: [ids] } o null
   */
  function tamDelCuadro(marcadores, grad, tolerancia) {
    grad = grad || { ax: 0, ay: 0 };
    tolerancia = tolerancia || 0.03;
    var lista = marcadores.filter(function (m) { return m.refinado; });
    if (!lista.length) return null;
    var corr = lista.map(function (m) {
      return { id: m.id, t: m.tam / (1 + grad.ax * m.qx + grad.ay * m.qy) };
    });
    var med = mediana(corr.map(function (c) { return c.t; }));
    if (corr.length >= 3) {
      corr = corr.filter(function (c) { return Math.abs(c.t / med - 1) <= tolerancia; });
    } else if (corr.length === 2 && Math.abs(corr[0].t / corr[1].t - 1) > tolerancia) {
      // con dos que no coinciden no hay forma de saber cuál está bien
      return null;
    }
    if (!corr.length) return null;
    var suma = corr.reduce(function (s, c) { return s + c.t; }, 0);
    return { tam: suma / corr.length, usados: corr.map(function (c) { return c.id; }) };
  }

  /**
   * Aprende el gradiente de tamaño a lo largo de la imagen (tam relativo vs
   * posición normalizada) a partir de cuadros con 2 o más marcadores.
   * @param cuadros array de arrays de marcadores
   */
  function aprenderGradiente(cuadros) {
    var a = 0, b = 0, c = 0, d = 0, e = 0, n = 0;
    cuadros.forEach(function (mks) {
      mks = mks.filter(function (m) { return m.refinado; });
      if (mks.length < 2) return;
      var mt = 0, mx = 0, my = 0;
      mks.forEach(function (m) { mt += m.tam / mks.length; mx += m.qx / mks.length; my += m.qy / mks.length; });
      mks.forEach(function (m) {
        var r = m.tam / mt - 1, u = m.qx - mx, v = m.qy - my;
        a += u * u; b += u * v; c += v * v; d += u * r; e += v * r; n++;
      });
    });
    var det = a * c - b * b;
    if (n < 6 || Math.abs(det) < 1e-9) return { ax: 0, ay: 0 };
    return { ax: (d * c - b * e) / det, ay: (a * e - b * d) / det };
  }

  var Vision = { analizarCuadro: analizarCuadro, refinarEsquinas: refinarEsquinas, tamDelCuadro: tamDelCuadro,
    aprenderGradiente: aprenderGradiente, mediana: mediana, aGris: aGris, area: area };
  global.Vision = Vision;
  if (typeof module !== 'undefined' && module.exports) module.exports = Vision;
})(typeof self !== 'undefined' ? self : globalThis);
