<?php

namespace App\Livewire\Curvas;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * El área del disco (P23), modal hijo de Curvas desde P24: lo que corta cada
 * muestra de una curva, que pasa sus gramos a kg MS/ha. Se guarda en la fila
 * de Configuracion pero solo la usan las curvas. Se escribe con coma decimal.
 */
class AreaDisco extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    /** De un aro de 10 cm a uno de un metro cuadrado. */
    public const AREA_MINIMA = 0.005;

    public const AREA_MAXIMA = 1;

    public string $area_disco_m2 = '';

    public function mount(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->area_disco_m2 = str_replace('.', ',', rtrim(rtrim(number_format(Configuracion::instancia()->area_disco_m2, 4, '.', ''), '0'), '.'));

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'curvas');

        $this->resetErrorBag();

        $datos = Validator::make([
            'area_disco_m2' => str_replace(',', '.', trim($this->area_disco_m2)),
        ], [
            'area_disco_m2' => ['required', 'numeric', 'between:'.self::AREA_MINIMA.','.self::AREA_MAXIMA],
        ], [], [
            'area_disco_m2' => __('curvas.area_disco.campo'),
        ])->validate();

        Configuracion::instancia()->fill(['area_disco_m2' => round((float) $datos['area_disco_m2'], 4)])->save();

        $this->toastExito(__('curvas.area_disco.toasts.guardada'));
        $this->marcarFormularioLimpio();
        $this->dispatch('area-disco-guardada');
        $this->cerrarFormulario();
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['area_disco_m2'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-area-disco');
    }

    public function render()
    {
        return view('livewire.curvas.area-disco');
    }
}
