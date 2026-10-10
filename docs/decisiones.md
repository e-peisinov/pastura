# Decisiones

Bitácora de decisiones del proyecto: `Pn — fecha — decisión`, con el motivo.
Una decisión revertida no se borra: se marca. Las `Dn` que aparecen en el
código son decisiones del proyecto de origen del kit (resumen en
`C:\Users\Usuario\Desktop\especificaciones\03-decisiones.md`).

**P1 — 2026-10-10 — Una sola organización.** El sistema es, por ahora, para un
productor con varios campos. No hay separación por cliente ni multi-empresa:
si más adelante hace falta, se agrega un nivel arriba de `campos`.

**P2 — 2026-10-10 — La app de campo es una PWA aparte, no Livewire.** Livewire
necesita al servidor en cada interacción y en el campo no hay señal. La app
(`poc/`) corre entera en el celular (IndexedDB, service worker) y habla con el
panel solo por la API de sincronización.

**P3 — 2026-10-10 — Medición por tamaño aparente de los marcadores.** La altura
sale de `h = c + a / t` (t = tamaño aparente de los marcadores ArUco 4×4,
números 0 a 3) ajustada con alturas conocidas, más un gradiente de perspectiva
aprendido en la misma calibración. No hace falta conocer la focal ni el tamaño
real de los marcadores. Probado con imágenes sintéticas (error rms ≈ 0,26 cm
por imagen) y en un iPhone contra una pantalla.

**P4 — 2026-10-10 — Sincronización idempotente por uuid.** La app genera el
`uuid` de cada recorrida y de cada medición. El servidor hace upsert por uuid,
así un reintento después de un corte de señal no duplica nada.

**P5 — 2026-10-10 — Contorno del potrero en GeoJSON, superficie calculada.**
El contorno se guarda como geometría GeoJSON en una columna `json` (sin
extensiones espaciales: los tests corren en SQLite). La superficie se calcula
al leer (regla 8).

**P6 — 2026-10-10 — Roles: Administrador y Recorredor.** El Recorredor
reemplaza al Mecánico del origen: hace las recorridas con la app y en el panel
ve solo las suyas.

**P7 — 2026-10-10 — Configuración sin pantalla.** La marca blanca (nombre,
logo, color) usa la tabla `configuracion` del kit, cargada por seeder. La
pantalla de Configuración queda anunciada en el sidebar para más adelante.

**P8 — 2026-10-10 — El CSV de recorridas va una fila por punto.** Sale en el
formato del exportador del origen (UTF-8 con BOM, `;`, coma decimal) e incluye
los puntos descartados y los de recorridas anuladas, marcados en la columna
Estado: el CSV es el dato crudo y el filtro lo hace quien lo abre. Exportar,
como descartar y anular, pide Editar en Recorridas y respeta la pertenencia.
El período del listado admite "sin fecha" (atajo *Todo*), por eso no usa
`FiltraPorPeriodo`, que cae siempre en hoy.

**P9 — 2026-10-10 — Todo en un servidor Apache propio; la app vive en `/app`.**
El panel y la app de campo se publican juntos en el mismo servidor Apache (con
HTTPS: sin él, el celular no habilita cámara, GPS ni service worker). La app
pasa de `poc/` a `public/app/` y llama a la API con rutas relativas a su
propia dirección, así anda en la raíz del dominio o en una subcarpeta. Al
compartir origen no hace falta CORS. GitHub Pages queda congelado en la
última versión de `poc/` (mide, pero no sincroniza); el workflow
`pages.yml` se borra cuando el servidor esté andando.

**P10 — 2026-10-10 — La app se autentica con tokens de Sanctum, no con la
sesión del panel.** La app manda usuario y contraseña del panel y recibe un
token Bearer (uno por celular, con el nombre del dispositivo). La sesión de
Laravel vence a las 2 horas y el token CSRF hay que pedirlo con señal; un
recorredor puede pasar días sin señal y tiene que poder subir al volver sin
reloguearse. Los tokens no vencen, pero se revocan al salir de la app, y en
cada pedido se revisa que el usuario siga activo (si no, 401). Toda la API,
salvo el ingreso, pide Editar en Recorridas; el ingreso rechaza a quien no lo
tiene (cambiado por P12: ese permiso se pide para bajar campos y subir). Sanctum queda solo con tokens (`guard` vacío): la API no mira la
cookie de sesión del panel aunque compartan origen.

**P11 — 2026-10-10 — Reglas de la sincronización.** `PUT /api/v1/recorridas/{uuid}`
hace upsert de la recorrida y de cada medición por su uuid (P4), todo en una
transacción. El dueño sale del token, nunca del JSON: un uuid que ya es de
otro usuario da 403, también para un administrador. El potrero tiene que
estar activo y su campo también (regla 4), al crear y al actualizar. Las
mediciones que no vienen en el pedido **se conservan**: la app puede subir en
partes y un celular que pierde datos no borra nada en el panel. La API nunca
toca `activo`: lo que se anuló o descartó en el panel sigue así aunque la app
lo vuelva a subir. Las fechas llegan en ISO 8601 con zona y se guardan en la
hora del servidor. Si el panel rechaza una recorrida, la app deja cambiarle
el potrero (misma recorrida, nueva versión) y la vuelve a subir; el panel la
mueve de potrero con todas sus mediciones.


**P12 — 2026-10-10 — La app es la puerta de entrada: desde ahí se mide o se
va al panel.** Después de ingresar, la app muestra un inicio con dos
opciones: **Hacer una recorrida** e **Ir al panel**. Entra cualquier usuario
activo; Editar en Recorridas se pide solo para bajar los campos y subir
recorridas (`puede_recorrer` en el usuario que devuelve la API), y sin ese
permiso la opción de medir queda deshabilitada con el motivo. Para no volver
a escribir la contraseña, la app pide con su token `POST /api/v1/enlace-panel`
y recibe un enlace firmado que vence en un minuto y sirve una sola vez (el
pase se guarda en la caché y se consume al usarlo); el enlace abre la sesión
del panel de ese usuario, reemplazando la que hubiera en ese navegador, y
lleva al tablero. Se descartó compartir una sola sesión entre las dos partes
(P10: la app necesita un token que no venza sin señal). El panel suma
"Abrir la app de campo" en el menú del usuario.
