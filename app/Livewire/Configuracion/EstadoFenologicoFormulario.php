<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\EstadoFenologico;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Alta y edición de un estado de la pastura (hijo de Configuracion, P23):
 * solo el nombre, único sin distinguir mayúsculas. Es lo que elige el
 * recorredor al empezar y lo que separa las curvas de un potrero.
 */
class EstadoFenologicoFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    public ?int $estadoId = null;

    public string $nombre = '';

    public function mount(?int $estadoId = null): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        if ($estadoId !== null) {
            $estado = EstadoFenologico::query()->findOrFail($estadoId);

            $this->estadoId = $estado->id;
            $this->nombre = $estado->nombre;
        }

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->resetErrorBag();

        $datos = Validator::make(['nombre' => trim($this->nombre)], [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnico()],
        ], [], [
            'nombre' => __('configuracion.estados.campos.nombre'),
        ])->validate();

        if ($this->estadoId === null) {
            EstadoFenologico::query()->create($datos + ['activo' => true]);
            $this->toastExito(__('configuracion.estados.toasts.creado'));
        } else {
            EstadoFenologico::query()->findOrFail($this->estadoId)->update($datos);
            $this->toastExito(__('configuracion.estados.toasts.modificado'));
        }

        $this->marcarFormularioLimpio();
        $this->dispatch('estado-fenologico-guardado');
        $this->cerrarFormulario();
    }

    /** "Rebrote" y "rebrote" son el mismo estado. */
    private function nombreUnico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) {
            $repetido = EstadoFenologico::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $valor)])
                ->when($this->estadoId !== null, fn ($q) => $q->whereKeyNot($this->estadoId))
                ->exists();

            if ($repetido) {
                $falla(__('validation.unique', ['attribute' => __('configuracion.estados.campos.nombre')]));
            }
        };
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-estado-fenologico');
    }

    public function render()
    {
        return view('livewire.configuracion.estado-fenologico-formulario');
    }
}
