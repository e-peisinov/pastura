# Estándares de UX / UI

> **GeoGrass:** este archivo se copió del proyecto de origen del kit (Demo Taller, `C:\xampp\htdocs\demotaller`). Valen las reglas, no los nombres de los módulos del taller (órdenes, clientes, caja...). Las referencias `Dn` son decisiones del origen, resumidas en `C:\Users\Usuario\Desktop\especificaciones\03-decisiones.md`; las decisiones propias de este proyecto son `Pn` y están en [decisiones.md](decisiones.md).

Demo Taller — sistema de gestión para talleres mecánicos y gomerías

Este documento es la **fuente de verdad de la capa de presentación**. Todo lo que
se escriba en `resources/views/` y en los componentes Livewire tiene que respetar
estas reglas, para que las pantallas se vean y se comporten igual sin importar
quién las haya escrito.

> **Origen:** las reglas vienen de los estándares que ya se probaron en otro
> proyecto del equipo. Acá están adaptadas al dominio de taller: **valen las
> reglas, no los nombres de los módulos del proyecto original.**

---

## §0 — Estado de implementación

El proyecto arrancó de cero el 2026-09-03, así que no hubo ninguna pantalla que
migrar: **estas piezas fueron el primer sprint** y ninguna pantalla se escribe
antes de que exista la pieza que necesita. Los textos compartidos de las piezas
viven en `lang/es/ui.php`.

| Pieza | Ruta | Estado |
|---|---|---|
| `<x-ui.modal>` | `resources/views/components/ui/modal.blade.php` | Hecha (2026-09-03) |
| `<x-ui.modal-confirmacion>` | `resources/views/components/ui/modal-confirmacion.blade.php` | Hecha (2026-09-03) |
| `<x-ui.toasts>` | `resources/views/components/ui/toasts.blade.php` | Hecha (2026-09-03) |
| `<x-ui.combobox>` | `resources/views/components/ui/combobox.blade.php` | Hecha (2026-09-03) |
| `<x-ui.tabla>` / `<x-ui.cards>` | `resources/views/components/ui/` | Hecha (2026-09-03) |
| `<x-ui.paginacion>` | `resources/views/components/ui/paginacion.blade.php` | Hecha (2026-09-03) |
| Trait `EnviaToasts` | `app/Livewire/Traits/EnviaToasts.php` | Hecha (2026-09-03) |
| Trait `ConfirmaDescarte` | `app/Livewire/Traits/ConfirmaDescarte.php` | Hecha (2026-09-03) |
| Trait `PaginacionResponsive` | `app/Livewire/Traits/PaginacionResponsive.php` | Hecha (2026-09-03) |
| Trait `BuscaEntidad` | `app/Livewire/Traits/BuscaEntidad.php` | Hecha (2026-09-03) |
| Trait `AbreOrdenEnModal` + parcial `livewire.tablero.partials.orden-detalle` (un bloque del tablero abre la orden en el modal de Órdenes sin salir, [D78](decisiones.md)) | `app/Livewire/Traits/AbreOrdenEnModal.php`, `resources/views/livewire/tablero/partials/orden-detalle.blade.php` | Hecha (2026-09-18) |
| Helper `Tema` | `app/Support/Tema.php` | Hecha (2026-09-03) |
| Layout con sidebar: `<x-ui.sidebar>` + `Modulos` | `resources/views/layouts/app.blade.php`, `resources/views/components/ui/sidebar.blade.php`, `app/Support/Modulos.php` | Hecha (2026-09-03) |
| `<x-ui.pagina>` (encabezado + slot `acciones`) | `resources/views/components/ui/pagina.blade.php` | Hecha (2026-09-03) |
| `<x-ui.boton>` (primario / secundario / peligro / enlace) | `resources/views/components/ui/boton.blade.php` | Hecha (2026-09-03) |
| `<x-ui.badge>` (gris / verde / rojo / ambar / azul) | `resources/views/components/ui/badge.blade.php` | Hecha (2026-09-03) |
| `<x-ui.campo>` + `<x-ui.input>` / `<x-ui.textarea>` / `<x-ui.checkbox>` | `resources/views/components/ui/` | Hecha (2026-09-03) |
| `<x-ui.solapas>` | `resources/views/components/ui/solapas.blade.php` | Hecha (2026-09-03) |
| `<x-ui.vacio>` (estado vacío de un listado) | `resources/views/components/ui/vacio.blade.php` | Hecha (2026-09-03) |
| `<x-ui.saldo>` (rojo debe / verde a favor / gris saldado) | `resources/views/components/ui/saldo.blade.php` | Hecha (2026-09-03) |
| `<livewire:selectores.selector-cliente>` (búsqueda server-side, `wire:model`) | `app/Livewire/Selectores/SelectorCliente.php` | Hecha (2026-09-03) |
| Helper `Formato` (moneda, número, fecha) | `app/Support/Formato.php` | Hecha (2026-09-03) |
| `<x-ui.accion>` (acción de fila: solo icono, §5) | `resources/views/components/ui/accion.blade.php` | Hecha (2026-09-04) |
| `<x-ui.panel>` (bloque del tablero: título, resumen, lista corta, "Ver todo") | `resources/views/components/ui/panel.blade.php` | Hecha (2026-09-07) |
| `<x-ui.indicador>` (contador del tablero: rótulo + valor; con `href` es enlace, con `wire:click` es un botón que filtra dentro del bloque y `activo` lo marca, [D76](decisiones.md)) | `resources/views/components/ui/indicador.blade.php` | Hecha (2026-09-07, botón 2026-09-18) |
| Plantilla de impresión `pdf.plantilla` + `Impresion` (membrete, acento, pie; §10) | `resources/views/pdf/plantilla.blade.php`, `app/Support/Impresiones/Impresion.php` | Hecha (2026-09-08) |
| Helper `NumeroEnLetras` (importe en palabras para el recibo) | `app/Support/NumeroEnLetras.php` | Hecha (2026-09-08) |
| `<livewire:impresiones.enviar-documento>` + `DocumentoEnviable` (mail con adjunto / WhatsApp con enlace) | `app/Livewire/Impresiones/EnviarDocumento.php`, `app/Support/Impresiones/DocumentoEnviable.php` | Hecha (2026-09-08) |
| `<x-ui.opciones>` (radio como botones segmentados: 2 a 4 valores fijos, la elegida con el acento vía `peer-checked:`) | `resources/views/components/ui/opciones.blade.php` | Hecha (2026-09-09) |
| Form `ChequeFormulario` + parcial `livewire.cheques.partials.campos` (los datos de un cheque, compartidos por el cobro de una orden y el pago a proveedor; valida los plazos de la Ley 24.452 y el límite de endosos, D53) | `app/Livewire/Forms/ChequeFormulario.php`, `resources/views/livewire/cheques/partials/campos.blade.php` | Hecha (2026-09-11) |
| `<x-ui.th>` + trait `OrdenaPorColumna` (cabecera de columna clickeable asc/desc, §5, D55) | `resources/views/components/ui/th.blade.php`, `app/Livewire/Traits/OrdenaPorColumna.php` | Hecha (2026-09-14) |
| Teclado primero en `<x-ui.modal>` (foco inicial, Enter tabea, F2 guarda, sin cierre por backdrop; §1 regla 8, D55) y Alt+letra en el layout (§9) | `resources/views/components/ui/modal.blade.php`, `resources/views/layouts/app.blade.php`, `app/Support/Modulo.php` | Hecha (2026-09-14) |
| `<livewire:clientes.cliente-formulario>` y `<livewire:vehiculos.vehiculo-formulario>` (formularios hijos que se montan abiertos y se apilan con `:z`; los usan su pantalla, la ficha del cliente, la orden y la factura; §1, D57) + trait `EligeClienteYVehiculo` (cabecera cliente / vehículo / km de la orden) | `app/Livewire/Clientes/ClienteFormulario.php`, `app/Livewire/Vehiculos/VehiculoFormulario.php`, `app/Livewire/Traits/EligeClienteYVehiculo.php` | Hecha (2026-09-14) |
| Trait `AbreRecorridaEnModal` + parcial `livewire.tablero.partials.recorrida-detalle` (un bloque del tablero abre la recorrida en su ficha, encima, D78; molde: `AbreOrdenEnModal`) | `app/Livewire/Traits/AbreRecorridaEnModal.php`, `resources/views/livewire/tablero/partials/recorrida-detalle.blade.php` | Hecha (2026-10-10) |
| `<x-ui.grafico-barras>` y `<x-ui.grafico-columnas>` + `Escala` (gráficos en HTML y CSS, sin librería; §11, D73) | `resources/views/components/ui/grafico-barras.blade.php`, `resources/views/components/ui/grafico-columnas.blade.php`, `app/Support/Reportes/Escala.php` | Hecha (2026-09-17) |

