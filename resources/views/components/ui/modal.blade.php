{{--
    <x-ui.modal> — estandares-ux.md §1.

    El estado (abierto/cerrado) vive en el componente Livewire: se envuelve en
    @if($showModal). Este componente garantiza:
      - panel anclado arriba (no centrado) que nunca supera el viewport: header
        y footer fijos, solo el body scrollea. Anclarlo arriba deja lugar abajo
        para que los desplegables de un modal chico no cuelguen a mitad de pantalla;
      - scroll-lock del fondo mientras está abierto;
      - cierre por X y Escape, los dos por la misma acción Livewire (`cerrar`),
        y Escape solo cierra el modal de más arriba. El click en el backdrop
        NO cierra (D55): un click afuera sin querer no puede tirar un
        formulario a medio cargar;
      - teclado primero (D55): al abrir, el foco va al primer campo del body;
        Enter en un campo pasa al siguiente (en el último, guarda); F2 guarda
        desde cualquier campo. Enter en un textarea sigue siendo salto de
        línea y en un botón sigue siendo click. Los desplegables (combobox,
        selectores) resuelven su propio Enter y avisan con el evento
        `ui-siguiente-campo` cuando eligieron algo con el teclado;
      - header con el gradiente de acento del tema (Tema::gradienteHeader()).

    Props:
      titulo   Título del header.
      icono    Nombre del icono ("fa-plus"). Se antepone fa-solid salvo que ya
               traiga un estilo (fa-regular, fa-brands).
      cerrar   Acción Livewire de cierre. Por omisión "closeModal".
      ancho    auto (por omisión, ajustado al contenido con tope de 72rem) |
               sm | md | lg | xl | 2xl | 3xl | 4xl. Los dos últimos son para
               los modales que llevan una tabla adentro (detalle de una orden).
      submit   Acción Livewire: envuelve body + footer en <form wire:submit.prevent>.
      z        50 (por omisión) | 60 | 70 para modales apilados.

    Slots: default (body), `footer` y `encabezado` (al lado del título, para
    badges de estado; envuelve a la línea de abajo si no entra).
--}}
@props([
    'titulo' => '',
    'icono' => null,
    'cerrar' => 'closeModal',
    'ancho' => 'auto',
    'submit' => null,
    'z' => 50,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Str;

    // Clases literales: nada se interpola (§7, trampa 7).
    $clasesAncho = [
        'auto' => 'w-fit max-w-full md:max-w-6xl',
        'sm' => 'w-full max-w-sm',
        'md' => 'w-full max-w-md',
        'lg' => 'w-full max-w-lg',
        'xl' => 'w-full max-w-xl',
        '2xl' => 'w-full max-w-2xl',
        '3xl' => 'w-full max-w-3xl',
        '4xl' => 'w-full max-w-4xl',
    ][$ancho] ?? 'w-fit max-w-full md:max-w-6xl';

    $claseZ = [
        50 => 'z-50',
        60 => 'z-[60]',
        70 => 'z-[70]',
    ][(int) $z] ?? 'z-50';

    if ($icono !== null && ! Str::startsWith($icono, ['fa-solid', 'fa-regular', 'fa-brands'])) {
        $icono = 'fa-solid '.$icono;
    }

    $idTitulo = 'modal-titulo-'.Str::random(6);
@endphp

<div
    x-data="{
        cerrar() { $wire.{{ $cerrar }}() },
        esElDeArriba() {
            const modales = document.querySelectorAll('[aria-modal=&quot;true&quot;]');
            return modales.length > 0 && modales[modales.length - 1] === $el;
        },
        init() {
            document.body.classList.add('overflow-hidden');
            this.enfocarPrimero();
        },
        // ----- Teclado primero (D55) -----------------------------------------
        // Los controles del body que reciben foco, en orden de documento y
        // solo los visibles (el buscador de un desplegable cerrado no cuenta).
        campos() {
            const selector = 'input:not([type=hidden]):not([disabled]), textarea:not([disabled]), select:not([disabled]), button[aria-haspopup=listbox]:not([disabled]), [data-campo-foco]';
            return Array.from($refs.cuerpo.querySelectorAll(selector))
                .filter(el => el.tabIndex >= 0 && el.getClientRects().length > 0);
        },
        enfocarPrimero() {
            this.$nextTick(() => {
                if (document.activeElement && $refs.cuerpo.contains(document.activeElement)) return;
                this.campos()[0]?.focus();
            });
        },
        // Mueve el foco al primer campo posterior a `desde` (sin contar los
        // que estén adentro de `desde`). Devuelve false si era el último.
        siguienteCampo(desde) {
            const siguiente = this.campos().find(campo =>
                campo !== desde
                && ! desde.contains(campo)
                && (desde.compareDocumentPosition(campo) & Node.DOCUMENT_POSITION_FOLLOWING)
            );
            if (! siguiente) return false;
            siguiente.focus();
            if (siguiente.select && ['text', 'number', 'search', 'tel', 'email', 'url'].includes(siguiente.type)) siguiente.select();
            return true;
        },
        // Enter en un campo del body: al siguiente; en el último, guardar.
        enterTabea(evento) {
            if (evento.defaultPrevented || evento.shiftKey || evento.ctrlKey || evento.altKey || evento.isComposing) return;
            const campo = evento.target;
            if (! (campo instanceof Element) || ! $refs.cuerpo.contains(campo)) return;
            if (['TEXTAREA', 'BUTTON', 'A', 'SELECT'].includes(campo.tagName) || campo.type === 'submit') return;
            evento.preventDefault();
            if (! this.siguienteCampo(campo)) this.guardar();
        },
        // Con submit, envía el form; sin form, el botón marcado data-atajo-guardar.
        guardar() {
            if ($refs.formulario) { $refs.formulario.requestSubmit(); return }
            $el.querySelector('[data-atajo-guardar]')?.click();
        },
        destroy() {
            // Solo se libera el scroll cuando no queda ningún OTRO modal
            // abierto. Se excluye este ($el), que puede seguir en el DOM o
            // haber salido ya según cómo lo haya cerrado Livewire: contar sin
            // filtrar dejaba el fondo scrolleando con un modal todavía abierto.
            const otros = Array.from(document.querySelectorAll('[aria-modal=&quot;true&quot;]'))
                .filter(modal => modal !== $el && document.body.contains(modal));

            if (otros.length === 0) {
                document.body.classList.remove('overflow-hidden');
            }
        },
    }"
    x-on:keydown.escape.window="if (esElDeArriba()) cerrar()"
    x-on:keydown.window="if ($event.key === 'F2' && esElDeArriba()) { $event.preventDefault(); guardar() }"
    x-on:keydown.enter="enterTabea($event)"
    x-on:ui-siguiente-campo="$event.stopPropagation(); if (! siguienteCampo($event.target)) $event.target.blur()"
    role="dialog"
    aria-modal="true"
    aria-labelledby="{{ $idTitulo }}"
    class="fixed inset-0 {{ $claseZ }} flex items-start justify-center p-4 sm:py-10 bg-black/50"
>
    <div
        {{ $attributes->class(['flex flex-col max-h-full bg-white rounded-lg shadow-xl', $clasesAncho]) }}
        x-on:click.stop
    >
        {{-- Header: siempre visible --}}
        <div class="flex items-center justify-between gap-4 px-4 py-3 sm:px-6 rounded-t-lg bg-gradient-to-r {{ Tema::gradienteHeader() }} text-white shrink-0">
            <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-1">
                <h2 id="{{ $idTitulo }}" class="flex items-center gap-2 text-base sm:text-lg font-semibold truncate">
                    @if($icono)
                        <i class="{{ $icono }}" aria-hidden="true"></i>
                    @endif
                    <span class="truncate">{{ $titulo }}</span>
                </h2>
                @isset($encabezado)
                    <div class="flex items-center gap-2 shrink-0">{{ $encabezado }}</div>
                @endisset
            </div>
            <button type="button" x-on:click="cerrar()"
                    class="shrink-0 rounded-md p-1 text-white/80 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60"
                    aria-label="{{ __('ui.modal.cerrar') }}">
                <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
            </button>
        </div>

        @if($submit)
            {{-- display: contents deja que el body siga siendo el único que scrollea --}}
            <form wire:submit.prevent="{{ $submit }}" x-ref="formulario" class="contents">
        @endif

            {{-- Body: lo único que scrollea --}}
            <div x-ref="cuerpo" class="flex-1 min-h-0 overflow-y-auto scroll-fino px-4 py-4 sm:px-6 space-y-4 text-gray-800">
                {{ $slot }}
            </div>

            {{-- Footer: siempre visible --}}
            @isset($footer)
                {{-- Envuelve en vez de apilar: en mobile los botones cortos
                     entran en una fila y el footer no se come el body. --}}
                <div {{ $footer->attributes->class(['flex flex-wrap items-center justify-end gap-2 px-4 py-3 sm:px-6 border-t border-gray-200 bg-gray-50 rounded-b-lg shrink-0']) }}>
                    {{ $footer }}
                </div>
            @endisset

        @if($submit)
            </form>
        @endif
    </div>
</div>
