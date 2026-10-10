<?php

namespace App\Livewire\Auditoria;

use App\Enums\AccionAuditada;
use App\Enums\EntidadAuditada;
use App\Livewire\Traits\FiltraPorPeriodo;
use App\Livewire\Traits\PaginacionResponsive;
use App\Models\Auditoria as Registro;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Auditoría (P18; molde: el módulo 38 del kit, sin el tiempo real): quién
 * anuló una recorrida o descartó un punto, cambió un contorno, dio de baja
 * un potrero o un campo, cambió el rol, los permisos o la contraseña de un
 * usuario, o tocó los tipos de pastura y los parámetros. Se lee, no se
 * edita: las filas las escriben los modelos. Un período (por omisión los
 * últimos 30 días, con desde / hasta en la URL) y filtros por qué, quién y
 * acción. Con Ver alcanza.
 */
#[Layout('layouts.app')]
class Auditoria extends Component
{
    use FiltraPorPeriodo, PaginacionResponsive, WithPagination;

    public const DIAS_POR_OMISION = 30;

    #[Url(as: 'desde', except: '')]
    public string $desde = '';

    #[Url(as: 'hasta', except: '')]
    public string $hasta = '';

    /** recorrida | medicion | potrero | campo | usuario | tipo_pastura | configuracion | null (todo). */
    #[Url(as: 'que', except: null)]
    public ?string $filtroEntidad = null;

    #[Url(as: 'accion', except: null)]
    public ?string $filtroAccion = null;

    #[Url(as: 'usuario', except: null)]
    public ?int $filtroUsuario = null;

    public function mount(): void
    {
        $this->checkIfMobile();

        if ($this->desde === '' && $this->hasta === '') {
            $this->desde = Carbon::today()->subDays(self::DIAS_POR_OMISION)->toDateString();
            $this->hasta = Carbon::today()->toDateString();
        }

        $this->normalizarPeriodo();
    }

    // ----- Filtros (el período está en FiltraPorPeriodo) ---------------------

    public function updatedFiltroEntidad(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroAccion(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroUsuario(): void
    {
        $this->resetPage();
    }

    public function limpiarFiltros(): void
    {
        $this->filtroEntidad = $this->filtroAccion = null;
        $this->filtroUsuario = null;
        $this->resetPage();
    }

    // ----- Render ------------------------------------------------------------

    public function render()
    {
        $registros = $this->consulta()
            ->with('usuario')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('livewire.auditoria.auditoria', [
            'registros' => $registros,
            'hayFiltro' => $this->filtroEntidad !== null || $this->filtroAccion !== null || $this->filtroUsuario !== null,
            // Combos poblados en render (§4c).
            'entidades' => EntidadAuditada::cases(),
            'acciones' => AccionAuditada::cases(),
            'usuarios' => User::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (User $u) => ['id' => $u->id, 'nombre' => $u->name]),
        ]);
    }

    private function consulta(): Builder
    {
        return Registro::query()
            ->entre(Carbon::parse($this->desde)->startOfDay(), Carbon::parse($this->hasta)->endOfDay())
            ->when($this->filtroEntidad !== null, fn (Builder $q) => $q->where('entidad', $this->filtroEntidad))
            ->when($this->filtroAccion !== null, fn (Builder $q) => $q->where('accion', $this->filtroAccion))
            ->when($this->filtroUsuario !== null, fn (Builder $q) => $q->where('user_id', $this->filtroUsuario));
    }
}
