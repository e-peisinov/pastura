<?php

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class TablaCardsTest extends TestCase
{
    public function test_la_tabla_se_oculta_en_mobile_y_envuelve_una_table(): void
    {
        $html = Blade::render('<x-ui.tabla><thead><tr><th>Campo</th></tr></thead><tbody></tbody></x-ui.tabla>');

        $this->assertStringContainsString('class="hidden md:block overflow-x-auto"', $html);
        $this->assertStringContainsString('<table class="min-w-full divide-y divide-gray-200', $html);
        $this->assertStringContainsString('<th>Campo</th>', $html);
    }

    public function test_las_cards_se_ocultan_en_escritorio(): void
    {
        $html = Blade::render('<x-ui.cards><div>una card</div></x-ui.cards>');

        $this->assertStringContainsString('class="md:hidden p-4 space-y-4"', $html);
        $this->assertStringContainsString('<div>una card</div>', $html);
    }

    public function test_aceptan_clases_extra_sin_perder_las_propias(): void
    {
        $tabla = Blade::render('<x-ui.tabla class="mt-4">x</x-ui.tabla>');
        $cards = Blade::render('<x-ui.cards class="mt-4">x</x-ui.cards>');

        $this->assertMatchesRegularExpression('/class="hidden md:block overflow-x-auto mt-4"/', $tabla);
        $this->assertMatchesRegularExpression('/class="md:hidden p-4 space-y-4 mt-4"/', $cards);
    }
}
