<?php

namespace Tests\Feature\Recorridas;

use App\Enums\Rol;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Database\Seeders\RecorridasEjemploSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder de ejemplo carga recorridas sobre La Esperanza / Lote 4, con los
 * puntos adentro del contorno, y al repetirlo reemplaza solo las suyas.
 */
class RecorridasEjemploSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_recorridas_dentro_del_contorno_y_es_repetible(): void
    {
        User::factory()->admin()->create(['email' => 'admin@pastura.test']);
        User::factory()->create(['email' => 'recorredor@pastura.test', 'rol' => Rol::Recorredor]);
        $potrero = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'La Esperanza']))->create([
            'nombre' => 'Lote 4',
            'contorno' => ['type' => 'Polygon', 'coordinates' => [[[-64.35, -33.12], [-64.342, -33.12], [-64.342, -33.128], [-64.35, -33.128], [-64.35, -33.12]]]],
        ]);
        $real = Recorrida::factory()->for($potrero)->create(['dispositivo' => 'iPhone']);

        $this->seed(RecorridasEjemploSeeder::class);
        $this->seed(RecorridasEjemploSeeder::class);

        $ejemplo = Recorrida::query()->where('dispositivo', RecorridasEjemploSeeder::DISPOSITIVO);
        $this->assertSame(8, $ejemplo->count());
        $this->assertSame(1, $ejemplo->clone()->where('activo', false)->count());
        $this->assertNotNull($real->fresh());

        $conGps = Medicion::query()->whereNotNull('latitud')->get();
        $this->assertNotEmpty($conGps);
        foreach ($conGps as $m) {
            $this->assertTrue($m->longitud > -64.35 && $m->longitud < -64.342 && $m->latitud > -33.128 && $m->latitud < -33.12);
        }
        $this->assertGreaterThan(0, Medicion::query()->where('activo', false)->count());
    }

    public function test_sin_el_potrero_de_prueba_no_carga_nada(): void
    {
        $this->seed(RecorridasEjemploSeeder::class);

        $this->assertSame(0, Recorrida::query()->count());
    }
}
