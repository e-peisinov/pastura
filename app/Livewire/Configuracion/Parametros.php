<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Parámetros del seguimiento (hijo de Configuracion, P17): cuántos días pasan
 * para que un potrero quede sin medir y cuántos puntos tiene que tener una
 * recorrida para no quedar para revisar.
 */
class Parametros extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    public const DIAS_MAXIMO = 365;

    public const PUNTOS_MAXIMO = 1000;

    public string $dias_sin_medir = '';

    public string $puntos_minimos = '';

    public function mount(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $configuracion = Configuracion::instancia();

        $this->dias_sin_medir = (string) $configuracion->dias_sin_medir;
        $this->puntos_minimos = (string) $configuracion->puntos_minimos;

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $datos = $this->validate([
            'dias_sin_medir' => ['required', 'integer', 'min:1', 'max:'.self::DIAS_MAXIMO],
            'puntos_minimos' => ['required', 'integer', 'min:1', 'max:'.self::PUNTOS_MAXIMO],
        ], [], [
            'dias_sin_medir' => __('configuracion.parametros.campos.dias_sin_medir'),
            'puntos_minimos' => __('configuracion.parametros.campos.puntos_minimos'),
        ]);

        $configuracion = Configuracion::instancia();
        $configuracion->fill([
            'dias_sin_medir' => (int) $datos['dias_sin_medir'],
            'puntos_minimos' => (int) $datos['puntos_minimos'],
        ])->save();

        $this->toastExito(__('configuracion.parametros.toasts.guardados'));
        $this->marcarFormularioLimpio();
        $this->dispatch('parametros-guardados');
        $this->cerrarFormulario();
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['dias_sin_medir', 'puntos_minimos'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-parametros');
    }

    public function render()
    {
        return view('livewire.configuracion.parametros');
    }
}
