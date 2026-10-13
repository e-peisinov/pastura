<?php

namespace Tests\Feature\Modelos;

use App\Models\Campo;
use App\Models\Potrero;
use App\Models\TipoPastura;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * La migración que pasa el tipo de pastura de texto libre a la tabla (P16):
 * cada texto distinto es un tipo, sin perder ninguno.
 */
class MigracionTipoPasturaTest extends TestCase
{
    use RefreshDatabase;

    public function test_convierte_los_textos_en_tipos_y_los_vuelve_a_texto_al_deshacerla(): void
    {
        $migracion = require database_path('migrations/2026_10_10_200002_cambiar_tipo_pastura_de_potreros.php');
        $migracion->down();

        $campo = Campo::factory()->create();
        $textos = ['Lote 1' => 'Alfalfa', 'Lote 2' => '  alfalfa ', 'Lote 3' => 'Festuca  alta', 'Lote 4' => null, 'Lote 5' => '   '];
        foreach ($textos as $nombre => $texto) {
            DB::table('potreros')->insert(['campo_id' => $campo->id, 'nombre' => $nombre, 'tipo_pastura' => $texto, 'activo' => true]);
        }

        $migracion->up();

        $this->assertSame(['Alfalfa', 'Festuca alta'], TipoPastura::query()->orderBy('nombre')->pluck('nombre')->all());
        $alfalfa = TipoPastura::query()->where('nombre', 'Alfalfa')->sole();
        $this->assertFalse($alfalfa->tieneAlturas());

        $tipoDe = fn (string $nombre) => Potrero::query()->where('nombre', $nombre)->sole()->tipoPastura?->nombre;
        $this->assertSame('Alfalfa', $tipoDe('Lote 1'));
        $this->assertSame('Alfalfa', $tipoDe('Lote 2'));
        $this->assertSame('Festuca alta', $tipoDe('Lote 3'));
        $this->assertNull($tipoDe('Lote 4'));
        $this->assertNull($tipoDe('Lote 5'));

        $migracion->down();

        $this->assertSame('Alfalfa', DB::table('potreros')->where('nombre', 'Lote 2')->value('tipo_pastura'));
        $this->assertNull(DB::table('potreros')->where('nombre', 'Lote 4')->value('tipo_pastura'));

        $migracion->up();
    }
}