Las pantallas Livewire de página completa usan `#[Layout('layouts.app')]` y
ponen su título con `<x-ui.pagina>` (no pueden llenar el slot `header` del
layout). Los botones de un ABM son `<x-ui.boton>`; los formularios,
`<x-ui.campo>` con el control adentro (el error de validación lo muestra el
campo, inline). La paginación con `WithPagination` se dibuja igual con
`<x-ui.paginacion>` tomando los valores del paginador y la acción `gotoPage`.

**Orden de construcción sugerido:** `Tema` → `<x-ui.modal>` →
`<x-ui.modal-confirmacion>` → `<x-ui.toasts>` + `EnviaToasts` →
`<x-ui.tabla>`/`<x-ui.cards>` → `<x-ui.paginacion>` + `PaginacionResponsive` →
`<x-ui.combobox>` → `ConfirmaDescarte` → `BuscaEntidad`.

Al marcar una pieza como hecha, poner la fecha en la fila.

---

## §1 — Modales

### Reglas

1. **Todo formulario y todo detalle van en modal** ([D32](decisiones.md)). No
   hay formularios inline en la página ni pantallas de detalle de un registro:
   el sistema tiene una pantalla por módulo (el listado) y todo lo que se hace
   sobre una fila —ver, crear, editar, cargar ítems, cobrar— pasa por un modal
   abierto encima de ese listado. Si el contenido no entra sin generar mucho
   scroll, **el modal se parte en solapas** (`<x-ui.solapas>` en el body), no
   se convierte en página.
2. **Nunca** un `confirm()` nativo, ni `wire:confirm`, ni SweetAlert. Siempre
   `<x-ui.modal-confirmacion>`.
3. El modal queda **fijo en pantalla y anclado arriba** (no centrado, así un
   desplegable abierto en un modal chico tiene lugar debajo y no cuelga a mitad
   de pantalla): el panel nunca supera el viewport y
   **solo scrollea su body**. Header (título + X) y footer (acciones) siempre
   visibles. Esto lo garantiza `<x-ui.modal>`; **no armar modales a mano.**
4. **Con un modal abierto, el fondo no scrollea.** El scroll-lock lo pone
   `<x-ui.modal>`; ninguna vista lo maneja por su cuenta.
5. Ancho auto-ajustado al contenido (`ancho="auto"`, por omisión) con tope de
   `72rem`. Para formularios fluidos (todo `w-full`, que colapsan con `w-fit`)
   fijar un tamaño: `sm | md | lg | xl | 2xl | 3xl | 4xl`. Los dos últimos son
   para los modales que llevan una tabla adentro (el detalle de una orden va
   en `4xl`): con menos ancho las columnas de importe se parten en dos líneas.
