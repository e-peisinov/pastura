<?php

namespace App\Livewire\Curvas;

use App\Livewire\Traits\EnviaToasts;
use App\Models\EstadoFenologico;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Los estados de la pastura (P23), en la pantalla de Curvas desde P24: solo
 * sirven para separar las curvas de un potrero, así que se administran al
 * lado de ellas. El alta y la edición van en el hijo EstadoFenologicoFormulario;
 * la baja y la reactivación son de una acción y viven acá. Cada acción pide
 * Editar en Curvas. Cualquier cambio avisa con estados-fenologicos-cambiados,
 * así la pantalla refresca su filtro.
 */
class EstadosFenologicos extends Component
{
    use EnviaToasts;

    /** Estado abierto en el formulario: null = cerrado, 0 = alta, id = edición. */
    public ?int $formularioId = null;

    /** Estado al que se le pidió la baja; abre el modal de confirmación. */
    public ?int $bajaId = null;

    public function nuevo(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->formularioId = 0;
    }

    public function editar(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->formularioId = EstadoFenologico::query()->findOrFail($id)->id;
    }

    #[On('cerrar-estado-fenologico')]
    public function cerrarFormulario(): void
    {
        $this->formularioId = null;
    }

    public function confirmarBaja(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->bajaId = EstadoFenologico::query()->findOrFail($id)->id;
    }

    public function cancelarBaja(): void
    {
        $this->bajaId = null;
    }

    /** Sin curvas ni recorridas se borra; si no, se desactiva y la app deja de ofrecerlo (D20). */
    public function baja(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $estado = EstadoFenologico::query()->findOrFail($this->bajaId);
        $this->bajaId = null;

        if ($estado->tieneHistoria()) {
            $estado->update(['activo' => false]);
            $this->toastAviso(__('curvas.estados.toasts.desactivado'));
        } else {
            $estado->delete();
            $this->toastExito(__('curvas.estados.toasts.eliminado'));
        }

        $this->dispatch('estados-fenologicos-cambiados');
    }

    public function reactivar(int $id): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        EstadoFenologico::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('curvas.estados.toasts.reactivado'));
        $this->dispatch('estados-fenologicos-cambiados');
    }

    /** El hijo guardó: alcanza con volver a dibujar. */
    #[On('estados-fenologicos-cambiados')]
    public function refrescar(): void {}

    public function render()
    {
        $enBaja = $this->bajaId !== null ? EstadoFenologico::query()->find($this->bajaId) : null;

        return view('livewire.curvas.estados-fenologicos', [
            'puedeEditar' => Gate::allows('puedeEditar', 'curvas'),
            // En el orden en que se cargaron: el mismo con que los ofrece la app.
            'estados' => EstadoFenologico::query()->withCount(['curvas', 'recorridas'])->orderByDesc('activo')->orderBy('id')->get(),
            'enBaja' => $enBaja,
            'bajaDesactiva' => $enBaja?->tieneHistoria() ?? false,
        ]);
    }
}
