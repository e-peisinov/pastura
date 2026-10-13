# Convenciones

> **GeoGrass:** este archivo se copió del proyecto de origen del kit (Demo Taller, `C:\xampp\htdocs\demotaller`). Valen las reglas, no los nombres de los módulos del taller (órdenes, clientes, caja...). Las referencias `Dn` son decisiones del origen, resumidas en `C:\Users\Usuario\Desktop\especificaciones\03-decisiones.md`; las decisiones propias de este proyecto son `Pn` y están en [decisiones.md](decisiones.md).

Cómo se escribe el código, la base de datos y los commits de este proyecto.
La capa de presentación tiene su propio documento:
[estandares-ux.md](estandares-ux.md).

---

## Código

- **Componentes Livewire de clase** (`app/Livewire/` + vista en
  `resources/views/livewire/`) para todas las pantallas del sistema.
  **Volt queda solo para lo que ya vino de Breeze**, no se extiende: mezclar los
  dos estilos en pantallas nuevas hace el código incoherente.
- **Un componente que pasa de ~400 líneas se parte** en subcomponentes. Es la
  lección más cara de Lubatti: `Ordenes.php` tiene 1.243 líneas y su vista 919,
  y ya no se puede tocar sin miedo. Señales de que hay que partir: más de un
  modal grande en la misma clase, dos flujos que no comparten estado, o una
  vista con más de tres `@if` de pantalla completa.
- **Idioma:** español para todo lo que nombra el dominio — modelos (`Orden`,
  `Vehiculo`), tablas (`ordenes`, `vehiculos`), propiedades, rutas y textos de
  UI. Los términos del framework quedan como vienen (`render`, `mount`, `boot`).
- **Nombres:** modelos en singular PascalCase, tablas en plural snake_case,
  claves foráneas `<singular>_id`.
- Traits de Livewire compartidos en `app/Livewire/Traits/`.
- Helpers y servicios sin estado en `app/Support/`.
- Enums del dominio en `app/Enums/` (`EstadoOrden`, `Rol`, `TipoMovimiento`…), con
  `etiqueta()` que lee de `lang/es/enums.php`. Las pantallas comparan contra el
  case (`EstadoOrden::EnTaller`), nunca contra la cadena `'en_taller'`.
- **Cuando los valores los define el taller, va una tabla y no un enum**
  ([D70](decisiones.md)): los métodos de pago son filas de `metodos_pago`. La
  columna guarda una `clave` que no cambia (con clave foránea), se lee con un
  cast propio (`app/Casts/`) y el enum queda solo para las filas de fábrica.
- Lo que se avisa en vivo sale **de los eventos del modelo y después del
  commit** ([D61](decisiones.md), [D72](decisiones.md)): un evento plano, un
  listener con try/catch y un `#[On]` por pantalla. Nunca `ShouldBroadcastNow`
  ni `wire:poll`.
- Los números de un reporte se calculan en una clase de `app/Support/`
  (`Reportes`), no en el componente, y **sin tablas de resumen** (regla 8);
  lo que dependería del motor de base (agrupar por mes) se hace en PHP
  ([D73](decisiones.md)).
- Las reglas de negocio que no pueden olvidarse viven en el modelo: congelar
  precio (`Orden::agregarServicio/agregarArticulo`), mover stock (eventos de
  `OrdenItem`), generar caja (evento de `Pago`), cerrar la goma anterior
  (`Vehiculo::colocarNeumatico`). Ninguna pantalla las reimplementa.
- **Se quedan en inglés (convención de Laravel — no traducir):** timestamps
  `created_at` / `updated_at` / `deleted_at`, clave primaria `id`, claves
  foráneas `cliente_id`, y todos los métodos y helpers de Eloquent/Laravel.

### Lo que NO se hereda de Lubatti

Lubatti arrastra nombres de un sistema legacy en FoxPro. **Acá no se usan**:

| Lubatti | Acá |
|---|---|
| `articu`, `provee`, `atencion`, `atenser` | `articulos`, `proveedores`, `ordenes`, `orden_items` |
| `id_cliente`, `idatencion`, `idservicio` | `cliente_id`, `orden_id`, `servicio_id` |
| `coniva`, `fecing`, `detart`, `punit`, `ptotal` | nombres completos y en español claro |
| `clientes.saldo` como columna | saldo calculado (accessor) |

---

## Filtros de listado

- **Un filtro nunca representa un estado con `null`.** Livewire no rehidrata
  `null` en una propiedad tipada (la deja en su valor por omisión), así que un
  filtro `?string $filtroEstado = 'activos'` con "Todos" = `null` volvía solo
  a "Activos" en la siguiente acción de la pantalla. La opción "Todos" es una
  cadena más de la lista (`'todos'`, `'todas'`), la propiedad es `string` sin
  `?`, y el combobox va con `:limpiable="false"` para que no tenga la X que
  dejaría el valor en `null`. Un filtro cuyo valor por omisión **es** `null`
  (tipo de artículo, método de pago, rango de fechas) puede seguir siendo
  `?string`: ahí `null` es el default y no se pierde nada.
