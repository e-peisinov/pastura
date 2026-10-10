/*
 * Si la app quedó lista para abrir sin señal. Lista = el service worker está
 * activo y la página de la app está guardada en la caché; si no, el inicio
 * avisa que hay que abrirla con señal antes de salir al campo.
 *
 * No toca la pantalla: recibe el service worker, la caché y la dirección de
 * la página, así se prueba en Node con unos falsos. `var` (no `const`) para
 * que el test lo vea al evaluar el archivo en un contexto aislado.
 */
var SinSenal = (() => {
  // Espera a que el service worker nuevo termine: activo, o descartado si la
  // descarga de los archivos se cortó.
  function esperarActivacion(trabajador) {
    return new Promise(resolver => {
      const revisar = () => {
        if (trabajador.state === 'activated') resolver(true);
        else if (trabajador.state === 'redundant') resolver(false);
      };
      trabajador.addEventListener('statechange', revisar);
      revisar();
    });
  }

  /**
   * @param sw        navigator.serviceWorker (undefined si el navegador no lo tiene)
   * @param cajas     window.caches
   * @param pagina    dirección de la página de la app (la que se abre sin señal)
   * @param alEsperar se llama si hay que esperar a que se prepare (la primera vez)
   * @return { estado: 'lista' | 'incompleta' | 'error' | 'sin-soporte', motivo? }
   */
  async function preparar({ sw, cajas, pagina, alEsperar }) {
    if (!sw || !cajas) return { estado: 'sin-soporte' };
    let registro;
    try {
      registro = await sw.register('sw.js');
    } catch (e) {
      return { estado: 'error', motivo: e.message || String(e) };
    }
    if (!registro.active) {
      const nuevo = registro.installing || registro.waiting;
      if (!nuevo) return { estado: 'incompleta' };
      alEsperar();
      if (!(await esperarActivacion(nuevo))) return { estado: 'incompleta' };
    }
    return (await cajas.match(pagina)) ? { estado: 'lista' } : { estado: 'incompleta' };
  }

  // Texto del aviso del inicio; null cuando no hace falta avisar.
  function aviso(r) {
    switch (r.estado) {
      case 'lista': return null;
      case 'preparando': return 'Preparando la app para usar sin señal. Dejala abierta con señal hasta que este aviso desaparezca.';
      case 'error': return `No se pudo preparar la app para usar sin señal (${r.motivo}). Abrila de nuevo con señal.`;
      case 'sin-soporte': return 'Este navegador no permite abrir la app sin señal: medí solo donde haya señal o usá otro navegador.';
      default: return 'La app todavía no está lista para usar sin señal. Abrila con señal un momento antes de salir al campo.';
    }
  }

  const textoAjustes = r => (r.estado === 'lista' ? '✓ Lista para usar sin señal.' : aviso(r));

  return { preparar, aviso, textoAjustes };
})();

if (typeof module !== 'undefined' && module.exports) module.exports = SinSenal;
