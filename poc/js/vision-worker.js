/*
 * Worker de visión: recibe cuadros RGBA y devuelve los marcadores detectados.
 * Corre fuera del hilo principal para que la cámara no se trabe.
 */
importScripts(
  '../vendor/js-aruco2/cv.js',
  '../vendor/js-aruco2/aruco.js',
  '../vendor/js-aruco2/aruco_4x4_1000.js',
  'vision-core.js'
);

self.onmessage = (e) => {
  const { id, ancho, alto, buffer, opciones } = e.data;
  try {
    const res = Vision.analizarCuadro(new Uint8ClampedArray(buffer), ancho, alto, opciones);
    self.postMessage({ id, ok: true, res, buffer }, [buffer]);
  } catch (err) {
    self.postMessage({ id, ok: false, error: String(err), buffer }, [buffer]);
  }
};