6. Cierra con la **X** (arriba a la derecha) y **Escape** — los dos por la
   misma acción Livewire (`cerrar="..."`, por omisión `closeModal`). **El
   click en el backdrop no cierra** ([D55](decisiones.md)): un click afuera
   sin querer no puede tirar un formulario a medio cargar.
7. El header toma el gradiente de acento del tema (vía `Tema` — ver §6). No
   pasar colores a mano.
8. **Teclado primero** ([D55](decisiones.md)), garantizado por `<x-ui.modal>`
   sin que la vista haga nada:
   - Al abrir, el **foco va al primer campo** del body.
   - **Enter pasa al campo siguiente**; en el último campo, guarda. Enter en
     un `textarea` sigue siendo salto de línea; en un botón o enlace sigue
     siendo click.
   - **F2 guarda** desde cualquier campo del modal de más arriba: envía el
     `<form>` de `submit="..."`. Un modal sin `submit` que igual tenga una
     acción principal la marca con `data-atajo-guardar` en el botón (el de
     emitir la factura, por ejemplo).
   - Los desplegables (`<x-ui.combobox>`, los selectores de cliente y de
     artículo) resuelven su propio Enter (abrir / elegir) y, al elegir con el
     teclado, disparan `ui-siguiente-campo`: el modal pasa el foco al campo
     que sigue. Un control nuevo que reciba foco y no sea `input`, `textarea`,
     `select` ni un botón con `aria-haspopup="listbox"` se marca con
     `data-campo-foco` para entrar en el recorrido.

### Uso

El estado sigue viviendo en el componente Livewire, como siempre:

```blade
@if($showModal)
    <x-ui.modal titulo="{{ $modalMode === 'create' ? 'Nueva Orden' : 'Modificar Orden' }}"
                icono="{{ $modalMode === 'create' ? 'fa-plus' : 'fa-pen' }}"
                cerrar="closeModal" ancho="lg" submit="save">
        {{-- campos --}}
        <x-slot:footer>
            <button type="button" wire:click="closeModal" class="...">Cancelar</button>
            <button type="submit" class="...">Guardar</button>
        </x-slot:footer>
    </x-ui.modal>
@endif

@if($showDeleteModal)
    <x-ui.modal-confirmacion confirmar="delete" cancelar="closeDeleteModal">
        ¿Está seguro de que desea eliminar esta orden? Esta acción no se puede deshacer.
    </x-ui.modal-confirmacion>
@endif
```

### Detalles

- `submit="save"` envuelve body + footer en `<form wire:submit.prevent>` (con
  `display: contents`, así el scroll del body sigue funcionando). El botón del
  footer va `type="submit"`.
- El body separa sus hijos con `space-y-4` por omisión — es el espaciado
  estándar de los formularios del proyecto.
- El footer **envuelve** (`flex-wrap`), no apila: en mobile los botones cortos
  entran en una fila y el footer no se come el alto del body. Lo que no sean
  botones (totales, resúmenes) no va en el footer por el mismo motivo.
- **Modales apilados:** subir `:z="60"` al de arriba (`:z="70"` para un
  tercero). **Escape solo cierra el modal de más arriba** — la guarda va en el
  componente (compara contra el último `[aria-modal]` del DOM); no hay que hacer
  nada por vista.
- **Un formulario que se abre desde más de un lugar es un componente hijo
  que se monta ya abierto** ([D57](decisiones.md)): el padre lo envuelve en
  `@if($mostrarFormulario)` con `:key`, le pasa `:z` cuando va apilado sobre
  otro modal, y lo desmonta cuando el hijo avisa (`xxx-guardado` /
  `cerrar-xxx-formulario`). El hijo pone su diálogo de descarte un nivel
  arriba de su propio `z`. Un padre que escucha `xxx-guardado` atiende el
  evento solo si fue él quien abrió el formulario (una bandera
  `creandoXxx`): en la misma pantalla puede haber otro modal con el mismo
  hijo. Los formularios de cliente y de vehículo ya son así.
- **Un hijo que a su vez tiene hijos** recibe `z` y se lo pasa **un nivel
  arriba** a los suyos, que también lo reciben como parámetro en vez de
  asumir un número: `OrdenFacturar` va en 50 desde Órdenes y en 60 desde los
  cobros ([D67](decisiones.md)), y sus modales de anular, enviar y receptor
  nuevo siguen a `$z + 10`. Ningún hijo escribe `:z="60"` a mano.
- El botón de confirmar de `modal-confirmacion` ya trae `wire:loading` **con
  `wire:target` acotado** (ver §7, trampa conocida).

### Colisión con Breeze

Breeze dejó un `resources/views/components/modal.blade.php` (`<x-modal>`) basado
en Alpine con `x-data` / `$dispatch`. **No es nuestro estándar.** Queda solo
donde ya lo usa el scaffolding de perfil/auth; toda pantalla del sistema usa
`<x-ui.modal>`. Cuando se reescriban las pantallas de perfil, se elimina.

---

## §2 — Cambios sin guardar

En formularios de alta/edición, **cerrar con cambios pendientes pide
confirmación** — con la X, con Escape y con el click afuera, los tres.

**Criterio de dirty state:** foto de los campos al abrir el formulario,
comparación floja (`!=`) al cerrar. Vive en el trait `ConfirmaDescarte`.

```php
use App\Livewire\Traits\ConfirmaDescarte;

class Ordenes extends Component
{
    use ConfirmaDescarte;

    protected function camposFormulario(): array
    {
        return ['cliente_id', 'vehiculo_id', 'kms', 'observaciones'];
    }

    public function cerrarFormulario(): void
    {
        $this->closeModal();   // el cierre real de siempre
    }

    public function openModal(...): void
    {
        // ...cargar campos...
        $this->marcarFormularioLimpio();   // <- la foto
    }
}
```

