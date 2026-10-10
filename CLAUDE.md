# CLAUDE.md — Pasturómetro

Sistema para medir la altura de las pasturas con un pasturómetro de disco y la
cámara del celular. Dos partes:

- **App de campo** (`public/app/`, se abre en `/app`): PWA offline, sin
  build, servida por el mismo Apache que el panel (P9). Mide con los
  marcadores ArUco del disco, guarda cada punto con GPS y sube las recorridas
  al panel por la API (`routes/api.php`, P10 y P11).
- **Panel de administración** (este Laravel): campos, potreros con su contorno
  en el mapa, recorridas y mediciones, usuarios y permisos. 100 % responsive.

Por ahora es para una sola organización (P1).

Arranca sobre el kit de especificaciones reutilizables de
`C:\Users\Usuario\Desktop\especificaciones\` (piezas de UI, convenciones,
módulos opt-in con código probado). Ninguna pieza del kit se reescribe: se
copia del proyecto de origen (`C:\xampp\htdocs\demotaller`) y se adapta según
su sección *Qué adaptar*.

## Documentación

| Archivo | Qué contiene |
|---|---|
| [docs/estandares-ux.md](docs/estandares-ux.md) | **Capa de presentación.** Modales, toasts, combobox, tablas, paginación, colores. Fuente de verdad de todo lo que se escribe en `resources/views/`. |
| [docs/convenciones.md](docs/convenciones.md) | Código, base de datos, git, localización, responsive, testing. |
| [docs/dominio.md](docs/dominio.md) | Modelo de datos, reglas de negocio, roles y alcance de la v1. |
| [docs/decisiones.md](docs/decisiones.md) | Bitácora de decisiones (`Pn`), con fecha y motivo. |
| [docs/glosario.md](docs/glosario.md) | **Vocabulario del negocio**: qué significa cada término, qué no significa y las palabras con más de un sentido. Los nombres nuevos del código usan su término canónico. |

Los changelogs no van acá: para eso está el historial de git. Qué pantallas
están hechas y cuáles faltan está en la tabla de alcance de
[docs/dominio.md](docs/dominio.md).

## Stack

| Capa | Herramienta | Versión |
|---|---|---|
| Framework | Laravel | ^12.0 |
| PHP | | ^8.2 |
| Componentes | Livewire | ^3.6.4 |
| Livewire SFC | Volt | ^1.7 (**solo** lo que trajo Breeze) |
| Auth scaffolding | Laravel Breeze | ^2.4 (stack Livewire + Volt) |
| CSS | Tailwind CSS | **3.4.19** (v3 vía PostCSS) |
| JS | Alpine.js | ^3.17 |
| Iconos | Font Awesome Free | **7.3.1** (npm, vía Vite) |
| Build | Vite | ^7 + `laravel-vite-plugin` ^2 |
| Base de datos | MySQL | XAMPP local, base `pastura` |
| Tests | PHPUnit | ^11.5 |
| API de la app | Laravel Sanctum (tokens Bearer) | ^4 |
| App de campo | JS sin build + js-aruco2 | `public/app/` |
| PDF | barryvdh/laravel-dompdf | ^3.1 |

La UI se arma con los componentes propios `<x-ui.*>`. No se instala Flux.

## Reglas duras

Estas no se discuten en el momento; si alguna no encaja, se cambia el
estándar primero y después la pantalla.

1. **Ninguna pantalla se escribe fuera del estándar UX.** Si falta una pieza
   (`<x-ui.*>`, un trait), se construye antes de la pantalla que la necesita.
2. **Nada de diálogos nativos**: ni `confirm()`, ni `wire:confirm`, ni
   SweetAlert. Las confirmaciones son `<x-ui.modal-confirmacion>`.
3. **Mensajes de éxito/error/aviso/info = toast.** Nunca banners inline. Los
   errores de validación de campo sí van inline con `@error`.
4. **Sin scroll horizontal en mobile, nunca.** Toda tabla de listado se
   renderiza doble: tabla en escritorio, cards en mobile.
5. **Todo cambio de esquema entra por migración**, jamás por phpMyAdmin. Los
   datos de referencia van por seeder.
6. **Componentes Livewire de clase** para todas las pantallas del sistema.
   Volt queda congelado en lo que vino de Breeze.
7. **Un componente que pasa de ~400 líneas se parte** en subcomponentes.
8. **Saldos y totales se calculan, no se guardan** en una columna (acá:
   promedios de una recorrida, superficie de un potrero).
9. **Español** para todo lo que nombra el dominio: modelos, tablas, rutas,
   propiedades, textos. Los términos del framework quedan como vienen
   (`render`, `mount`, `created_at`, `id`, `potrero_id`).
10. **Cada funcionalidad nueva lleva su test.** `php artisan test` en verde
    antes de cerrar. En la **lógica de dominio** (promedios, superficie,
    sincronización), **test primero**: los casos con números concretos se
    escriben como tests que fallan antes del código. Un **bug** se arregla
    después de tener un test que lo muestre (skill `diagnosticar-bug`).
11. **Sin dark mode.** No escribir variantes `dark:`.
12. **Nunca `migrate:fresh`, `migrate:refresh`, `db:wipe` ni nada que vacíe
    la base local.** El esquema se actualiza con `php artisan migrate` y los
    datos de referencia nuevos con `php artisan db:seed --class=...`.

## App de campo (`public/app/`)

- Sin build: HTML, CSS y JS planos. Se publica junto con el panel en el
  servidor Apache (P9), en `/app`; en local,
  http://localhost/pastura/public/app/. Necesita HTTPS (o `localhost`) para
  la cámara, el GPS y el service worker. GitHub Pages quedó congelado en la
  última versión de `poc/`.
- Habla con el panel solo por `../api/v1/` (`js/api.js`), con el token del
  ingreso. Es también la puerta al panel: su inicio ofrece hacer una
  recorrida o ir al panel con un enlace de un solo uso (P12). La cola de subida (`js/sincronizacion.js`) no toca la pantalla ni
  IndexedDB: se prueba en Node con `npm run test:app`, **test primero**.
- Al cambiar un archivo de `public/app/`, subir `VERSION` en
  `public/app/sw.js` si cambia la lista de archivos; el service worker
  actualiza en segundo plano y nunca intercepta la API (queda fuera de su
  alcance).
- `?prueba` en la URL hace correr el bucle de cámara aunque la pestaña esté
  oculta (pruebas automáticas en el navegador).
- El núcleo de visión (`public/app/js/vision-core.js`,
  `public/app/js/calibracion.js`) corre igual en Node: se prueba con imágenes
  sintéticas antes de tocarlo.

## Comandos

```bash
php artisan serve          # o http://localhost/pastura/public con XAMPP
npm run dev                # Vite en watch
npm run build              # build de producción
php artisan test           # suite completa
npm run test:app           # lógica de la app de campo (Node)
npm run test:build         # el CSS compilado anda en una subcarpeta (Node, compila)
php artisan migrate                # aplicar migraciones nuevas (nunca fresh, regla 12)
php artisan db:seed --class=UnSeeder   # un seeder de referencia puntual
php artisan migrate --seed         # SOLO en una instalación nueva, con la base vacía
```

Usuarios de desarrollo (seeder): `admin@pastura.test` y
`recorredor@pastura.test`, contraseña `password`.
