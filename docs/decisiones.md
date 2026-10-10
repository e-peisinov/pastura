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
