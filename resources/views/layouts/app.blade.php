{{--
    Layout de la aplicación (D24): sidebar propio a la izquierda, barra
    superior mínima solo en mobile (hamburguesa + nombre de la organización) y el
    contenido a la derecha. Sin nada del scaffolding de Breeze.

    Slots: default (contenido) y `header` opcional (título de la página).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ \App\Models\Configuracion::instancia()->nombre }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-800">
        {{-- Alt+letra navega al módulo del sidebar con esa data-tecla (D55).
             Solo los enlaces que el usuario ve, así los permisos mandan igual. --}}
        <div x-data="{
                menuAbierto: false,
                atajoModulo(evento) {
                    if (! evento.altKey || evento.ctrlKey || evento.metaKey || evento.shiftKey || evento.repeat) return;
                    const letra = /^Key([A-Z])$/.exec(evento.code)?.[1]?.toLowerCase();
                    if (! letra) return;
                    const enlace = document.querySelector('[data-sidebar] a[data-tecla=&quot;' + letra + '&quot;]');
                    if (! enlace) return;
                    evento.preventDefault();
                    this.menuAbierto = false;
                    Livewire.navigate(enlace.href);
                },
             }"
             x-on:keydown.window="atajoModulo($event)"
             x-on:keydown.escape.window="menuAbierto = false"
             x-on:resize.window.debounce.250ms="if (window.innerWidth >= 768) menuAbierto = false"
             class="min-h-screen">

            {{-- Barra superior: solo mobile --}}
            <header class="md:hidden sticky top-0 z-30 flex items-center gap-3 h-14 px-4 bg-white border-b border-gray-200" data-barra-mobile>
                <button type="button" x-on:click="menuAbierto = true"
                        class="p-2 -ml-2 rounded-md text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-gray-400"
                        aria-label="{{ __('layout.menu.abrir') }}" aria-controls="sidebar" :aria-expanded="menuAbierto">
                    <i class="fa-solid fa-bars text-lg" aria-hidden="true"></i>
                </button>
                <span class="font-semibold text-gray-800 truncate">{{ \App\Models\Configuracion::instancia()->nombre }}</span>
            </header>

            {{-- Backdrop del drawer mobile --}}
            <div x-show="menuAbierto" x-cloak x-transition.opacity.duration.200ms
                 x-on:click="menuAbierto = false"
                 class="fixed inset-0 z-40 bg-black/50 md:hidden" aria-hidden="true" data-backdrop-menu></div>

            <x-ui.sidebar id="sidebar" />

            {{-- Contenido --}}
            <div class="md:pl-64 flex flex-col min-h-screen">
                @isset($header)
                    <header class="flex bg-white border-b border-gray-200 md:min-h-16">
                        <div class="flex flex-1 items-center px-4 py-4 sm:px-6 lg:px-8 md:py-2">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main class="flex-1 min-w-0">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Toasts: se montan una sola vez, acá (estandares-ux.md §3) --}}
        <x-ui.toasts />
    </body>
</html>
