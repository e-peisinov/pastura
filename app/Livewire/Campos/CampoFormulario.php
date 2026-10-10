<?php

namespace App\Livewire\Campos;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Campo;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Alta y edición de un campo, en un modal propio (D57): un hijo que se monta
 * ya abierto. Avisa `campo-guardado` (id, nuevo) al guardar y
 * `cerrar-campo-formulario` al cerrar. Pide Editar en Campos (D49).
 */
class CampoFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public ?int $campoId = null;

    public int $z = 50;

    public string $nombre = '';

    public ?string $localidad = null;

    public ?string $observaciones = null;

    public function mount(?int $campoId = null, int $z = 50): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->z = $z;

        if ($campoId !== null) {
            $campo = Campo::query()->findOrFail($campoId);

            $this->campoId = $campo->id;
            $this->nombre = $campo->nombre;
            $this->localidad = $campo->localidad;
            $this->observaciones = $campo->observaciones;
        }

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $datos = $this->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('campos', 'nombre')->ignore($this->campoId)],
            'localidad' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
        ]);

        $datos = array_map(fn ($valor) => is_string($valor) ? (trim($valor) === '' ? null : trim($valor)) : $valor, $datos);

        $nuevo = $this->campoId === null;

        if ($nuevo) {
            $campo = Campo::query()->create($datos);
            $this->toastExito(__('campos.toasts.creado'));
        } else {
            $campo = Campo::query()->findOrFail($this->campoId);
            $campo->update($datos);
            $this->toastExito(__('campos.toasts.modificado'));
        }

        $this->dispatch('campo-guardado', id: $campo->id, nuevo: $nuevo);
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre', 'localidad', 'observaciones'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-campo-formulario');
    }

    public function render()
    {
        return view('livewire.campos.campo-formulario', [
            'zDescarte' => min($this->z + 10, 70),
        ]);
    }
}
