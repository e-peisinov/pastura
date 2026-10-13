{{--
    Instrucciones (P25). Índice de las secciones visibles (arriba en mobile,
    fijo a la izquierda en escritorio) y una tarjeta por sección. Los bloques
    salen de lang/es/instrucciones.php: texto, pasos, lista y nota.
--}}
@php
    use App\Livewire\Instrucciones\Instrucciones;
    use App\Support\Tema;
    use Illuminate\Support\Str;

    // **negrita** de los textos de lang; nada de HTML.
    $md = fn (string $texto) => Str::inlineMarkdown(
        str_replace(':url', $urlApp, $texto),
        ['html_input' => 'escape', 'allow_unsafe_links' => false],
    );
@endphp

<div>
    <x-ui.pagina :titulo="__('instrucciones.titulo')" icono="fa-circle-question">
        <div class="lg:flex lg:items-start lg:gap-6">
            {{-- Índice --}}
            <nav id="indice" class="mb-6 lg:mb-0 lg:sticky lg:top-4 lg:w-64 lg:shrink-0 bg-white border border-gray-200 rounded-lg p-4 scroll-mt-4"
                 aria-label="{{ __('instrucciones.indice') }}" data-indice>
                <p class="text-sm text-gray-500">{{ __('instrucciones.intro') }}</p>
                @foreach($grupos as $grupo => $secciones)
                    <p class="mt-4 text-xs font-medium uppercase tracking-wide text-gray-500">{{ __('instrucciones.grupos.'.$grupo) }}</p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach($secciones as $seccion)
                            <li>
                                <a href="#{{ $seccion['id'] }}" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900">
                                    <i class="fa-solid {{ $seccion['icono'] }} w-4 text-center {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                                    {{ __('instrucciones.secciones.'.$seccion['id'].'.titulo') }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            {{-- Secciones --}}
            <div class="min-w-0 flex-1 space-y-6">
                @foreach($grupos as $grupo => $secciones)
                    @foreach($secciones as $seccion)
                        <section id="{{ $seccion['id'] }}" class="bg-white border border-gray-200 rounded-lg scroll-mt-4" data-seccion="{{ $seccion['id'] }}">
                            <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-200">
                                <h2 class="flex items-center gap-2 font-semibold text-gray-800">
                                    <i class="fa-solid {{ $seccion['icono'] }} {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                                    {{ __('instrucciones.secciones.'.$seccion['id'].'.titulo') }}
                                </h2>
                                <a href="#indice" class="lg:hidden shrink-0 text-gray-400 hover:text-gray-600" title="{{ __('instrucciones.volver_arriba') }}">
                                    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
                                    <span class="sr-only">{{ __('instrucciones.volver_arriba') }}</span>
                                </a>
                            </header>
                            <div class="space-y-4 p-4 text-sm leading-relaxed text-gray-700">
                                @foreach(__('instrucciones.secciones.'.$seccion['id'].'.bloques') as $bloque)
                                    @switch($bloque['tipo'])
                                        @case('texto')
                                            <p>{!! $md($bloque['texto']) !!}</p>
                                            @break
                                        @case('nota')
                                            <p class="flex gap-2 rounded-md border {{ Tema::acentoBorde() }} {{ Tema::acentoFondoSuave() }} px-3 py-2 text-gray-800">
                                                <i class="fa-solid fa-circle-info mt-1 {{ Tema::acentoTexto() }}" aria-hidden="true"></i>
                                                <span>{!! $md($bloque['texto']) !!}</span>
                                            </p>
                                            @break
                                        @default
                                            <div>
                                                @isset($bloque['titulo'])
                                                    <h3 class="mb-2 font-semibold text-gray-800">{{ $bloque['titulo'] }}</h3>
                                                @endisset
                                                <{{ $bloque['tipo'] === 'pasos' ? 'ol' : 'ul' }} class="{{ $bloque['tipo'] === 'pasos' ? 'list-decimal' : 'list-disc' }} space-y-1.5 pl-5 marker:text-gray-400">
                                                    @foreach($bloque['items'] as $item)
                                                        <li>{!! $md($item) !!}</li>
                                                    @endforeach
                                                </{{ $bloque['tipo'] === 'pasos' ? 'ol' : 'ul' }}>
                                            </div>
                                    @endswitch
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                @endforeach
            </div>
        </div>
    </x-ui.pagina>
</div>
