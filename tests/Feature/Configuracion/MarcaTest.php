<?php

namespace Tests\Feature\Configuracion;

use App\Models\Configuracion;
use Database\Seeders\ConfiguracionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El sistema se llama GeoGrass (P22): es el nombre por omisión de la
 * organización, y la migración renombra la que todavía tenía el nombre
 * anterior sin tocar uno elegido por quien administra.
 */
class MarcaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_configuracion_guardada_el_sistema_se_llama_geograss(): void
    {
        $this->assertSame('GeoGrass', Configuracion::instancia()->nombre);
    }

    public function test_una_instalacion_nueva_arranca_como_geograss(): void
    {
        $this->seed(ConfiguracionSeeder::class);

        $this->assertSame('GeoGrass', Configuracion::query()->sole()->nombre);
    }

    public function test_la_migracion_renombra_solo_el_nombre_anterior(): void
    {
        $migracion = require database_path('migrations/2026_10_13_100001_renombrar_organizacion_a_geograss.php');

        $configuracion = Configuracion::factory()->create(['nombre' => 'Pasturómetro']);
        $migracion->up();
        $this->assertSame('GeoGrass', $configuracion->fresh()->nombre);

        $configuracion->update(['nombre' => 'Estancia La Paz']);
        $migracion->up();
        $this->assertSame('Estancia La Paz', $configuracion->fresh()->nombre);
    }
}
