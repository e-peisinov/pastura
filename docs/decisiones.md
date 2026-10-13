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
*Reemplazada por P17.*

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
lleva al tablero (se abre dentro de la app, P14). Se descartó compartir una sola sesión entre las dos partes
(P10: la app necesita un token que no venza sin señal). El panel suma
"Abrir la app de campo" en el menú del usuario.

**P13 — 2026-10-10 — El panel anda también en una subcarpeta.** En XAMPP el
panel vive en `/pastura/public` y Livewire escribía la dirección de su script
y la de sus pedidos desde la raíz (`/livewire/...`): no cargaba Livewire ni
Alpine (menús, buscadores y formularios sin respuesta). Livewire no tiene una
opción para eso (quita la subcarpeta a propósito), así que
`App\Support\Panel\LivewireEnSubcarpeta` le pone la subcarpeta adelante a esas
dos direcciones en la página que sale, solo cuando el pedido llega por una
subcarpeta. En la raíz del dominio no hace nada. Si se actualiza Livewire,
`SubcarpetaTest` avisa si cambió la forma de esas etiquetas.
Probando en el celular aparecieron dos más (2026-10-10): las pantallas de
Breeze redirigían con `route(..., absolute: false)`, que Laravel arma **sin**
la subcarpeta, y Livewire mandaba al ingresar a `/dashboard` de la raíz (en
XAMPP, su propia página); y el CSS pedía las fuentes de Font Awesome y las
imágenes de Leaflet en `/build/...` (íconos en blanco: el botón del menú en
mobile no se veía). Las redirecciones van con dirección absoluta (`route()`,
`url('/')`) y Vite escribe las del CSS relativas al propio CSS
(`renderBuiltUrl`). Lo cuidan `SubcarpetaTest` y `npm run test:build`.

**P14 — 2026-10-10 — El panel se abre dentro de la app instalada.** "Ir al
panel" abría una ventana nueva, y desde la app instalada en el iPhone eso
salía a Safari. Ahora navega en la misma ventana y el alcance (`scope`) del
manifest es la carpeta de arriba de `/app`, así que el panel queda dentro de
la app. La app sigue arrancando en `/app` (`start_url`) y el service worker
sigue limitado a `/app`: el panel nunca se guarda en el celular y necesita
señal. Se vuelve con "Abrir la app de campo" del menú del usuario. La sesión
del panel queda guardada en la app, aparte de la del navegador. En el iPhone
el cambio de alcance se toma al volver a agregar el ícono.

**P15 — 2026-10-10 — El inicio avisa mientras la app no está lista sin
señal.** En la primera prueba en un iPhone, la app no abrió en modo avión: el
service worker no había quedado, aunque las mediciones sí (no se pudo
reproducir; lo más probable es que iOS cortara la primera preparación al
pasar enseguida a otra cosa). Antes eso se veía solo en Ajustes. Ahora
`SinSenal` (`js/sin-senal.js`) da por lista la app cuando el service worker
está activo **y** la página está guardada en la caché, y mientras no lo está
el inicio muestra un aviso: "Preparando…" la primera vez, o que hay que
abrirla con señal antes de salir al campo. Si falta, se reintenta al volver
la señal.

**P16 — 2026-10-10 — Tipos de pastura con sus alturas de referencia y su
fórmula de kg MS/ha, configurables en el panel.** `tipo_pastura` deja de ser
texto libre: es una tabla (`tipos_pastura`) que se administra en
Configuración, y cada potrero apunta a uno. Cada tipo tiene su **altura de
ingreso** (desde ahí el potrero está listo para pastorear), su **altura de
salida** (el remanente: debajo de eso no se sigue comiendo) y su **fórmula de
kg MS/ha** (`A × altura + B`). Las dos alturas van juntas o ninguna, y la de
ingreso es mayor que la de salida; la fórmula, igual: A y B juntos o
ninguno. La fórmula estaba en los ajustes de cada celular, así que dos
recorredores podían medir con fórmulas distintas y los kg MS/ha del panel no
se podían comparar ni sumar. Desde ahora el panel calcula los kg MS/ha de
cada punto desde su altura con la fórmula **vigente** del tipo de pastura
del potrero, al leer (regla 8): cambiar la fórmula recalcula la historia,
que es lo que permite comparar un potrero consigo mismo en el tiempo. Lo que
guarda `mediciones.kg_ms_ha` queda como lo que vio el recorredor en el
celular. La app baja la fórmula con los campos y deja de pedirla en sus
ajustes. La migración convierte los textos que ya había en tipos (sin
alturas ni fórmula) sin perder ninguno. *La fórmula por tipo de pastura
quedó reemplazada por P23; las alturas de referencia siguen.*

**P17 — 2026-10-10 — Configuración con pantalla (reemplaza P7).** Tres
bloques, cada uno con su modal: los datos de la organización (nombre, logo y
color; el módulo 31 del kit sin datos fiscales ni métodos de pago), los
tipos de pastura (P16) y los **parámetros** del seguimiento: cuántos días
pasan para que un potrero quede **sin medir** (7 al instalar) y cuántos
puntos como mínimo tiene que tener una recorrida para no quedar **para
revisar** (20 al instalar). Es un módulo de la matriz (`configuracion`): el
administrador nace con Editar y el recorredor sin acceso.

