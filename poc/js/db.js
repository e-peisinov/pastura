/*
 * Persistencia local en IndexedDB. Todo queda en el celular: no hace falta
 * conexión para guardar ni para leer.
 *
 *   ajustes      clave → valor (ajustes, calibración vigente, potrero actual)
 *   calibracion  puntos de calibración { id, alturaCm, cuadros, creado }
 *   mediciones   { id, potrero, creado, alturaCm, ... }
 */
const DB = (() => {
  const NOMBRE = 'pasturometro';
  const VERSION = 1;
  let conexion = null;

  function abrir() {
    if (conexion) return Promise.resolve(conexion);
    return new Promise((resolver, rechazar) => {
      const pedido = indexedDB.open(NOMBRE, VERSION);
      pedido.onupgradeneeded = () => {
        const db = pedido.result;
        if (!db.objectStoreNames.contains('ajustes')) db.createObjectStore('ajustes');
        if (!db.objectStoreNames.contains('calibracion')) db.createObjectStore('calibracion', { keyPath: 'id' });
        if (!db.objectStoreNames.contains('mediciones')) {
          const s = db.createObjectStore('mediciones', { keyPath: 'id' });
          s.createIndex('potrero', 'potrero');
          s.createIndex('creado', 'creado');
        }
      };
      pedido.onsuccess = () => { conexion = pedido.result; resolver(conexion); };
      pedido.onerror = () => rechazar(pedido.error);
    });
  }

  async function tx(store, modo, fn) {
    const db = await abrir();
    return new Promise((resolver, rechazar) => {
      const t = db.transaction(store, modo);
      const s = t.objectStore(store);
      let resultado;
      const r = fn(s);
      if (r && 'onsuccess' in r) r.onsuccess = () => { resultado = r.result; };
      t.oncomplete = () => resolver(resultado);
      t.onerror = () => rechazar(t.error);
      t.onabort = () => rechazar(t.error);
    });
  }

  return {
    leer: (clave, porDefecto = null) => tx('ajustes', 'readonly', s => s.get(clave)).then(v => (v === undefined ? porDefecto : v)),
    guardar: (clave, valor) => tx('ajustes', 'readwrite', s => s.put(valor, clave)),

    puntos: () => tx('calibracion', 'readonly', s => s.getAll()),
    guardarPunto: p => tx('calibracion', 'readwrite', s => s.put(p)),
    borrarPunto: id => tx('calibracion', 'readwrite', s => s.delete(id)),
    borrarPuntos: () => tx('calibracion', 'readwrite', s => s.clear()),

    mediciones: () => tx('mediciones', 'readonly', s => s.getAll()),
    guardarMedicion: m => tx('mediciones', 'readwrite', s => s.put(m)),
    borrarMedicion: id => tx('mediciones', 'readwrite', s => s.delete(id)),

    async borrarTodo() {
      await tx('ajustes', 'readwrite', s => s.clear());
      await tx('calibracion', 'readwrite', s => s.clear());
      await tx('mediciones', 'readwrite', s => s.clear());
    },
  };
})();
