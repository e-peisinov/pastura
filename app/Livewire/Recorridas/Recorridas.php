<?php

namespace App\Livewire\Recorridas;

use App\Livewire\Traits\OrdenaPorColumna;
use App\Livewire\Traits\PaginacionResponsive;
use App\Models\Campo;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use App\Support\Recorridas\ExportadorRecorridas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Listado de recorridas (molde: Campos). Lo que se muestra de cada una
 * (puntos, altura media, kg MS/ha) sale de Recorrida::conResumen() (regla 8).
 * El recorredor ve solo las suyas; quien administra, todas (dominio.md,
 * regla 3). Los filtros viajan en la URL. La ficha es el hijo RecorridaDetalle
 * (regla 7), que avisa con `recorrida-actualizada` cuando cambia algo.
 *
 * El período no usa FiltraPorPeriodo: ahí una fecha vacía cae en hoy, y acá
 * "sin fecha" es un valor legítimo (el atajo "Todo").
 */
#[Layout('layouts.app')]
class Recorridas extends Component
{
    use OrdenaPorColumna, PaginacionResponsive, WithPagination;

    #[Url(as: 'campo', except: null)]
    public ?int $filtroCampo = null;

    #[Url(as: 'potrero', except: null)]
    public ?int $filtroPotrero = null;

    #[Url(as: 'recorredor', except: null)]
    public ?int $filtroRecorredor = null;

    #[Url(except: null)]
    public ?string $desde = null;

    #[Url(except: null)]
    public ?string $hasta = null;

    /** activas | anuladas | todas (convenciones.md: un filtro no representa un estado con null). */
    #[Url(as: 'estado', except: 'activas')]
    public string $filtroEstado = 'activas';

    public ?int $detalleId = null;

    public function mount(): void
    {
        $this->checkIfMobile();
        $this->normalizarPeriodo();

        if (! in_array($this->filtroEstado, ['activas', 'anuladas', 'todas'], true)) {
            $this->filtroEstado = 'activas';
        }
    }

    // ----- Orden por columna -------------------------------------------------

    protected function columnasOrdenables(): array
    {
        return [
            'fecha' => 'recorridas.iniciada_en',
            'altura' => fn (Builder $q, string $dir) => $q->orderBy('altura_media', $dir),
        ];
    }

    protected function ordenInicial(): array
    {
        return ['fecha', 'desc'];
    }

    // ----- Filtros -----------------------------------------------------------

    /** El potrero depende del campo: si ya no es de ese campo, se limpia. */
    public function updatedFiltroCampo(): void
    {
        if ($this->filtroPotrero !== null && ! Potrero::query()->whereKey($this->filtroPotrero)->where('campo_id', $this->filtroCampo)->exists()) {
            $this->filtroPotrero = null;
        }

        $this->resetPage();
    }

