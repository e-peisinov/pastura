{{--
    Layout de las pantallas de acceso (D52): login, recuperación y
    restablecimiento de contraseña. Mismo chrome que la aplicación (fondo
    gris, Figtree, acento del tema) con la marca de la organización arriba, como en
    el sidebar (D8). Nada del scaffolding de Breeze.

    Slot: default (título y formulario de la pantalla).
--}}
@php
    use App\Models\Configuracion;
    use App\Support\Tema;

    $configuracion = Configuracion::instancia();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $configuracion->nombre }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-gray-50 text-gray-800">
        <div class="min-h-screen flex flex-col items-center justify-center px-4 py-10" data-pantalla-acceso>
            {{-- Marca de la organización --}}
            <a href="{{ route('inicio') }}" wire:navigate class="flex flex-col items-center gap-3 text-center focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-400 rounded-md">
                @if($configuracion->logo_path)
                    <img src="{{ asset('storage/'.$configuracion->logo_path) }}" alt="" class="h-16 w-16 rounded-lg object-contain">
                @else
                    <span class="flex items-center justify-center h-16 w-16 rounded-lg text-2xl {{ Tema::acentoFondo() }}">
                        <i class="fa-solid fa-seedling" aria-hidden="true"></i>
                    </span>
                @endif
                <span class="text-xl font-semibold text-gray-800" data-nombre-organizacion>{{ $configuracion->nombre }}</span>
            </a>

            {{-- Tarjeta --}}
            {{-- Tarjeta: cada pantalla pone su título y su formulario adentro --}}
            <div class="w-full sm:max-w-md mt-6 p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                {{ $slot }}
            </div>
        </div>

        {{-- Toasts: los mensajes de las pantallas de acceso también son toast (regla 3) --}}
        <x-ui.toasts />
    </body>
</html>
