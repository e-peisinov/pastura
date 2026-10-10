{{--
    <x-ui.tabla> — estandares-ux.md §5.

    Vista de escritorio de un listado. Se oculta en mobile: la respuesta mobile
    es <x-ui.cards> con la misma información, nunca una tabla recortada ni con
    scroll horizontal. El slot recibe <thead> y <tbody> directamente.
--}}
<div {{ $attributes->class(['hidden md:block overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-gray-200 text-sm text-gray-800">
        {{ $slot }}
    </table>
</div>