- Los filtros que tienen que sobrevivir a una navegación o venir de un enlace
  (`/ordenes?cliente=11`, deudores desde el tablero) llevan `#[Url]`; el resto,
  no.

---

## Base de datos

- **MySQL** — base `demotaller`, el `.env` local ya apunta ahí. Nada de features
  específicas de SQLite.
- Toda estructura entra por **migración**, nunca por phpMyAdmin a mano: el repo
  es compartido y los compañeros necesitan poder reconstruir la base con
  `php artisan migrate`.
- **Migraciones limpias, no parches.** En Lubatti 35 de 60 migraciones son
  `add_x_to_y` / `modify_z` / `fix_z`. Acá el esquema se piensa antes (está en
  [dominio.md](dominio.md)) y se escribe de una. Los parches son legítimos
  cuando el cambio es genuinamente nuevo, no cuando se olvidó una columna.
- **Clave primaria:** siempre `id` autoincremental (`$table->id()`). Nunca usar
  otra columna como PK.
- **Claves foráneas:** siempre `*_id` con `foreignId()->constrained()`. Nunca
  `*_codigo` ni variantes.
- **Saldos y totales se calculan, no se guardan.** Un accessor
  (`getSaldoAttribute()`) o un `withSum`. Lubatti creó `clientes.saldo`, lo
  mantuvo desincronizado un tiempo y después tuvo que borrarlo por migración.
- Datos de referencia (estados, marcas, medidas de neumático típicas) van por
  **seeder**, no en la migración.
- Datos de prueba mediante **seeders y factories**.
- **La base local no se vacía nunca** ([D54](decisiones.md)): ni
  `migrate:fresh`, ni `migrate:refresh`, ni `db:wipe`. Tiene datos que el
  usuario cargó a mano para probar (su CUIT y condición de IVA para ARCA, un
  usuario propio, casos de prueba). Esquema nuevo: `php artisan migrate`.
  Datos de referencia nuevos: `php artisan db:seed --class=ElSeeder`.
  `DemoSeeder` no es idempotente (duplicaría clientes, órdenes y pagos):
  corre solo sobre una base vacía. Los tests corren en SQLite en memoria y
  no tocan MySQL.

---

## Localización (Argentina)

- `APP_LOCALE=es`, `APP_FALLBACK_LOCALE=es`, `APP_FAKER_LOCALE=es_AR`
- Zona horaria: `America/Argentina/Buenos_Aires` (`APP_TIMEZONE` en `.env`)
- Fechas de cara al usuario: **dd/mm/aaaa**
- Moneda: **ARS ($)**, formato `$ 1.234,56` (punto de miles, coma decimal)
- Textos de cara al usuario (labels, validaciones, botones) en `lang/es/`,
  **nunca hardcodeados**. `lang/en/` fue eliminado a propósito.

---

## Responsive — mobile-first, sí o sí

- Todo se desarrolla **responsive desde el primer momento**. No se difiere para
  "después": en Lubatti el retrofit responsive costó tres rondas completas.
- **Mobile-first:** estilar primero el viewport chico y escalar con prefijos de
  Tailwind (`sm:`, `md:`, `lg:`).
- Sin estilos inline ni valores px sueltos: colores, espaciados y tipografías
  salen de `tailwind.config.js`.
- El caso de uso móvil es real: el mecánico usa el teléfono en el box.

---

## Testing

- **Cada funcionalidad nueva lleva su test** (PHPUnit).
- Cubrir en particular reglas de negocio, cálculos y validaciones: total de una
  orden, saldo del cliente, descuento de stock, transiciones de estado.
- Los tests de Livewire van con `Livewire::test(...)`.
- Correr la suite antes de cerrar: `php artisan test`.
- Las impresiones en PDF se prueban por su HTML (`XxxPdf::html()`), no
  renderizando el documento (estandares-ux.md §10).
- La suite corre sobre **SQLite en memoria** (`phpunit.xml`), no sobre la base
  MySQL local. Por eso las consultas crudas (`selectRaw`, `whereRaw`) se
  escriben con SQL que sirva en los dos motores.

---

## Git

- Repositorio compartido con otras personas. Rama principal: **`main`**.
- **Se trabaja directamente sobre `main`.** Antes de empezar, `git pull`.
- Para un cambio grande o riesgoso sigue valiendo abrir una rama
  (`feat/...`, `fix/...`) y unirla cuando esté verde.
- Mensajes de commit **en español**, imperativos y concretos:
  `Agregar ABM de vehículos`, `Corregir cálculo del total de la orden`.
- **`.env` está en `.gitignore`.** Todo cambio de configuración que los demás
  necesiten va también a `.env.example`.
