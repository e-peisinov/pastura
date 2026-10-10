/*
 * Service worker: guarda la app completa en el celular para que abra y
 * funcione sin conexión. Al cambiar cualquier archivo, subir VERSION.
 */
const VERSION = 'poc-1';
const ARCHIVOS = [
  './',
  'index.html',
  'manifest.webmanifest',
  'css/app.css',
  'js/app.js',
  'js/db.js',
  'js/calibracion.js',
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
  e.waitUntil(caches.open(VERSION).then(c => c.addAll(ARCHIVOS)).then(() => self.skipWaiting()));
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
  if (e.request.method !== 'GET' || new URL(e.request.url).origin !== location.origin) return;
  e.respondWith(
    caches.open(VERSION).then(cache =>
      cache.match(e.request, { ignoreSearch: true }).then(guardada => {
        const red = fetch(e.request)
          .then(r => { if (r.ok) cache.put(e.request, r.clone()); return r; })
          .catch(() => guardada);
        if (guardada) { e.waitUntil(red); return guardada; }
        return red;
      })
    )
  );
});
