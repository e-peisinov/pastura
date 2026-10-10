<?php

namespace Tests\Unit\Support;

use App\Enums\ColorAcento;
use App\Models\Configuracion;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tema::reiniciar();
    }

    protected function tearDown(): void
    {
        Tema::reiniciar();
        parent::tearDown();
    }

    public function test_sin_configuracion_cargada_usa_green(): void
    {
        $this->assertSame(ColorAcento::Green, Tema::paleta());
        $this->assertSame('from-green-800 to-green-900', Tema::gradienteHeader());
        $this->assertSame('bg-green-800 hover:bg-green-900 text-white', Tema::botonPrimario());
        $this->assertSame('text-green-800', Tema::acentoTexto());
    }

    public function test_lee_el_color_de_acento_de_la_configuracion(): void
    {
        Configuracion::factory()->create(['color_acento' => ColorAcento::Teal]);

        $this->assertSame(ColorAcento::Teal, Tema::paleta());
        $this->assertSame('from-teal-700 to-teal-800', Tema::gradienteHeader());
        $this->assertSame('bg-teal-700 hover:bg-teal-800 text-white', Tema::botonPrimario());
    }

    public function test_cachea_la_paleta_dentro_del_request_hasta_reiniciar(): void
    {
        $configuracion = Configuracion::factory()->create(['color_acento' => ColorAcento::Blue]);
        $this->assertSame(ColorAcento::Blue, Tema::paleta());

        $configuracion->update(['color_acento' => ColorAcento::Zinc]);
        $this->assertSame(ColorAcento::Blue, Tema::paleta());

        Tema::reiniciar();
        $this->assertSame(ColorAcento::Zinc, Tema::paleta());
    }

    public function test_forzar_pisa_lo_que_dice_la_base(): void
    {
        Configuracion::factory()->create(['color_acento' => ColorAcento::Slate]);

        Tema::forzar(ColorAcento::Blue);
        $this->assertSame('text-blue-700', Tema::acentoTexto());

        Tema::forzar(null);
        $this->assertSame('text-slate-700', Tema::acentoTexto());
    }

    public function test_cada_paleta_tiene_todas_las_clases_y_ninguna_se_interpola(): void
    {
        $metodos = [
            'gradienteHeader', 'botonPrimario', 'acentoTexto', 'acentoFondo',
            'acentoFondoSuave', 'acentoBorde', 'anilloFoco', 'enlace',
        ];

        foreach (ColorAcento::cases() as $paleta) {
            Tema::forzar($paleta);

            foreach ($metodos as $metodo) {
                $clases = Tema::$metodo();

                $this->assertNotSame('', $clases, "$paleta->value::$metodo vacío");
                $this->assertStringContainsString(
                    "-{$paleta->value}-",
                    $clases,
                    "$paleta->value::$metodo no usa su propio color",
                );
                $this->assertStringNotContainsString('dark:', $clases);
            }
        }
    }

    public function test_las_paletas_disponibles_son_las_del_enum(): void
    {
        $this->assertSame(ColorAcento::cases(), Tema::paletasDisponibles());
    }
}
