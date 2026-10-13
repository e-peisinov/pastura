/*
 * Service worker: guarda la app completa en el celular para que abra y
 * funcione sin conexión. Al cambiar cualquier archivo, subir VERSION.
 */
const VERSION = 'app-9';
const ARCHIVOS = [
  './',
  'index.html',
  'manifest.webmanifest',
  'css/app.css',
  'js/app.js',
  'js/db.js',
  'js/api.js',
  'js/sincronizacion.js',
  'js/calibracion.js',
  'js/biomasa.js',
  'js/sin-senal.js',
  'js/vision-core.js',
  'js/vision-worker.js',
  'vendor/js-aruco2/cv.js',
  'vendor/js-aruco2/aruco.js',
  'vendor/js-aruco2/aruco_4x4_1000.js',
  'icons/icon-192.png',
  'icons/icon-512.png',
  'icons/icon-maskable-512.png',
];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(ARCHIVOS.map(u => new Request(u, { cache: 'reload' })))).then(() => self.skipWaiting()));
});

self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys()
      .then(claves => Promise.all(claves.filter(k => k !== VERSION).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

// Responde con lo guardado (funciona sin señal) y, si hay red, actualiza la
// copia en segundo plano para la próxima vez que se abra la app.
self.addEventListener('fetch', e => {
  // Solo los archivos de la app (su alcance, /app/): la API y el panel van
  // siempre a la red, nunca a la copia guardada.
  if (e.request.method !== 'GET' || !e.request.url.startsWith(self.registration.scope)) return;
  // La clave va sin la query (?prueba): la misma página se guarda una sola vez.
  const clave = new URL(e.request.url);
  clave.search = '';
  e.respondWith(
    caches.open(VERSION).then(cache =>
      cache.match(clave.href).then(guardada => {
        // Por URL (un pedido de navegación no acepta opciones) y no-cache:
        // revalida contra el servidor en vez de usar la caché HTTP.
        const red = fetch(e.request.url, { cache: 'no-cache' })
          .then(r => { if (r.ok) cache.put(clave.href, r.clone()); return r; })
          .catch(() => guardada);
        if (guardada) { e.waitUntil(red); return guardada; }
        return red;
      })
    )
  );
});
