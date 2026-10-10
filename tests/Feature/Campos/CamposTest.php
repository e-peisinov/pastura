<?php

namespace Tests\Feature\Campos;

use App\Enums\Nivel;
use App\Livewire\Campos\CampoFormulario;
use App\Livewire\Campos\Campos;
use App\Livewire\Campos\PotreroFormulario;
use App\Models\Campo;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CamposTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private const CUADRADO = [
        'type' => 'Polygon',
        'coordinates' => [[[-64.35, -33.12], [-64.34, -33.12], [-64.34, -33.13], [-64.35, -33.13], [-64.35, -33.12]]],
    ];

    public function test_el_listado_muestra_potreros_superficie_y_ultima_recorrida(): void
    {
        $campo = Campo::factory()->create(['nombre' => 'La Esperanza', 'localidad' => 'Río Cuarto']);
        $potrero = Potrero::factory()->for($campo)->create(['contorno' => self::CUADRADO]);
        Potrero::factory()->for($campo)->create();
        Recorrida::factory()->for($potrero)->create(['iniciada_en' => '2026-10-05 09:30:00']);
        Campo::factory()->inactivo()->create(['nombre' => 'El Viejo']);

        $this->actingAs($this->admin)->get(route('campos'))
            ->assertOk()
            ->assertSeeInOrder(['La Esperanza', 'Río Cuarto', '2 potreros', '103,8 ha', '05/10/2026'])
            ->assertDontSee('El Viejo');
    }

    public function test_la_busqueda_encuentra_por_nombre_o_localidad(): void
    {
        Campo::factory()->create(['nombre' => 'La Esperanza', 'localidad' => 'Río Cuarto']);
        Campo::factory()->create(['nombre' => 'San José', 'localidad' => 'Sampacho']);

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->set('busqueda', 'samp')
            ->assertSee('San José')
            ->assertDontSee('La Esperanza');
    }

    public function test_alta_de_un_campo_con_nombre_unico(): void
    {
        Campo::factory()->create(['nombre' => 'La Esperanza']);

        Livewire::actingAs($this->admin)->test(CampoFormulario::class)
            ->set('nombre', 'La Esperanza')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'unique'])
            ->set('nombre', '  Don Pedro  ')
            ->set('localidad', 'Alcira Gigena')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('campo-guardado');

        $this->assertDatabaseHas('campos', ['nombre' => 'Don Pedro', 'localidad' => 'Alcira Gigena', 'activo' => true]);
    }

    public function test_sin_recorridas_el_campo_se_borra_con_sus_potreros(): void
    {
        $campo = Campo::factory()->create();
        Potrero::factory()->count(2)->for($campo)->create();

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('confirmarBaja', $campo->id)
            ->call('baja')
            ->assertDispatched('toast', tipo: 'exito');

        $this->assertDatabaseMissing('campos', ['id' => $campo->id]);
        $this->assertDatabaseCount('potreros', 0);
    }

    public function test_con_recorridas_el_campo_se_desactiva(): void
    {
        $potrero = Potrero::factory()->create();
        Recorrida::factory()->for($potrero)->create();

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('confirmarBaja', $potrero->campo_id)
            ->call('baja')
            ->assertDispatched('toast', tipo: 'aviso');

        $this->assertFalse($potrero->campo->fresh()->activo);
        $this->assertDatabaseCount('recorridas', 1);
    }

    public function test_la_ficha_muestra_el_mapa_y_la_ultima_altura_de_cada_potrero(): void
    {
        $campo = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $potrero = Potrero::factory()->for($campo)->create(['nombre' => 'Lote 4', 'contorno' => self::CUADRADO]);
        $recorrida = Recorrida::factory()->for($potrero)->create(['iniciada_en' => '2026-10-05 09:30:00']);
        Medicion::factory()->for($recorrida)->create(['altura_cm' => 10]);
        Medicion::factory()->for($recorrida)->create(['altura_cm' => 13]);

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('verDetalle', $campo->id)
            ->assertSeeHtml('data-detalle-campo')
            ->assertSeeHtml('data-mapa-campo')
            ->assertSee('Lote 4')
            ->assertSee('Última: 05/10/2026 · 11,5 cm');
    }

    public function test_alta_de_un_potrero_con_contorno_desde_la_ficha(): void
    {
        $campo = Campo::factory()->create();

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('verDetalle', $campo->id)
            ->call('agregarPotrero')
            ->assertSet('mostrarPotrero', true)
            ->assertSeeHtml('data-formulario-potrero');

        Livewire::actingAs($this->admin)->test(PotreroFormulario::class, ['campoId' => $campo->id])
            ->set('nombre', 'Lote 4')
            ->set('tipo_pastura', 'Alfalfa')
            ->set('contorno', self::CUADRADO)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertDispatched('potrero-guardado');

        $potrero = Potrero::query()->where('campo_id', $campo->id)->sole();
        $this->assertSame('Alfalfa', $potrero->tipo_pastura);
        $this->assertEqualsWithDelta(103.79, $potrero->superficieHa(), 0.05);
    }

    public function test_el_nombre_del_potrero_es_unico_dentro_del_campo(): void
    {
        $campo = Campo::factory()->create();
        Potrero::factory()->for($campo)->create(['nombre' => 'Lote 4']);
        Potrero::factory()->create(['nombre' => 'Lote 5']);

        Livewire::actingAs($this->admin)->test(PotreroFormulario::class, ['campoId' => $campo->id])
            ->set('nombre', 'Lote 4')
            ->call('guardar')
            ->assertHasErrors(['nombre'])
            // Otro campo puede tener un "Lote 5"; este también.
            ->set('nombre', 'Lote 5')
            ->call('guardar')
            ->assertHasNoErrors();
    }

    public function test_un_contorno_que_no_es_poligono_se_rechaza(): void
    {
        $campo = Campo::factory()->create();

        Livewire::actingAs($this->admin)->test(PotreroFormulario::class, ['campoId' => $campo->id])
            ->set('nombre', 'Lote 4')
            ->set('contorno', ['type' => 'Point', 'coordinates' => [-64.3, -33.1]])
            ->call('guardar')
            ->assertHasErrors(['contorno']);

        $this->assertDatabaseCount('potreros', 0);
    }

    public function test_un_potrero_de_otro_campo_no_se_toca_desde_la_ficha(): void
    {
        $campo = Campo::factory()->create();
        $ajeno = Potrero::factory()->create();

        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('verDetalle', $campo->id)
            ->call('confirmarBajaPotrero', $ajeno->id);
    }

    public function test_el_potrero_con_recorridas_se_desactiva_y_sin_ellas_se_borra(): void
    {
        $campo = Campo::factory()->create();
        $conHistoria = Potrero::factory()->for($campo)->create();
        $sinHistoria = Potrero::factory()->for($campo)->create();
        Recorrida::factory()->for($conHistoria)->create();

        Livewire::actingAs($this->admin)->test(Campos::class)
            ->call('verDetalle', $campo->id)
            ->call('confirmarBajaPotrero', $conHistoria->id)
            ->call('bajaPotrero')
            ->call('confirmarBajaPotrero', $sinHistoria->id)
            ->call('bajaPotrero');

        $this->assertFalse($conHistoria->fresh()->activo);
        $this->assertDatabaseMissing('potreros', ['id' => $sinHistoria->id]);
    }

    public function test_con_ver_se_consulta_sin_editar(): void
    {
        $lector = User::factory()->conNivel('campos', Nivel::Ver)->create();
        $campo = Campo::factory()->create(['nombre' => 'La Esperanza']);

        $this->actingAs($lector)->get(route('campos'))->assertOk()->assertSee('La Esperanza');

        Livewire::actingAs($lector)->test(Campos::class)
            ->assertDontSeeHtml('wire:click="nuevo"')
            ->call('verDetalle', $campo->id)
            ->assertDontSeeHtml('data-agregar-potrero')
            ->call('nuevo')->assertForbidden();

        Livewire::actingAs($lector)->test(Campos::class)->call('confirmarBaja', $campo->id)->assertForbidden();
        Livewire::actingAs($lector)->test(CampoFormulario::class)->assertForbidden();
        Livewire::actingAs($lector)->test(PotreroFormulario::class, ['campoId' => $campo->id])->assertForbidden();
    }

    public function test_sin_acceso_la_ruta_da_403(): void
    {
        $sinAcceso = User::factory()->conNivel('campos', Nivel::SinAcceso)->create();

        $this->actingAs($sinAcceso)->get(route('campos'))->assertForbidden();
    }
}