```blade
<x-ui.modal ... cerrar="intentarCerrar">...</x-ui.modal>

@if($confirmandoDescarte)
    <x-ui.modal-confirmacion tipo="advertencia" titulo="Cambios sin guardar"
        boton="Descartar cambios" confirmar="descartarCambios" cancelar="seguirEditando" :z="60">
        Hay cambios sin guardar. ¿Desea descartarlos?
    </x-ui.modal-confirmacion>
@endif
```

No hace falta guard de `beforeunload` del navegador: los formularios del
proyecto viven en modales y la navegación interna pasa por el cierre del modal.

Un bloque de campos que se repite en dos formularios va en un **Form de
Livewire** (`Livewire\Form`, como `ChequeFormulario`) montado como propiedad
pública y con `wire:model="cheque.numero"` en un parcial compartido. El
trait lo entiende: si un campo de `camposFormulario()` es un Form, la foto
compara sus campos (`all()`), así el descarte detecta cambios adentro.

---

## §3 — Toasts

Todos los mensajes de **éxito / error / aviso / info** van como **toast**:
arriba a la derecha, auto-dismiss (**éxito e info 4 s, aviso 6 s, error 8 s**),
siempre con botón de cierre.

Los **errores de validación de campos siguen inline** con `@error` — esos no son
toasts.

### Presentación

- **Color pleno** según el tipo: éxito `emerald`, error `red`, info `blue`,
  aviso `amber`; texto blanco. Estos cuatro son **semánticos** y no cambian con
  el color de acento del tema (§6).
- **Entra y sale animado.** La salida marca el toast como `saliendo`, corre la
  animación y **recién después** lo saca del array — un `x-for` remueve el nodo
  en seco si no.

### Montaje

`<x-ui.toasts />` va montado **una sola vez** en
`resources/views/layouts/app.blade.php`. **No montarlo en ninguna otra vista.**

### Canal según el contexto

**En acciones Livewire** — el trait `EnviaToasts`. El layout no se re-renderiza
en una acción, así que un flash de sesión **no llega**:

```php
use App\Livewire\Traits\EnviaToasts;
// ...
$this->toastExito('Orden creada exitosamente.');
$this->toastError('No se pudo registrar la salida: stock insuficiente.');
$this->toastInfo(...);
$this->toastAviso(...);
```

**En redirects de página completa** — `session()->flash()` sigue funcionando; el
contenedor levanta esos flashes al cargar la página. Claves soportadas:
`message`, `success`, `ok`, `info`, `error`.

**Desde el servidor, sin acción del usuario (tiempo real, [D60](decisiones.md),
[D61](decisiones.md))** — un evento de Reverb llega a
`resources/js/tiempo-real.js`, que dispara el mismo evento de navegador
`toast` (`{ tipo, mensaje }`) que escucha el contenedor. El texto viene
armado del servidor (`OrdenActualizada::carga()`), en español y con el
`__()` de siempre, junto con `para` (la capacidad que hay que tener para
leerlo) y `usuario_id`; el JS no redacta nada y no muestra el toast a quien
causó el cambio. Lo que tiene que
seguir a la vista después del toast va al **circulito rojo del sidebar**
(`Modulo::notificaciones()`, actualizado en vivo por `notificaciones.js`
con el evento `notificacion-modulo`), no a un segundo toast.

### Reglas

- **No crear claves de flash nuevas.** Dentro de Livewire, usar siempre el trait.
- **Nunca banners inline.** Si en algún momento aparece un banner de mensaje en
  una vista, se borra: convivir banner + toast duplica el mensaje en los flujos
  con redirect.

---

## §4 — Selects / dropdowns

### a) `<x-ui.combobox>` para toda lista de opciones de datos

Vale para **filtros y formularios** por igual. Ordena alfabéticamente solo
(transliterando tildes) y, con **más de 10 opciones**, muestra un buscador por
escritura que filtra ignorando tildes; `:buscar="true"` lo muestra siempre
(para una lista corta que igual se tipea, como las órdenes con saldo en el
cobro desde Caja). Teclado: **flechas / Enter / Escape**; al elegir con
Enter avisa al modal (`ui-siguiente-campo`) para que el foco pase al campo
siguiente (§1, regla 8). Los selectores con búsqueda server-side
(`selector-cliente`, `selector-articulo`) se manejan igual: flechas para
recorrer los resultados y Enter para elegir el resaltado ([D55](decisiones.md)).

```blade
<x-ui.combobox wire:model.live="filtroEstado"
               :opciones="$estados"
               placeholder="Estado" vacio="Todos" :ordenar="false" />
```

- Respeta `wire:model` y `wire:model.live` tal cual (usa `$wire.entangle`).
- Un filtro con una opción "Todos" explícita va con `:limpiable="false"` (sin
  la X) y sin `vacio`: el valor nunca es `null` (ver "Filtros de listado" en
  [convenciones.md](convenciones.md)). `vacio` queda para los filtros cuyo
  valor por omisión sí es `null` (tipo de artículo, método de pago).
- `:ordenar="false"` para **órdenes semánticos legítimos** — en este sistema:
  los **estados de la orden** (van por flujo, no por alfabeto), las
  **posiciones de rueda** (traen su propia columna `orden`: delanteras antes
  que traseras, no alfabético), los períodos por fecha y los años descendentes.
- Solo funciona **dentro de una vista Livewire** — como todos los selects del
  proyecto.
- Los `<select>` nativos chicos (Sí/No, 3-4 opciones fijas) pueden quedarse,
  pero **siempre con `@selected(...)`** (ver §7, trampa conocida del morph).

### b) Listas enormes → búsqueda server-side

Las opciones del combobox **viajan enteras en cada render** (igual que un
`<select>`). Para listas grandes eso es inaceptable.

