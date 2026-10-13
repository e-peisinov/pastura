/*
 * GeoGrass: app de campo.
 * Cámara → worker de visión → calibración → medición con GPS, todo offline.
 * Cada medición pertenece a una recorrida (campo y potrero del panel); la
 * cola sube las recorridas cuando hay señal (P9 a P11).
 */
(() => {
  'use strict';

  const $ = sel => document.querySelector(sel);
  const nf1 = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const nf0 = new Intl.NumberFormat('es-AR', { maximumFractionDigits: 0 });
  const nf4 = new Intl.NumberFormat('es-AR', { minimumFractionDigits: 4, maximumFractionDigits: 4 });

  const AJUSTES_DEFECTO = { canoCm: 120, cuadros: 10, resolucion: 1920 };
  const IDS = [0, 1, 2, 3];
  const GPS_VIEJO_MS = 60000;
  const CAPTURA_MAX_MS = 8000;
  // ?prueba: el bucle corre con temporizador aunque la pestaña esté oculta (pruebas automáticas)
  const PRUEBA = new URLSearchParams(location.search).has('prueba');
  const siguiente = fn => (PRUEBA ? setTimeout(fn, 40) : requestAnimationFrame(fn));

  const estado = {
    ajustes: { ...AJUSTES_DEFECTO },
    cal: null,
    puntos: [],
    mediciones: [],
    sesion: null,          // { token, usuario: { id, nombre, email, rol } } (P10)
    sesionVencida: false,  // el panel respondió 401: hay que volver a ingresar
    catalogo: null,        // { generado_en, campos: [{ id, nombre, potreros: [...] }] }
    recorridas: [],
    vista: null,           // uuid de la recorrida que muestra Registros, o 'anteriores'
    cambioPotrero: null,   // uuid de la rechazada a la que se le está cambiando el potrero
    sinSenal: null,        // si abre sin señal: SinSenal.preparar (preparando, lista, incompleta…)
    tab: 'inicio',
    fuente: null,          // 'camara' | 'foto' | null
    stream: null,
    foto: null,
    wakeLock: null,
    ocupado: false,
    ultimoRes: null,
    alturasVivo: [],
    captura: null,
    gps: null,
    gpsError: null,
    inclinacion: null,
  };

  const lienzo = $('#lienzo');
  const ctx = lienzo.getContext('2d', { willReadFrequently: true });
  const video = $('#video');

  /* ================= utilidades ================= */

  function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map(x => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
  }

  let toastTimer = null;
  function toast(texto, tipo = 'ok') {
    const t = $('#toast');
    t.textContent = texto;
    t.className = `toast toast-${tipo}`;
    t.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { t.hidden = true; }, 3500);
  }

  const numero = v => (v === '' || v === null || v === undefined || Number.isNaN(Number(v)) ? null : Number(v));

  // La fórmula la pone el panel por tipo de pastura y llega con el catálogo (P16).
  // La curva de biomasa del potrero para el estado de la recorrida (P23).
  const curvaDe = r => Biomasa.curvaDe(estado.catalogo, r.potreroId, r.estadoId ?? null);
  const kgMsHa = (alturaCm, r) => Biomasa.kgMsHa(alturaCm, curvaDe(r));

  // Botón de dos toques para acciones destructivas (sin diálogos del navegador).
  function dosToques(boton, textoConfirmar, accion) {
    boton.addEventListener('click', async () => {
      if (boton.dataset.confirmando) {
        delete boton.dataset.confirmando;
        await accion();
        return;
      }
      const original = boton.textContent;
      boton.dataset.confirmando = '1';
      boton.textContent = textoConfirmar;
      setTimeout(() => {
        if (boton.dataset.confirmando) { delete boton.dataset.confirmando; boton.textContent = original; }
      }, 3000);
    });
  }

  /* ================= worker de visión ================= */

  const worker = new Worker('js/vision-worker.js');
  let bufferLibre = null;

  worker.onmessage = (e) => {
    const { ok, res, buffer, error } = e.data;
    bufferLibre = buffer;
    estado.ocupado = false;
    if (!ok) { console.error('[vision]', error); return; }
    estado.ultimoRes = res;
    alRecibirCuadro(res);
  };

  function enviarCuadro() {
    const w = lienzo.width, h = lienzo.height;
    if (!w || !h) return;
    const img = ctx.getImageData(0, 0, w, h);
    let buffer = img.data.buffer;
    if (bufferLibre && bufferLibre.byteLength === buffer.byteLength) {
      new Uint8ClampedArray(bufferLibre).set(img.data);
      buffer = bufferLibre;
      bufferLibre = null;
    }
    estado.ocupado = true;
    worker.postMessage({ ancho: w, alto: h, buffer, opciones: { ids: IDS } }, [buffer]);
  }

  /* ================= bucle de cámara ================= */

  function tabConCamara() { return estado.tab === 'medir' || estado.tab === 'calibrar'; }

  function bucle() {
    siguiente(bucle);
    if (!estado.fuente || (document.hidden && !PRUEBA) || !tabConCamara()) return;

    const src = estado.fuente === 'camara' ? video : estado.foto;
    const w = estado.fuente === 'camara' ? video.videoWidth : src.width;
    const h = estado.fuente === 'camara' ? video.videoHeight : src.height;
    if (!w || !h) return;
    if (lienzo.width !== w || lienzo.height !== h) { lienzo.width = w; lienzo.height = h; }

    ctx.drawImage(src, 0, 0, w, h);
    if (!estado.ocupado) enviarCuadro();
    dibujarMarcadores(estado.ultimoRes);
  }

  function dibujarMarcadores(res) {
    if (!res || res.ancho !== lienzo.width) return;
    const g = Math.max(2, lienzo.width / 300);
    ctx.lineWidth = g;
    ctx.font = `bold ${g * 8}px system-ui, sans-serif`;
    for (const m of res.marcadores) {
      ctx.strokeStyle = m.refinado ? '#3ee07a' : '#ffb020';
      ctx.beginPath();
      m.esquinas.forEach((p, i) => (i ? ctx.lineTo(p.x, p.y) : ctx.moveTo(p.x, p.y)));
      ctx.closePath();
      ctx.stroke();
      const c = m.esquinas.reduce((s, p) => ({ x: s.x + p.x / 4, y: s.y + p.y / 4 }), { x: 0, y: 0 });
      ctx.fillStyle = ctx.strokeStyle;
      ctx.fillText(String(m.id), c.x - g * 2.5, c.y + g * 3);
    }
  }

  function alRecibirCuadro(res) {
    const n = res.marcadores.length;
    const chip = $('#hud-marcadores');
    chip.textContent = `${n}/4`;
    chip.classList.toggle('chip-ok', n >= 2);
    chip.classList.toggle('chip-mal', n === 0);

    // lectura en vivo
    const grad = estado.cal ? estado.cal.grad : null;
    const t = Vision.tamDelCuadro(res.marcadores, grad);
    if (t && estado.cal) {
      estado.alturasVivo.push(Calibracion.altura(estado.cal, t.tam));
      if (estado.alturasVivo.length > 5) estado.alturasVivo.shift();
      $('#hud-etiqueta').textContent = 'Altura del disco';
      $('#hud-valor').textContent = nf1.format(Vision.mediana(estado.alturasVivo));
      $('#hud-unidad').textContent = 'cm';
    } else if (t) {
      $('#hud-etiqueta').textContent = 'Sin calibrar · tamaño';
      $('#hud-valor').textContent = nf1.format(t.tam);
      $('#hud-unidad').textContent = 'px';
    } else {
      estado.alturasVivo = [];
      $('#hud-valor').textContent = '–';
    }

    // captura en curso
    const cap = estado.captura;
    if (cap) {
      const utiles = res.marcadores
        .filter(m => m.refinado)
        .map(({ id, tam, qx, qy, refinado }) => ({ id, tam, qx, qy, refinado }));
      if (utiles.length) cap.cuadros.push(utiles);
      cap.progreso(Math.min(1, cap.cuadros.length / cap.objetivo));
      if (cap.cuadros.length >= cap.objetivo || Date.now() - cap.inicio > CAPTURA_MAX_MS) {
        estado.captura = null;
        cap.terminar(cap.cuadros);
      }
    }
  }

  // Junta N cuadros con marcadores legibles (o lo que haya en el tiempo máximo).
  function capturar(alProgresar) {
    return new Promise(resolver => {
      estado.captura = {
        cuadros: [],
        objetivo: estado.ajustes.cuadros,
        inicio: Date.now(),
        progreso: alProgresar,
        terminar: resolver,
      };
    });
  }

  /* ================= cámara y foto ================= */

  async function activarCamara() {
    const err = $('#error-camara');
    err.hidden = true;
    pedirPermisoOrientacion();
    if (!window.isSecureContext || !navigator.mediaDevices) {
      err.textContent = 'La cámara solo funciona si la app se abre con https (o desde localhost).';
      err.hidden = false;
      return;
    }
    const lado = estado.ajustes.resolucion;
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        audio: false,
        video: { facingMode: { ideal: 'environment' }, width: { ideal: lado }, height: { ideal: Math.round(lado * 9 / 16) } },
      });
      const pista = stream.getVideoTracks()[0];
      try { await pista.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }); } catch (_) { /* no soportado */ }
      video.srcObject = stream;
      await video.play();
      estado.stream = stream;
      estado.fuente = 'camara';
      mantenerPantalla(true);
      mostrarCamara(true);
    } catch (e) {
      err.textContent = e && e.name === 'NotAllowedError'
        ? 'No hay permiso para usar la cámara. Habilitalo en los ajustes del navegador.'
        : `No se pudo abrir la cámara (${e && e.name ? e.name : e}).`;
      err.hidden = false;
    }
  }

  function apagarCamara() {
    if (estado.stream) estado.stream.getTracks().forEach(t => t.stop());
    estado.stream = null;
    estado.fuente = null;
    estado.foto = null;
    estado.ultimoRes = null;
    estado.captura = null;
    video.srcObject = null;
    mantenerPantalla(false);
    mostrarCamara(false);
  }

  async function usarFoto(archivo) {
    if (!archivo) return;
    if (estado.stream) apagarCamara();
    try {
      estado.foto = await createImageBitmap(archivo);
    } catch (_) {
      toast('No se pudo abrir la imagen', 'mal');
      return;
    }
    estado.fuente = 'foto';
    estado.ultimoRes = null;
    mostrarCamara(true);
  }

  function mostrarCamara(activa) {
    $('#camara-apagada').hidden = activa;
    $('#hud').hidden = !activa;
    lienzo.classList.toggle('visible', activa);
    actualizarBotones();
  }

  async function mantenerPantalla(si) {
    try {
      if (si && 'wakeLock' in navigator) estado.wakeLock = await navigator.wakeLock.request('screen');
      else if (!si && estado.wakeLock) { await estado.wakeLock.release(); estado.wakeLock = null; }
    } catch (_) { /* opcional */ }
  }

  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && estado.fuente === 'camara') mantenerPantalla(true);
  });

  /* ================= GPS y nivel ================= */

  function iniciarGps() {
    if (!('geolocation' in navigator)) { estado.gpsError = 'Sin GPS'; pintarGps(); return; }
    navigator.geolocation.watchPosition(
      pos => {
        estado.gps = { lat: pos.coords.latitude, lon: pos.coords.longitude, precision: pos.coords.accuracy, ts: pos.timestamp };
        estado.gpsError = null;
        pintarGps();
      },
      e => { estado.gpsError = e.code === 1 ? 'GPS sin permiso' : 'Buscando GPS…'; pintarGps(); },
      { enableHighAccuracy: true, maximumAge: 0, timeout: 30000 }
    );
    setInterval(pintarGps, 5000);
  }

  function pintarGps() {
    const chip = $('#chip-gps');
    chip.classList.remove('chip-ok', 'chip-medio', 'chip-mal');
    const g = estado.gps;
    if (!g || Date.now() - g.ts > GPS_VIEJO_MS) {
      chip.textContent = estado.gpsError || 'Buscando GPS…';
      chip.classList.add('chip-mal');
      return;
    }
    chip.textContent = `GPS ±${nf0.format(g.precision)} m`;
    chip.classList.add(g.precision <= 10 ? 'chip-ok' : g.precision <= 30 ? 'chip-medio' : 'chip-mal');
  }

  function pedirPermisoOrientacion() {
    if (typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
      DeviceOrientationEvent.requestPermission().catch(() => {});
    }
  }

  window.addEventListener('deviceorientation', e => {
    if (e.beta === null || e.gamma === null) return;
    // Celular boca arriba con la cámara hacia el suelo: 0° = caño vertical.
    const b = e.beta * Math.PI / 180, g = e.gamma * Math.PI / 180;
    const ang = Math.acos(Math.max(-1, Math.min(1, Math.cos(b) * Math.cos(g)))) * 180 / Math.PI;
    estado.inclinacion = ang;
    const chip = $('#chip-nivel');
    chip.hidden = false;
    chip.textContent = `Nivel ${nf0.format(ang)}°`;
    chip.classList.toggle('chip-ok', ang <= 5);
    chip.classList.toggle('chip-medio', ang > 5 && ang <= 10);
    chip.classList.toggle('chip-mal', ang > 10);
  });

  /* ================= medir ================= */

  async function medir() {
    const recorrida = recorridaAbierta();
    if (!estado.cal || !estado.fuente || estado.captura || !recorrida) return;
    const boton = $('#btn-medir');
    boton.disabled = true;
    $('#btn-medir-texto').textContent = 'Midiendo…';
    const cuadros = await capturar(p => { $('#btn-medir-progreso').style.width = `${p * 100}%`; });
    $('#btn-medir-progreso').style.width = '0';
    $('#btn-medir-texto').textContent = 'Medir';
    actualizarBotones();

    const r = Calibracion.resumirCaptura(cuadros, estado.cal.grad);
    if (!r || r.validos < 3) {
      toast('No se pudo leer bien el disco. Revisá que se vean al menos 2 marcadores.', 'mal');
      return;
    }
    const alturaCm = Calibracion.altura(estado.cal, r.tam);
    const g = estado.gps && Date.now() - estado.gps.ts <= GPS_VIEJO_MS ? estado.gps : null;
    const m = {
      id: uuid(),
      recorrida: recorrida.uuid,
      potrero: recorrida.potreroNombre,
      creado: Date.now(),
      alturaCm,
      distanciaCm: Calibracion.distancia(estado.cal, r.tam),
      kgMsHa: kgMsHa(alturaCm, recorrida),
      tamPx: r.tam,
      dispersionCm: 1.4826 * r.mad * Calibracion.cmPorPixel(estado.cal, r.tam),
      imagenes: r.validos,
      marcadores: Math.round(cuadros.reduce((s, c) => s + c.length, 0) / cuadros.length * 10) / 10,
      lat: g ? g.lat : null,
      lon: g ? g.lon : null,
      precisionM: g ? g.precision : null,
      gpsEdadS: g ? Math.round((Date.now() - g.ts) / 1000) : null,
      inclinacion: estado.inclinacion,
      calibracion: { a: estado.cal.a, c: estado.cal.c, r2: estado.cal.r2, grad: estado.cal.grad, creado: estado.cal.creado },
      fuente: estado.fuente,
    };
    await DB.guardarMedicion(m);
    estado.mediciones.push(m);
    await marcarCambio(recorrida.uuid);
    if (navigator.storage && navigator.storage.persist) navigator.storage.persist().catch(() => {});
    pintarUltima(m);
    pintarRegistros();
    toast(g ? 'Punto guardado' : 'Punto guardado sin GPS', g ? 'ok' : 'medio');
    sincronizar();
  }

  // Borra un punto del celular. Uno que ya está en el panel no se borra acá:
  // el panel lo conserva (P11) y se descarta desde la ficha de la recorrida.
  async function borrarMedicion(id) {
    const m = estado.mediciones.find(x => x.id === id);
    if (!m) return false;
    if (m.subida) {
      toast('Este punto ya está en el panel: descartalo desde la ficha de la recorrida.', 'medio');
      return false;
    }
    await DB.borrarMedicion(id);
    estado.mediciones = estado.mediciones.filter(x => x.id !== id);
    if (m.recorrida) await marcarCambio(m.recorrida);
    return true;
  }

  function pintarUltima(m) {
    const caja = $('#ultima');
    caja.hidden = false;
    const kg = m.kgMsHa !== null ? `<div><span>kg MS/ha</span><strong>${nf0.format(m.kgMsHa)}</strong></div>` : '';
    const gps = m.lat !== null
      ? `${m.lat.toFixed(6)}, ${m.lon.toFixed(6)} (±${nf0.format(m.precisionM)} m)`
      : 'Sin GPS en el momento de medir';
    caja.innerHTML = `
      <div class="ultima-cabecera"><h2>Última medición</h2><span class="sub">${new Date(m.creado).toLocaleTimeString('es-AR')}</span></div>
      <div class="kpis">
        <div><span>Altura (Y)</span><strong>${nf1.format(m.alturaCm)} cm</strong></div>
        <div><span>Cámara–disco (Z)</span><strong>${nf1.format(m.distanciaCm)} cm</strong></div>
        ${kg}
      </div>
      <p class="sub">${gps}</p>
      <p class="sub">${m.imagenes} imágenes · dispersión ±${nf1.format(m.dispersionCm)} cm${m.inclinacion !== null ? ` · nivel ${nf0.format(m.inclinacion)}°` : ''}</p>
      <button class="btn btn-secundario btn-chico" id="btn-deshacer">Deshacer</button>`;
    $('#btn-deshacer').addEventListener('click', async () => {
      if (!(await borrarMedicion(m.id))) return;
      caja.hidden = true;
      pintarRegistros();
      toast('Medición descartada');
    });
  }

  /* ================= calibrar ================= */

  async function capturarPunto() {
    const altura = numero($('#input-altura').value);
    if (altura === null || altura < 0) { toast('Cargá la altura conocida en cm', 'mal'); return; }
    if (!estado.fuente || estado.captura) return;
    const boton = $('#btn-capturar');
    boton.disabled = true;
    $('#btn-capturar-texto').textContent = 'Capturando…';
    const cuadros = await capturar(p => { $('#btn-capturar-progreso').style.width = `${p * 100}%`; });
    $('#btn-capturar-progreso').style.width = '0';
    $('#btn-capturar-texto').textContent = 'Capturar';
    actualizarBotones();

    const r = Calibracion.resumirCaptura(cuadros, null);
    if (!r || r.validos < 3) { toast('No se pudo leer bien el disco. Probá de nuevo.', 'mal'); return; }
    const punto = { id: uuid(), alturaCm: altura, cuadros, creado: Date.now() };
    await DB.guardarPunto(punto);
    estado.puntos.push(punto);
    await recalibrar();
    $('#input-altura').value = String(altura + 10);
    toast(`Punto de ${nf1.format(altura)} cm capturado`);
  }

  async function recalibrar() {
    estado.cal = Calibracion.ajustar(estado.puntos);
    estado.alturasVivo = [];
    await DB.guardar('calibracion', estado.cal);
    pintarCalibracion();
    actualizarBotones();
  }

  function pintarCalibracion() {
    const cuerpo = $('#tabla-calib tbody');
    const porId = new Map((estado.cal ? estado.cal.puntos : []).map(p => [p.id, p]));
    const filas = [...estado.puntos].sort((a, b) => a.alturaCm - b.alturaCm);
    cuerpo.innerHTML = filas.map(p => {
      const d = porId.get(p.id);
      const err = d ? (Math.abs(d.error) < 0.05 ? 0 : d.error) : null;
      const clase = err === null ? '' : Math.abs(err) <= 0.5 ? 'ok' : Math.abs(err) <= 1.5 ? 'medio' : 'mal';
      return `<tr>
        <td>${nf1.format(p.alturaCm)} cm</td>
        <td>${d ? `${nf1.format(d.estimada)} cm` : '–'}</td>
        <td class="${clase}">${err === null ? '–' : `${err > 0 ? '+' : ''}${nf1.format(err)}`}</td>
        <td><button class="btn-x" data-borrar-punto="${p.id}" aria-label="Borrar punto">×</button></td>
      </tr>`;
    }).join('') || '<tr><td colspan="4" class="vacio">Todavía no hay puntos.</td></tr>';

    const res = $('#resumen-calib');
    $('#btn-borrar-calib').hidden = estado.puntos.length === 0;
    if (!estado.cal) {
      res.textContent = estado.puntos.length
        ? 'Falta al menos otro punto con una altura distinta.'
        : 'Capturá al menos 2 alturas distintas.';
      res.className = 'resumen';
      return;
    }
    const c = estado.cal;
    let texto = `Calibrado con ${c.puntos.length} puntos · R² ${nf4.format(c.r2)} · cámara a ${nf1.format(c.c)} cm del suelo.`;
    const cano = estado.ajustes.canoCm;
    if (cano && Math.abs(c.c - cano) > 15) {
      texto += ` Ojo: el caño está cargado en ${nf1.format(cano)} cm. Revisá las alturas o el ajuste.`;
    }
    res.textContent = texto;
    res.className = `resumen ${c.r2 >= 0.995 ? 'ok' : c.r2 >= 0.98 ? 'medio' : 'mal'}`;
  }

  /* ================= registros ================= */

  const esc = Sincro.escapar;
  const hora = ms => new Date(ms).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
  const fechaHora = ms => new Date(ms).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' });

  function medicionesDe(vista) {
    const lista = vista === 'anteriores'
      ? estado.mediciones.filter(m => !m.recorrida)
      : estado.mediciones.filter(m => m.recorrida === vista);
    return lista.sort((a, b) => b.creado - a.creado);
  }

  function recorridasOrdenadas() {
    return [...estado.recorridas].sort((a, b) => b.iniciada - a.iniciada);
  }

  // Lo que Registros muestra si no se eligió nada: la abierta, o la más reciente.
  function vistaVigente() {
    const existe = estado.vista === 'anteriores'
      ? estado.mediciones.some(m => !m.recorrida)
      : estado.recorridas.some(r => r.uuid === estado.vista);
    if (existe) return estado.vista;
    const abierta = recorridaAbierta();
    if (abierta) return abierta.uuid;
    const [ultima] = recorridasOrdenadas();
    if (ultima) return ultima.uuid;
    return estado.mediciones.some(m => !m.recorrida) ? 'anteriores' : null;
  }

  /** [texto, clase del chip, detalle] del estado de subida de una recorrida. */
  function estadoSync(r) {
    const usuario = estado.sesion && estado.sesion.usuario;
    if (!usuario || r.usuarioId !== usuario.id) return [`De ${r.usuarioNombre || 'otro usuario'}`, '', 'Se sube cuando ese usuario vuelva a ingresar.'];
    if (r.sync === 'nueva') return ['Sin puntos', '', ''];
    if (r.sync === 'sincronizada') return ['En el panel', 'chip-ok', ''];
    if (r.sync === 'rechazada') return ['Rechazada', 'chip-mal', r.error || ''];
    return ['Pendiente', 'chip-medio', r.intentos ? `Sin conexión con el panel. Próximo intento: ${hora(r.proximo)}` : ''];
  }

  function pintarRecorridas() {
    const vista = vistaVigente();
    const items = recorridasOrdenadas().map(r => {
      const [texto, clase, detalle] = estadoSync(r);
      const puntos = medicionesDe(r.uuid).length;
      return `
      <li data-ver="${esc(r.uuid)}" class="${r.uuid === vista ? 'elegida' : ''}">
        <div>
          <strong>${esc(r.potreroNombre)}</strong> · ${esc(r.campoNombre)}${r.terminada ? '' : ' · <em>en curso</em>'}
          <span class="sub">${fechaHora(r.iniciada)} · ${puntos} ${puntos === 1 ? 'punto' : 'puntos'}</span>
          ${detalle ? `<span class="${r.sync === 'rechazada' ? 'error-sync' : 'sub'}">${esc(detalle)}</span>` : ''}
        </div>
        <span class="chip ${clase}">${texto}</span>
      </li>`;
    });
    const anteriores = medicionesDe('anteriores').length;
    if (anteriores) {
      items.push(`
      <li data-ver="anteriores" class="${vista === 'anteriores' ? 'elegida' : ''}">
        <div>
          <strong>Anteriores a las recorridas</strong>
          <span class="sub">${anteriores} ${anteriores === 1 ? 'punto' : 'puntos'} · quedan solo en el celular</span>
        </div>
      </li>`);
    }
    $('#lista-recorridas').innerHTML = items.join('') || '<li class="vacio">Todavía no hay recorridas. Empezá una desde Medir.</li>';
    pintarSync();
  }

  function pintarSync() {
    const usuario = estado.sesion && estado.sesion.usuario;
    const mias = usuario ? estado.recorridas.filter(r => r.usuarioId === usuario.id) : [];
    const pendientes = mias.filter(r => r.sync === 'pendiente').length;
    const rechazadas = mias.filter(r => r.sync === 'rechazada').length;

    const chip = $('#chip-sync');
    chip.classList.remove('chip-medio', 'chip-mal');
    if (estado.sesionVencida || !usuario) {
      chip.textContent = 'Sin sesión';
      chip.classList.add('chip-mal');
      chip.hidden = !mias.length && !estado.sesionVencida;
    } else {
      chip.textContent = `↑ ${pendientes + rechazadas}`;
      chip.classList.add(rechazadas ? 'chip-mal' : 'chip-medio');
      chip.hidden = pendientes + rechazadas === 0;
    }
    $('#aviso-sesion').hidden = !estado.sesionVencida;

    let texto;
    if (estado.sesionVencida) texto = 'La sesión venció: ingresá de nuevo para subir.';
    else if (!navigator.onLine && pendientes) texto = `Sin señal: ${pendientes} ${pendientes === 1 ? 'recorrida espera' : 'recorridas esperan'} para subir.`;
    else if (rechazadas) texto = `${rechazadas} ${rechazadas === 1 ? 'recorrida rechazada' : 'recorridas rechazadas'} por el panel. Revisá el motivo: podés cambiarle el potrero o tocar Subir ahora para reintentar.`;
    else if (pendientes) texto = `${pendientes} ${pendientes === 1 ? 'recorrida pendiente' : 'recorridas pendientes'} de subir.`;
    else texto = mias.length ? 'Todo está en el panel.' : '';
    $('#estado-sync').textContent = texto;
    $('#estado-sync').hidden = !texto;
  }

  function pintarRegistros() {
    const abierta = recorridaAbierta();
    $('#potrero-actual').textContent = abierta ? `${abierta.potreroNombre} · ${abierta.campoNombre}` : 'Sin recorrida';
    pintarRecorridas();

    const vista = vistaVigente();
    const r = estado.recorridas.find(x => x.uuid === vista);
    $('#reg-titulo').textContent = r ? `${r.potreroNombre} · ${r.campoNombre}` : vista === 'anteriores' ? 'Anteriores a las recorridas' : 'Sin recorridas';
    $('#reg-estado').textContent = r ? `${fechaHora(r.iniciada)}${r.terminada ? ` a ${hora(r.terminada)}` : ' · en curso'}${r.estadoNombre ? ` · ${r.estadoNombre}` : ''}` : '';
    pintarCambioPotrero(r);
    $('#btn-csv-recorrida').disabled = !vista;

    const lista = vista ? medicionesDe(vista) : [];
    $('#kpi-puntos').textContent = String(lista.length);
    const media = lista.length ? lista.reduce((s, m) => s + m.alturaCm, 0) / lista.length : null;
    $('#kpi-altura').textContent = media === null ? '–' : `${nf1.format(media)} cm`;
    const conKg = lista.filter(m => m.kgMsHa !== null);
    $('#kpi-kg-caja').hidden = !conKg.length && !(r && curvaDe(r));
    $('#kpi-kg').textContent = conKg.length ? nf0.format(conKg.reduce((s, m) => s + m.kgMsHa, 0) / conKg.length) : '–';

    const ul = $('#lista-mediciones');
    if (!lista.length) {
      ul.innerHTML = '<li class="vacio">Sin mediciones en esta recorrida todavía.</li>';
      return;
    }
    ul.innerHTML = lista.map(m => `
      <li>
        <div>
          <strong>${nf1.format(m.alturaCm)} cm</strong>${m.kgMsHa !== null ? ` · ${nf0.format(m.kgMsHa)} kg MS/ha` : ''}
          <span class="sub">${new Date(m.creado).toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' })} · ${m.lat !== null ? `±${nf0.format(m.precisionM)} m` : 'sin GPS'} · ±${nf1.format(m.dispersionCm)} cm</span>
        </div>
        ${m.subida ? '<span class="chip chip-ok" title="Ya está en el panel">✓</span>' : `<button class="btn-x" data-borrar-medicion="${m.id}" aria-label="Borrar medición">×</button>`}
      </li>`).join('');
  }

  function csv(lista) {
    const d = v => (v === null || v === undefined ? '' : String(v).replace('.', ','));
    const cab = ['fecha', 'hora', 'potrero', 'altura_cm', 'distancia_cm', 'kg_ms_ha', 'latitud', 'longitud', 'precision_m', 'inclinacion_grados', 'imagenes', 'dispersion_cm', 'id'];
    const filas = lista.sort((a, b) => a.creado - b.creado).map(m => {
      const f = new Date(m.creado);
      return [
        f.toLocaleDateString('es-AR'), f.toLocaleTimeString('es-AR'), `"${m.potrero.replace(/"/g, '""')}"`,
        d(m.alturaCm.toFixed(1)), d(m.distanciaCm.toFixed(1)), d(m.kgMsHa === null ? null : Math.round(m.kgMsHa)),
        d(m.lat === null ? null : m.lat.toFixed(7)), d(m.lon === null ? null : m.lon.toFixed(7)),
        d(m.precisionM === null ? null : Math.round(m.precisionM)), d(m.inclinacion === null ? null : m.inclinacion.toFixed(1)),
        m.imagenes, d(m.dispersionCm.toFixed(2)), m.id,
      ].join(';');
    });
    return '﻿' + [cab.join(';'), ...filas].join('\r\n');
  }

  async function exportar(lista, nombre) {
    if (!lista.length) { toast('No hay mediciones para exportar', 'medio'); return; }
    const archivo = new File([csv(lista)], `${nombre}.csv`, { type: 'text/csv' });
    if (navigator.canShare && navigator.canShare({ files: [archivo] })) {
      try { await navigator.share({ files: [archivo], title: nombre }); return; } catch (e) { if (e.name === 'AbortError') return; }
    }
    const a = document.createElement('a');
    a.href = URL.createObjectURL(archivo);
    a.download = archivo.name;
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  /* ================= ajustes ================= */

  function pintarAjustes() {
    const a = estado.ajustes;
    $('#aj-cano').value = a.canoCm ?? '';
    $('#aj-cuadros').value = a.cuadros;
    $('#aj-resolucion').value = String(a.resolucion);
  }

  async function guardarAjustes() {
    const a = estado.ajustes;
    a.canoCm = numero($('#aj-cano').value);
    a.cuadros = Math.max(3, Math.min(30, numero($('#aj-cuadros').value) || AJUSTES_DEFECTO.cuadros));
    a.resolucion = Number($('#aj-resolucion').value);
    await DB.guardar('ajustes', a);
    pintarCalibracion();
    pintarRegistros();
  }

  /* ================= navegación y estado de botones ================= */

  function irA(tab) {
    estado.tab = tab;
    document.querySelectorAll('.tabs button').forEach(b => b.classList.toggle('activa', b.dataset.tab === tab));
    document.querySelectorAll('.tab').forEach(s => { s.hidden = s.id !== `tab-${tab}`; });
    $('#vista-camara').hidden = !tabConCamara();
    $('#medir-arriba').hidden = tab !== 'medir';
    if (tab === 'registros') pintarRegistros();
    if (tab === 'inicio') pintarInicio();
    if (tab === 'ayuda') pintarAyuda();
  }

  /* ================= inicio: medir o ir al panel (P12) ================= */

  function pintarInicio() {
    const u = estado.sesion && estado.sesion.usuario;
    $('#inicio-saludo').textContent = u ? `Hola, ${u.nombre.split(' ')[0]}` : 'GeoGrass';

    const abierta = recorridaAbierta();
    const recorrida = $('#btn-ir-recorrida');
    recorrida.disabled = !!u && !puedeRecorrer();
    if (recorrida.disabled) {
      $('#ir-recorrida-titulo').textContent = 'Hacer una recorrida';
      $('#ir-recorrida-detalle').textContent = 'Tu usuario no tiene permiso para hacer recorridas. Pedíselo a la administración.';
    } else if (abierta) {
      const puntos = medicionesDe(abierta.uuid).length;
      $('#ir-recorrida-titulo').textContent = 'Seguir la recorrida';
      $('#ir-recorrida-detalle').textContent = `${abierta.potreroNombre} · ${abierta.campoNombre} · ${puntos} ${puntos === 1 ? 'punto' : 'puntos'}`;
    } else {
      $('#ir-recorrida-titulo').textContent = 'Hacer una recorrida';
      $('#ir-recorrida-detalle').textContent = 'Medir la altura del pasto con la cámara, con o sin señal.';
    }

    $('#btn-ir-panel').disabled = !navigator.onLine;
    $('#ir-panel-detalle').textContent = navigator.onLine
      ? 'Campos, potreros, recorridas y usuarios. Para volver: menú del usuario → Abrir la app de campo.'
      : 'Necesitás conexión para entrar al panel.';
    pintarAyuda();
  }

  // La guía corta se lee sin señal; el instructivo completo está en el panel.
  function pintarAyuda() {
    $('#btn-instructivo').disabled = !navigator.onLine;
    $('#instructivo-detalle').textContent = navigator.onLine
      ? 'Curvas de biomasa, campos, reportes y todo lo del panel.'
      : 'Necesitás conexión para abrir el instructivo completo.';
  }

  function irARecorrida() {
    if (!estado.sesion) { mostrarIngreso(false); return; }
    irA('medir');
  }

  // El panel se abre con un enlace de un solo uso, sin volver a escribir la
  // contraseña, en la misma ventana: instalada, la app lo muestra adentro
  // porque el panel entra en el alcance del manifest (P14). Con destino
  // 'instrucciones' abre el instructivo en vez del tablero (P25).
  async function irAlPanel(destino = null) {
    if (!estado.sesion || estado.sesionVencida) { mostrarIngreso(false); return; }
    if (!navigator.onLine) { toast('Necesitás conexión para entrar al panel', 'mal'); return; }
    const boton = $(destino ? '#btn-instructivo' : '#btn-ir-panel');
    if (boton.dataset.abriendo) return;
    boton.dataset.abriendo = '1';
    try {
      const { url } = await Api.enlacePanel(destino);
      location.assign(url);
    } catch (e) {
      if (e.estado === 401) {
        await sesionVencida();
        mostrarIngreso(false);
      } else {
        toast(e.estado === 0 ? 'Sin conexión con el panel' : e.message, 'mal');
      }
    } finally {
      delete boton.dataset.abriendo;
    }
  }

  function actualizarBotones() {
    const hayFuente = !!estado.fuente;
    const capturando = !!estado.captura;
    const abierta = recorridaAbierta();
    $('#btn-medir').disabled = !hayFuente || !estado.cal || capturando || !abierta;
    $('#btn-capturar').disabled = !hayFuente || capturando;
    $('#aviso-calibrar').hidden = !!estado.cal;
    $('#nueva-recorrida').hidden = !!abierta || !estado.sesion || !puedeRecorrer();
    $('#aviso-permiso').hidden = !estado.sesion || puedeRecorrer();
    $('#recorrida-actual').hidden = !abierta;
    if (abierta) {
      const puntos = medicionesDe(abierta.uuid).length;
      $('#ra-potrero').textContent = `${abierta.potreroNombre} · ${abierta.campoNombre}`;
      $('#ra-detalle').textContent = [abierta.estadoNombre, `${puntos} ${puntos === 1 ? 'punto' : 'puntos'}`, `desde las ${hora(abierta.iniciada)}`].filter(Boolean).join(' · ');
    }
  }

  /* ================= sesión (P10) ================= */

  // Las sesiones guardadas antes de P12 no traen `puede_recorrer`: solo
  // podían ingresar los que tenían el permiso.
  function puedeRecorrer() {
    const u = estado.sesion && estado.sesion.usuario;
    return !!u && u.puede_recorrer !== false;
  }

  // El rol o los permisos pueden cambiar en el panel: se repasan al abrir la app.
  async function refrescarUsuario() {
    if (!estado.sesion || estado.sesionVencida || !navigator.onLine) return;
    try {
      const { usuario } = await Api.yo();
      estado.sesion = { ...estado.sesion, usuario };
      await DB.guardar('sesion', estado.sesion);
      actualizarBotones();
      pintarCuenta();
      pintarInicio();
      if (puedeRecorrer()) {
        await cargarCatalogo();
        sincronizar();
      }
    } catch (e) {
      if (e.estado === 401) await sesionVencida();
    }
  }

  function mostrarIngreso(obligatorio) {
    $('#pantalla-ingreso').hidden = false;
    $('#btn-cancelar-ingreso').hidden = obligatorio;
    $('#ingreso-error').hidden = true;
    const email = estado.sesion ? estado.sesion.usuario.email : '';
    if (email && !$('#ingreso-email').value) $('#ingreso-email').value = email;
    ($('#ingreso-email').value ? $('#ingreso-password') : $('#ingreso-email')).focus();
  }

  async function ingresar(e) {
    e.preventDefault();
    const email = $('#ingreso-email').value.trim();
    const password = $('#ingreso-password').value;
    const error = $('#ingreso-error');
    error.hidden = true;
    if (!email || !password) {
      error.textContent = 'Completá el email y la contraseña.';
      error.hidden = false;
      return;
    }
    const boton = $('#btn-ingresar');
    boton.disabled = true;
    boton.textContent = 'Ingresando…';
    try {
      const r = await Api.ingresar(email, password, Sincro.describirDispositivo(navigator.userAgent));
      estado.sesion = { token: r.token, usuario: r.usuario };
      estado.sesionVencida = false;
      await DB.guardar('sesion', estado.sesion);
      Api.usarToken(r.token);
      $('#ingreso-password').value = '';
      $('#pantalla-ingreso').hidden = true;
      toast(`Hola, ${r.usuario.nombre}`);
      pintarCuenta();
      actualizarBotones();
      pintarRegistros();
      irA('inicio');
      if (puedeRecorrer()) {
        await cargarCatalogo();
        sincronizar();
      }
    } catch (err) {
      error.textContent = err.estado === 0
        ? 'Sin conexión con el panel. Para ingresar hace falta señal (solo la primera vez).'
        : err.message;
      error.hidden = false;
    } finally {
      boton.disabled = false;
      boton.textContent = 'Ingresar';
    }
  }

  async function salir() {
    try { await Api.salir(); } catch (_) { /* sin señal: el token se revoca desde el panel */ }
    estado.sesion = null;
    estado.sesionVencida = false;
    Api.usarToken(null);
    await DB.borrar('sesion');
    pintarCuenta();
    actualizarBotones();
    pintarRegistros();
    irA('inicio');
    mostrarIngreso(true);
  }

  async function sesionVencida() {
    if (estado.sesionVencida) return;
    estado.sesionVencida = true;
    pintarSync();
    pintarInicio();
    toast('La sesión venció. Ingresá de nuevo para subir las recorridas.', 'medio');
  }

  function pintarCuenta() {
    const u = estado.sesion && estado.sesion.usuario;
    const pendientes = u ? estado.recorridas.filter(r => r.usuarioId === u.id && r.sync !== 'sincronizada' && r.sync !== 'nueva').length : 0;
    $('#cuenta-usuario').textContent = u
      ? `${u.nombre} (${u.email}).${pendientes ? ` Tiene ${pendientes} ${pendientes === 1 ? 'recorrida' : 'recorridas'} sin subir: si salís quedan en el celular y se suben cuando vuelvas a ingresar.` : ''}`
      : 'Sin ingresar.';
    $('#btn-salir').hidden = !u;
  }

  /* ================= campos y potreros ================= */

  async function cargarCatalogo({ avisar = false } = {}) {
    if (!estado.sesion || !puedeRecorrer()) return;
    try {
      estado.catalogo = await Api.campos();
      await DB.guardar('catalogo', estado.catalogo);
      pintarCatalogo();
      if (avisar) toast('Lista de campos actualizada');
    } catch (e) {
      if (e.estado === 401) await sesionVencida();
      else if (e.estado === 403) await refrescarUsuario();
      else if (avisar) toast(e.estado === 0 ? 'Sin conexión con el panel' : e.message, 'mal');
    }
  }

  async function pintarCatalogo() {
    const campos = estado.catalogo ? estado.catalogo.campos : [];
    const ayuda = $('#ayuda-catalogo');
    ayuda.hidden = campos.length > 0;
    ayuda.textContent = estado.catalogo
      ? 'No hay campos con potreros activos. Cargalos en el panel y tocá Actualizar campos.'
      : 'Todavía no se bajó la lista de campos: conectate y tocá Actualizar campos.';

    const ultimo = await DB.leer('ultimoPotrero', null);
    const campoDelUltimo = campos.find(c => c.potreros.some(p => p.id === ultimo));
    const selCampo = $('#sel-campo');
    const elegido = Number(selCampo.value) || (campoDelUltimo && campoDelUltimo.id);
    selCampo.innerHTML = campos.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    if (campos.some(c => c.id === elegido)) selCampo.value = String(elegido);
    pintarPotreros(ultimo);
    await pintarEstados();
    $('#btn-empezar').disabled = !campos.length;
  }

  /*
   * El estado de la pastura (P23): propone el último que se usó en ese
   * potrero y avisa cuál no tiene curva (se mide igual, sin kilos). Un
   * catálogo de antes, o un panel sin estados, no lo pide.
   */
  async function pintarEstados() {
    const estados = Biomasa.estadosDe(estado.catalogo);
    const potreroId = Number($('#sel-potrero').value);
    const sel = $('#sel-estado');
    $('#campo-estado').hidden = !estados.length;
    const elegido = estados.some(e => e.id === Number(sel.value)) && sel.dataset.potrero === String(potreroId)
      ? Number(sel.value)
      : Biomasa.estadoPropuesto(estados, await DB.leer('ultimosEstados', {}), potreroId);
    sel.innerHTML = estados.map(e => {
      const sinCurva = !Biomasa.curvaDe(estado.catalogo, potreroId, e.id);
      return `<option value="${e.id}">${esc(e.nombre)}${sinCurva ? ' (sin curva)' : ''}</option>`;
    }).join('');
    sel.dataset.potrero = String(potreroId);
    if (elegido !== null) sel.value = String(elegido);
    pintarAyudaEstado();
  }

  function pintarAyudaEstado() {
    const estadoId = Number($('#sel-estado').value) || null;
    $('#ayuda-estado').hidden = $('#campo-estado').hidden || estadoId === null
      || !!Biomasa.curvaDe(estado.catalogo, Number($('#sel-potrero').value), estadoId);
  }

  function estadoElegido() {
    if ($('#campo-estado').hidden) return null;
    return Biomasa.estadosDe(estado.catalogo).find(e => e.id === Number($('#sel-estado').value)) || null;
  }

  function campoElegido(selCampo = '#sel-campo') {
    return (estado.catalogo ? estado.catalogo.campos : []).find(c => c.id === Number($(selCampo).value));
  }

  function pintarPotreros(preferido, selCampo = '#sel-campo', selPotrero = '#sel-potrero') {
    const campo = campoElegido(selCampo);
    const potreros = campo ? campo.potreros : [];
    const sel = $(selPotrero);
    sel.innerHTML = potreros.map(p => {
      const extra = [p.tipo_pastura, p.superficie_ha !== null ? `${nf1.format(p.superficie_ha)} ha` : null].filter(Boolean).join(' · ');
      return `<option value="${p.id}">${esc(p.nombre)}${extra ? ` (${esc(extra)})` : ''}</option>`;
    }).join('');
    if (potreros.some(p => p.id === preferido)) sel.value = String(preferido);
  }

  /* ================= recorridas ================= */

  function recorridaAbierta() {
    const u = estado.sesion && estado.sesion.usuario;
    return u ? estado.recorridas.find(r => !r.terminada && r.usuarioId === u.id) || null : null;
  }

  async function empezarRecorrida() {
    const campo = campoElegido();
    const potrero = campo && campo.potreros.find(p => p.id === Number($('#sel-potrero').value));
    if (!potrero || !estado.sesion || recorridaAbierta()) { toast('Elegí el campo y el potrero', 'mal'); return; }
    const pastura = estadoElegido();
    if (!pastura && Biomasa.estadosDe(estado.catalogo).length) { toast('Elegí cómo está la pastura', 'mal'); return; }
    const u = estado.sesion.usuario;
    const r = {
      uuid: uuid(),
      usuarioId: u.id,
      usuarioNombre: u.nombre,
      campoId: campo.id,
      campoNombre: campo.nombre,
      potreroId: potrero.id,
      potreroNombre: potrero.nombre,
      estadoId: pastura ? pastura.id : null,
      estadoNombre: pastura ? pastura.nombre : null,
      iniciada: Date.now(),
      terminada: null,
      dispositivo: Sincro.describirDispositivo(navigator.userAgent),
      // 'nueva' no se sube: pasa a 'pendiente' con el primer punto.
      sync: 'nueva', rev: 0, revSubida: 0, intentos: 0, proximo: 0, error: null,
    };
    await DB.guardarRecorrida(r);
    await DB.guardar('ultimoPotrero', potrero.id);
    if (pastura) await DB.guardar('ultimosEstados', { ...(await DB.leer('ultimosEstados', {})), [potrero.id]: pastura.id });
    estado.recorridas.push(r);
    estado.vista = r.uuid;
    $('#ultima').hidden = true;
    actualizarBotones();
    pintarRegistros();
    toast(`Recorrida en ${potrero.nombre}`);
  }

  async function terminarRecorrida() {
    const abierta = recorridaAbierta();
    if (!abierta) return;
    if (!medicionesDe(abierta.uuid).length) {
      // Sin puntos no hay nada que guardar ni que subir.
      await DB.borrarRecorrida(abierta.uuid);
      estado.recorridas = estado.recorridas.filter(r => r.uuid !== abierta.uuid);
      toast('Recorrida sin puntos: no se guardó');
    } else {
      const r = { ...(await DB.recorrida(abierta.uuid)), terminada: Date.now() };
      await DB.guardarRecorrida(r);
      estado.recorridas = estado.recorridas.map(x => (x.uuid === r.uuid ? r : x));
      toast('Recorrida terminada');
    }
    $('#ultima').hidden = true;
    $('#btn-terminar').textContent = 'Terminar';
    actualizarBotones();
    pintarRegistros();
    pintarCatalogo();
  }

  // Un punto nuevo o borrado: la recorrida cambia de versión y vuelve a la cola.
  async function marcarCambio(uuidRecorrida) {
    const guardada = await DB.recorrida(uuidRecorrida);
    if (!guardada) return;
    const r = { ...guardada, rev: (guardada.rev || 0) + 1, sync: 'pendiente', intentos: 0, proximo: 0, error: null };
    await DB.guardarRecorrida(r);
    estado.recorridas = estado.recorridas.map(x => (x.uuid === r.uuid ? r : x));
    actualizarBotones();
  }

  /* ================= cambiar el potrero de una rechazada ================= */

  function puedoCambiarPotrero(r) {
    const u = estado.sesion && estado.sesion.usuario;
    return !!r && !!u && r.usuarioId === u.id && Sincro.puedeCambiarPotrero(r);
  }

  function pintarCambioPotrero(r) {
    const visible = puedoCambiarPotrero(r);
    $('#cambio-potrero').hidden = !visible;
    if (!visible) { estado.cambioPotrero = null; return; }
    $('#cp-motivo').textContent = r.error || 'El panel rechazó esta recorrida.';
    const abierto = estado.cambioPotrero === r.uuid;
    $('#btn-cambiar-potrero').hidden = abierto;
    $('#cp-formulario').hidden = !abierto;
  }

  function pintarSelectoresCambio(r) {
    const campos = estado.catalogo ? estado.catalogo.campos : [];
    const selCampo = $('#cp-campo');
    selCampo.innerHTML = campos.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join('');
    if (campos.some(c => c.id === r.campoId)) selCampo.value = String(r.campoId);
    pintarPotreros(r.potreroId, '#cp-campo', '#cp-potrero');
    $('#cp-ayuda').hidden = campos.length > 0;
    $('#cp-ayuda').textContent = 'No hay campos con potreros activos: conectate y tocá Actualizar campos en Medir.';
    $('#btn-cp-guardar').disabled = !campos.length;
  }

  async function abrirCambioPotrero() {
    const r = estado.recorridas.find(x => x.uuid === vistaVigente());
    if (!puedoCambiarPotrero(r)) return;
    estado.cambioPotrero = r.uuid;
    pintarSelectoresCambio(r);
    pintarCambioPotrero(r);
    // La lista guardada puede traer el potrero que se dio de baja: se baja de nuevo si hay señal.
    if (navigator.onLine) {
      await cargarCatalogo();
      if (estado.cambioPotrero === r.uuid) pintarSelectoresCambio(r);
    }
  }

  async function guardarCambioPotrero() {
    const campo = campoElegido('#cp-campo');
    const potrero = campo && campo.potreros.find(p => p.id === Number($('#cp-potrero').value));
    const guardada = estado.cambioPotrero && await DB.recorrida(estado.cambioPotrero);
    if (!potrero) { toast('Elegí el campo y el potrero', 'mal'); return; }
    if (!guardada || !puedoCambiarPotrero(guardada)) { estado.cambioPotrero = null; pintarRegistros(); return; }

    const r = Sincro.cambiarPotrero(guardada, campo, potrero);
    await DB.guardarRecorrida(r);
    estado.recorridas = estado.recorridas.map(x => (x.uuid === r.uuid ? r : x));
    estado.cambioPotrero = null;
    actualizarBotones();
    pintarRegistros();
    toast(`Recorrida pasada a ${potrero.nombre}`);
    sincronizar({ forzar: true });
  }

  /* ================= sincronización (P11) ================= */

  const cola = Sincro.crearCola({
    almacen: {
      recorridas: () => DB.recorridas(),
      medicionesDe: uuid => DB.medicionesDe(uuid),
      guardarRecorrida: r => DB.guardarRecorrida(r),
      marcarSubidas: ids => DB.marcarSubidas(ids),
    },
    api: Api,
  });

  async function sincronizar({ forzar = false } = {}) {
    if (!estado.sesion || estado.sesionVencida) {
      if (forzar) mostrarIngreso(false);
      return;
    }
    if (!puedeRecorrer()) { pintarSync(); return; }
    if (!navigator.onLine && !forzar) { pintarSync(); return; }
    try {
      const r = await cola.procesar(estado.sesion.usuario.id, { forzar });
      estado.recorridas = await DB.recorridas();
      estado.mediciones = await DB.mediciones();
      if (r.sinSesion) await sesionVencida();
      if (r.rechazadas) toast('El panel rechazó una recorrida. Mirá el motivo en Registros.', 'mal');
      else if (forzar && r.pendientes) toast('Sin conexión con el panel. Se reintenta solo.', 'medio');
      else if (forzar && r.subidas) toast(`${r.subidas === 1 ? 'Recorrida subida' : `${r.subidas} recorridas subidas`}`);
      else if (forzar && !r.sinSesion) toast('No hay nada para subir');
    } catch (e) {
      console.error('[sync]', e);
    }
    pintarRegistros();
    pintarCuenta();
  }

  /* ================= offline e instalación ================= */

  // El inicio avisa mientras la app no está lista para abrir sin señal; si no
  // lo está, se reintenta al volver la señal.
  function mostrarSinSenal(r) {
    estado.sinSenal = r.estado;
    const texto = SinSenal.aviso(r);
    $('#aviso-sin-senal').textContent = texto || '';
    $('#aviso-sin-senal').hidden = !texto;
    $('#estado-offline').textContent = SinSenal.textoAjustes(r);
  }

  async function prepararSinSenal() {
    if (estado.sinSenal === 'preparando') return;
    const r = await SinSenal.preparar({
      sw: navigator.serviceWorker,
      cajas: window.caches,
      pagina: new URL('./', location.href).href,
      alEsperar: () => mostrarSinSenal({ estado: 'preparando' }),
    });
    mostrarSinSenal(r);
  }

  function iniciarOffline() {
    prepararSinSenal();
    window.addEventListener('online', () => {
      if (estado.sinSenal !== 'lista' && estado.sinSenal !== 'sin-soporte') prepararSinSenal();
    });

    let eventoInstalar = null;
    window.addEventListener('beforeinstallprompt', e => {
      e.preventDefault();
      eventoInstalar = e;
      $('#btn-instalar').hidden = false;
    });
    $('#btn-instalar').addEventListener('click', async () => {
      if (!eventoInstalar) return;
      eventoInstalar.prompt();
      await eventoInstalar.userChoice;
      eventoInstalar = null;
      $('#btn-instalar').hidden = true;
    });
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    if (ios && !navigator.standalone) {
      const ayuda = $('#ayuda-instalar');
      ayuda.textContent = 'En iPhone: tocá Compartir y después "Agregar a inicio". Así los datos quedan guardados de forma permanente.';
      ayuda.hidden = false;
    }
  }

  /* ================= arranque ================= */

  async function iniciar() {
    estado.ajustes = { ...AJUSTES_DEFECTO, ...(await DB.leer('ajustes', {})) };
    estado.puntos = await DB.puntos();
    estado.mediciones = await DB.mediciones();
    estado.recorridas = await DB.recorridas();
    estado.sesion = await DB.leer('sesion', null);
    estado.catalogo = await DB.leer('catalogo', null);
    estado.cal = Calibracion.ajustar(estado.puntos);
    Api.usarToken(estado.sesion && estado.sesion.token);

    pintarAjustes();
    pintarCalibracion();
    pintarCatalogo();
    pintarCuenta();
    pintarRegistros();
    actualizarBotones();
    irA('inicio');
    if (!estado.sesion) mostrarIngreso(true);

    $('#btn-camara').addEventListener('click', activarCamara);
    $('#btn-apagar').addEventListener('click', apagarCamara);
    $('#input-foto').addEventListener('change', e => usarFoto(e.target.files[0]));
    $('#btn-medir').addEventListener('click', medir);
    $('#btn-capturar').addEventListener('click', capturarPunto);
    document.querySelectorAll('.tabs button').forEach(b => b.addEventListener('click', () => irA(b.dataset.tab)));
    document.querySelectorAll('[data-ir]').forEach(b => b.addEventListener('click', () => irA(b.dataset.ir)));

    $('#tabla-calib').addEventListener('click', async e => {
      const id = e.target.dataset.borrarPunto;
      if (!id) return;
      await DB.borrarPunto(id);
      estado.puntos = estado.puntos.filter(p => p.id !== id);
      await recalibrar();
    });
    dosToques($('#btn-borrar-calib'), '¿Seguro? Tocá de nuevo', async () => {
      await DB.borrarPuntos();
      estado.puntos = [];
      await recalibrar();
      $('#btn-borrar-calib').textContent = 'Borrar calibración';
      $('#input-altura').value = '0';
      toast('Calibración borrada');
    });

    $('#btn-ir-recorrida').addEventListener('click', irARecorrida);
    $('#btn-ir-panel').addEventListener('click', () => irAlPanel());
    $('#btn-ir-ayuda').addEventListener('click', () => irA('ayuda'));
    $('#btn-instructivo').addEventListener('click', () => irAlPanel('instrucciones'));
    $('#form-ingreso').addEventListener('submit', ingresar);
    $('#btn-cancelar-ingreso').addEventListener('click', () => { $('#pantalla-ingreso').hidden = true; });
    document.querySelectorAll('[data-ingresar]').forEach(b => b.addEventListener('click', () => mostrarIngreso(false)));
    dosToques($('#btn-salir'), '¿Seguro? Tocá de nuevo', async () => {
      $('#btn-salir').textContent = 'Salir';
      await salir();
    });

    $('#sel-campo').addEventListener('change', () => { pintarPotreros(null); pintarEstados(); });
    $('#sel-potrero').addEventListener('change', () => pintarEstados());
    $('#sel-estado').addEventListener('change', pintarAyudaEstado);
    $('#btn-empezar').addEventListener('click', empezarRecorrida);
    $('#btn-actualizar-campos').addEventListener('click', () => cargarCatalogo({ avisar: true }));
    dosToques($('#btn-terminar'), '¿Terminar?', terminarRecorrida);

    $('#btn-subir').addEventListener('click', () => sincronizar({ forzar: true }));
    $('#btn-cambiar-potrero').addEventListener('click', abrirCambioPotrero);
    $('#cp-campo').addEventListener('change', () => pintarPotreros(null, '#cp-campo', '#cp-potrero'));
    $('#btn-cp-guardar').addEventListener('click', guardarCambioPotrero);
    $('#btn-cp-cancelar').addEventListener('click', () => { estado.cambioPotrero = null; pintarRegistros(); });
    $('#chip-sync').addEventListener('click', () => (estado.sesionVencida ? mostrarIngreso(false) : irA('registros')));
    $('#lista-recorridas').addEventListener('click', e => {
      const li = e.target.closest('[data-ver]');
      if (!li) return;
      estado.vista = li.dataset.ver;
      pintarRegistros();
    });
    $('#lista-mediciones').addEventListener('click', async e => {
      const b = e.target.closest('[data-borrar-medicion]');
      if (!b) return;
      if (!b.dataset.confirmando) {
        b.dataset.confirmando = '1';
        b.textContent = '¿Borrar?';
        b.classList.add('btn-x-confirmar');
        setTimeout(() => { if (b.isConnected) { delete b.dataset.confirmando; b.textContent = '×'; b.classList.remove('btn-x-confirmar'); } }, 3000);
        return;
      }
      if (await borrarMedicion(b.dataset.borrarMedicion)) {
        actualizarBotones();
        pintarRegistros();
        sincronizar();
      }
    });
    $('#btn-csv-recorrida').addEventListener('click', () => {
      const vista = vistaVigente();
      const r = estado.recorridas.find(x => x.uuid === vista);
      const nombre = r ? `${r.potreroNombre}-${new Date(r.iniciada).toISOString().slice(0, 10)}` : 'anteriores';
      exportar(medicionesDe(vista), `mediciones-${nombre.replace(/[^\w-]+/g, '-')}`);
    });
    $('#btn-csv-todo').addEventListener('click', () => exportar([...estado.mediciones], 'mediciones-todas'));

    ['#aj-cano', '#aj-cuadros', '#aj-resolucion'].forEach(s => $(s).addEventListener('change', guardarAjustes));
    dosToques($('#btn-borrar-todo'), '¿Seguro? Se borra todo, también lo que no se subió. Tocá de nuevo', async () => {
      await DB.borrarTodo();
      location.reload();
    });

    iniciarGps();
    iniciarOffline();
    siguiente(bucle);

    // La cola sube sola: al volver la señal, al volver a la app y cada minuto.
    window.addEventListener('online', () => { pintarInicio(); sincronizar(); });
    window.addEventListener('offline', () => { pintarInicio(); pintarSync(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) sincronizar(); });
    setInterval(() => sincronizar(), 60000);
    if (estado.sesion && navigator.onLine) cargarCatalogo();
    sincronizar();
    refrescarUsuario();
  }

  iniciar().catch(e => {
    console.error(e);
    toast(`Error al iniciar: ${e.message || e}`, 'mal');
  });
})();
