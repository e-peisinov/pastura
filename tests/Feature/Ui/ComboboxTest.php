<?php

namespace Tests\Feature\Ui;

use App\Enums\Nivel;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class ComboboxTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Componente de prueba. Las opciones se cargan en render() (§4c) y se fijan
     * desde el test por el static porque Livewire instancia la clase sin args.
     */
    private function componente(): Component
    {
        return new class extends Component
        {
            public static mixed $opciones = [];

            public static string $extra = '';

            public $valor = null;

            public function render(): string
            {
                return '<div><x-ui.combobox wire:model="valor" :opciones="$this->opciones()" '.static::$extra.' /></div>';
            }

            public function opciones(): mixed
            {
                return static::$opciones;
            }
        };
    }

    private function html(mixed $opciones, string $extra = ''): string
    {
        $componente = $this->componente();
        $componente::$opciones = $opciones;
        $componente::$extra = $extra;

        $html = Livewire::test($componente)->html();

        return html_entity_decode($html);
    }

    /** @return list<string> textos en el orden en que salen en data-opciones */
    private function textos(string $html): array
    {
        preg_match('/data-opciones="(.*?)"\s+data-disabled/s', $html, $m);
        $opciones = json_decode($m[1], true);

        return array_column($opciones, 'texto');
    }

    public function test_ordena_alfabeticamente_transliterando_tildes(): void
    {
        $html = $this->html(['b' => 'Ébano', 'a' => 'Zapato', 'c' => 'ardilla', 'd' => 'Índice']);

        $this->assertSame(['ardilla', 'Ébano', 'Índice', 'Zapato'], $this->textos($html));
    }

    public function test_ordenar_false_respeta_el_orden_semantico(): void
    {
        $html = $this->html(['zeta', 'alfa', 'beta'], ':ordenar="false"');

        $this->assertSame(['zeta', 'alfa', 'beta'], $this->textos($html));
    }

    public function test_acepta_una_lista_de_enums_con_etiqueta(): void
    {
        $html = $this->html(Nivel::cases(), ':ordenar="false"');

        $this->assertStringContainsString('{"valor":"sin_acceso","texto":"Sin acceso"}', $html);
        $this->assertStringContainsString('{"valor":"editar","texto":"Editar"}', $html);
    }

    public function test_acepta_una_lista_de_modelos_o_arrays_con_clave_y_etiqueta(): void
    {
        $html = $this->html(collect([
            ['id' => 7, 'nombre' => 'La Pastora'],
            ['id' => 3, 'nombre' => 'El Ombú'],
        ]));

        $this->assertStringContainsString('{"valor":3,"texto":"El Ombú"}', $html);
        $this->assertStringContainsString('{"valor":7,"texto":"La Pastora"}', $html);
        $this->assertSame(['El Ombú', 'La Pastora'], $this->textos($html));
    }

    public function test_clave_y_etiqueta_son_configurables(): void
    {
        $html = $this->html([['codigo' => 'DI', 'descripcion' => 'Delantera izquierda']], 'clave="codigo" etiqueta="descripcion"');

        $this->assertStringContainsString('{"valor":"DI","texto":"Delantera izquierda"}', $html);
    }

    public function test_respeta_wire_model_y_wire_model_live_via_entangle(): void
    {
        $normal = $this->html(['a' => 'A']);
        $this->assertMatchesRegularExpression("/entangle\('valor'\)(?!\.live)/", $normal);

        $componente = $this->componente();
        $componente::$opciones = ['a' => 'A'];
        $componente::$extra = '';
        $live = new class extends Component
        {
            public $valor = null;

            public function render(): string
            {
                return '<div><x-ui.combobox wire:model.live="valor" :opciones="[\'a\' => \'A\']" /></div>';
            }
        };
        $this->assertStringContainsString("entangle('valor').live", Livewire::test($live)->html());
    }

    public function test_con_mas_de_10_opciones_aparece_el_buscador(): void
    {
        $diez = $this->html(array_map(fn ($i) => "Opción $i", range(1, 10)));
        $once = $this->html(array_map(fn ($i) => "Opción $i", range(1, 11)));

        $this->assertStringNotContainsString('x-ref="buscador"', $diez);
        $this->assertStringContainsString('x-ref="buscador"', $once);
        $this->assertStringContainsString('placeholder="Buscar..."', $once);
    }

    public function test_el_filtro_por_escritura_ignora_tildes(): void
    {
        $html = $this->html(['a' => 'A']);

        $this->assertStringContainsString("normalize('NFD').replace(/[\\u0300-\\u036f]/g, '').toLowerCase()", $html);
    }

    public function test_teclado_flechas_enter_y_escape(): void
    {
        $html = $this->html(['a' => 'A']);

        $this->assertStringContainsString('x-on:keydown.down.prevent="mover(1)"', $html);
        $this->assertStringContainsString('x-on:keydown.up.prevent="mover(-1)"', $html);
        $this->assertStringContainsString('x-on:keydown.enter.prevent="confirmar()"', $html);
        $this->assertStringContainsString('x-on:keydown.escape.stop="cerrar()"', $html);
    }

    public function test_placeholder_y_opcion_vacia(): void
    {
        $html = $this->html(['a' => 'A'], 'placeholder="Estado" vacio="Todos"');

        $this->assertStringContainsString('>Estado</span>', $html);
        $this->assertMatchesRegularExpression('/<li role="option"[^>]*x-on:click="limpiar\(\)"[^>]*>\s*Todos\s*<\/li>/s', $html);
    }

    public function test_sin_vacio_no_hay_opcion_vacia_y_el_placeholder_es_el_generico(): void
    {
        $html = $this->html(['a' => 'A']);

        $this->assertStringContainsString('>Seleccionar...</span>', $html);
        $this->assertStringNotContainsString('x-on:click="limpiar()"\n', $html);
        $this->assertSame(0, preg_match('/<li role="option"[^>]*x-on:click="limpiar\(\)"/', $html));
    }

    public function test_con_mas_de_200_opciones_y_debug_avisa_en_el_log(): void
    {
        config(['app.debug' => true]);
        Log::spy();

        $this->html(array_map(fn ($i) => "Artículo $i", range(1, 201)));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $mensaje) => str_contains($mensaje, 'recibió 201 opciones') && str_contains($mensaje, 'wire:model="valor"'),
        );
    }

    public function test_sin_debug_no_avisa_aunque_haya_mas_de_200(): void
    {
        config(['app.debug' => false]);
        Log::spy();

        $this->html(array_map(fn ($i) => "Artículo $i", range(1, 201)));

        Log::shouldNotHaveReceived('warning');
    }

    public function test_con_200_o_menos_no_avisa(): void
    {
        config(['app.debug' => true]);
        Log::spy();

        $this->html(array_map(fn ($i) => "Artículo $i", range(1, 200)));

        Log::shouldNotHaveReceived('warning');
    }

    public function test_disabled_deshabilita_el_boton(): void
    {
        $html = $this->html(['a' => 'A'], ':disabled="true"');

        $this->assertStringContainsString('data-disabled="1"', $html);
        $this->assertMatchesRegularExpression('/<button type="button" x-ref="boton"[^>]*disabled/', $html);
    }

    public function test_usa_el_anillo_de_foco_y_el_resaltado_del_tema_sin_dark(): void
    {
        Tema::reiniciar();

        $html = $this->html(['a' => 'A']);

        $this->assertStringContainsString('focus:ring-green-600', $html);
        $this->assertStringContainsString("'bg-green-100 text-green-900': resaltado === indice", $html);
        $this->assertStringNotContainsString('dark:', $html);
    }

    public function test_limpiable_false_saca_la_x_para_los_filtros_con_todos_explicito(): void
    {
        $conX = $this->html(['todos' => 'Todos', 'activos' => 'Activos']);
        $sinX = $this->html(['todos' => 'Todos', 'activos' => 'Activos'], ':limpiable="false"');

        // Un filtro con "Todos" como opción nunca vale null (convenciones.md).
        $this->assertStringContainsString('x-on:click.stop="limpiar()"', $conX);
        $this->assertStringNotContainsString('x-on:click.stop="limpiar()"', $sinX);
        $this->assertStringContainsString('"valor":"todos"', $sinX);
    }
}
