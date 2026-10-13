<?php

namespace Tests\Feature;

use App\Enums\EstadoPotrero;
use App\Enums\Rol;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Models\User;
use App\Support\Geo\Contorno;
use App\Support\Tablero\EstadoPotreros;
use App\Support\Tablero\RevisionRecorridas;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El set de demo (para el video y las presentaciones) solo agrega datos: dos
 * campos con sus potreros, tipos de pastura con alturas y fórmula, usuarios
 * y un historial de recorridas que deja el tablero con potreros en cada
 * estado. Correrlo dos veces no duplica nada.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_carga_el_set_de_demo_sin_tocar_lo_que_ya_habia(): void
    {
        $propio = Potrero::factory()->for(Campo::factory()->create(['nombre' => 'Campo propio']))->create();
        $real = Recorrida::factory()->for($propio)->create(['dispositivo' => 'iPhone']);

        $this->seed(DemoSeeder::class);

        $this->assertSame(['El Mirador', 'Estancia La Aurora'], Campo::query()->where('nombre', '!=', 'Campo propio')->orderBy('nombre')->pluck('nombre')->all());
        $this->assertSame(11, Potrero::query()->whereKeyNot($propio->id)->count());
        $this->assertSame(0, Potrero::query()->whereKeyNot($propio->id)->whereNull('contorno')->count());
        $this->assertSame(4, TipoPastura::query()->whereNotNull('biomasa_a')->whereNotNull('altura_ingreso_cm')->count());
        $this->assertSame(Rol::Admin, User::query()->where('email', DemoSeeder::ADMIN)->first()->rol);
        $this->assertNotNull($real->fresh());
        $this->assertSame(1, $propio->recorridas()->count());
    }

    public function test_deja_potreros_en_cada_estado_y_recorridas_para_revisar(): void
    {
        $this->seed(DemoSeeder::class);

        $estados = EstadoPotreros::filas()->countBy(fn ($f) => $f->estado->value);
        $this->assertSame(4, $estados[EstadoPotrero::Listo->value]);
        $this->assertSame(4, $estados[EstadoPotrero::EnCrecimiento->value]);
        $this->assertSame(2, $estados[EstadoPotrero::BajoRemanente->value]);
        $this->assertSame(1, $estados[EstadoPotrero::NuncaMedido->value]);
        $this->assertSame(2, EstadoPotreros::filas()->where('sinMedir', true)->count());

        $this->assertSame(3, RevisionRecorridas::filas()->count());
    }

    public function test_los_puntos_caen_adentro_del_potrero_salvo_el_que_esta_fuera_a_proposito(): void
    {
        $this->seed(DemoSeeder::class);

        $fuera = Medicion::query()->with('recorrida.potrero')->whereNotNull('latitud')->get()
            ->reject(fn (Medicion $m) => Contorno::contiene($m->recorrida->potrero->contorno, $m->latitud, $m->longitud));

        $this->assertCount(1, $fuera);
        $this->assertSame('Avena 1', $fuera->first()->recorrida->potrero->nombre);
    }

    public function test_es_repetible(): void
    {
        $this->seed(DemoSeeder::class);
        $recorridas = Recorrida::query()->count();
        $mediciones = Medicion::query()->count();

        $this->seed(DemoSeeder::class);

        $this->assertSame($recorridas, Recorrida::query()->count());
        $this->assertSame($mediciones, Medicion::query()->count());
        $this->assertSame(11, Potrero::query()->count());
        $this->assertSame(1, User::query()->where('email', DemoSeeder::ADMIN)->count());
    }
}
