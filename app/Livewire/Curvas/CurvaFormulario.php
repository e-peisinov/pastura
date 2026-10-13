<?php

namespace App\Livewire\Curvas;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use App\Support\Biomasa\RectaBiomasa;
use App\Support\Formato;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Alta y edición de una curva de biomasa (hijo de Curvas, P23): potrero,
 * estado, fecha de los cortes y las muestras (altura del disco y peso seco).
 * Mientras se cargan, muestra la recta y el gráfico con las muestras
 * completas. Se guarda con al menos 3 muestras y 2 alturas distintas. Una
 * curva nueva queda vigente y reemplaza a la que había para ese potrero y
 * ese estado (CurvaBiomasa::booted). Los números se escriben con coma
 * decimal (convenciones.md, "Localización").
 */
class CurvaFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    /** Filas vacías con las que arranca una curva nueva (Mateo corta unas 15). */
    public const FILAS_INICIALES = 5;

    public ?int $curvaId = null;

    public ?int $potrero_id = null;

    public ?int $estado_fenologico_id = null;

    public string $fecha = '';

    public ?string $observaciones = null;

    /** @var list<array{altura: string, peso: string}> */
    public array $muestras = [];

    public function mount(?int $curvaId = null, ?int $potreroId = null, ?int $estadoId = null): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        if ($curvaId !== null) {
            $curva = CurvaBiomasa::query()->with('muestras')->findOrFail($curvaId);

            $this->curvaId = $curva->id;
            $this->potrero_id = $curva->potrero_id;
            $this->estado_fenologico_id = $curva->estado_fenologico_id;
            $this->fecha = $curva->fecha->toDateString();
            $this->observaciones = $curva->observaciones;
            $this->muestras = $curva->muestras
                ->map(fn ($m) => ['altura' => self::aTexto($m->altura_cm), 'peso' => self::aTexto($m->peso_seco_g)])
                ->values()
                ->all();
        } else {
            // Desde el listado filtrado: el potrero y el estado que se estaban mirando.
            $this->potrero_id = $potreroId !== null && $this->potrerosElegibles()->has($potreroId) ? $potreroId : null;
            $this->estado_fenologico_id = $estadoId !== null && $this->estadosElegibles()->has($estadoId) ? $estadoId : null;
            $this->fecha = Carbon::today()->toDateString();
            $this->muestras = array_fill(0, self::FILAS_INICIALES, ['altura' => '', 'peso' => '']);
        }

        $this->marcarFormularioLimpio();
    }

    // ----- Muestras ----------------------------------------------------------

    public function agregarMuestra(): void
    {
        $this->muestras[] = ['altura' => '', 'peso' => ''];
    }

    public function quitarMuestra(int $indice): void
    {
        unset($this->muestras[$indice]);
        $this->muestras = array_values($this->muestras);
        $this->resetValidation();
    }

    // ----- Guardar -----------------------------------------------------------

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $datos = $this->validate([
            'potrero_id' => ['required', 'integer', Rule::in($this->potrerosElegibles()->keys()->all())],
            'estado_fenologico_id' => ['required', 'integer', Rule::in($this->estadosElegibles()->keys()->all())],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'potrero_id' => __('curvas.campos.potrero'),
            'estado_fenologico_id' => __('curvas.campos.estado'),
            'fecha' => __('curvas.campos.fecha'),
            'observaciones' => __('curvas.campos.observaciones'),
        ]);
        $muestras = $this->muestrasValidadas();

        $datos['observaciones'] = is_string($datos['observaciones']) && trim($datos['observaciones']) !== '' ? trim($datos['observaciones']) : null;
        $nueva = $this->curvaId === null;

        DB::transaction(function () use ($datos, $muestras, $nueva) {
            $curva = $nueva ? new CurvaBiomasa(['activo' => true]) : CurvaBiomasa::query()->with('muestras')->findOrFail($this->curvaId);
            $reemplazada = $nueva || $curva->activo ? $this->vigenteQueReemplaza() : null;
            $antes = $nueva ? null : $curva->recta()?->texto();

            $curva->fill($datos)->save();
            $curva->muestras()->delete();
            $curva->muestras()->createMany($muestras);

            // Las muestras no son columnas: el cambio de la recta se audita aparte (P18).
            $despues = $curva->load('muestras')->recta()?->texto();
            if (! $nueva && $antes !== $despues) {
                $curva->auditarCambios(['recta' => [$antes, $despues]]);
            }

            $this->toastExito(match (true) {
                ! $nueva => __('curvas.toasts.modificada'),
                $reemplazada !== null => __('curvas.toasts.creada_reemplaza', ['fecha' => Formato::fecha($reemplazada->fecha)]),
                default => __('curvas.toasts.creada'),
            });
        });

        $this->marcarFormularioLimpio();
        $this->dispatch('curva-guardada');
    }

    /**
     * Las filas completas, como números. Una fila vacía no cuenta; una a
     * medias, o fuera de rango, es un error en esa fila.
     *
     * @return list<array{altura_cm: float, peso_seco_g: float}>
     */
    private function muestrasValidadas(): array
    {
        $errores = [];
        $validas = [];

        foreach ($this->muestras as $i => $fila) {
            $altura = self::aNumero($fila['altura'] ?? '');
            $peso = self::aNumero($fila['peso'] ?? '');

            if ($altura === null && $peso === null) {
                continue;
            }

            if ($altura === null || $peso === null) {
                $errores["muestras.{$i}.".($altura === null ? 'altura' : 'peso')] = __('curvas.validacion.muestra_incompleta');

                continue;
            }

            if (! is_numeric($altura) || $altura < 0 || $altura > 999.9) {
                $errores["muestras.{$i}.altura"] = __('curvas.validacion.altura');
            }
            if (! is_numeric($peso) || $peso < 0.01 || $peso > 99999.99) {
                $errores["muestras.{$i}.peso"] = __('curvas.validacion.peso');
            }

            if (! isset($errores["muestras.{$i}.altura"]) && ! isset($errores["muestras.{$i}.peso"])) {
                $validas[] = ['altura_cm' => round((float) $altura, 1), 'peso_seco_g' => round((float) $peso, 2)];
            }
        }

        if ($errores === [] && count($validas) < CurvaBiomasa::MUESTRAS_MINIMAS) {
            $errores['muestras'] = __('curvas.validacion.pocas_muestras', ['minimo' => CurvaBiomasa::MUESTRAS_MINIMAS]);
        } elseif ($errores === [] && count(array_unique(array_column($validas, 'altura_cm'))) < 2) {
            $errores['muestras'] = __('curvas.validacion.alturas_iguales');
        }

        if ($errores !== []) {
            throw ValidationException::withMessages($errores);
        }

        return $validas;
    }

    /** La vigente de ese potrero y ese estado que esta curva dejaría reemplazada, si hay. */
    private function vigenteQueReemplaza(): ?CurvaBiomasa
    {
        $vigente = CurvaBiomasa::vigentePara($this->potrero_id, $this->estado_fenologico_id);

        return $vigente !== null && $vigente->id !== $this->curvaId ? $vigente : null;
    }

    // ----- Opciones ----------------------------------------------------------

    /**
     * Los potreros activos de campos activos, más el que la curva ya tenía.
     *
     * @return Collection<int, string> id => "Campo · Potrero"
     */
    private function potrerosElegibles(): Collection
    {
        $actual = $this->curvaId !== null ? CurvaBiomasa::query()->whereKey($this->curvaId)->value('potrero_id') : null;

        return Potrero::query()
            ->with('campo')
            ->where(fn ($q) => $q->where(fn ($q) => $q->where('activo', true)->whereHas('campo', fn ($c) => $c->where('activo', true)))
                ->when($actual !== null, fn ($q) => $q->orWhere('id', $actual)))
            ->get()
            ->mapWithKeys(fn (Potrero $p) => [$p->id => $p->nombreCompleto()])
            ->sort();
    }

    /**
     * Los estados activos, más el que la curva ya tenía.
     *
     * @return Collection<int, string> id => nombre
     */
    private function estadosElegibles(): Collection
    {
        $actual = $this->curvaId !== null ? CurvaBiomasa::query()->whereKey($this->curvaId)->value('estado_fenologico_id') : null;

        return EstadoFenologico::query()
            ->where(fn ($q) => $q->where('activo', true)->when($actual !== null, fn ($q) => $q->orWhere('id', $actual)))
            ->orderBy('id')
            ->pluck('nombre', 'id');
    }

    // ----- Números con coma ----------------------------------------------------

    /** "12,5" → "12.5"; vacío → null. Lo que no es número llega tal cual y se rechaza. */
    private static function aNumero(string $texto): ?string
    {
        $texto = trim($texto);

        return $texto === '' ? null : str_replace(',', '.', $texto);
    }

    /** 12.5 → "12,5"; 14.0 → "14". */
    private static function aTexto(float $valor): string
    {
        return str_replace('.', ',', rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.'));
    }

    // ----- Cierre y render ---------------------------------------------------

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['potrero_id', 'estado_fenologico_id', 'fecha', 'observaciones', 'muestras'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-curva-formulario');
    }

    public function render()
    {
        $area = Configuracion::instancia()->area_disco_m2;

        // La vista previa: solo las filas completas y con números.
        $puntos = collect($this->muestras)
            ->map(fn ($f) => [self::aNumero($f['altura'] ?? ''), self::aNumero($f['peso'] ?? '')])
            ->filter(fn ($p) => is_numeric($p[0]) && is_numeric($p[1]) && $p[1] > 0)
            ->map(fn ($p) => [(float) $p[0], RectaBiomasa::kgMsHaDeMuestra((float) $p[1], $area)])
            ->values();

        $reemplaza = $this->curvaId === null || CurvaBiomasa::query()->whereKey($this->curvaId)->value('activo')
            ? $this->vigenteQueReemplaza()
            : null;

        return view('livewire.curvas.curva-formulario', [
            'potreros' => $this->potrerosElegibles()->all(),
            'estados' => $this->estadosElegibles()->all(),
            'area' => $area,
            'puntos' => $puntos->all(),
            'recta' => RectaBiomasa::ajustar($puntos),
            'reemplaza' => $reemplaza,
            'zDescarte' => self::Z_DESCARTE,
        ]);
    }
}