**Regla:** si una lista puede superar ~200 opciones, no va combobox — va
**buscador server-side**. Con `APP_DEBUG=true`, `<x-ui.combobox>` deja un aviso
en el log si recibe más de 200 opciones, así la regla se nota en desarrollo y no
en producción:

- input con `wire:model.live.debounce.300ms`
- mínimo **2 caracteres** antes de consultar
- `limit(20)` en la consulta
- lista de resultados clickeable

En este sistema los candidatos son el **catálogo de artículos y neumáticos** (un
taller real tiene miles de renglones), los **clientes** y los **vehículos por
patente**. Se resuelve con el trait `BuscaEntidad`, que ya trae el mínimo de 2
caracteres, el `limit(20)` y la propiedad computada `resultadosBusqueda`. El
componente solo define `consultaBusqueda()` y `etiquetaResultado()` — no se
copia el patrón por pantalla.

> En Lubatti este patrón tuvo que **reemplazar** selects que cargaban todas las
> filas. Acá se arranca directamente así en esas tres entidades.

**En los listados** de esas mismas entidades (artículos, clientes, vehículos)
el buscador de la pantalla también es `BuscaEntidad` ([D26](decisiones.md)):
sin texto, el listado paginado con sus filtros; con 2 o más caracteres, hasta
20 resultados y un aviso de que hay que afinar si se llega al tope. Los
filtros de tipo/estado siguen aplicando dentro de la búsqueda.

**Para elegir un cliente en un formulario** (vehículos, órdenes) va el
componente hijo `<livewire:selectores.selector-cliente wire:model="cliente_id" />`:
misma búsqueda server-side, muestra el elegido con una X para cambiarlo. Los
combos de marcas y tipos de vehículo sí van en `<x-ui.combobox>`.

### c) Los combos se pueblan en `render()`

**Nunca en propiedades públicas.** Trampa conocida: Livewire rehidrata las
colecciones por clave y el combo se rompe o se desordena entre requests.

---

## §5 — Tablas en mobile

**Sin scroll horizontal en mobile, nunca.** Toda tabla de listado se renderiza
doble: **tabla en escritorio, cards con la misma información en mobile**.

```blade
<x-ui.tabla>            {{-- hidden md:block overflow-x-auto --}}
    <thead>...</thead>
    <tbody>...</tbody>
</x-ui.tabla>

<x-ui.cards>            {{-- md:hidden p-4 space-y-4 --}}
    @forelse($filas as $fila)
        <div class="bg-white border rounded-lg p-4">...</div>
    @empty
        ...
    @endforelse
</x-ui.cards>
```

### Reglas

- **Las acciones de una fila son solo el icono** ([D33](decisiones.md)), en la
  tabla y en la card: `<x-ui.accion>`, nunca `<x-ui.boton>` con texto al lado
  del icono. La etiqueta es obligatoria y viaja en `title` y `aria-label`, así
  que sigue estando para el tooltip y el lector de pantalla. El área de toque
  es de 36 px para que sirva en el teléfono. Los botones **con** texto quedan
  para las acciones de pantalla ("Nueva orden") y los footers de modal.
- **No usar `hidden sm:table-cell` para "esconder columnas".** La respuesta
  mobile es la card, no una tabla recortada.
- **No duplicar lógica entre tabla y card.** Flags de permisos, mapas de colores
  de estado, formatos, etc. se calculan **una vez** — en el componente, o en un
  `@php` antes de las dos vistas — nunca dentro de cada `@forelse`.
- **Elegir varias filas para una acción es un modo, no una columna fija**
  ([D67](decisiones.md), el modal de cobros): un botón de pantalla
  ("Facturar cobros") enciende una casilla por fila (`<x-ui.checkbox>` sin
  rótulo, con `aria-label`, ligada a un array con `wire:model.live`) y una
  barra arriba de la tabla con lo que suma la selección, *Cancelar* y la
  acción con la cantidad ("Facturar 2 cobros"). Las filas que no aplican no
  llevan casilla; las tildadas se pintan `bg-gray-50`. En la card mobile la
  casilla va a la izquierda del encabezado. Cualquier cambio del listado
  (`orden-actualizada`) apaga el modo.
- **Las columnas se ordenan clickeando la cabecera** ([D55](decisiones.md)):
  primer click ascendente, segundo descendente, otra columna vuelve a
  ascendente. El componente usa el trait `OrdenaPorColumna` (define
  `columnasOrdenables()` con la clave de cada cabecera y cómo se ordena, y
  `ordenInicial()`), llama `$this->aplicarOrden($query)` en la consulta del
  listado, y el `<thead>` se arma con `<x-ui.th columna="nombre"
  :orden="$this->orden()">Cliente</x-ui.th>`. La columna que manda muestra la
  flecha con el acento y `aria-sort`; las demás la insinúan al pasar el mouse.
  La columna de Acciones (y cualquier otra que no se ordena) es `<x-ui.th>`
  sin `columna`; `derecha` alinea los importes. Las claves que no están en el
  mapa se ignoran: el navegador no elige por qué columna se ordena. Las
  cards mobile siguen el mismo orden porque salen de la misma consulta.
- Para una tabla de **columnas variables**, la card mobile genérica es un
  `<dl>` con un par rótulo → valor por columna, y la pantalla no debe saber
  nada de ninguna tabla en particular: le pide columnas y filas a quien las
  arma. La pantalla de Reportes ([D73](decisiones.md)) **no usa tablas**: sus
  bloques son gráficos y listas (§11), que ya entran en mobile; las tablas de
  columnas variables de los reportes existen solo en el CSV y en el PDF
  (`ExportadorReportes::secciones()`, [D74](decisiones.md)).

### Paginación

- Un solo componente: `<x-ui.paginacion :current-page="$currentPage"
  :total-pages="$totalPages" :offset="$offset" :per-page="$perPage"
  :total-records="$totalRecords" />`. Los colores los toma del tema (§6).
