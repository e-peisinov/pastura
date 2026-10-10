<?php

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ModalConfirmacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_por_omision_es_de_peligro_con_textos_de_eliminacion(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.modal-confirmacion confirmar="delete" cancelar="closeDeleteModal">
                ¿Está seguro de que desea eliminar esta recorrida?
            </x-ui.modal-confirmacion>
        BLADE);

        $this->assertStringContainsString('Confirmar eliminación', $html);
        $this->assertStringContainsString('¿Está seguro de que desea eliminar esta recorrida?', $html);
        $this->assertStringContainsString('>Eliminar<', $html);
        $this->assertMatchesRegularExpression('/>\s*Cancelar\s*</', $html);
        $this->assertStringContainsString('bg-red-600', $html);
        $this->assertStringContainsString('fa-solid fa-triangle-exclamation', $html);
    }

    public function test_confirmar_y_cancelar_van_a_sus_acciones_livewire(): void
    {
        $html = Blade::render('<x-ui.modal-confirmacion confirmar="delete" cancelar="closeDeleteModal">x</x-ui.modal-confirmacion>');

        $this->assertStringContainsString('wire:click="delete"', $html);
        $this->assertStringContainsString('wire:click="closeDeleteModal"', $html);
        // La X, el backdrop y Escape también cancelan.
        $this->assertStringContainsString('$wire.closeDeleteModal()', $html);
    }

    public function test_el_boton_de_confirmar_tiene_wire_loading_acotado_con_wire_target(): void
    {
        $html = Blade::render('<x-ui.modal-confirmacion confirmar="delete" cancelar="cerrar">x</x-ui.modal-confirmacion>');

        $this->assertStringContainsString('wire:loading.attr="disabled" wire:target="delete"', $html);

        // Ningún wire:loading suelto (§7, trampa 1).
        preg_match_all('/wire:loading(?:\.[a-z]+)*(?:="[^"]*")?/', $html, $loadings, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty($loadings[0]);
        foreach ($loadings[0] as [$texto, $pos]) {
            $resto = substr($html, $pos, 200);
            $this->assertStringContainsString('wire:target="delete"', $resto, "wire:loading sin wire:target: $texto");
        }
    }

    public function test_tipo_advertencia_con_titulo_boton_y_z_propios(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.modal-confirmacion tipo="advertencia" titulo="Cambios sin guardar"
                boton="Descartar cambios" confirmar="descartarCambios" cancelar="seguirEditando" :z="60">
                Hay cambios sin guardar. ¿Desea descartarlos?
            </x-ui.modal-confirmacion>
        BLADE);

        $this->assertStringContainsString('Cambios sin guardar', $html);
        $this->assertStringContainsString('>Descartar cambios<', $html);
        $this->assertStringContainsString('bg-amber-600', $html);
        $this->assertStringContainsString(' z-[60] ', $html);
        $this->assertStringNotContainsString('bg-red-600', $html);
    }

    public function test_tipo_info_usa_azul_y_sus_textos(): void
    {
        $html = Blade::render('<x-ui.modal-confirmacion tipo="info" confirmar="a" cancelar="b">x</x-ui.modal-confirmacion>');

        $this->assertStringContainsString('bg-blue-600', $html);
        $this->assertStringContainsString('>Aceptar<', $html);
        $this->assertStringContainsString('>Confirmar<', $html);
    }

    public function test_un_tipo_desconocido_cae_en_peligro(): void
    {
        $html = Blade::render('<x-ui.modal-confirmacion tipo="cualquiera" confirmar="a" cancelar="b">x</x-ui.modal-confirmacion>');

        $this->assertStringContainsString('bg-red-600', $html);
    }
}
