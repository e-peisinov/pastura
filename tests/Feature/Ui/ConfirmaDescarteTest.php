<?php

namespace Tests\Feature\Ui;

use App\Livewire\Traits\ConfirmaDescarte;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class ConfirmaDescarteTest extends TestCase
{
    private function componente(): Component
    {
        return new class extends Component
        {
            use ConfirmaDescarte;

            public bool $showModal = false;

            public ?int $campo_id = null;

            public string $observaciones = '';

            public $kms = null;

            protected function camposFormulario(): array
            {
                return ['campo_id', 'observaciones', 'kms'];
            }

            public function openModal(): void
            {
                $this->showModal = true;
                $this->campo_id = 3;
                $this->observaciones = 'Cambio de aceite';
                $this->kms = 10;
                $this->marcarFormularioLimpio();
            }

            public function cerrarFormulario(): void
            {
                $this->showModal = false;
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };
    }

    public function test_sin_cambios_cierra_directo(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->assertSet('showModal', true)
            ->call('intentarCerrar')
            ->assertSet('showModal', false)
            ->assertSet('confirmandoDescarte', false);
    }

    public function test_con_cambios_pide_confirmacion_y_no_cierra(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('observaciones', 'Cambio de aceite y filtro')
            ->call('intentarCerrar')
            ->assertSet('showModal', true)
            ->assertSet('confirmandoDescarte', true);
    }

    public function test_descartar_cambios_cierra_el_formulario(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('campo_id', 5)
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true)
            ->call('descartarCambios')
            ->assertSet('showModal', false)
            ->assertSet('confirmandoDescarte', false);
    }

    public function test_seguir_editando_vuelve_al_formulario_con_los_cambios(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('campo_id', 5)
            ->call('intentarCerrar')
            ->call('seguirEditando')
            ->assertSet('showModal', true)
            ->assertSet('confirmandoDescarte', false)
            ->assertSet('campo_id', 5);
    }

    public function test_la_comparacion_es_floja(): void
    {
        // 10 vs "10" y "" vs null no son cambios: es lo que devuelve un input.
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('kms', '10')
            ->call('intentarCerrar')
            ->assertSet('showModal', false);
    }

    public function test_volver_al_valor_original_deja_el_formulario_limpio(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('observaciones', 'otra cosa')
            ->set('observaciones', 'Cambio de aceite')
            ->call('intentarCerrar')
            ->assertSet('showModal', false);
    }

    public function test_marcar_limpio_despues_de_guardar_resetea_la_foto(): void
    {
        Livewire::test($this->componente())
            ->call('openModal')
            ->set('observaciones', 'editado')
            ->call('marcarFormularioLimpio')
            ->call('intentarCerrar')
            ->assertSet('showModal', false);
    }
}
