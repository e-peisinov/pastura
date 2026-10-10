{{--
    <x-ui.combobox> — estandares-ux.md §4a.

    Toda lista de opciones de datos, en filtros y formularios. Solo dentro de
    una vista Livewire: respeta wire:model y wire:model.live vía @entangle.

      <x-ui.combobox wire:model.live="filtroEstado" :opciones="$estados"
                     placeholder="Estado" vacio="Todos" :ordenar="false" />

    Props:
      opciones     array asociativo valor => texto, lista de modelos/arrays
                   (usa `clave` y `etiqueta`), lista de enums con etiqueta(),
                   o lista de cadenas.
      placeholder  Texto cuando no hay nada elegido.
      vacio        Texto de la opción "sin valor" (null). Si no se pasa, no hay
                   opción vacía pero sí se puede limpiar con la X.
      ordenar      true por omisión: alfabético transliterando tildes. false
                   para órdenes semánticos (estados de la orden, posiciones de
                   rueda, períodos, años descendentes).
      clave / etiqueta   Campos a usar cuando las opciones son modelos o arrays
                   (por omisión id / nombre).
      disabled     Deshabilita el control.
      limpiable    true por omisión: la X que deja la selección en null. false para
                   los filtros que ya tienen una opción "Todos" explícita: ahí el
                   valor nunca es null (convenciones.md, "Filtros").

    Con más de 10 opciones aparece un buscador que filtra ignorando tildes;
    `:buscar="true"` lo muestra siempre (una lista corta que igual se tipea).
    Teclado: flechas / Enter / Escape. Con más de 200 opciones y APP_DEBUG,
    deja un aviso en el log: esa lista va con búsqueda server-side (§4b,
    trait BuscaEntidad).

    Las opciones viajan en data-opciones y Alpine las lee con un getter, así
    un re-render de Livewire (combo dependiente) las actualiza sin re-iniciar.
--}}
@props([
    'opciones' => [],
    'placeholder' => null,
    'vacio' => null,
    'ordenar' => true,
    'clave' => 'id',
    'etiqueta' => 'nombre',
    'disabled' => false,
    'buscar' => null,
    'limpiable' => true,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Facades\Log;
    use Illuminate\Support\Str;

    $items = $opciones instanceof \Illuminate\Support\Enumerable ? $opciones->all() : (array) $opciones;
    $esLista = array_is_list($items);

    $normalizadas = [];
    foreach ($items as $indice => $item) {
        if ($item instanceof \BackedEnum) {
            $valor = $item->value;
            $texto = method_exists($item, 'etiqueta') ? $item->etiqueta() : $item->name;
        } elseif (is_array($item) || is_object($item)) {
            $valor = data_get($item, $clave);
            $texto = data_get($item, $etiqueta);
        } elseif ($esLista) {
            $valor = $item;
            $texto = $item;
        } else {
            $valor = $indice;
            $texto = $item;
        }

        $normalizadas[] = ['valor' => $valor, 'texto' => (string) $texto];
    }

    if ($ordenar) {
        usort($normalizadas, fn ($a, $b) => strnatcasecmp(Str::ascii($a['texto']), Str::ascii($b['texto'])));
    }

    if (config('app.debug') && count($normalizadas) > 200) {
        Log::warning(sprintf(
            '<x-ui.combobox wire:model="%s"> recibió %d opciones: con más de 200 va búsqueda server-side (estandares-ux.md §4b, trait BuscaEntidad).',
            $attributes->wire('model')->value() ?? '?',
            count($normalizadas),
        ));
    }

    $conBuscador = $buscar ?? count($normalizadas) > 10;
    $placeholder ??= __('ui.combobox.placeholder');
    $idLista = 'combobox-'.Str::random(6);
@endphp

<div
    x-data="{
        valor: @entangle($attributes->wire('model')),
        abierto: false,
        busqueda: '',
        resaltado: -1,
        get opciones() { return JSON.parse($el.dataset.opciones) },
        normalizar(texto) { return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase() },
        get filtradas() {
            const q = this.normalizar(this.busqueda.trim());
            return q ? this.opciones.filter(o => this.normalizar(o.texto).includes(q)) : this.opciones;
        },
        get seleccionada() {
            if (this.valor === null || this.valor === undefined || this.valor === '') return null;
            return this.opciones.find(o => String(o.valor) === String(this.valor)) ?? null;
        },
        get vacio() { return this.seleccionada === null },
        // El panel se posiciona con `fixed` contra el botón: así sobresale del
        // modal en vez de quedar recortado por el body, que scrollea (§1).
        panel: { top: 0, left: 0, width: 0, arriba: false },
        posicionar() {
            const r = this.$refs.boton.getBoundingClientRect();
            const alto = this.$refs.panel?.offsetHeight ?? 280;
            // Si abajo no entra y arriba sí, se despliega hacia arriba.
            this.panel.arriba = (r.bottom + alto + 8 > window.innerHeight) && (r.top - alto - 8 > 0);
            this.panel.top = this.panel.arriba ? r.top - alto - 4 : r.bottom + 4;
            this.panel.left = r.left;
            this.panel.width = r.width;
        },
        abrir() {
            if ($el.dataset.disabled === '1') return;
            this.abierto = true;
            this.busqueda = '';
            this.resaltado = Math.max(0, this.filtradas.findIndex(o => this.seleccionada && String(o.valor) === String(this.seleccionada.valor)));
            this.$nextTick(() => {
                this.posicionar();
                (this.$refs.buscador ?? this.$refs.boton)?.focus();
                this.enfocarResaltado();
            });
        },
        cerrar() { this.abierto = false; this.busqueda = ''; this.resaltado = -1 },
        alternar() { this.abierto ? this.cerrar() : this.abrir() },
        elegir(opcion) { this.valor = opcion.valor; this.cerrar(); this.$refs.boton.focus() },
        limpiar() { this.valor = null; this.cerrar(); this.$refs.boton.focus() },
        mover(delta) {
            if (! this.abierto) { this.abrir(); return }
            const n = this.filtradas.length;
            if (n === 0) return;
            this.resaltado = (this.resaltado + delta + n) % n;
            this.enfocarResaltado();
        },
        enfocarResaltado() {
            this.$refs.lista?.children[this.resaltado]?.scrollIntoView({ block: 'nearest' });
        },
        confirmar() {
            if (! this.abierto) { this.abrir(); return }
            const opcion = this.filtradas[this.resaltado];
            if (! opcion) return;
            this.elegir(opcion);
            // Elegido con el teclado: el modal pasa el foco al campo siguiente (D55).
            this.$dispatch('ui-siguiente-campo');
        },
    }"
    x-on:keydown.down.prevent="mover(1)"
    x-on:keydown.up.prevent="mover(-1)"
    x-on:keydown.enter.prevent="confirmar()"
    x-on:keydown.escape.stop="cerrar()"
    x-on:click.outside="cerrar()"
    x-on:scroll.window.capture="if (abierto) posicionar()"
    x-on:resize.window="if (abierto) posicionar()"
    data-opciones="{{ json_encode($normalizadas, JSON_UNESCAPED_UNICODE) }}"
    data-disabled="{{ $disabled ? '1' : '0' }}"
    {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative w-full text-sm']) }}
>
    {{-- Control --}}
    <button type="button" x-ref="boton" x-on:click="alternar()"
            :aria-expanded="abierto" aria-haspopup="listbox" aria-controls="{{ $idLista }}"
            @disabled($disabled)
            class="flex w-full items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-gray-800 shadow-sm focus:outline-none focus:ring-1 disabled:bg-gray-50 disabled:text-gray-400 disabled:cursor-not-allowed {{ Tema::anilloFoco() }}">
        <span class="flex-1 truncate" :class="vacio && 'text-gray-400'"
              x-text="seleccionada ? seleccionada.texto : @js($placeholder)">{{ $placeholder }}</span>
        @if(! $disabled && $limpiable)
            <span x-show="! vacio" x-cloak x-on:click.stop="limpiar()" role="button" tabindex="-1"
                  class="flex shrink-0 items-center rounded p-0.5 text-gray-400 hover:text-gray-600" aria-label="{{ __('ui.combobox.limpiar') }}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </span>
        @endif
        <i class="fa-solid fa-chevron-down shrink-0 text-xs text-gray-400 transition-transform" :class="abierto && 'rotate-180'" aria-hidden="true"></i>
    </button>

    {{-- Desplegable: fixed para que no lo recorte el modal (z sobre todo) --}}
    <div x-ref="panel" x-show="abierto" x-cloak x-transition.opacity.duration.100ms
         :style="`top: ${panel.top}px; left: ${panel.left}px; width: ${panel.width}px`"
         class="fixed z-[80] rounded-md border border-gray-200 bg-white shadow-lg">
        @if($conBuscador)
            <div class="border-b border-gray-200 p-2">
                <input type="text" x-ref="buscador" x-model="busqueda" x-on:input="resaltado = 0"
                       placeholder="{{ __('ui.combobox.buscar') }}" autocomplete="off"
                       class="w-full rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-800 focus:outline-none focus:ring-1 {{ Tema::anilloFoco() }}">
            </div>
        @endif

        {{-- Tope de 7 filas: con más, la lista se iría de la pantalla --}}
        <ul x-ref="lista" id="{{ $idLista }}" role="listbox" class="max-h-[17.5rem] overflow-y-auto scroll-fino py-1">
            @if($vacio !== null)
                <li role="option" x-on:click="limpiar()" :aria-selected="vacio"
                    class="cursor-pointer px-3 py-2 text-gray-500 hover:bg-gray-100"
                    :class="vacio && 'font-medium'">
                    {{ $vacio }}
                </li>
            @endif

            <template x-for="(opcion, indice) in filtradas" :key="String(opcion.valor)">
                <li role="option"
                    x-on:click="elegir(opcion)"
                    x-on:mouseenter="resaltado = indice"
                    :aria-selected="seleccionada && String(seleccionada.valor) === String(opcion.valor)"
                    class="cursor-pointer px-3 py-2 text-gray-800"
                    :class="{
                        '{{ Tema::acentoFondoSuave() }}': resaltado === indice,
                        'font-medium': seleccionada && String(seleccionada.valor) === String(opcion.valor),
                    }"
                    x-text="opcion.texto"></li>
            </template>

            <li x-show="filtradas.length === 0" x-cloak class="px-3 py-2 text-gray-400">
                <span x-text="opciones.length === 0 ? @js(__('ui.combobox.sin_opciones')) : @js(__('ui.combobox.sin_resultados'))"></span>
            </li>
        </ul>
    </div>
</div>
