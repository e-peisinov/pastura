{{--
    <x-ui.grafico-dispersion> — estandares-ux.md §11. La relación entre dos
    medidas: puntos (x, y) y, si la hay, la recta que los ajusta. Sin
    JavaScript: se redibuja con Livewire y entra en mobile sin scroll.

      <x-ui.grafico-dispersion :puntos="[[5, 800], [10, 1400]]" :recta="['a' => 108, 'b' => 250]"
                               eje-x="Altura (cm)" eje-y="kg MS/ha" />

    Props:
      puntos   Lista de [x, y] (números >= 0).
      recta    ['a' => pendiente, 'b' => ordenada] o null. Se dibuja acotada en
               cero (los kg nunca son negativos), de 0 al techo del eje x.
      ejeX     Rótulo del eje horizontal.
      ejeY     Rótulo del eje vertical.
      nombrePuntos  Cómo se llaman los puntos en la leyenda (por omisión, "Puntos").

    Los dos ejes arrancan en cero con un paso redondo (10 cm, 1.000 kg). Los
    puntos van en azul y la recta en naranja (§11, regla 3). Cada punto lleva
    su title con los dos valores; la lista que acompaña al gráfico es la
    tabla (regla 6).
--}}
@props([
    'puntos' => [],
    'recta' => null,
    'ejeX' => '',
    'ejeY' => '',
    'nombrePuntos' => null,
])

@php
    use App\Support\Formato;

    $puntos = array_values(array_filter($puntos, fn ($p) => is_numeric($p[0] ?? null) && is_numeric($p[1] ?? null)));

    $maxX = 0.0;
    $maxY = 0.0;
    foreach ($puntos as [$x, $y]) {
        $maxX = max($maxX, (float) $x);
        $maxY = max($maxY, (float) $y);
    }
    // Ejes con paso redondo (1, 2, 2,5 o 5 por una potencia de 10) y de 3 a 6
    // marcas: 0 / 10 / 20 / 30 cm, no 0 / 12,5 / 25 / 37,5 / 50 (Escala, de 4 fijas).
    $eje = function (float $maximo): array {
        if ($maximo <= 0) {
            return [1.0, [0.0, 1.0]];
        }
        $crudo = $maximo / 4;
        $potencia = 10 ** floor(log10($crudo));
        $paso = 10 * $potencia;
        foreach ([1, 2, 2.5, 5] as $factor) {
            if ($crudo <= $factor * $potencia) {
                $paso = $factor * $potencia;
                break;
            }
        }
        $techo = ceil($maximo / $paso - 1e-9) * $paso;

        return [$techo, array_map(fn (int $i) => $i * $paso, range(0, (int) round($techo / $paso)))];
    };

    [$techoX, $marcasX] = $eje($maxX);
    // La recta se dibuja hasta el techo de x: el de y tiene que alcanzarla.
    if ($recta !== null) {
        $maxY = max($maxY, $recta['a'] * $techoX + $recta['b']);
    }
    [$techoY, $marcasY] = $eje($maxY);
    $marcasY = array_reverse($marcasY);

    $pctX = fn (float $x) => round(min(100, max(0, $x / $techoX * 100)), 2);
    $pctY = fn (float $y) => round(min(100, max(0, $y / $techoY * 100)), 2);

    // La recta de 0 al techo de x, acotada en cero: si corta el eje, arranca donde
    // se hace positiva. Coordenadas del svg: 0..100, con y hacia abajo.
    $tramo = null;
    if ($recta !== null) {
        $desde = $recta['b'] >= 0 || $recta['a'] <= 0 ? 0.0 : min($techoX, -$recta['b'] / $recta['a']);
        $valor = fn (float $x) => max(0.0, $recta['a'] * $x + $recta['b']);
        $tramo = [
            [$pctX($desde), 100 - $pctY($valor($desde))],
            [$pctX($techoX), 100 - $pctY($valor($techoX))],
        ];
    }

    $numero = fn (float $v) => rtrim(rtrim(Formato::numero($v, 1), '0'), ',');
@endphp

<figure {{ $attributes }} data-grafico-dispersion>
    <div class="flex gap-2">
        {{-- Eje y --}}
        <div class="flex h-56 w-12 shrink-0 flex-col justify-between text-right text-[11px] leading-none text-gray-400 tabular-nums" aria-hidden="true">
            @foreach($marcasY as $marca)
                <span>{{ $numero($marca) }}</span>
            @endforeach
        </div>

        <div class="min-w-0 flex-1">
            <div class="relative h-56">
                {{-- Líneas de referencia, discretas --}}
                <div class="absolute inset-0 flex flex-col justify-between" aria-hidden="true">
                    @foreach($marcasY as $marca)
                        <span class="block h-px w-full {{ $loop->last ? 'bg-gray-300' : 'bg-gray-100' }}"></span>
                    @endforeach
                </div>

                @if($tramo !== null)
                    <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" data-recta>
                        <line x1="{{ $tramo[0][0] }}" y1="{{ $tramo[0][1] }}" x2="{{ $tramo[1][0] }}" y2="{{ $tramo[1][1] }}"
                              stroke="#eb6834" stroke-width="2" vector-effect="non-scaling-stroke" />
                    </svg>
                @endif

                @foreach($puntos as [$x, $y])
                    <span class="absolute h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full bg-[#2a78d6] ring-2 ring-white"
                          style="left: {{ $pctX((float) $x) }}%; bottom: {{ $pctY((float) $y) }}%"
                          title="{{ $numero((float) $x) }} · {{ Formato::numero((float) $y) }}" data-punto></span>
                @endforeach
            </div>

            {{-- Eje x --}}
            <div class="mt-1 flex justify-between text-[11px] leading-none text-gray-400 tabular-nums" aria-hidden="true">
                @foreach($marcasX as $marca)
                    <span>{{ $numero($marca) }}</span>
                @endforeach
            </div>
        </div>
    </div>

    <figcaption class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-gray-500">
        <span>{{ $ejeY }} / {{ $ejeX }}</span>
        <span class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-[#2a78d6]" aria-hidden="true"></span>{{ $nombrePuntos ?? __('ui.grafico.puntos') }}
            </span>
            @if($tramo !== null)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-0.5 w-4 bg-[#eb6834]" aria-hidden="true"></span>{{ __('ui.grafico.recta') }}
                </span>
            @endif
        </span>
    </figcaption>
</figure>
