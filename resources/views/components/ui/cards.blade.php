{{--
    <x-ui.cards> — estandares-ux.md §5.

    Vista mobile de un listado: una card por fila con la misma información que
    la tabla de escritorio. Se oculta desde md. El slot recibe el @forelse con
    las cards (<div class="bg-white border border-gray-200 rounded-lg p-4">).
--}}
<div {{ $attributes->class(['md:hidden p-4 space-y-4']) }}>
    {{ $slot }}
</div>