- Con `WithPagination` de Livewire queda `links()`.
- **Cantidad por página responsiva:** trait `PaginacionResponsive` — **5 en
  móvil, 10 en escritorio**. `$this->checkIfMobile()` en `mount()`, y el
  `x-data` del wrapper llama `updatePerPage(isMobile)` en el resize (breakpoint
  **768 px**). No copiar ese par de métodos dentro de cada componente.

---

## §6 — Color: un solo acento, sobrio (`Tema`)

Es un sistema administrativo que se usa muchas horas por día: **la paleta es
uniforme y poco llamativa**. No hay un color por módulo (ver
[D7](decisiones.md)).

- **Acento único** para headers de modal, botones primarios, paginación y
  acentos de pantalla: `slate`. El gradiente del header es
  `from-slate-700 to-slate-800`.
- **Chrome neutro:** fondos `bg-white` / `bg-gray-50`, texto `text-gray-800`,
  secundario `text-gray-500`, bordes y divisores `border-gray-200` /
  `divide-gray-200`.
- **Overlay de modal:** `bg-black/50`. Nunca `bg-gray-600 bg-opacity-50`.
- **Los colores semánticos sí son de color**, y son los únicos: éxito
  `emerald`, error/peligro `red`, aviso `amber`, info `blue`. Se usan en toasts
  (§3), badges de estado y saldos (rojo = debe, verde = a favor, gris =
  saldado).
- **Sin dark mode** ([D6](decisiones.md)): no escribir variantes `dark:`. Si
  algún día entra, se agrega en `Tema` y en los componentes `<x-ui.*>`, no
  pantalla por pantalla.

### El helper `Tema`

`app/Support/Tema.php` centraliza las clases de acento. **Ninguna vista escribe
un color de acento a mano** — así cambiar la paleta es una línea:

```php
Tema::gradienteHeader();   // 'from-slate-700 to-slate-800'
Tema::botonPrimario();     // 'bg-slate-700 hover:bg-slate-800 text-white'
Tema::acentoTexto();       // 'text-slate-700'
```

**Las clases se escriben literales** en `Tema` (`'from-slate-700 to-slate-800'`)
y nunca por interpolación: Tailwind arma el CSS escaneando los archivos, y una
clase construida en tiempo de ejecución jamás llega al build. Por eso
`tailwind.config.js` incluye `./app/**/*.php` en `content`.

### Marca blanca

El acento es **elegible entre un puñado de paletas escritas literales** en
`Tema` (`slate`, `zinc`, `blue`, `teal`), y `configuracion.color_acento` dice
cuál usar ([D8](decisiones.md)). Eso permite pintar el demo con los colores del
taller que lo va a ver, sin tocar código y sin romper el build de Tailwind. No
se acepta un color arbitrario por hex: no llegaría al CSS.

### Navegación y layout

El layout es propio, con **sidebar a la izquierda** y el usuario abajo de todo
([D24](decisiones.md)): nada del scaffolding de barra superior de Breeze. El
sidebar arma sus enlaces desde **una sola lista de módulos** (nombre, ruta,
icono, nivel por omisión del mecánico) y **muestra solo los que ya tienen su
ruta** (`Route::has()`) y sobre los que el usuario tiene al menos *Ver*
([D49](decisiones.md)). La barra crece sola a medida que aparecen las pantallas;
no hay que tocar la vista para sumar un módulo. Un módulo que todavía no
existe puede estar **anunciado** ([D66](decisiones.md)): va en
`Modulos::anunciados()`, se dibuja al final apagado (`text-gray-400`), sin
enlace, con la etiqueta "Próximamente" a la derecha y el title
"Módulo: próximamente". Nunca una página "En construcción": un ítem apagado
promete sin cortar la navegación. En mobile el sidebar es un drawer con
backdrop `bg-black/50`, cierre por Escape y click afuera.

**Las dos cabeceras miden lo mismo** ([D77](decisiones.md)). La del sidebar (logo y nombre del
taller) es `h-16`; la de la página (`<x-ui.pagina>` y el slot `header` del
layout) lleva `md:min-h-16` **en el propio `<header>`** (el que tiene el
`border-b`, así el borde entra en los 64 px igual que en el sidebar) y
`md:py-2` con el contenido centrado en el div interno; el borde inferior de
una y de otra es una sola línea de punta a punta en escritorio.
En mobile la cabecera de la página se apila (título arriba, acciones abajo) y
la altura la pone el contenido.

**Scroll fino** ([D77](decisiones.md)). Toda caja con scroll propio (el `nav` del sidebar, el cuerpo
de `<x-ui.modal>`, las listas de `<x-ui.combobox>` y de los selectores, la
tabla de `<x-ui.grafico-columnas>`, la lista filtrada del tablero) lleva la
clase `scroll-fino` de `app.css` junto con `overflow-y-auto`: barra de 6px,
gris claro, redondeada, sin fondo ni flechas, que se oscurece al pasar el
mouse. En Firefox sale por `scrollbar-width: thin` y `scrollbar-color`; en el
resto por `::-webkit-scrollbar`. El scroll de la página entera queda con la
barra del sistema.

Las pantallas de acceso (login, olvido y restablecimiento de contraseña) usan
`layouts/guest` ([D52](decisiones.md)): mismo fondo y tipografía, la marca del
taller arriba como en el sidebar y una tarjeta blanca con el formulario armado
con `<x-ui.campo>`, `<x-ui.input>`, `<x-ui.checkbox>` y `<x-ui.boton>`. Los
toasts se montan también ahí. Nada de `<x-text-input>`, `<x-primary-button>`
ni el resto del scaffolding de Breeze en pantallas nuevas.

---

## §7 — Trampas conocidas

Errores que ya costaron tiempo en proyectos anteriores. Vale la pena leerlos una
vez antes de escribir la primera pantalla.

