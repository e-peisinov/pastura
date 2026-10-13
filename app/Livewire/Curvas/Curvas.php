<?php

namespace App\Livewire\Curvas;

use App\Livewire\Traits\EnviaToasts;
use App\Livewire\Traits\PaginacionResponsive;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\CurvaBiomasa;
use App\Models\EstadoFenologico;
use App\Models\Potrero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Curvas de biomasa (P23): una por potrero y estado fenológico, con sus
 * muestras y la recta que sale de ellas (se calcula, regla 8). Los filtros
 * viajan en la URL, así la ficha del campo puede abrir las de un potrero.
 *
 * La ficha (gráfico y muestras) es un modal de acá; el alta y la edición van
 * en el hijo CurvaFormulario (D57). Con Ver se consulta; cada acción pide
 * Editar en Curvas.
 */
#[Layout('layouts.app')]
class Curvas extends Component
{
    use EnviaToasts, PaginacionResponsive, WithPagination;

    #[Url(as: 'campo', except: null)]
    public ?int $filtroCampo = null;

    #[Url(as: 'potrero', except: null)]
    public ?int $filtroPotrero = null;

    #[Url(as: 'estado', except: null)]
    public ?int $filtroEstado = null;

    /** vigentes | reemplazadas | todas (convenciones.md: un filtro no representa un estado con null). */
    #[Url(as: 'vigencia', except: 'vigentes')]
    public string $filtroVigencia = 'vigentes';

    /** Curva abierta en la ficha. */
    public ?int $detalleId = null;

    /** Curva en el formulario: null = cerrado, 0 = alta, id = edición. */
    public ?int $formularioId = null;

    public ?int $bajaId = null;

    public function mount(): void
    {
        $this->checkIfMobile();

        if (! in_array($this->filtroVigencia, ['vigentes', 'reemplazadas', 'todas'], true)) {
            $this->filtroVigencia = 'vigentes';
        }

        // Desde la ficha del campo llega solo el potrero: el campo sale de ahí.
        if ($this->filtroPotrero !== null && $this->filtroCampo === null) {
            $this->filtroCampo = Potrero::query()->whereKey($this->filtroPotrero)->value('campo_id');
        }
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

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroVigencia(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->filtroCampo = $this->filtroPotrero = $this->filtroEstado = null;
        $this->filtroVigencia = 'vigentes';
        $this->resetPage();
    }

    // ----- Ficha -------------------------------------------------------------

    public function verDetalle(int $id): void
    {
        $this->detalleId = CurvaBiomasa::query()->findOrFail($id)->id;
    }

    public function cerrarDetalle(): void
    {
        $this->detalleId = null;
    }

    // ----- Alta / edición (hijo, D57) ----------------------------------------

    public function nueva(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->formularioId = 0;
    }

    public function editar(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->formularioId = CurvaBiomasa::query()->findOrFail($id)->id;
        $this->detalleId = null;
    }

    #[On('cerrar-curva-formulario')]
    public function cerrarFormulario(): void
    {
        $this->formularioId = null;
    }

    #[On('curva-guardada')]
    public function curvaGuardada(): void
    {
        $this->formularioId = null;
    }

    // ----- Vigencia y baja ---------------------------------------------------

    /** La reemplazada vuelve a ser la vigente; la que estaba queda reemplazada (CurvaBiomasa::booted). */
    public function hacerVigente(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        CurvaBiomasa::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('curvas.toasts.vigente'));
    }

    public function confirmarBaja(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->bajaId = CurvaBiomasa::query()->findOrFail($id)->id;
    }

    public function cancelarBaja(): void
    {
        $this->bajaId = null;
    }

    /** Se borra con sus muestras: los kg se calculan al leer, no queda nada colgando. */
    public function baja(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $curva = CurvaBiomasa::query()->findOrFail($this->bajaId);
        DB::transaction(fn () => $curva->delete());

        $this->bajaId = null;
        $this->detalleId = null;
        $this->toastExito(__('curvas.toasts.eliminada'));
    }

    // ----- Render ------------------------------------------------------------

    public function render()
    {
        $area = Configuracion::instancia()->area_disco_m2;

        $curvas = $this->consultaFiltrada()
            ->with(['potrero.campo', 'estadoFenologico', 'muestras'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        $detalle = $this->detalleId !== null
            ? CurvaBiomasa::query()->with(['potrero.campo', 'estadoFenologico', 'muestras'])->find($this->detalleId)
            : null;

        $enBaja = $this->bajaId !== null ? CurvaBiomasa::query()->with(['potrero.campo', 'estadoFenologico'])->find($this->bajaId) : null;

        // Los combos se pueblan en render (estandares-ux.md §4c).
        return view('livewire.curvas.curvas', [
            'curvas' => $curvas,
            'area' => $area,
            'detalle' => $detalle,
            'enBaja' => $enBaja,
            'puedeEditar' => Gate::allows('puedeEditar', 'curvas'),
            'campos' => Campo::query()->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'potreros' => $this->filtroCampo === null ? [] : Potrero::query()
                ->where('campo_id', $this->filtroCampo)->orderBy('nombre')->pluck('nombre', 'id')->all(),
            'estados' => EstadoFenologico::query()->orderBy('id')->pluck('nombre', 'id')->all(),
            'vigencias' => [
                'vigentes' => __('curvas.filtros.vigentes'),
                'reemplazadas' => __('curvas.filtros.reemplazadas'),
                'todas' => __('ui.filtros.todas'),
            ],
            'hayFiltro' => $this->filtroCampo !== null || $this->filtroPotrero !== null
                || $this->filtroEstado !== null || $this->filtroVigencia !== 'vigentes',
        ]);
    }

    private function consultaFiltrada(): Builder
    {
        return CurvaBiomasa::query()
            ->when($this->filtroPotrero !== null, fn (Builder $q) => $q->where('potrero_id', $this->filtroPotrero))
            ->when($this->filtroCampo !== null, fn (Builder $q) => $q->whereIn(
                'potrero_id',
                Potrero::query()->select('id')->where('campo_id', $this->filtroCampo),
            ))
            ->when($this->filtroEstado !== null, fn (Builder $q) => $q->where('estado_fenologico_id', $this->filtroEstado))
            ->when($this->filtroVigencia === 'vigentes', fn (Builder $q) => $q->where('activo', true))
            ->when($this->filtroVigencia === 'reemplazadas', fn (Builder $q) => $q->where('activo', false));
    }
}
