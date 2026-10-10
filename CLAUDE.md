# CLAUDE.md — Pasturómetro

Sistema para medir la altura de las pasturas con un pasturómetro de disco y la
cámara del celular. Dos partes:

- **App de campo** (`poc/`): PWA offline, sin build, publicada en GitHub Pages
  (https://e-peisinov.github.io/pastura/). Mide con los marcadores ArUco del
  disco, guarda cada punto con GPS y sincroniza con el panel.
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
| App de campo | JS sin build + js-aruco2 | `poc/` |

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

## App de campo (`poc/`)

- Sin build: HTML, CSS y JS planos. Se publica sola en GitHub Pages en cada
  push a `main` que toque `poc/` (`.github/workflows/pages.yml`).
- Al cambiar un archivo de `poc/`, subir `VERSION` en `poc/sw.js` si cambia la
  lista de archivos; el service worker actualiza en segundo plano.
- `?prueba` en la URL hace correr el bucle de cámara aunque la pestaña esté
  oculta (pruebas automáticas en el navegador).
- El núcleo de visión (`poc/js/vision-core.js`, `poc/js/calibracion.js`) corre
  igual en Node: se prueba con imágenes sintéticas antes de tocarlo.

## Comandos

```bash
php artisan serve          # o http://localhost/pastura/public con XAMPP
npm run dev                # Vite en watch
npm run build              # build de producción
php artisan test           # suite completa
php artisan migrate                # aplicar migraciones nuevas (nunca fresh, regla 12)
php artisan db:seed --class=UnSeeder   # un seeder de referencia puntual
php artisan migrate --seed         # SOLO en una instalación nueva, con la base vacía
```

Usuarios de desarrollo (seeder): `admin@pastura.test` y
`recorredor@pastura.test`, contraseña `password`.