1. **`wire:loading` sin `wire:target`.** Un `wire:loading` suelto se activa con
   *cualquier* request del componente, no solo con la acción del botón. Siempre
   acotado: `wire:loading wire:target="save"`.
2. **`<select>` nativo sin `@selected(...)`.** El morph de Livewire no
   restablece la opción elegida si el `selected` no está en el HTML renderizado.
3. **Combos poblados en propiedades públicas** (§4c).
4. **Toast + banner conviviendo** (§3): mensaje duplicado en flujos con redirect.
5. **`x-for` que remueve el nodo antes de la animación de salida** (§3).
6. **Escape cerrando todos los modales apilados a la vez** (§1).
7. **Clases de Tailwind armadas por interpolación** (§6): no llegan al build.
8. **Anchos fijos que rompen en mobile** (`w-1/2`, `w-64`, `w-[55%]`): van
   `w-full` + breakpoint. En Lubatti hubo que corregir esto en seis pantallas.
9. **Modal sin tope de altura**: `max-h` + solo el body scrolleando. Lo
   resuelve `<x-ui.modal>`; el problema aparece cuando alguien arma un modal a
   mano.
10. **`min-h` en el div interno de una cabecera con `border-b`**: el borde
    queda un píxel más abajo que el del sidebar (§6, [D77](decisiones.md)).
    La altura va en el elemento que tiene el borde.

---

## §8 — Convenciones de escritura

- **Idioma:** la UI es **en español** (rioplatense; trato de *usted* en los
  mensajes de confirmación, como en los ejemplos). Los identificadores de código
  que nombran conceptos del dominio también en español — `Orden`, `Vehiculo`,
  `articulos` — para que hablen el mismo idioma que el taller.
- **Textos de cara al usuario** (labels, validaciones, botones) en `lang/es/`,
  **nunca hardcodeados**.
- **Iconos:** **Font Awesome Free 7** (`@fortawesome/fontawesome-free`),
  instalado por npm e importado en `resources/css/app.css`. Se usa la variante
  **solid** salvo excepción:

  ```blade
  <i class="fa-solid fa-plus"></i>
  ```

  Los atributos `icono="fa-plus"` de `<x-ui.modal>` reciben **solo el nombre del
  icono**; el componente antepone `fa-solid`. Si alguna vez hace falta otro
  estilo, se pasa completo (`icono="fa-regular fa-calendar"`) y el componente
  respeta el `fa-` de estilo que ya venga.

  Nada de CDN: el paquete viaja por Vite (las `.woff2` salen al build), así el
  sistema funciona sin internet — que en un taller pasa. Los alias viejos
  (`fas`, `far`, `fab`) siguen funcionando, pero **no se usan**: la sintaxis del
  proyecto es `fa-solid` / `fa-regular` / `fa-brands`.
- **Tailwind:** el proyecto corre **Tailwind v3** vía PostCSS. Nada de sintaxis
  de v4 (`@theme`, `@import "tailwindcss"`) mientras eso no cambie. Y nada de
  `bg-opacity-*`: en v4 está eliminado, así que se escribe `bg-black/50` desde
  ya para no tener que corregirlo cuando se migre.
- **Sin estilos inline ni valores px sueltos:** colores, espaciados y
  tipografías salen de `tailwind.config.js`.
- **Mobile-first:** estilar primero el viewport chico y escalar con `sm:`,
  `md:`, `lg:`. No se difiere el responsive para "después".

---

## §9 — Navegación entre pantallas (`wire:navigate`)

**Todo enlace interno lleva `wire:navigate`** ([D31](decisiones.md)). Cambiar
de módulo no recarga la página: Livewire pide el HTML nuevo, reemplaza el
`<body>` y actualiza la URL. Se ve la barra de progreso del navegador, pero el
CSS, el JS y Alpine **no se vuelven a bajar ni a arrancar**, así que la
segunda visita a una pantalla es instantánea.

```blade
<a href="{{ route('vehiculos') }}" wire:navigate>Vehículos</a>
```

- `<x-ui.boton :href="...">` y `<x-ui.accion :href="...">` **ya lo ponen
  solos** cuando el destino es interno: no hay que acordarse.
- La excepción son los enlaces que tienen que salir de la aplicación: externos
  (`https://otro-sitio`), descargas y los que abren en otra pestaña
  (`target="_blank"`). El componente los detecta y no agrega nada; escribiendo
  el `<a>` a mano, simplemente no se pone el atributo.
- **`tel:` y `mailto:` nunca** llevan `wire:navigate`.
- El formulario de cerrar sesión es un POST: no lleva `wire:navigate`.
- **Alt+letra cambia de módulo** ([D55](decisiones.md)): cada `Modulo` de
  `Modulos::todos()` lleva su letra (Alt+O órdenes, Alt+C clientes, Alt+B el
  box de Trabajo, Alt+J caja, Alt+K reportes...), el sidebar la imprime en `data-tecla` y en
  el `title` del enlace, y el layout escucha `keydown` en `window` y navega
  con `Livewire.navigate()` al enlace que tenga esa letra. Como solo se
  buscan los enlaces del sidebar, los permisos mandan igual: un módulo que el
  usuario no ve no tiene atajo. No se usan Alt+D ni Alt+E, que en Windows son
  de la barra de direcciones y el menú del navegador.

### Qué hay que cuidar

- **El estado del componente no sobrevive** a una navegación: cada pantalla se
  monta de cero. Lo que tenga que viajar entre pantallas va en la URL, con
  `#[Url]` (`/ordenes?cliente=11`).
- **Nada de `<script>` con estado propio dentro de una vista.** El layout monta
  Alpine y los toasts una sola vez; una vista que necesite JS lo hace con
  Alpine (`x-data`), que Livewire re-inicializa solo.
