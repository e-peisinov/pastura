{{--
    <x-ui.opciones> — un grupo de opciones excluyentes (radio) dibujado como
    botones segmentados: para elegir entre 2 y 4 valores fijos de un vistazo
    (el nivel de permiso de un módulo: Sin acceso / Ver / Editar).

    Es un <input type="radio"> por opción, escondido (sr-only) con `peer`, y
    la opción elegida se pinta con el acento del tema vía Tema::opcionElegida()
    (clases literales, §7). Respeta wire:model tal cual: no hay round-trip
    para marcar la elegida.

    Props:
      opciones  array asociativo valor => texto, o lista de enums con etiqueta().
      nombre    Nombre del grupo (atributo name de los radios y prefijo del id).
      disabled  Deshabilita el grupo.
--}}
@props([
    'opciones' => [],
    'nombre' => null,
    'disabled' => false,
])

@php
    use App\Support\Tema;
    use Illuminate\Support\Str;

    $normalizadas = [];
    foreach ($opciones as $valor => $texto) {
        if ($texto instanceof \BackedEnum) {
            $normalizadas[$texto->value] = method_exists($texto, 'etiqueta') ? $texto->etiqueta() : $texto->name;
        } else {
            $normalizadas[$valor] = $texto;
        }
    }

    $grupo = $nombre ?? 'opciones-'.Str::random(6);
    $claseOpcion = 'block cursor-pointer select-none px-3 py-1.5 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors '
        .'peer-focus-visible:ring-2 peer-focus-visible:ring-inset peer-disabled:cursor-not-allowed peer-disabled:opacity-60 '
        .Tema::opcionElegida();
@endphp

<div role="radiogroup" data-opciones="{{ $grupo }}"
     {{ $attributes->except(['wire:model', 'wire:model.live'])->class(['inline-flex max-w-full rounded-md border border-gray-300 overflow-hidden divide-x divide-gray-300']) }}>
    @foreach($normalizadas as $valor => $texto)
        <label class="relative min-w-0">
            <input type="radio" name="{{ $grupo }}" value="{{ $valor }}" id="campo-{{ $grupo }}-{{ $valor }}"
                   class="peer sr-only" @disabled($disabled)
                   {{ $attributes->only(['wire:model', 'wire:model.live']) }}>
            <span class="{{ $claseOpcion }}">{{ $texto }}</span>
        </label>
    @endforeach
</div>
