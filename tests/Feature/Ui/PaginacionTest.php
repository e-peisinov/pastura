<?php

namespace Tests\Feature\Ui;

use App\Enums\ColorAcento;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PaginacionTest extends TestCase
{
    use RefreshDatabase;

    private function render(int $actual, int $total, int $porPagina = 10, int $registros = 95, string $extra = ''): string
    {
        $offset = ($actual - 1) * $porPagina;

        return Blade::render(
            '<x-ui.paginacion :current-page="$a" :total-pages="$t" :offset="$o" :per-page="$p" :total-records="$r" '.$extra.' />',
            ['a' => $actual, 't' => $total, 'o' => $offset, 'p' => $porPagina, 'r' => $registros],
        );
    }

    public function test_muestra_el_rango_de_registros(): void
    {
        $html = $this->render(2, 10);

        $this->assertStringContainsString('Mostrando 11 a 20 de 95 registros', $html);
    }

    public function test_la_ultima_pagina_no_pasa_del_total(): void
    {
        $html = $this->render(10, 10);

        $this->assertStringContainsString('Mostrando 91 a 95 de 95 registros', $html);
    }

    public function test_sin_registros_lo_dice_y_no_muestra_controles(): void
    {
        $html = $this->render(1, 1, 10, 0);

        $this->assertStringContainsString('No hay registros para mostrar', $html);
        $this->assertStringNotContainsString('wire:click', $html);
    }

    public function test_los_botones_llaman_a_gotoPage_por_omision(): void
    {
        $html = $this->render(2, 5, 10, 45);

        $this->assertStringContainsString('wire:click="gotoPage(1)"', $html);
        $this->assertStringContainsString('wire:click="gotoPage(3)"', $html);
        $this->assertStringContainsString('aria-current="page">2<', $html);
    }

    public function test_la_accion_es_configurable(): void
    {
        $html = $this->render(1, 3, 10, 25, 'accion="irAPagina"');

        $this->assertStringContainsString('wire:click="irAPagina(2)"', $html);
        $this->assertStringNotContainsString('gotoPage', $html);
    }

    public function test_en_los_extremos_deshabilita_anterior_o_siguiente(): void
    {
        $primera = $this->render(1, 5, 10, 45);
        $ultima = $this->render(5, 5, 10, 45);

        $this->assertStringNotContainsString('wire:click="gotoPage(0)"', $primera);
        $this->assertStringContainsString('aria-label="Siguiente"', $primera);
        $this->assertStringNotContainsString('aria-label="Anterior"', $primera);

        $this->assertStringNotContainsString('wire:click="gotoPage(6)"', $ultima);
        $this->assertStringContainsString('aria-label="Anterior"', $ultima);
        $this->assertStringNotContainsString('aria-label="Siguiente"', $ultima);
    }

    public function test_con_muchas_paginas_resume_con_puntos_suspensivos(): void
    {
        $html = $this->render(10, 20, 10, 195);

        $this->assertStringContainsString('gotoPage(1)', $html);
        $this->assertStringContainsString('gotoPage(2)', $html);
        $this->assertStringContainsString('gotoPage(9)', $html);
        $this->assertStringContainsString('gotoPage(11)', $html);
        $this->assertStringContainsString('gotoPage(19)', $html);
        $this->assertStringContainsString('gotoPage(20)', $html);
        $this->assertStringNotContainsString('gotoPage(5)', $html);
        $this->assertSame(2, substr_count($html, '>…<'));
    }

    public function test_mobile_solo_ve_pagina_x_de_y_y_escritorio_los_numeros(): void
    {
        $html = $this->render(2, 5, 10, 45);

        $this->assertMatchesRegularExpression('/md:hidden[^>]*>\s*Página 2 de 5/', $html);
        $this->assertStringContainsString('hidden md:flex', $html);
    }

    public function test_la_pagina_activa_toma_el_color_del_tema(): void
    {
        Tema::forzar(ColorAcento::Blue);

        $html = $this->render(2, 5, 10, 45);

        $this->assertStringContainsString('bg-blue-700 text-white" aria-current="page"', $html);
        $this->assertStringNotContainsString('dark:', $html);
    }
}
