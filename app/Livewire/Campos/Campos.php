<?php

namespace App\Livewire\Campos;

use App\Livewire\Traits\BuscaEntidad;
use App\Livewire\Traits\EnviaToasts;
use App\Livewire\Traits\OrdenaPorColumna;
use App\Livewire\Traits\PaginacionResponsive;
use App\Models\Campo;
use App\Models\Potrero;
use App\Models\Recorrida;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ABM de campos y, desde su ficha, de sus potreros (molde: Clientes del kit).
 *
 * Buscador server-side (BuscaEntidad); sin búsqueda, listado paginado. Las
 * cabeceras ordenan (OrdenaPorColumna). Con Ver se consulta; cada acción pide
 * Editar en Campos (D49). El alta y la edición van en hijos que se montan
 * abiertos (D57): CampoFormulario y, apilado sobre la ficha, PotreroFormulario.
 */
#[Layout('layouts.app')]
class Campos extends Component
{
    use BuscaEntidad, EnviaToasts, OrdenaPorColumna, PaginacionResponsive, WithPagination;

    public string $filtroEstado = 'activos';

    public bool $mostrarFormulario = false;

    public ?int $campoId = null;

    /** Campo abierto en el modal de detalle. */
    public ?int $detalleId = null;

    /** Potrero que edita PotreroFormulario (null = alta) cuando $mostrarPotrero. */
    public bool $mostrarPotrero = false;

    public ?int $potreroId = null;

    public ?int $bajaId = null;

    public ?int $bajaPotreroId = null;

    public function mount(): void
    {
        $this->checkIfMobile();
    }

    // ----- Orden por columna -------------------------------------------------

    protected function columnasOrdenables(): array
    {
        return [
            'nombre' => 'nombre',
            'potreros' => fn (Builder $q, string $dir) => $q->orderBy(
                Potrero::query()->selectRaw('count(*)')->whereColumn('potreros.campo_id', 'campos.id')->where('potreros.activo', true),
                $dir,
            ),
            'ultima_recorrida' => fn (Builder $q, string $dir) => $q->orderBy('ultima_recorrida', $dir),
            'estado' => 'activo',
        ];
    }

    protected function ordenInicial(): array
    {
        return ['nombre', 'asc'];
    }

    // ----- Búsqueda y filtros ------------------------------------------------

    protected function consultaBusqueda(string $texto): Builder
    {
        $palabras = preg_split('/\s+/', trim($texto)) ?: [];

        return $this->aplicarOrden($this->consultaFiltrada()->where(function (Builder $q) use ($palabras) {
            foreach ($palabras as $palabra) {
                $q->where(fn (Builder $q) => $q->where('nombre', 'like', "%{$palabra}%")
                    ->orWhere('localidad', 'like', "%{$palabra}%"));
            }
        }));
    }

    public function etiquetaResultado(Model $modelo): string
    {
        return trim($modelo->nombre.' · '.$modelo->localidad, ' ·');
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    // ----- Detalle -----------------------------------------------------------

    public function verDetalle(int $id): void
    {
        $this->detalleId = Campo::query()->findOrFail($id)->id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    // ----- Alta / edición del campo (D57) ------------------------------------

    public function nuevo(): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->campoId = null;
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->campoId = Campo::query()->findOrFail($id)->id;
        $this->detalleId = null;
        $this->mostrarFormulario = true;
    }

    #[On(['campo-guardado', 'cerrar-campo-formulario'])]
    public function cerrarFormulario(): void
    {
        $this->mostrarFormulario = false;
        $this->campoId = null;
    }

    // ----- Potreros, desde la ficha (D57) ------------------------------------

    public function agregarPotrero(): void
    {
        Gate::authorize('puedeEditar', 'campos');
        abort_if($this->detalleId === null, 404);

        $this->potreroId = null;
        $this->mostrarPotrero = true;
    }

    public function editarPotrero(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->potreroId = $this->potreroDelDetalle($id)->id;
        $this->mostrarPotrero = true;
    }

    #[On(['potrero-guardado', 'cerrar-potrero-formulario'])]
    public function cerrarPotrero(): void
    {
        $this->mostrarPotrero = false;
        $this->potreroId = null;
    }

    public function confirmarBajaPotrero(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->bajaPotreroId = $this->potreroDelDetalle($id)->id;
    }

    public function cancelarBajaPotrero(): void
    {
        $this->bajaPotreroId = null;
    }

    public function bajaPotrero(): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $potrero = $this->potreroDelDetalle($this->bajaPotreroId);

        if ($potrero->tieneHistoria()) {
            $potrero->update(['activo' => false]);
            $this->toastAviso(__('campos.potrero.toasts.desactivado'));
        } else {
            $potrero->delete();
            $this->toastExito(__('campos.potrero.toasts.eliminado'));
        }

        $this->bajaPotreroId = null;
    }

