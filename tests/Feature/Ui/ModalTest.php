<?php

namespace Tests\Feature\Ui;

use App\Enums\ColorAcento;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class ModalTest extends TestCase
{
    use RefreshDatabase;

    private function render(string $blade): string
    {
        return Blade::render($blade);
    }

    public function test_renderiza_titulo_icono_solid_y_el_slot(): void
    {
        $html = $this->render('<x-ui.modal titulo="Nueva recorrida" icono="fa-plus">Contenido del body</x-ui.modal>');

        $this->assertStringContainsString('Nueva recorrida', $html);
        $this->assertStringContainsString('class="fa-solid fa-plus"', $html);
        $this->assertStringContainsString('Contenido del body', $html);
        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
    }

    public function test_respeta_un_icono_que_ya_trae_estilo(): void
    {
        $html = $this->render('<x-ui.modal titulo="x" icono="fa-regular fa-calendar">.</x-ui.modal>');

        $this->assertStringContainsString('class="fa-regular fa-calendar"', $html);
        $this->assertStringNotContainsString('fa-solid fa-regular', $html);
    }

    public function test_la_x_y_escape_usan_la_misma_accion_de_cierre_y_el_backdrop_no_cierra(): void
    {
        $html = $this->render('<x-ui.modal titulo="x" cerrar="cerrarFormulario">.</x-ui.modal>');

        $this->assertStringContainsString('$wire.cerrarFormulario()', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window="if (esElDeArriba()) cerrar()"', $html);
        $this->assertStringContainsString('aria-label="Cerrar"', $html);

        // D55: un click afuera sin querer no tira un formulario a medio cargar.
        $this->assertStringNotContainsString('x-on:click.self', $html);
        $this->assertStringNotContainsString('click.outside', $html);
    }

    public function test_teclado_primero_foco_inicial_enter_tabea_y_f2_guarda(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.modal titulo="x" submit="save">
                <input name="a">
                <x-slot:footer><button type="submit">Guardar</button></x-slot:footer>
            </x-ui.modal>
        BLADE);

        // Al abrir, el foco va al primer campo del body.
        $this->assertStringContainsString('this.enfocarPrimero();', $html);
        $this->assertStringContainsString('x-ref="cuerpo"', $html);

        // Enter pasa al siguiente campo; en el último, guarda. Un textarea o
        // un botón conservan su Enter.
        $this->assertStringContainsString('x-on:keydown.enter="enterTabea($event)"', $html);
        $this->assertStringContainsString("['TEXTAREA', 'BUTTON', 'A', 'SELECT'].includes(campo.tagName)", $html);
        $this->assertStringContainsString('if (! this.siguienteCampo(campo)) this.guardar();', $html);

        // F2 guarda solo desde el modal de más arriba, enviando el form.
        $this->assertStringContainsString("if (\$event.key === 'F2' && esElDeArriba()) { \$event.preventDefault(); guardar() }", $html);
        $this->assertStringContainsString('x-ref="formulario"', $html);
        $this->assertStringContainsString('$refs.formulario.requestSubmit()', $html);
        // Sin form, F2 dispara el botón marcado data-atajo-guardar.
        $this->assertStringContainsString("querySelector('[data-atajo-guardar]')?.click()", $html);

        // Los desplegables avisan cuando eligieron con el teclado y el modal
        // pasa al campo siguiente, sin que el aviso suba a un modal padre.
        $this->assertStringContainsString('x-on:ui-siguiente-campo="$event.stopPropagation(); if (! siguienteCampo($event.target)) $event.target.blur()"', $html);
    }

    public function test_por_omision_cierra_con_closeModal(): void
    {
        $html = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');

        $this->assertStringContainsString('$wire.closeModal()', $html);
    }

    public function test_pone_scroll_lock_al_fondo_y_limita_la_altura_al_viewport(): void
    {
        $html = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');

        $this->assertStringContainsString("document.body.classList.add('overflow-hidden')", $html);
        $this->assertStringContainsString("document.body.classList.remove('overflow-hidden')", $html);
        $this->assertStringContainsString('max-h-full', $html);
        $this->assertStringContainsString('overflow-y-auto', $html);
        $this->assertStringContainsString('bg-black/50', $html);
        $this->assertStringNotContainsString('bg-opacity', $html);

        // Al cerrar el modal de arriba el fondo sigue bloqueado si queda otro
        // abierto: se cuentan los OTROS modales, excluyendo el propio.
        $this->assertStringContainsString('modal !== $el', $html);
        $this->assertStringContainsString('otros.length === 0', $html);
    }

    public function test_ancho_auto_por_omision_con_tope_y_tamanos_fijos(): void
    {
        $auto = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');
        $lg = $this->render('<x-ui.modal titulo="x" ancho="lg">.</x-ui.modal>');
        $xxl = $this->render('<x-ui.modal titulo="x" ancho="2xl">.</x-ui.modal>');

        $this->assertStringContainsString('w-fit', $auto);
        $this->assertStringContainsString('md:max-w-6xl', $auto);
        $this->assertStringContainsString('w-full max-w-lg', $lg);
        $this->assertStringContainsString('w-full max-w-2xl', $xxl);

        // Los anchos grandes son para los modales que llevan una tabla adentro.
        $this->assertStringContainsString('w-full max-w-3xl', $this->render('<x-ui.modal titulo="x" ancho="3xl">.</x-ui.modal>'));
        $this->assertStringContainsString('w-full max-w-4xl', $this->render('<x-ui.modal titulo="x" ancho="4xl">.</x-ui.modal>'));
    }

    public function test_submit_envuelve_body_y_footer_en_un_form_con_display_contents(): void
    {
        $html = $this->render(<<<'BLADE'
            <x-ui.modal titulo="x" submit="save">
                <input name="a">
                <x-slot:footer><button type="submit">Guardar</button></x-slot:footer>
            </x-ui.modal>
        BLADE);

        $this->assertStringContainsString('<form wire:submit.prevent="save" x-ref="formulario" class="contents">', $html);
        $this->assertMatchesRegularExpression('/<form[^>]*>.*<input name="a">.*Guardar.*<\/form>/s', $html);
    }

    public function test_sin_submit_no_hay_form(): void
    {
        $html = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');

        $this->assertStringNotContainsString('<form', $html);
    }

    public function test_el_body_separa_sus_hijos_con_space_y_4(): void
    {
        $html = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');

        $this->assertMatchesRegularExpression('/overflow-y-auto[^"]*space-y-4/', $html);
    }

    public function test_z_index_para_modales_apilados_con_clases_literales(): void
    {
        $this->assertStringContainsString(' z-50 ', $this->render('<x-ui.modal titulo="x">.</x-ui.modal>'));
        $this->assertStringContainsString(' z-[60] ', $this->render('<x-ui.modal titulo="x" :z="60">.</x-ui.modal>'));
        $this->assertStringContainsString(' z-[70] ', $this->render('<x-ui.modal titulo="x" :z="70">.</x-ui.modal>'));
    }

    public function test_el_header_toma_el_gradiente_del_tema(): void
    {
        Tema::forzar(ColorAcento::Teal);

        $html = $this->render('<x-ui.modal titulo="x">.</x-ui.modal>');

        $this->assertStringContainsString('bg-gradient-to-r from-teal-700 to-teal-800', $html);
        $this->assertStringNotContainsString('dark:', $html);
    }
}
