<?php

namespace App\Livewire\Traits;

use Livewire\Form;

/**
 * Cambios sin guardar (estandares-ux.md §2).
 *
 * Cerrar un formulario con cambios pendientes pide confirmación, ya sea con la
 * X, con Escape o con el click afuera: los tres pasan por `intentarCerrar`.
 *
 * Criterio de dirty state: foto de los campos al abrir el formulario
 * (`marcarFormularioLimpio()`) y comparación floja (!=) al cerrar.
 *
 * El componente define:
 *   - `camposFormulario(): array` con los nombres de las propiedades del formulario;
 *   - `cerrarFormulario(): void` con el cierre real de siempre.
 *
 * En la vista:
 *   <x-ui.modal ... cerrar="intentarCerrar">
 *   @if($confirmandoDescarte)
 *       <x-ui.modal-confirmacion tipo="advertencia" titulo="Cambios sin guardar"
 *           boton="Descartar cambios" confirmar="descartarCambios" cancelar="seguirEditando" :z="60">
 *           Hay cambios sin guardar. ¿Desea descartarlos?
 *       </x-ui.modal-confirmacion>
 *   @endif
 */
trait ConfirmaDescarte
{
    public bool $confirmandoDescarte = false;

    /** @var array<string, mixed> */
    public array $fotoFormulario = [];

    /** @return list<string> propiedades públicas que componen el formulario */
    abstract protected function camposFormulario(): array;

    /** El cierre real del formulario (por ejemplo, `$this->closeModal()`). */
    abstract public function cerrarFormulario(): void;

    /** Saca la foto de los campos. Llamar al terminar de cargar el formulario. */
    public function marcarFormularioLimpio(): void
    {
        $this->fotoFormulario = $this->valoresFormulario();
        $this->confirmandoDescarte = false;
    }

    public function formularioSucio(): bool
    {
        $actual = $this->valoresFormulario();

        foreach ($this->camposFormulario() as $campo) {
            // Comparación floja a propósito: "" vs null, 10 vs "10" no son cambios.
            if (($actual[$campo] ?? null) != ($this->fotoFormulario[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /** Acción de la X, Escape y el click afuera del modal del formulario. */
    public function intentarCerrar(): void
    {
        if ($this->formularioSucio()) {
            $this->confirmandoDescarte = true;

            return;
        }

        $this->cerrarConfirmado();
    }

    public function descartarCambios(): void
    {
        $this->cerrarConfirmado();
    }

    public function seguirEditando(): void
    {
        $this->confirmandoDescarte = false;
    }

    private function cerrarConfirmado(): void
    {
        $this->confirmandoDescarte = false;
        $this->fotoFormulario = [];
        $this->cerrarFormulario();
    }

    /** @return array<string, mixed> */
    private function valoresFormulario(): array
    {
        $valores = [];

        foreach ($this->camposFormulario() as $campo) {
            $valor = $this->{$campo} ?? null;

            // Un Form de Livewire (los datos del cheque, D53) se compara por sus campos.
            $valores[$campo] = $valor instanceof Form ? $valor->all() : $valor;
        }

        return $valores;
    }
}