**P18 — 2026-10-10 — Alcance de tablero, reportes y auditoría.** Se cerró
con el usuario antes de construirlos:
- **Los recorredores trabajan en todos los campos**: no hay asignación de
  campos ni de potreros a un recorredor.
- **Recorrida para revisar**: una recorrida activa con menos puntos activos
  que el mínimo de P17, con algún punto activo sin GPS o con algún punto
  activo fuera del contorno de su potrero.
- **No se registran pastoreos** (entrada y salida de animales) por ahora: una
  caída de altura entre dos recorridas se muestra como tal, sin explicarla.
- **Tablero**: el administrador ve el estado de los potreros (listos, en
  crecimiento, bajo el remanente, sin medir, nunca medidos), las últimas
  recorridas y las recorridas para revisar; el recorredor ve sus recorridas y
  los potreros para medir. La cola de subida vive en el celular y el panel
  no la ve.
- **Reportes**: con CSV y PDF (se instala dompdf y la plantilla del kit).
  El recorredor nace sin acceso.
- **Auditoría** de lo sensible, no de todo: anular y reactivar una
  recorrida, descartar y restaurar un punto, mover una recorrida de potrero;
  cambiar el contorno, desactivar o borrar un potrero o un campo; cambiar el
  rol, los permisos, la contraseña (hecha por el administrador) o desactivar
  un usuario; cambiar los tipos de pastura o los parámetros. Ni los ingresos
  ni las subidas: es ruido para lo que cuenta.
- **Orden**: Configuración → kg MS/ha calculados en el panel (API y app) →
  Tablero → Auditoría → Reportes, un commit por etapa.

**P19 — 2026-10-10 — Cómo se arma el tablero.** Detalles que se resolvieron
al construir el de P18:
- **Potreros para medir es un bloque aparte** del estado de los potreros:
  la acción es otra (salir a medir, no pastorear) y lo ven los dos roles.
- La referencia de un potrero es su **última recorrida activa con al menos
  un punto activo**: una anulada o con todo descartado no dice la altura.
  Un potrero sin medir sigue mostrando su último estado, con los días.
- **Bordes**: listo desde la altura de ingreso justa; bajo el remanente
  solo por debajo de la de salida (la de salida justa está en crecimiento);
  sin medir con **más** días que los de Configuración.
- **Para revisar mira los últimos 30 días**: no hay una marca de
  "revisada", así que sin ventana una recorrida vieja quedaría para
  siempre. Los puntos descartados no cuentan para ningún motivo.

**P20 — 2026-10-10 — Cómo se audita.** El módulo 38 del kit, adaptado a lo
de P18 y sin el tiempo real (acá no hay Reverb: la pantalla se lee al
entrar). Cada modelo dice qué vigila: la recorrida, `activo` y
`potrero_id` (no su alta ni lo que cambia la app al subir); el punto,
`activo`; el potrero, `tipo_pastura_id` (cambia los kg, P16), `contorno`
(se guarda la superficie, no la geometría) y `activo`, más su baja; el
campo, `activo` y su baja; el tipo de pastura, todo, con su alta y su baja;
la configuración, solo los parámetros (no la marca). El usuario: `rol` y
`activo`; la **contraseña solo cuando la cambia otro** (la propia, en el
perfil o por correo, no) y **nunca se guarda su valor**, ni cifrado; los
**permisos** se comparan al guardar la matriz (`asignarNiveles`), módulo
por módulo, y no cuentan en el alta de un usuario. Lo que llega por la API
queda a nombre del dueño del token. La pantalla es de quien administra;
con Ver alcanza.

**P21 — 2026-10-10 — Qué muestran los reportes.** El módulo 39 del kit,
adaptado al pastoreo, con dompdf y la plantilla de impresión del kit:
- **Período** en la URL, con atajos (este mes, 3, 6 y 12 meses desde un día
  1); abre en **los últimos 3 meses**, una temporada de pastoreo. El campo
  y el potrero elegidos también van en la URL; por omisión, el primer campo
  activo y su potrero con más recorridas en el período.
- **Resumen** contra el período anterior del mismo largo: recorridas,
  puntos, potreros medidos y hectáreas medidas (la superficie de esos
  potreros). Cuentan las recorridas activas con algún punto activo.
- **Evolución de un potrero: una columna por recorrida**, no por día ni
  por mes. Es la excepción a "los tramos vacíos se dibujan" (§11): un día
  sin medir no es un potrero de 0 cm, y un promedio mensual borra el
  crecimiento. La etiqueta de cada columna es su fecha. La **tasa de
  crecimiento** se calcula contra la recorrida anterior del mismo período
  (cm/día y kg MS/ha/día); si bajó, se lee "bajó", sin explicar por qué
  (los pastoreos no se registran, P18).
- **Potreros del campo**: la altura de la última recorrida del período,
  los más altos primero.
