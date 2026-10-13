<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\Configuracion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Parámetros (hijo de Configuracion, P17): cuántos días pasan para que un
 * potrero quede sin medir, cuántos puntos tiene que tener una recorrida para
 * no quedar para revisar y el área del disco, que pasa los gramos de cada
 * muestra de una curva a kg MS/ha (P23). El área se escribe con coma decimal.
 */
class Parametros extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    public const DIAS_MAXIMO = 365;

    public const PUNTOS_MAXIMO = 1000;

    /** De un aro de 10 cm a uno de un metro cuadrado. */
    public const AREA_MINIMA = 0.005;

    public const AREA_MAXIMA = 1;

    public string $dias_sin_medir = '';

    public string $puntos_minimos = '';

    public string $area_disco_m2 = '';

    public function mount(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $configuracion = Configuracion::instancia();

        $this->dias_sin_medir = (string) $configuracion->dias_sin_medir;
        $this->puntos_minimos = (string) $configuracion->puntos_minimos;
        $this->area_disco_m2 = str_replace('.', ',', rtrim(rtrim(number_format($configuracion->area_disco_m2, 4, '.', ''), '0'), '.'));

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $this->resetErrorBag();

        $datos = Validator::make([
            'dias_sin_medir' => $this->dias_sin_medir,
            'puntos_minimos' => $this->puntos_minimos,
            'area_disco_m2' => str_replace(',', '.', trim($this->area_disco_m2)),
        ], [
            'dias_sin_medir' => ['required', 'integer', 'min:1', 'max:'.self::DIAS_MAXIMO],
            'puntos_minimos' => ['required', 'integer', 'min:1', 'max:'.self::PUNTOS_MAXIMO],
            'area_disco_m2' => ['required', 'numeric', 'between:'.self::AREA_MINIMA.','.self::AREA_MAXIMA],
        ], [], [
            'dias_sin_medir' => __('configuracion.parametros.campos.dias_sin_medir'),
            'puntos_minimos' => __('configuracion.parametros.campos.puntos_minimos'),
            'area_disco_m2' => __('configuracion.parametros.campos.area_disco_m2'),
        ])->validate();

        $configuracion = Configuracion::instancia();
        $configuracion->fill([
            'dias_sin_medir' => (int) $datos['dias_sin_medir'],
            'puntos_minimos' => (int) $datos['puntos_minimos'],
            'area_disco_m2' => round((float) $datos['area_disco_m2'], 4),
        ])->save();

        $this->toastExito(__('configuracion.parametros.toasts.guardados'));
        $this->marcarFormularioLimpio();
        $this->dispatch('parametros-guardados');
        $this->cerrarFormulario();
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['dias_sin_medir', 'puntos_minimos', 'area_disco_m2'];
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
