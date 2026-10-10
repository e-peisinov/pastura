{{--
    <x-ui.saldo> — muestra un saldo con el color semántico de §6:
    rojo = debe (positivo), verde = a favor (negativo), gris = saldado.

    Props:
      monto  El saldo (positivo = debe).
      badge  true para mostrarlo como badge con rótulo; false, solo el número coloreado.
--}}
@props([
    'monto' => 0,
    'badge' => true,
])

@php
    use App\Support\Formato;

    $monto = round((float) $monto, 2);
    [$color, $rotulo, $texto] = match (true) {
        $monto > 0 => ['rojo', __('clientes.saldo.debe'), 'text-red-700'],
        $monto < 0 => ['verde', __('clientes.saldo.a_favor'), 'text-emerald-700'],
        default => ['gris', __('clientes.saldo.saldado'), 'text-gray-500'],
    };
@endphp

@if($badge)
    <span {{ $attributes->class(['inline-flex items-center gap-2 tabular-nums']) }} data-saldo="{{ $monto }}">
        <span class="font-medium {{ $texto }}">{{ Formato::moneda(abs($monto)) }}</span>
        <x-ui.badge :color="$color">{{ $rotulo }}</x-ui.badge>
    </span>
@else
    <span {{ $attributes->class(['font-medium tabular-nums', $texto]) }} data-saldo="{{ $monto }}">{{ Formato::moneda(abs($monto)) }}</span>
@endif
