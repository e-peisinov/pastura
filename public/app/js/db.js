/*
 * Persistencia local en IndexedDB. Todo queda en el celular: no hace falta
 * conexión para guardar ni para leer.
 *
 *   ajustes      clave → valor (ajustes, calibración, sesión, catálogo de campos)
 *   calibracion  puntos de calibración { id, alturaCm, cuadros, creado }
 *   recorridas   { uuid, usuarioId, potreroId, iniciada, terminada, rev, sync, ... }  (v2)
 *   mediciones   { id, recorrida, creado, alturaCm, ..., subida }
 *
 * Las mediciones de la v1 no tienen `recorrida` (eran por potrero en texto
 * libre): quedan en el celular como "anteriores" y no se suben.
 */
const DB = (() => {
  const NOMBRE = 'pasturometro';
  const VERSION = 2;
  let conexion = null;

  function abrir() {
    if (conexion) return Promise.resolve(conexion);
    return new Promise((resolver, rechazar) => {
      const pedido = indexedDB.open(NOMBRE, VERSION);
      pedido.onupgradeneeded = () => {
        const db = pedido.result;
        if (!db.objectStoreNames.contains('ajustes')) db.createObjectStore('ajustes');
        if (!db.objectStoreNames.contains('calibracion')) db.createObjectStore('calibracion', { keyPath: 'id' });
        const mediciones = db.objectStoreNames.contains('mediciones')
          ? pedido.transaction.objectStore('mediciones')
          : db.createObjectStore('mediciones', { keyPath: 'id' });
        if (!mediciones.indexNames.contains('potrero')) mediciones.createIndex('potrero', 'potrero');
        if (!mediciones.indexNames.contains('creado')) mediciones.createIndex('creado', 'creado');
        if (!mediciones.indexNames.contains('recorrida')) mediciones.createIndex('recorrida', 'recorrida');
        if (!db.objectStoreNames.contains('recorridas')) db.createObjectStore('recorridas', { keyPath: 'uuid' });
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
    borrar: clave => tx('ajustes', 'readwrite', s => s.delete(clave)),

    puntos: () => tx('calibracion', 'readonly', s => s.getAll()),
    guardarPunto: p => tx('calibracion', 'readwrite', s => s.put(p)),
    borrarPunto: id => tx('calibracion', 'readwrite', s => s.delete(id)),
    borrarPuntos: () => tx('calibracion', 'readwrite', s => s.clear()),

    recorridas: () => tx('recorridas', 'readonly', s => s.getAll()),
    guardarRecorrida: r => tx('recorridas', 'readwrite', s => s.put(r)),
    recorrida: uuid => tx('recorridas', 'readonly', s => s.get(uuid)),
    borrarRecorrida: uuid => tx('recorridas', 'readwrite', s => s.delete(uuid)),

    mediciones: () => tx('mediciones', 'readonly', s => s.getAll()),
    medicionesDe: uuid => tx('mediciones', 'readonly', s => s.index('recorrida').getAll(uuid)),
    guardarMedicion: m => tx('mediciones', 'readwrite', s => s.put(m)),
    borrarMedicion: id => tx('mediciones', 'readwrite', s => s.delete(id)),
    async marcarSubidas(ids) {
      const db = await abrir();
      return new Promise((resolver, rechazar) => {
        const t = db.transaction('mediciones', 'readwrite');
        const s = t.objectStore('mediciones');
        ids.forEach(id => {
          const r = s.get(id);
          r.onsuccess = () => { if (r.result && !r.result.subida) s.put({ ...r.result, subida: true }); };
        });
        t.oncomplete = () => resolver();
        t.onerror = () => rechazar(t.error);
      });
    },

    async borrarTodo() {
      for (const store of ['ajustes', 'calibracion', 'recorridas', 'mediciones']) {
        await tx(store, 'readwrite', s => s.clear());
      }
    },
  };
})();
