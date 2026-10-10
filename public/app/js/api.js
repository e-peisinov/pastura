/*
 * Cliente de la API del panel (P9, P10). La app vive en /app del mismo
 * servidor, así que la API está en ../api/v1/ respecto de esta página: anda
 * igual en la raíz del dominio o en una subcarpeta. Cada error lleva
 * `estado` (0 = sin red) y `cuerpo` (el JSON de la respuesta, si hubo).
 */
const Api = (() => {
  'use strict';

  const BASE = new URL('../api/v1/', location.href);
  const ESPERA_MS = 20000;
  let token = null;

  async function pedir(metodo, ruta, cuerpo) {
    const control = new AbortController();
    const reloj = setTimeout(() => control.abort(), ESPERA_MS);
    let respuesta;
    try {
      respuesta = await fetch(new URL(ruta, BASE), {
        method: metodo,
        headers: {
          Accept: 'application/json',
          ...(cuerpo !== undefined ? { 'Content-Type': 'application/json' } : {}),
          ...(token ? { Authorization: `Bearer ${token}` } : {}),
        },
        body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
        cache: 'no-store',
        credentials: 'omit',
        signal: control.signal,
      });
    } catch (e) {
      throw Object.assign(new Error('Sin conexión con el panel.'), { estado: 0 });
    } finally {
      clearTimeout(reloj);
    }

    const datos = respuesta.status === 204 ? null : await respuesta.json().catch(() => null);
    if (!respuesta.ok) {
      const errores = datos && datos.errors ? Object.values(datos.errors).flat() : [];
      const mensaje = errores[0] || (datos && datos.message) || `El panel respondió ${respuesta.status}.`;
      throw Object.assign(new Error(mensaje), { estado: respuesta.status, cuerpo: datos });
    }
    return datos;
  }

  return {
    usarToken: t => { token = t || null; },
    ingresar: (email, password, dispositivo) => pedir('POST', 'ingreso', { email, password, dispositivo }),
    salir: () => pedir('POST', 'salida'),
    yo: () => pedir('GET', 'yo'),
    campos: () => pedir('GET', 'campos'),
    subirRecorrida: (uuid, cuerpo) => pedir('PUT', `recorridas/${encodeURIComponent(uuid)}`, cuerpo),
  };
})();