    public function reactivarPotrero(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->potreroDelDetalle($id)->update(['activo' => true]);
        $this->toastExito(__('campos.potrero.toasts.reactivado'));
    }

    /** Un potrero que no es del campo abierto no se toca (02, "un id ajeno"). */
    private function potreroDelDetalle(?int $id): Potrero
    {
        abort_if($this->detalleId === null || $id === null, 404);

        return Potrero::query()->where('campo_id', $this->detalleId)->findOrFail($id);
    }

    // ----- Baja y reactivación del campo -------------------------------------

    public function confirmarBaja(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->bajaId = $id;
    }

    public function cancelarBaja(): void
    {
        $this->bajaId = null;
    }

    /** Sin historia se borra con sus potreros; con historia se desactiva (D20). */
    public function baja(): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $campo = Campo::query()->findOrFail($this->bajaId);

        if ($campo->tieneHistoria()) {
            $campo->update(['activo' => false]);
            $this->toastAviso(__('campos.toasts.desactivado'));
        } else {
            DB::transaction(function () use ($campo) {
                $campo->potreros()->delete();
                $campo->delete();
            });
            $this->toastExito(__('campos.toasts.eliminado'));
        }

        $this->bajaId = null;
        $this->detalleId = null;
    }

    public function reactivar(int $id): void
    {
        Gate::authorize('puedeEditar', 'campos');

        Campo::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('campos.toasts.reactivado'));
    }

    // ----- Render ------------------------------------------------------------

    public function render()
    {
        $buscando = $this->busquedaAlcanzaElMinimo();

        $campos = $buscando
            ? $this->resultadosBusqueda
            : $this->aplicarOrden($this->consultaFiltrada())->paginate($this->perPage);

        $detalle = $this->detalleId !== null
            ? Campo::query()->with(['potreros' => fn ($q) => $q->orderByDesc('activo')->orderBy('nombre'), 'potreros.tipoPastura'])->find($this->detalleId)
            : null;

        // La última recorrida activa de cada potrero de la ficha, con su resumen (regla 8).
        $ultimas = $detalle === null ? collect() : Recorrida::query()
            ->conResumen()
            ->activas()
            ->whereIn('potrero_id', $detalle->potreros->pluck('id'))
            ->orderByDesc('iniciada_en')
            ->get()
            ->unique('potrero_id')
            ->keyBy('potrero_id');

        $campoEnBaja = $this->bajaId !== null ? Campo::query()->find($this->bajaId) : null;
        $potreroEnBaja = $this->bajaPotreroId !== null ? Potrero::query()->find($this->bajaPotreroId) : null;

        return view('livewire.campos.campos', [
            'campos' => $campos,
            'buscando' => $buscando,
            'puedeEditar' => Gate::allows('puedeEditar', 'campos'),
            'hayFiltro' => $buscando || $this->filtroEstado !== 'activos',
            'estados' => [
                'todos' => __('ui.filtros.todos'),
                'activos' => __('ui.filtros.activos'),
                'inactivos' => __('ui.filtros.inactivos'),
            ],
            'detalle' => $detalle,
            'ultimas' => $ultimas,
            'campoEnBaja' => $campoEnBaja,
            'bajaDesactiva' => $campoEnBaja?->tieneHistoria() ?? false,
            'potreroEnBaja' => $potreroEnBaja,
            'bajaPotreroDesactiva' => $potreroEnBaja?->tieneHistoria() ?? false,
        ]);
    }

    private function consultaFiltrada(): Builder
    {
        return Campo::query()
            ->select('campos.*')
            ->addSelect(['ultima_recorrida' => Recorrida::query()
                ->selectRaw('max(recorridas.iniciada_en)')
                ->join('potreros', 'potreros.id', '=', 'recorridas.potrero_id')
                ->whereColumn('potreros.campo_id', 'campos.id')
                ->where('recorridas.activo', true),
            ])
            ->withCount(['potreros' => fn (Builder $q) => $q->where('activo', true)])
            ->with(['potreros' => fn ($q) => $q->select('id', 'campo_id', 'contorno', 'activo')])
            ->when($this->filtroEstado === 'activos', fn (Builder $q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivos', fn (Builder $q) => $q->where('activo', false));
    }
}
