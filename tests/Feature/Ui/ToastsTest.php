<?php

namespace Tests\Feature\Ui;

use App\Livewire\Traits\EnviaToasts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class ToastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_contenedor_escucha_el_evento_toast_y_trae_las_duraciones_y_colores_del_estandar(): void
    {
        $html = Blade::render('<x-ui.toasts />');

        $this->assertStringContainsString('x-on:toast.window="agregar($event.detail)"', $html);
        $this->assertStringContainsString('exito: 4000, info: 4000, aviso: 6000, error: 8000', $html);
        $this->assertStringContainsString("'bg-emerald-600': toast.tipo === 'exito'", $html);
        $this->assertStringContainsString("'bg-red-600': toast.tipo === 'error'", $html);
        $this->assertStringContainsString("'bg-blue-600': toast.tipo === 'info'", $html);
        $this->assertStringContainsString("'bg-amber-600': toast.tipo === 'aviso'", $html);
        $this->assertStringContainsString('aria-label="Cerrar aviso"', $html);
        $this->assertStringNotContainsString('dark:', $html);
    }

    public function test_la_salida_marca_saliendo_y_recien_despues_saca_del_array(): void
    {
        $html = Blade::render('<x-ui.toasts />');

        $this->assertStringContainsString('toast.saliendo = true;', $html);
        $this->assertMatchesRegularExpression(
            '/toast\.saliendo = true;.*setTimeout\(\(\) => \{ this\.toasts = this\.toasts\.filter/s',
            $html,
        );
    }

    public function test_levanta_los_flashes_de_sesion_de_un_redirect(): void
    {
        session()->flash('success', 'Recorrida creada exitosamente.');
        session()->flash('error', 'Algo falló.');
        session()->flash('otra_clave', 'Esto no es un toast.');

        $html = html_entity_decode(Blade::render('<x-ui.toasts />'));

        $this->assertStringContainsString('{"tipo":"exito","mensaje":"Recorrida creada exitosamente."}', $html);
        $this->assertStringContainsString('{"tipo":"error","mensaje":"Algo falló."}', $html);
        $this->assertStringContainsString('JSON.parse($el.dataset.flashes)', $html);
        $this->assertStringNotContainsString('Esto no es un toast', $html);
    }

    public function test_las_claves_message_y_ok_tambien_son_exito(): void
    {
        session()->flash('message', 'Perfil actualizado.');
        session()->flash('ok', 'Listo.');
        session()->flash('info', 'Dato.');

        $html = html_entity_decode(Blade::render('<x-ui.toasts />'));

        $this->assertStringContainsString('{"tipo":"exito","mensaje":"Perfil actualizado."}', $html);
        $this->assertStringContainsString('{"tipo":"exito","mensaje":"Listo."}', $html);
        $this->assertStringContainsString('{"tipo":"info","mensaje":"Dato."}', $html);
    }

    public function test_esta_montado_una_sola_vez_en_el_layout_de_la_app(): void
    {
        $this->actingAs(User::factory()->create());

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'data-toasts'));
    }

    public function test_el_trait_dispara_el_evento_toast_con_tipo_y_mensaje(): void
    {
        $componente = new class extends Component
        {
            use EnviaToasts;

            public function exito(): void
            {
                $this->toastExito('Recorrida creada exitosamente.');
            }

            public function error(): void
            {
                $this->toastError('No se pudo registrar la salida: stock insuficiente.');
            }

            public function info(): void
            {
                $this->toastInfo('Dato.');
            }

            public function aviso(): void
            {
                $this->toastAviso('Ojo.');
            }

            public function render(): string
            {
                return '<div></div>';
            }
        };

        Livewire::test($componente)
            ->call('exito')
            ->assertDispatched('toast', tipo: 'exito', mensaje: 'Recorrida creada exitosamente.')
            ->call('error')
            ->assertDispatched('toast', tipo: 'error', mensaje: 'No se pudo registrar la salida: stock insuficiente.')
            ->call('info')
            ->assertDispatched('toast', tipo: 'info', mensaje: 'Dato.')
            ->call('aviso')
            ->assertDispatched('toast', tipo: 'aviso', mensaje: 'Ojo.');
    }

    public function test_los_metodos_del_trait_no_son_acciones_publicas(): void
    {
        foreach (['toastExito', 'toastError', 'toastInfo', 'toastAviso'] as $metodo) {
            $reflexion = new \ReflectionMethod(EnviaToasts::class, $metodo);
            $this->assertTrue($reflexion->isProtected(), "$metodo debería ser protected");
        }
    }
}
