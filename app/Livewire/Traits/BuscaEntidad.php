<?php

namespace App\Livewire\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * Búsqueda server-side (estandares-ux.md §4b) para las listas que pueden
 * superar ~200 opciones: catálogo de artículos y neumáticos, clientes y
 * vehículos por patente.
 *
 * El trait trae el mínimo de 2 caracteres, el limit(20) y la propiedad
 * computada `resultadosBusqueda`. El componente solo define:
 *   - `consultaBusqueda(string $texto): Builder` — la consulta filtrada por el texto;
 *   - `etiquetaResultado(Model $modelo): string` — cómo se muestra cada resultado.
 *
 * En la vista:
 *   <input type="text" wire:model.live.debounce.300ms="busqueda" ...>
 *   @foreach($this->resultadosBusqueda as $resultado)
 *       <button type="button" wire:click="elegirCliente({{ $resultado->id }})">
 *           {{ $this->etiquetaResultado($resultado) }}
 *       </button>
 *   @endforeach
 */
trait BuscaEntidad
{
    public const MINIMO_BUSQUEDA = 2;

    public const LIMITE_BUSQUEDA = 20;

    public string $busqueda = '';

    abstract protected function consultaBusqueda(string $texto): Builder;

    abstract public function etiquetaResultado(Model $modelo): string;

    /** @return Collection<int, Model> vacía si no se alcanzó el mínimo de caracteres */
    #[Computed]
    public function resultadosBusqueda(): Collection
    {
        if (! $this->busquedaAlcanzaElMinimo()) {
            return new Collection;
        }

        return $this->consultaBusqueda($this->textoBusqueda())
            ->limit(self::LIMITE_BUSQUEDA)
            ->get();
    }

    public function busquedaAlcanzaElMinimo(): bool
    {
        return mb_strlen($this->textoBusqueda()) >= self::MINIMO_BUSQUEDA;
    }

    public function limpiarBusqueda(): void
    {
        $this->busqueda = '';
        unset($this->resultadosBusqueda);
    }

    /** Mensaje de ayuda para la vista: falta escribir, o no hubo resultados. */
    public function mensajeBusqueda(): ?string
    {
        if (! $this->busquedaAlcanzaElMinimo()) {
            return __('ui.busqueda.minimo', ['minimo' => self::MINIMO_BUSQUEDA]);
        }

        if ($this->resultadosBusqueda->isEmpty()) {
            return __('ui.busqueda.sin_resultados', ['texto' => $this->textoBusqueda()]);
        }

        return null;
    }

    public function updatedBusqueda(): void
    {
        // La computada se cachea por request; al cambiar el texto se invalida.
        unset($this->resultadosBusqueda);
    }

    private function textoBusqueda(): string
    {
        return trim($this->busqueda);
    }
}