- Para probarlo: entrar a un módulo, ir a otro y volver. La segunda vez la
  pantalla aparece sin parpadeo blanco.

---

## §10 — Impresiones (PDF)

Todo lo que sale en papel ([D51](decisiones.md)) se arma con dompdf sobre
una plantilla común, aparte del comprobante fiscal de ARCA ([D37](decisiones.md)),
que tiene el formato que ARCA manda.

- **La plantilla es `resources/views/pdf/plantilla.blade.php`.** Cada
  impresión la extiende y completa `titulo`, `estilos`, `cabecera` (lo que va
  a la derecha del membrete) y `contenido`. El membrete (logo, nombre,
  dirección, teléfono, CUIT) y el pie (emisión, número de página) los pone la
  plantilla.
- **dompdf no procesa Tailwind.** Los estilos van en un `<style>` propio,
  con clases literales. El acento de la paleta llega en `$acento` (hexa, lo
  resuelve `Impresion::colorAcento`) y el logo en `$logo` (data URI: dompdf no
  sale a buscar archivos). No hay `counter(pages)`: el pie dice solo "Página N".
- **Un generador por documento** en `app/Support/Impresiones/` (`OrdenPdf`,
  `ReciboPdf`, `CajaPdf`, `EstadoCuentaPdf`, `HistorialVehiculoPdf`,
  `TrabajoPdf`, `InventarioPdf`, `ReportesPdf`), con `generar()` que devuelve el PDF,
  `html()` que devuelve el HTML para los tests y `nombreArchivo()`. Un
  controlador por ruta, que hace `->stream(nombre)`.
- **Los botones abren en otra pestaña**: `<x-ui.boton>` o `<x-ui.accion>` con
  `:href`, `target="_blank"` y `:navegar="false"` (§9: un PDF con
  `wire:navigate` se pega como texto en el body). Un listado filtrado lleva
  los filtros en la URL del PDF (Caja: `FiltrosCaja::paraUrl()`), así se
  imprime exactamente lo que se ve.
- **Enviar al cliente**: `<livewire:impresiones.enviar-documento tipo="orden|recibo" :id :z>`
  es el modal de mail (PDF adjunto, `DocumentoMail`) y WhatsApp (enlace
  firmado, sin vencimiento, `DocumentoEnviable::urlPublica()`). Se apila con
  el `z` que le pasa el padre y cierra con el evento `cerrar-enviar-documento`.
- **Tests**: revisar el HTML con `XxxPdf::html()` (contenido, totales,
  textos) y una sola petición a la ruta que compruebe `application/pdf`, el
  nombre del archivo y que el cuerpo empiece con `%PDF`. Renderizar el PDF
  entero en cada aserción es lento.

## §11 — Gráficos

Los gráficos son **HTML y CSS, sin librería de JavaScript** ([D73](decisiones.md)):
se redibujan con Livewire como cualquier otra vista, entran en mobile sin
scroll horizontal (regla 4) y se pueden probar con `assertSeeHtml`. Hay dos
piezas y no se dibuja un gráfico a mano fuera de ellas:

- **`<x-ui.grafico-barras :filas color>`** — barras horizontales para
  comparar magnitudes de una lista corta (lo más vendido, egresos por
  categoría, cobros por método). Cada fila trae `etiqueta`, `valor`, `texto`
  (el valor formateado, va en la punta) y `detalle` opcional. Como cada fila
  muestra su número, el gráfico es también su tabla.
- **`<x-ui.grafico-columnas :tramos :series :formato>`** — columnas en el
  tiempo (por día o por mes), con una o dos series lado a lado, leyenda, eje
  con marcas redondas (`App\Support\Reportes\Escala`), globo al pasar el
  mouse o enfocar con Tab, y "Ver tabla" con todos los valores.

### Reglas

1. **Primero la forma, después el color.** Un número suelto es una tarjeta
   del resumen, no un gráfico de una barra. Una lista de gente a la que hay
   que llamar es una lista con su acción (como los bloques del Tablero), no
   un gráfico ([D75](decisiones.md)). Comparar magnitudes: barras.
   Evolución en el tiempo: columnas. **Nada de tortas**: las partes de un
   total van en barras con su porcentaje al lado.
2. **Un solo eje.** Nunca dos escalas en el mismo gráfico. Dos medidas de
   distinta escala son dos gráficos (por eso ingresos y egresos por
   categoría van en bloques separados, cada uno con la suya).
3. **Una serie, un color; el color es de la serie, no de su posición.** Azul
   `#2a78d6` para la primera (o única) y naranja `#eb6834` para la segunda. El
   par está validado para daltonismo y contraste sobre blanco; si hace falta
   una tercera serie, se valida el trío antes de sumarla. Los colores de
   estado (rojo, amarillo, verde) **no se usan como serie**. El acento del
   tema (§6) no pinta datos: es del chrome.
4. **El texto nunca va del color de la serie.** Valores, rótulos y leyenda
   usan los grises de siempre; la identidad la da el cuadradito de color al
   lado.
5. **Con dos series hay leyenda; con una, alcanza el título del bloque.**
6. **Nada queda solo en el gráfico**: el valor está escrito (barras) o está
   en el globo y en "Ver tabla" (columnas).
7. **Los tramos vacíos se dibujan** (un mes sin ventas es una columna en
   cero, no un hueco en el eje) y **un período sin datos muestra
   `<x-ui.vacio>`**, no un gráfico vacío.
8. **Una variación (subió / bajó) nunca va solo en color** ([D74](decisiones.md)):
   flecha, palabras y el valor de antes al lado. Verde es "mejor" y rojo es
   "peor", no "sube" y "baja": en egresos, bajar es lo verde.
9. Las clases de color son **literales** dentro del componente (§7, trampa de
   Tailwind): un color nuevo se agrega ahí, no se pasa armado desde afuera.
