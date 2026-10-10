<?php

namespace Tests\Feature\Configuracion;

use App\Enums\ColorAcento;
use App\Livewire\Configuracion\DatosOrganizacion;
use App\Models\Configuracion;
use App\Models\User;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Datos de la organización (D8, P17): la marca blanca. Nombre, logo y color,
 * en un modal de Configuración.
 */
class DatosOrganizacionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(DatosOrganizacion::DISCO);
        $this->admin = User::factory()->admin()->create();
    }

    protected function tearDown(): void
    {
        Tema::reiniciar();

        parent::tearDown();
    }

    public function test_carga_los_datos_actuales_y_los_guarda(): void
    {
        Configuracion::factory()->create(['nombre' => 'Pasturómetro', 'color_acento' => ColorAcento::Green]);

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->assertSet('nombre', 'Pasturómetro')
            ->assertSet('color_acento', 'green')
            ->assertSeeHtml('data-paleta="teal"')
            ->set('nombre', '  Estancia La Paz ')
            ->call('elegirPaleta', 'teal')
            ->assertSet('color_acento', 'teal')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('configuracion'));

        $configuracion = Configuracion::instancia();
        $this->assertSame('Estancia La Paz', $configuracion->nombre);
        $this->assertSame(ColorAcento::Teal, $configuracion->color_acento);
        $this->assertSame(ColorAcento::Teal, Tema::paleta());
        $this->assertSame(__('configuracion.organizacion.toasts.guardados'), session('success'));
    }

    public function test_el_nombre_es_obligatorio_y_la_paleta_tiene_que_ser_una_de_las_permitidas(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->set('nombre', '')
            ->call('elegirPaleta', 'fucsia')
            ->assertSet('color_acento', 'green')
            ->set('color_acento', 'fucsia')
            ->call('guardar')
            ->assertHasErrors(['nombre' => 'required', 'color_acento']);
    }

    public function test_sube_el_logo_y_reemplaza_el_anterior(): void
    {
        Storage::disk(DatosOrganizacion::DISCO)->put('logos/viejo.png', 'viejo');
        Configuracion::factory()->create(['logo_path' => 'logos/viejo.png']);

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->assertSeeHtml('data-logo-actual')
            ->set('logo', UploadedFile::fake()->image('nuevo.png', 200, 200))
            ->assertHasNoErrors('logo')
            ->assertSeeHtml('data-logo-nuevo')
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('configuracion'));

        $ruta = Configuracion::instancia()->logo_path;
        $this->assertStringStartsWith('logos/', $ruta);
        Storage::disk(DatosOrganizacion::DISCO)->assertExists($ruta);
        Storage::disk(DatosOrganizacion::DISCO)->assertMissing('logos/viejo.png');
    }

    public function test_rechaza_un_archivo_que_no_es_imagen_o_pesa_mas_de_un_mega(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->set('logo', UploadedFile::fake()->create('lista.pdf', 100, 'application/pdf'))
            ->assertHasErrors('logo')
            ->assertSet('logo', null)
            ->set('logo', UploadedFile::fake()->image('enorme.png')->size(2048))
            ->assertHasErrors('logo');

        $this->assertNull(Configuracion::instancia()->logo_path);
    }

    public function test_quita_el_logo(): void
    {
        Storage::disk(DatosOrganizacion::DISCO)->put('logos/viejo.png', 'viejo');
        Configuracion::factory()->create(['logo_path' => 'logos/viejo.png']);

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->call('sacarLogo')
            ->assertSet('quitar_logo', true)
            ->assertDontSeeHtml('data-logo-actual')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertNull(Configuracion::instancia()->logo_path);
        Storage::disk(DatosOrganizacion::DISCO)->assertMissing('logos/viejo.png');
    }

    public function test_cerrar_con_cambios_pide_confirmacion_y_un_logo_elegido_cuenta_como_cambio(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs($this->admin)->test(DatosOrganizacion::class)
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', false)
            ->assertDispatched('cerrar-datos-organizacion')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true)
            ->call('seguirEditando')
            ->set('logo', null)
            ->set('nombre', 'Otro nombre')
            ->call('intentarCerrar')
            ->assertSet('confirmandoDescarte', true);
    }

    public function test_solo_con_editar_en_configuracion(): void
    {
        Configuracion::factory()->create();

        Livewire::actingAs(User::factory()->create())->test(DatosOrganizacion::class)->assertForbidden();
    }
}
