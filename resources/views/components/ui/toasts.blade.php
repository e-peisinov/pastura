{{--
    <x-ui.toasts /> — estandares-ux.md §3.

    Se monta UNA sola vez, en resources/views/layouts/app.blade.php. Recibe:
      - el evento de navegador `toast` que dispara el trait EnviaToasts
        (detail: { tipo, mensaje });
      - los flashes de sesión de un redirect de página completa, con las claves
        message | success | ok (éxito), info y error. No inventar claves nuevas.

    Auto-dismiss: éxito e info 4 s, aviso 6 s, error 8 s. Siempre con botón de
    cierre. La salida marca el toast como `saliendo`, corre la animación y recién
    después lo saca del array (§7, trampa 5).
--}}
@php
    $flashes = [];
    foreach (['success' => 'exito', 'message' => 'exito', 'ok' => 'exito', 'info' => 'info', 'error' => 'error'] as $clave => $tipo) {
        if (session()->has($clave) && filled(session($clave))) {
            $flashes[] = ['tipo' => $tipo, 'mensaje' => (string) session($clave)];
        }
    }
@endphp

<div
    x-data="{
        toasts: [],
        contador: 0,
        duraciones: { exito: 4000, info: 4000, aviso: 6000, error: 8000 },
        agregar(detalle) {
            const tipo = ['exito', 'error', 'info', 'aviso'].includes(detalle.tipo) ? detalle.tipo : 'info';
            const id = ++this.contador;
            this.toasts.push({ id, tipo, mensaje: detalle.mensaje ?? '', visible: false, saliendo: false });
            // Se toma el objeto de vuelta del array: el literal recién creado
            // no es reactivo, y cambiarle `visible` no dispararía la animación
            // de entrada (el toast quedaba con opacidad 0, invisible).
            const toast = this.toasts.find(t => t.id === id);
            requestAnimationFrame(() => { toast.visible = true });
            toast.temporizador = setTimeout(() => this.cerrar(id), this.duraciones[tipo]);
        },
        cerrar(id) {
            const toast = this.toasts.find(t => t.id === id);
            if (! toast || toast.saliendo) return;
            clearTimeout(toast.temporizador);
            toast.saliendo = true;
            // Recién cuando terminó la animación se saca del array.
            setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id) }, 300);
        },
        init() {
            // Flashes de sesión de un redirect de página completa.
            JSON.parse($el.dataset.flashes).forEach(flash => this.agregar(flash));
        },
    }"
    x-on:toast.window="agregar($event.detail)"
    class="fixed top-4 right-4 left-4 sm:left-auto sm:w-96 z-[80] flex flex-col gap-2 pointer-events-none"
    aria-live="polite"
    data-toasts
    data-flashes="{{ json_encode($flashes, JSON_UNESCAPED_UNICODE) }}"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            role="status"
            class="pointer-events-auto flex items-start gap-3 rounded-lg shadow-lg px-4 py-3 text-white text-sm transition-all duration-300 ease-out"
            :class="{
                'bg-emerald-600': toast.tipo === 'exito',
                'bg-red-600': toast.tipo === 'error',
                'bg-blue-600': toast.tipo === 'info',
                'bg-amber-600': toast.tipo === 'aviso',
                'opacity-100 translate-x-0': toast.visible && ! toast.saliendo,
                'opacity-0 translate-x-8': ! toast.visible || toast.saliendo,
            }"
        >
            <i class="fa-solid mt-0.5 shrink-0" aria-hidden="true"
               :class="{
                   'fa-circle-check': toast.tipo === 'exito',
                   'fa-circle-xmark': toast.tipo === 'error',
                   'fa-circle-info': toast.tipo === 'info',
                   'fa-triangle-exclamation': toast.tipo === 'aviso',
               }"></i>
            <p class="flex-1 break-words" x-text="toast.mensaje"></p>
            <button type="button" x-on:click="cerrar(toast.id)"
                    class="shrink-0 -m-1 p-1 rounded text-white/80 hover:text-white hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white/60"
                    aria-label="{{ __('ui.toasts.cerrar') }}">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </div>
    </template>
</div>
