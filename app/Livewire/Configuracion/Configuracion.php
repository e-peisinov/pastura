<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Traits\EnviaToasts;
use App\Models\Configuracion as ConfiguracionDeLaOrganizacion;
use App\Models\EstadoFenologico;
use App\Models\TipoPastura;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Configuración (P17): bloques que se leen de un vistazo y se editan en un
 * modal cada uno (§1): los datos de la organización (marca blanca, D8), los
 * tipos de pastura con sus alturas (P16), los estados de la pastura (P23) y
 * los parámetros (del seguimiento y el área del disco).
 *
 * Es el padre: muestra y abre los modales hijos (DatosOrganizacion,
 * TipoPasturaFormulario, EstadoFenologicoFormulario, Parametros). La baja y
 * la reactivación de un tipo o de un estado son de una acción y viven acá.
 * Cada acción pide Editar en Configuración.
 */
#[Layout('layouts.app')]
class Configuracion extends Component
{
    use EnviaToasts;

    public bool $mostrarOrganizacion = false;

    public bool $mostrarParametros = false;

    /** Tipo abierto en el formulario: null = cerrado, 0 = alta, id = edición. */
    public ?int $tipoFormulario = null;

    /** Tipo al que se le pidió la baja; abre el modal de confirmación. */
    public ?int $bajaId = null;

    /** Estado abierto en el formulario: null = cerrado, 0 = alta, id = edición. */
    public ?int $estadoFormulario = null;

    /** Estado al que se le pidió la baja. */
    public ?int $bajaEstadoId = null;

    // ----- Datos de la organización (modal hijo) ----------------------------

    public function abrirOrganizacion(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->mostrarOrganizacion = true;
    }

    #[On('cerrar-datos-organizacion')]
    public function cerrarOrganizacion(): void
    {
        $this->mostrarOrganizacion = false;
    }

    // ----- Parámetros (modal hijo) ------------------------------------------

    public function abrirParametros(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->mostrarParametros = true;
    }

    #[On('cerrar-parametros')]
    public function cerrarParametros(): void
    {
        $this->mostrarParametros = false;
    }

    // ----- Tipos de pastura (modal hijo + baja) -----------------------------

    public function nuevoTipo(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->tipoFormulario = 0;
    }

    public function editarTipo(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->tipoFormulario = TipoPastura::query()->findOrFail($id)->id;
    }

    #[On('cerrar-tipo-pastura')]
    public function cerrarTipo(): void
    {
        $this->tipoFormulario = null;
    }

    public function confirmarBaja(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->bajaId = TipoPastura::query()->findOrFail($id)->id;
    }

    public function cancelarBaja(): void
    {
        $this->bajaId = null;
    }

    /** Sin potreros se borra; con potreros se desactiva (D20). */
    public function baja(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $tipo = TipoPastura::query()->findOrFail($this->bajaId);
        $this->bajaId = null;

        if ($tipo->tieneHistoria()) {
            $tipo->update(['activo' => false]);
            $this->toastAviso(__('configuracion.tipos.toasts.desactivado'));

            return;
        }

        $tipo->delete();
        $this->toastExito(__('configuracion.tipos.toasts.eliminado'));
    }

    public function reactivar(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        TipoPastura::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('configuracion.tipos.toasts.reactivado'));
    }

    // ----- Estados de la pastura (modal hijo + baja, P23) --------------------

    public function nuevoEstado(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->estadoFormulario = 0;
    }

    public function editarEstado(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->estadoFormulario = EstadoFenologico::query()->findOrFail($id)->id;
    }

    #[On('cerrar-estado-fenologico')]
    public function cerrarEstado(): void
    {
        $this->estadoFormulario = null;
    }

    public function confirmarBajaEstado(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->bajaEstadoId = EstadoFenologico::query()->findOrFail($id)->id;
    }

    public function cancelarBajaEstado(): void
    {
        $this->bajaEstadoId = null;
    }

    /** Sin curvas ni recorridas se borra; si no, se desactiva y la app deja de ofrecerlo (D20). */
    public function bajaEstado(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $estado = EstadoFenologico::query()->findOrFail($this->bajaEstadoId);
        $this->bajaEstadoId = null;

        if ($estado->tieneHistoria()) {
            $estado->update(['activo' => false]);
            $this->toastAviso(__('configuracion.estados.toasts.desactivado'));

            return;
        }

        $estado->delete();
        $this->toastExito(__('configuracion.estados.toasts.eliminado'));
    }

    public function reactivarEstado(int $id): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        EstadoFenologico::query()->findOrFail($id)->update(['activo' => true]);
        $this->toastExito(__('configuracion.estados.toasts.reactivado'));
    }

    /** Un hijo guardó: alcanza con volver a dibujar. */
    #[On(['tipo-pastura-guardado', 'estado-fenologico-guardado', 'parametros-guardados'])]
    public function refrescar(): void {}

    // ----- Render -----------------------------------------------------------

    public function render()
    {
        $tipoEnBaja = $this->bajaId !== null ? TipoPastura::query()->find($this->bajaId) : null;
        $estadoEnBaja = $this->bajaEstadoId !== null ? EstadoFenologico::query()->find($this->bajaEstadoId) : null;

        return view('livewire.configuracion.configuracion', [
            'configuracion' => ConfiguracionDeLaOrganizacion::instancia(),
            'puedeEditar' => Gate::allows('puedeEditar', 'configuracion'),
            'tipos' => TipoPastura::query()->withCount('potreros')->orderByDesc('activo')->orderBy('nombre')->get(),
            'tipoEnBaja' => $tipoEnBaja,
            'bajaDesactiva' => $tipoEnBaja?->tieneHistoria() ?? false,
            // En el orden en que se cargaron: el mismo con que los ofrece la app.
            'estadosFenologicos' => EstadoFenologico::query()->withCount(['curvas', 'recorridas'])->orderByDesc('activo')->orderBy('id')->get(),
            'estadoEnBaja' => $estadoEnBaja,
            'bajaEstadoDesactiva' => $estadoEnBaja?->tieneHistoria() ?? false,
        ]);
    }
}
