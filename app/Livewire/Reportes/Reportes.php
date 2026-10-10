<?php

namespace App\Livewire\Reportes;

use App\Models\Campo;
use App\Models\Potrero;
use App\Support\Formato;
use App\Support\Reportes\ExportadorReportes;
use App\Support\Reportes\Reportes as Calculos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reportes (P18, P21; molde: el módulo 39 del kit): una pantalla de solo
 * lectura con el período arriba y, abajo, el resumen contra el período
 * anterior, la evolución de un potrero con su crecimiento, los potreros de
 * un campo, la disponibilidad forrajera por campo y la actividad por
 * recorredor. Los números salen de App\Support\Reportes\Reportes; acá solo
 * se elige el período, el campo y el potrero, y se arma lo que dibujan los
 * gráficos.
 *
 * Todo viaja en la URL, así un reporte se comparte o se guarda. Por
 * omisión, los últimos tres meses, el primer campo y su potrero más
 * recorrido. Sale en CSV y en PDF; exportar es leer: alcanza con Ver.
 */
#[Layout('layouts.app')]
class Reportes extends Component
{
    /** Atajos de período: clave => meses hacia atrás, contando el actual. */
    public const ATAJOS = ['mes' => 1, 'trimestre' => 3, 'semestre' => 6, 'anio' => 12];

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    #[Url(as: 'campo', except: null)]
    public ?int $campo = null;

    #[Url(as: 'potrero', except: null)]
    public ?int $potrero = null;

    public function mount(): void
    {
        if ($this->desde === '' && $this->hasta === '') {
            $this->atajo('trimestre');
        }

        $this->normalizarPeriodo();
        $this->normalizarEleccion();
    }

    public function updatedDesde(): void
    {
        $this->normalizarPeriodo();
    }

    public function updatedHasta(): void
    {
        $this->normalizarPeriodo(mandaHasta: true);
    }

    /** Otro campo: su potrero más recorrido del período. */
    public function updatedCampo(): void
    {
        $this->potrero = null;
        $this->normalizarEleccion();
    }

    public function updatedPotrero(): void
    {
        $this->normalizarEleccion();
    }

    /** Este mes, o los últimos 3, 6 o 12 meses (siempre desde un día 1). */
    public function atajo(string $cual): void
    {
        $periodo = Calculos::ultimosMeses(self::ATAJOS[$cual] ?? Calculos::MESES_POR_OMISION);

        $this->desde = $periodo->desde;
        $this->hasta = $periodo->hasta;
    }

    /** El atajo que coincide con el período elegido, para marcarlo. */
    public function atajoActivo(): ?string
    {
        if ($this->hasta !== Carbon::today()->toDateString()) {
            return null;
        }

        foreach (self::ATAJOS as $cual => $meses) {
            if ($this->desde === Calculos::ultimosMeses($meses)->desde) {
                return $cual;
            }
        }

        return null;
    }

    /** Fechas inválidas caen en hoy; si quedan cruzadas, la que se tocó manda (como FiltraPorPeriodo). */
    private function normalizarPeriodo(bool $mandaHasta = false): void
    {
        $hoy = Carbon::today()->toDateString();
        $valida = fn (string $fecha) => $fecha !== '' && Carbon::hasFormat($fecha, 'Y-m-d');

        $this->desde = $valida($this->desde) ? $this->desde : $hoy;
        $this->hasta = $valida($this->hasta) ? $this->hasta : $hoy;

        if ($this->desde > $this->hasta) {
            $mandaHasta ? $this->desde = $this->hasta : $this->hasta = $this->desde;
        }
    }

    /**
     * Un campo activo (el elegido o el primero) y un potrero de ese campo (el
     * elegido, o el que tiene más recorridas activas en el período, o el
     * primero).
     */
    private function normalizarEleccion(): void
    {
        $campos = Campo::query()->where('activo', true);
        $this->campo = $campos->clone()->whereKey($this->campo)->value('id') ?? $campos->orderBy('nombre')->value('id');

        if ($this->campo === null) {
            $this->potrero = null;

            return;
        }

        $potreros = Potrero::query()->where('campo_id', $this->campo)->where('activo', true);

        if ($this->potrero !== null && $potreros->clone()->whereKey($this->potrero)->exists()) {
            return;
        }

        $this->potrero = $potreros->clone()
            ->withCount(['recorridas' => fn ($q) => $q->where('activo', true)
                ->whereBetween('iniciada_en', [$this->desde.' 00:00:00', $this->hasta.' 23:59:59'])])
            ->orderByDesc('recorridas_count')
            ->orderBy('nombre')
            ->value('id');
    }

    private function calculos(): Calculos
    {
        return new Calculos($this->desde, $this->hasta, $this->campo, $this->potrero);
    }

    public function exportar(): StreamedResponse
    {
        Gate::authorize('puedeVer', 'reportes');

        $calculos = $this->calculos();
        $csv = ExportadorReportes::csv($calculos);

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            ExportadorReportes::nombreArchivo($calculos),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render()
    {
        $calculos = $this->calculos();
        $kg = fn (?float $valor) => $valor === null ? null : __('reportes.kg', ['valor' => Formato::numero($valor)]);
        $potrerosCampo = $this->campo !== null ? $calculos->potrerosDelCampo($this->campo) : null;

        return view('livewire.reportes.reportes', [
            'resumen' => $calculos->comparacion(),
            'anterior' => $calculos->anterior(),
            'urlPdf' => route('reportes.pdf', array_filter(['desde' => $this->desde, 'hasta' => $this->hasta, 'campo' => $this->campo, 'potrero' => $this->potrero])),
            'atajos' => array_keys(self::ATAJOS),
            'atajoActivo' => $this->atajoActivo(),
            // Combos poblados en render (§4c).
            'campos' => Campo::query()->where('activo', true)->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'potreros' => $this->campo === null ? [] : Potrero::query()->where('campo_id', $this->campo)->where('activo', true)
                ->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'evolucion' => $this->potrero !== null ? $calculos->evolucion($this->potrero) : null,
            'potrerosCampo' => $potrerosCampo === null ? null : [
                'filas' => array_map(fn (array $f) => [
                    'etiqueta' => $f['nombre'],
                    'valor' => $f['altura'],
                    'texto' => __('recorridas.cm', ['valor' => Formato::numero($f['altura'], 1)]),
                    'detalle' => $f['kg'] === null
                        ? __('reportes.potreros.detalle_sin_kg', ['fecha' => Formato::fecha($f['fecha'])])
                        : __('reportes.potreros.detalle', ['fecha' => Formato::fecha($f['fecha']), 'kg' => $kg($f['kg'])]),
                ], $potrerosCampo['filas']),
                'sin_datos' => $potrerosCampo['sin_datos'],
            ],
            'disponibilidad' => array_map(fn (array $f) => [
                'etiqueta' => $f['nombre'],
                'valor' => $f['kg'],
                'texto' => __('reportes.disponibilidad.valor', ['valor' => Formato::numero($f['kg'])]),
                'detalle' => __('reportes.disponibilidad.detalle', ['con_dato' => $f['con_dato'], 'potreros' => $f['potreros']]),
            ], $calculos->disponibilidad()),
            'recorredores' => array_map(fn (array $f) => [
                'etiqueta' => $f['nombre'],
                'valor' => $f['recorridas'],
                'texto' => (string) $f['recorridas'],
                'detalle' => trans_choice('reportes.recorredores.detalle', $f['puntos'], ['count' => $f['puntos']]),
            ], $calculos->porRecorredor()),
        ]);
    }
}