- **Disponibilidad forrajera** por campo: kg MS/ha de la última recorrida
  del período × hectáreas, de los potreros con contorno y con fórmula
  (*desde P23, con curva de biomasa para el estado de esa recorrida*); se
  dice cuántos de los potreros del campo cubre.
- **Actividad por recorredor**: recorridas y puntos.
- CSV y PDF salen de las mismas tablas (`ExportadorReportes::secciones`).

**P22 — 2026-10-13 — El sistema se llama GeoGrass.** Cambio de marca:
"Pasturómetro" pasa a ser **GeoGrass**, con el dominio **geograss.com.ar**
(el del servidor de P9). Cambian el nombre de la app (título, manifest,
pantalla de ingreso), `APP_NAME`, el remitente de los correos
(`no-responder@geograss.com.ar`) y el nombre por omisión de la
organización; una migración renombra la organización que todavía se
llamaba "Pasturómetro" y deja un nombre elegido en Configuración. **No
cambian**: "pasturómetro" como nombre del instrumento (el caño con el
disco, glosario) y los identificadores técnicos: la base del celular sigue
siendo `pasturometro` (cambiarla dejaría sin sus recorridas a los
celulares que ya la usan), y el repo y la base de datos siguen como
`pastura`.

**P23 — 2026-10-13 — Los kg MS/ha salen de curvas de biomasa por potrero y
por estado fenológico (reemplaza la fórmula de P16).** Lo explicó Mateo
(usuario y agrónomo) por audio: no hay una relación fija entre centímetros
y kilos; se **calibra en cada potrero** (él dice "lote"). En unos 15 puntos
se mide la altura con el pasturómetro y, justo debajo del disco, se corta
el pasto, se **seca en estufa hasta peso constante** y se pesa. La recta
de esos puntos es la **curva de biomasa** del potrero. Y no sirve para
siempre: la estructura de la planta cambia cómo se comprime bajo el disco
(una alfalfa recién pastoreada no se parece a una en plena primavera), así
que se hace **una curva por estado fenológico**.
- **Catálogo de estados fenológicos** en Configuración (como los tipos de
  pastura). Arranca con tres por seeder, que Mateo puede cambiar.
- **Curva** = potrero + estado + fecha de los cortes + sus **muestras**
  (altura del disco en cm y peso seco en g). Hay **una vigente** por
  potrero y estado: guardar o reactivar otra la deja reemplazada.
- Cada muestra vale `peso seco / área del disco × 10` kg MS/ha (1 g/m² =
  10 kg/ha). El **área del disco** es un parámetro de Configuración
  (0,10 m² al instalar, lo que dijo Mateo: gramos × 100).
- **A, B y R² se calculan de las muestras, no se guardan** (regla 8): la
  recta de mínimos cuadrados sale de cinco sumas, así entra en la misma
  subconsulta de los listados. Una curva se guarda con **al menos 3
  muestras y 2 alturas distintas**, así siempre tiene recta. No se escriben
  A y B a mano: si hay una planilla, se cargan sus puntos.
- El **recorredor elige el estado al empezar la recorrida** (la app propone
  el último que usó en ese potrero) y la recorrida lo guarda. Los kg de un
  punto son `max(0, A × altura + B)` con la curva **vigente** del potrero
  para ese estado, al leer: corregir una curva recalcula la historia, como
  en P16.
- **Sin curva para ese potrero y estado, no hay kg MS/ha.** No hay fórmula
  de respaldo: un número de catálogo se leería como medido. Por eso la
  fórmula A y B del tipo de pastura se elimina (columnas, pantalla, API).
  Una recorrida sin estado (las de antes, o de una app vieja) tampoco tiene
  kilos hasta que tenga uno.
- Las curvas tienen **su módulo** (`curvas`, Alt+B): el administrador nace
  con Editar y el recorredor con Ver. Se llega también desde cada potrero
  de la ficha del campo. Las muestras se cargan **en el panel** por ahora;
  un "modo curva" en la app (medir los puntos y numerar las bolsas) queda
  para después.
- La palabra **calibración** sigue siendo la de la cámara (glosario); lo de
  los cortes es la **curva de biomasa**.

**P24 — 2026-10-13 — Los estados de la pastura y el área del disco se
administran en Curvas (modifica P23).** Los dos solo sirven para las curvas:
quien arma una y no encuentra el estado que necesita lo tiene que poder
crear ahí, sin ir a otro módulo. Pasan de Configuración a la pantalla de
Curvas, debajo del listado: el ABM de estados (`EstadosFenologicos`, con su
formulario) y el área del disco (`AreaDisco`). Se **mueven, no se copian**:
Configuración queda con la organización, los tipos de pastura y los
parámetros del seguimiento. El permiso pasa a ser **Editar en Curvas** (el
administrador por omisión); el recorredor, que nace con Ver, los mira. El
área se sigue guardando en la fila de `configuracion` (no cambia el
esquema). Los estados los gestiona el usuario libremente: no hace falta
confirmar con Mateo una lista cerrada.
