<?php

namespace App\Livewire\Configuracion;

use App\Livewire\Traits\ConfirmaDescarte;
use App\Livewire\Traits\EnviaToasts;
use App\Models\TipoPastura;
use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

/**
 * Alta y edición de un tipo de pastura (hijo de Configuracion, P16): nombre,
 * alturas de ingreso y de salida (las dos o ninguna, ingreso > salida) y la
 * fórmula de kg MS/ha (A y B, o ninguno). Los números se escriben con coma
 * decimal (convenciones.md, "Localización") y se aceptan también con punto.
 */
class TipoPasturaFormulario extends Component
{
    use ConfirmaDescarte, EnviaToasts;

    public const Z_DESCARTE = 60;

    public const ALTURA_MAXIMA = 300;

    public const BIOMASA_MAXIMA = 100000;

    public ?int $tipoId = null;

    public string $nombre = '';

    public string $altura_ingreso_cm = '';

    public string $altura_salida_cm = '';

    public string $biomasa_a = '';

    public string $biomasa_b = '';

    public function mount(?int $tipoId = null): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        if ($tipoId !== null) {
            $tipo = TipoPastura::query()->findOrFail($tipoId);

            $this->tipoId = $tipo->id;
            $this->nombre = $tipo->nombre;
            $this->altura_ingreso_cm = self::aTexto($tipo->altura_ingreso_cm);
            $this->altura_salida_cm = self::aTexto($tipo->altura_salida_cm);
            $this->biomasa_a = self::aTexto($tipo->biomasa_a);
            $this->biomasa_b = self::aTexto($tipo->biomasa_b);
        }

        $this->marcarFormularioLimpio();
    }

    public function guardar(): void
    {
        Gate::authorize('puedeEditar', 'configuracion');

        $entrada = [
            'nombre' => trim($this->nombre),
            'altura_ingreso_cm' => self::aNumero($this->altura_ingreso_cm),
            'altura_salida_cm' => self::aNumero($this->altura_salida_cm),
            'biomasa_a' => self::aNumero($this->biomasa_a),
            'biomasa_b' => self::aNumero($this->biomasa_b),
        ];

        $reglaIngreso = ['nullable', 'numeric', 'gt:0', 'max:'.self::ALTURA_MAXIMA, 'required_with:altura_salida_cm'];
        if (is_numeric($entrada['altura_salida_cm'])) {
            $reglaIngreso[] = 'gt:altura_salida_cm';
        }

        $datos = Validator::make($entrada, [
            'nombre' => ['required', 'string', 'max:100', $this->nombreUnico()],
            'altura_ingreso_cm' => $reglaIngreso,
            'altura_salida_cm' => ['nullable', 'numeric', 'gt:0', 'max:'.self::ALTURA_MAXIMA, 'required_with:altura_ingreso_cm'],
            'biomasa_a' => ['nullable', 'numeric', 'between:-'.self::BIOMASA_MAXIMA.','.self::BIOMASA_MAXIMA, 'required_with:biomasa_b'],
            'biomasa_b' => ['nullable', 'numeric', 'between:-'.self::BIOMASA_MAXIMA.','.self::BIOMASA_MAXIMA, 'required_with:biomasa_a'],
        ], [
            'altura_ingreso_cm.required_with' => __('configuracion.tipos.validacion.alturas_juntas'),
            'altura_salida_cm.required_with' => __('configuracion.tipos.validacion.alturas_juntas'),
            'altura_ingreso_cm.gt' => __('configuracion.tipos.validacion.ingreso_mayor'),
            'biomasa_a.required_with' => __('configuracion.tipos.validacion.formula_junta'),
            'biomasa_b.required_with' => __('configuracion.tipos.validacion.formula_junta'),
        ], [
            'nombre' => __('configuracion.tipos.campos.nombre'),
            'altura_ingreso_cm' => __('configuracion.tipos.campos.altura_ingreso_cm'),
            'altura_salida_cm' => __('configuracion.tipos.campos.altura_salida_cm'),
            'biomasa_a' => __('configuracion.tipos.campos.biomasa_a'),
            'biomasa_b' => __('configuracion.tipos.campos.biomasa_b'),
        ])->validate();

        if ($this->tipoId === null) {
            TipoPastura::query()->create($datos + ['activo' => true]);
            $this->toastExito(__('configuracion.tipos.toasts.creado'));
        } else {
            TipoPastura::query()->findOrFail($this->tipoId)->update($datos);
            $this->toastExito(__('configuracion.tipos.toasts.modificado'));
        }

        $this->marcarFormularioLimpio();
        $this->dispatch('tipo-pastura-guardado');
        $this->cerrarFormulario();
    }

    /** Único sin distinguir mayúsculas: "Alfalfa" y "alfalfa" son el mismo tipo. */
    private function nombreUnico(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $falla) {
            $repetido = TipoPastura::query()
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $valor)])
                ->when($this->tipoId !== null, fn ($q) => $q->whereKeyNot($this->tipoId))
                ->exists();

            if ($repetido) {
                $falla(__('validation.unique', ['attribute' => __('configuracion.tipos.campos.nombre')]));
            }
        };
    }

    /** "22,5" → "22.5"; vacío → null. Lo que no es número llega tal cual y lo rechaza `numeric`. */
    private static function aNumero(string $texto): ?string
    {
        $texto = trim($texto);

        return $texto === '' ? null : str_replace(',', '.', $texto);
    }

    /** 22.5 → "22,5"; 20.0 → "20"; null → "". */
    private static function aTexto(?float $valor): string
    {
        if ($valor === null) {
            return '';
        }

        return str_replace('.', ',', rtrim(rtrim(number_format($valor, 3, '.', ''), '0'), '.'));
    }

    /** @return list<string> */
    protected function camposFormulario(): array
    {
        return ['nombre', 'altura_ingreso_cm', 'altura_salida_cm', 'biomasa_a', 'biomasa_b'];
    }

    public function cerrarFormulario(): void
    {
        $this->dispatch('cerrar-tipo-pastura');
    }

    public function render()
    {
        return view('livewire.configuracion.tipo-pastura-formulario');
    }
}