    public function updatedFiltroPotrero(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroRecorredor(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedDesde(): void
    {
        $this->normalizarPeriodo();
        $this->resetPage();
    }

    public function updatedHasta(): void
    {
        $this->normalizarPeriodo(mandaHasta: true);
        $this->resetPage();
    }

    public function ultimos30Dias(): void
    {
        $this->periodo(Carbon::today()->subDays(29)->toDateString(), Carbon::today()->toDateString());
    }

    public function esteMes(): void
    {
        $this->periodo(Carbon::today()->startOfMonth()->toDateString(), Carbon::today()->toDateString());
    }

    public function todo(): void
    {
        $this->periodo(null, null);
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCampo = $this->filtroPotrero = $this->filtroRecorredor = null;
        $this->desde = $this->hasta = null;
        $this->filtroEstado = 'activas';
        $this->resetPage();
    }

    private function periodo(?string $desde, ?string $hasta): void
    {
        $this->desde = $desde;
        $this->hasta = $hasta;
        $this->resetPage();
    }

    /** Una fecha inválida o vacía queda en null (sin tope); si quedan cruzadas, la que se tocó manda. */
    private function normalizarPeriodo(bool $mandaHasta = false): void
    {
        $this->desde = self::fechaValida($this->desde) ? $this->desde : null;
        $this->hasta = self::fechaValida($this->hasta) ? $this->hasta : null;

        if ($this->desde !== null && $this->hasta !== null && $this->desde > $this->hasta) {
            $mandaHasta ? $this->desde = $this->hasta : $this->hasta = $this->desde;
        }
    }

    private static function fechaValida(?string $fecha): bool
    {
        return $fecha !== null && $fecha !== '' && Carbon::hasFormat($fecha, 'Y-m-d');
    }

    // ----- Ficha (hijo RecorridaDetalle) -------------------------------------

    public function verDetalle(int $id): void
    {
        $this->detalleId = Recorrida::query()->visiblesPara($this->usuario())->findOrFail($id)->id;
    }

    #[On('cerrar-recorrida-detalle')]
    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    /** El hijo cambió algo (un punto, el estado): el listado se vuelve a dibujar con el resumen nuevo. */
    #[On('recorrida-actualizada')]
    public function refrescar(): void {}

    // ----- Exportar ----------------------------------------------------------

    /** El listado tal como está filtrado, una fila por punto. */
    public function exportar(): StreamedResponse
    {
        Gate::authorize('puedeEditar', 'recorridas');

        $csv = ExportadorRecorridas::csv($this->consultaFiltrada());

        return response()->streamDownload(
            function () use ($csv) {
                echo $csv;
            },
            ExportadorRecorridas::nombreArchivo($this->desde, $this->hasta),
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    // ----- Render ------------------------------------------------------------

    public function render()
    {
        $usuario = $this->usuario();
        $esAdministrador = ! $usuario->esRecorredor();

        $recorridas = $this->aplicarOrden($this->consultaFiltrada()->conResumen()->with(['potrero.campo', 'user']))
            ->paginate($this->perPage);

        // Los combos se pueblan en render (estandares-ux.md §4c).
        $campos = Campo::query()->orderBy('nombre')->pluck('nombre', 'id')->all();
        $potreros = $this->filtroCampo === null ? [] : Potrero::query()
            ->where('campo_id', $this->filtroCampo)->orderBy('nombre')->pluck('nombre', 'id')->all();
        $recorredores = $esAdministrador
            ? User::query()->whereHas('recorridas')->orderBy('name')->pluck('name', 'id')->all()
            : [];

        return view('livewire.recorridas.recorridas', [
            'recorridas' => $recorridas,
            'esAdministrador' => $esAdministrador,
            'puedeEditar' => Gate::allows('puedeEditar', 'recorridas'),
            'campos' => $campos,
            'potreros' => $potreros,
            'recorredores' => $recorredores,
            'estados' => [
                'activas' => __('recorridas.filtros.activas'),
                'anuladas' => __('recorridas.filtros.anuladas'),
                'todas' => __('ui.filtros.todas'),
            ],
            'hayFiltro' => $this->filtroCampo !== null || $this->filtroPotrero !== null
                || ($esAdministrador && $this->filtroRecorredor !== null)
                || $this->desde !== null || $this->hasta !== null || $this->filtroEstado !== 'activas',
        ]);
    }

    /** Pertenencia y filtros; sin resumen ni orden (los agrega quien la usa). */
    private function consultaFiltrada(): Builder
    {
        $usuario = $this->usuario();

        return Recorrida::query()
            ->visiblesPara($usuario)
            ->when($this->filtroPotrero !== null, fn (Builder $q) => $q->where('recorridas.potrero_id', $this->filtroPotrero))
            ->when($this->filtroCampo !== null, fn (Builder $q) => $q->whereIn(
                'recorridas.potrero_id',
                Potrero::query()->select('id')->where('campo_id', $this->filtroCampo),
            ))
            // El filtro de recorredor es del administrador: al recorredor ya lo acota visiblesPara.
            ->when(! $usuario->esRecorredor() && $this->filtroRecorredor !== null, fn (Builder $q) => $q->where('recorridas.user_id', $this->filtroRecorredor))
            ->when($this->desde !== null, fn (Builder $q) => $q->where('recorridas.iniciada_en', '>=', $this->desde.' 00:00:00'))
            ->when($this->hasta !== null, fn (Builder $q) => $q->where('recorridas.iniciada_en', '<=', $this->hasta.' 23:59:59'))
            ->when($this->filtroEstado === 'activas', fn (Builder $q) => $q->where('recorridas.activo', true))
            ->when($this->filtroEstado === 'anuladas', fn (Builder $q) => $q->where('recorridas.activo', false));
    }

    private function usuario(): User
    {
        return auth()->user();
    }
}
