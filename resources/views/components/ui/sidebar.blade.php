{{--
    <x-ui.sidebar /> — D24 y estandares-ux.md §6 "Navegación y layout".

    Sidebar fijo a la izquierda en escritorio; en mobile es un drawer que abre
    la barra superior del layout. Espera vivir dentro de un x-data con
    `menuAbierto` (lo pone layouts/app.blade.php).

    Arriba: nombre y logo del taller (marca blanca, D8). Después los módulos
    que salen de App\Support\Modulos, ya filtrados por Route::has() y por rol,
    y al final los anunciados (D66): apagados, sin enlace, "Próximamente".
    Abajo de todo: el usuario, con un menú de Perfil y Cerrar sesión.
--}}
@php
    use App\Models\Configuracion;
    use App\Support\Modulos;
    use App\Support\Tema;

    $configuracion = Configuracion::instancia();
    $usuario = auth()->user();
    $modulos = Modulos::visibles($usuario);
    $anunciados = Modulos::anunciados();

    $claseItem = 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors';
    $claseInactivo = 'text-gray-700 hover:bg-gray-100 hover:text-gray-900';
    $claseActivo = Tema::acentoFondo();
@endphp

<aside
    class="fixed inset-y-0 left-0 z-50 flex w-64 max-w-[85vw] flex-col bg-white border-r border-gray-200 transform transition-transform duration-200 ease-in-out -translate-x-full md:translate-x-0"
    :class="{ 'translate-x-0': menuAbierto, '-translate-x-full': ! menuAbierto }"
    aria-label="{{ __('layout.menu.principal') }}"
    data-sidebar
>
    {{-- Taller --}}
    <div class="flex items-center gap-3 h-16 px-4 border-b border-gray-200 shrink-0">
        @if($configuracion->logo_path)
            <img src="{{ asset('storage/'.$configuracion->logo_path) }}" alt="" class="h-9 w-9 rounded object-contain shrink-0">
        @else
            <span class="flex items-center justify-center h-9 w-9 rounded shrink-0 {{ Tema::acentoFondo() }}">
                <i class="fa-solid fa-wrench" aria-hidden="true"></i>
            </span>
        @endif
        <span class="font-semibold text-gray-800 truncate" data-nombre-organizacion>{{ $configuracion->nombre }}</span>
        <button type="button" x-on:click="menuAbierto = false"
                class="md:hidden ml-auto -mr-1 p-1 rounded text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-400"
                aria-label="{{ __('layout.menu.cerrar') }}">
            <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
        </button>
    </div>

    {{-- Módulos --}}
    <nav class="flex-1 min-h-0 overflow-y-auto scroll-fino px-3 py-3 space-y-1">
        @foreach($modulos as $modulo)
            {{-- wire:navigate: cambiar de módulo no recarga la página (D31). --}}
            {{-- data-tecla: el layout escucha Alt+letra y navega a este enlace (D55). --}}
            <a href="{{ $modulo->url() }}" wire:navigate
               class="group {{ $claseItem }} {{ $modulo->activo() ? $claseActivo : $claseInactivo }}"
               @if($modulo->activo()) aria-current="page" @endif
               title="{{ $modulo->titulo() }}"
               data-modulo="{{ $modulo->nombre }}"
               @if($modulo->tecla) data-tecla="{{ $modulo->tecla }}" @endif>
                <i class="fa-solid {{ $modulo->icono }} w-5 text-center" aria-hidden="true"></i>
                <span class="truncate">{{ $modulo->etiqueta() }}</span>
                @php $notificaciones = $modulo->notificaciones(); @endphp
                @if($notificaciones !== null)
                    {{-- Circulito rojo (D60): lo que espera en el módulo. notificaciones.js lo actualiza en vivo. --}}
                    <span class="ml-auto inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[11px] font-semibold leading-none text-white tabular-nums"
                          data-notificacion="{{ $modulo->nombre }}" @if($notificaciones === 0) hidden @endif
                          title="{{ trans_choice('layout.notificaciones.'.$modulo->nombre, $notificaciones) }}">{{ $notificaciones }}</span>
                @endif
                @if($modulo->tecla)
                    <kbd class="{{ $notificaciones !== null ? 'ml-2' : 'ml-auto' }} hidden md:inline rounded border border-gray-200 px-1 text-[10px] font-mono text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" aria-hidden="true">Alt+{{ strtoupper($modulo->tecla) }}</kbd>
                @endif
            </a>
        @endforeach

        {{-- Anunciados (D66): el módulo todavía no existe; se ve apagado y no lleva a ningún lado. --}}
        @foreach($anunciados as $modulo)
            <span class="{{ $claseItem }} cursor-default text-gray-400"
                  title="{{ $modulo->titulo() }}"
                  aria-disabled="true"
                  data-modulo="{{ $modulo->nombre }}" data-anunciado>
                <i class="fa-solid {{ $modulo->icono }} w-5 text-center" aria-hidden="true"></i>
                <span class="truncate">{{ $modulo->etiqueta() }}</span>
                <span class="ml-auto rounded border border-gray-200 px-1.5 text-[10px] font-medium uppercase tracking-wide text-gray-400">{{ __('layout.proximamente') }}</span>
            </span>
        @endforeach
    </nav>

    {{-- Usuario --}}
    <div class="border-t border-gray-200 p-3 shrink-0" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.stop="abierto = false">
        <div x-show="abierto" x-cloak x-transition.opacity.duration.100ms
             class="mb-2 rounded-md border border-gray-200 bg-white shadow-lg py-1 text-sm" role="menu" data-menu-usuario>
            <a href="{{ route('profile') }}" wire:navigate role="menuitem"
               class="flex items-center gap-3 px-3 py-2 text-gray-700 hover:bg-gray-100">
                <i class="fa-solid fa-user w-5 text-center text-gray-400" aria-hidden="true"></i>
                {{ __('layout.usuario.perfil') }}
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" role="menuitem"
                        class="flex w-full items-center gap-3 px-3 py-2 text-left text-gray-700 hover:bg-gray-100">
                    <i class="fa-solid fa-right-from-bracket w-5 text-center text-gray-400" aria-hidden="true"></i>
                    {{ __('layout.usuario.cerrar_sesion') }}
                </button>
            </form>
        </div>

        <button type="button" x-on:click="abierto = ! abierto" :aria-expanded="abierto" aria-haspopup="menu"
                class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-300"
                aria-label="{{ __('layout.usuario.menu') }}">
            <span class="flex items-center justify-center h-9 w-9 rounded-full shrink-0 {{ Tema::acentoFondoSuave() }} font-semibold uppercase">
                {{ mb_substr($usuario->name, 0, 1) }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-medium text-gray-800 truncate" data-nombre-usuario>{{ $usuario->name }}</span>
                <span class="block text-xs text-gray-500 truncate">{{ $usuario->rol?->etiqueta() }}</span>
            </span>
            <i class="fa-solid fa-chevron-up text-xs text-gray-400 transition-transform" :class="abierto && 'rotate-180'" aria-hidden="true"></i>
        </button>
    </div>
</aside>
