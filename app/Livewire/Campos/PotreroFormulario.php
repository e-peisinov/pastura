<?php

namespace App\Livewire\Campos;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Campo;
use App\Models\Potrero;
use App\Support\Geo\Superficie;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Alta y edición de un potrero con su contorno en el mapa (P5), apilado sobre
 * la ficha del campo (D57). El contorno lo edita el componente Alpine
 * `mapaContorno` (resources/js/mapa.js) y llega por entangle como GeoJSON.
 * Avisa `potrero-guardado` y `cerrar-potrero-formulario`. Pide Editar en Campos.
 */
class PotreroFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public int $campoId;

    public ?int $potreroId = null;

    public int $z = 60;

    public string $nombre = '';

    public ?string $tipo_pastura = null;

    public ?string $observaciones = null;

    /** @var array<string, mixed>|null Geometría GeoJSON (Polygon o MultiPolygon). */
    public ?array $contorno = null;

    public function mount(int $campoId, ?int $potreroId = null, int $z = 60): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $this->campoId = Campo::query()->findOrFail($campoId)->id;
        $this->z = $z;

        if ($potreroId !== null) {
            $potrero = Potrero::query()->where('campo_id', $this->campoId)->findOrFail($potreroId);

            $this->potreroId = $potrero->id;
            $this->nombre = $potrero->nombre;
            $this->tipo_pastura = $potrero->tipo_pastura;
            $this->observaciones = $potrero->observaciones;
            $this->contorno = $potrero->contorno;
        }

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'campos');

        $datos = $this->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('potreros', 'nombre')->where('campo_id', $this->campoId)->ignore($this->potreroId),
            ],
            'tipo_pastura' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:2000'],
            'contorno' => ['nullable', 'array'],
        ], [
            'nombre.unique' => __('campos.potrero.nombre_repetido'),
        ]);

        if ($this->contorno !== null && Superficie::hectareas($this->contorno) === null) {
            $this->addError('contorno', __('campos.potrero.contorno_invalido'));

            return;
        }

        foreach (['nombre', 'tipo_pastura', 'observaciones'] as $campo) {
            $datos[$campo] = is_string($datos[$campo]) && trim($datos[$campo]) !== '' ? trim($datos[$campo]) : null;
        }
        $datos['nombre'] ??= '';

        $nuevo = $this->potreroId === null;

        if ($nuevo) {
            $potrero = Potrero::query()->create($datos + ['campo_id' => $this->campoId]);
            $this->toastExito(__('campos.potrero.toasts.creado'));
        } else {
            $potrero = Potrero::query()->where('campo_id', $this->campoId)->findOrFail($this->potreroId);
            $potrero->update($datos);
            $this->toastExito(__('campos.potrero.toasts.modificado'));
        }

        $this->dispatch('potrero-guardado', id: $potrero->id, nuevo: $nuevo);
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre', 'tipo_pastura', 'observaciones', 'contorno'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-potrero-formulario');
    }

    public function render()
    {
        // Los otros potreros del campo, punteados de fondo para dibujar al lado.
        $vecinos = Potrero::query()
            ->where('campo_id', $this->campoId)
            ->when($this->potreroId !== null, fn ($q) => $q->whereKeyNot($this->potreroId))
            ->whereNotNull('contorno')
            ->get(['id', 'nombre', 'contorno'])
            ->map(fn (Potrero $p) => ['nombre' => $p->nombre, 'contorno' => $p->contorno])
            ->values();

        return view('livewire.campos.potrero-formulario', [
            'vecinos' => $vecinos,
            'zDescarte' => min($this->z + 10, 70),
        ]);
    }
}
