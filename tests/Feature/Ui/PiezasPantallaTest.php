<?php

namespace Tests\Feature\Ui;

use App\Enums\ColorAcento;
use App\Support\Tema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Piezas chicas que comparten todas las pantallas: <x-ui.pagina>, <x-ui.boton>,
 * <x-ui.badge>, <x-ui.campo> + <x-ui.input>/<x-ui.textarea>/<x-ui.checkbox>,
 * <x-ui.solapas> y <x-ui.vacio>.
 */
class PiezasPantallaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Tema::forzar(ColorAcento::Slate);
    }

    protected function tearDown(): void
    {
        Tema::reiniciar();

        parent::tearDown();
    }

    public function test_pagina_renderiza_titulo_icono_y_contenido(): void
    {
        // El slot `acciones` se prueba en una pantalla real (UsuariosTest): renderizar
        // un slot nombrado con Blade::render deja un buffer abierto en PHPUnit.
        $html = Blade::render('<x-ui.pagina titulo="Campos" icono="fa-tags">cuerpo</x-ui.pagina>');

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('<span>Campos</span>', $html);
        $this->assertStringContainsString('class="fa-solid fa-tags text-slate-700"', $html);
        $this->assertStringContainsString('cuerpo', $html);
    }

    public function test_boton_primario_toma_el_acento_del_tema_y_el_type_por_omision_es_button(): void
    {
        $html = Blade::render('<x-ui.boton icono="fa-plus" wire:click="nueva">Nuevo campo</x-ui.boton>');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('bg-slate-700 hover:bg-slate-800 text-white', $html);
        $this->assertStringContainsString('wire:click="nueva"', $html);
        $this->assertStringContainsString('class="fa-solid fa-plus"', $html);
        $this->assertStringContainsString('Nuevo campo', $html);
    }

    public function test_boton_variantes_y_enlace(): void
    {
        $this->assertStringContainsString('bg-red-600', Blade::render('<x-ui.boton variante="peligro">x</x-ui.boton>'));
        $this->assertStringContainsString('border-gray-300 bg-white', Blade::render('<x-ui.boton variante="secundario">x</x-ui.boton>'));
        $this->assertStringContainsString('type="submit"', Blade::render('<x-ui.boton type="submit">x</x-ui.boton>'));

        $enlace = Blade::render('<x-ui.boton href="/admin/usuarios" variante="secundario">Ir</x-ui.boton>');
        $this->assertStringContainsString('<a href="/admin/usuarios"', $enlace);
        $this->assertStringNotContainsString('<button', $enlace);
    }

    public function test_badge_usa_solo_colores_semanticos(): void
    {
        $this->assertStringContainsString('bg-emerald-100 text-emerald-800', Blade::render('<x-ui.badge color="verde">Activa</x-ui.badge>'));
        $this->assertStringContainsString('bg-red-100 text-red-800', Blade::render('<x-ui.badge color="rojo">Debe</x-ui.badge>'));
        $this->assertStringContainsString('bg-amber-100', Blade::render('<x-ui.badge color="ambar">x</x-ui.badge>'));
        $this->assertStringContainsString('bg-blue-100', Blade::render('<x-ui.badge color="azul">x</x-ui.badge>'));
        $this->assertStringContainsString('bg-gray-100 text-gray-700', Blade::render('<x-ui.badge>Inactiva</x-ui.badge>'));
    }

    public function test_campo_muestra_rotulo_asterisco_y_el_error_inline(): void
    {
        $errores = new ViewErrorBag;
        $errores->put('default', new MessageBag(['nombre' => ['El nombre es obligatorio.']]));
        View::share('errors', $errores);

        $html = Blade::render('<x-ui.campo etiqueta="Nombre" nombre="nombre" obligatorio><x-ui.input nombre="nombre" wire:model="nombre" /></x-ui.campo>');

        $this->assertMatchesRegularExpression('/<label\s+for="campo-nombre"/', $html);
        $this->assertStringContainsString('Nombre', $html);
        $this->assertStringContainsString('<span class="text-red-600" aria-hidden="true">*</span>', $html);
        $this->assertStringContainsString('id="campo-nombre"', $html);
        $this->assertStringContainsString('wire:model="nombre"', $html);
        $this->assertStringContainsString('data-error="nombre">El nombre es obligatorio.</p>', $html);
        $this->assertStringContainsString('border-red-500', $html);
    }

    public function test_input_sin_error_usa_el_borde_gris_y_el_anillo_del_tema(): void
    {
        View::share('errors', new ViewErrorBag);

        $html = Blade::render('<x-ui.input nombre="nombre" wire:model="nombre" />');

        $this->assertStringContainsString('border-gray-300', $html);
        $this->assertStringNotContainsString('border-red-500', $html);
        $this->assertStringContainsString('focus:ring-slate-500', $html);
    }

    public function test_textarea_y_checkbox(): void
    {
        // Sin $errors compartido (fuera de una request) también renderiza.
        $textarea = Blade::render('<x-ui.textarea nombre="observaciones" wire:model="observaciones" :filas="4" />');
        $this->assertStringContainsString('<textarea rows="4"', $textarea);
        $this->assertStringContainsString('id="campo-observaciones"', $textarea);

        $checkbox = Blade::render('<x-ui.checkbox nombre="requiere_posicion" etiqueta="Por rueda" ayuda="Pide posición" wire:model="requiere_posicion" />');
        $this->assertStringContainsString('type="checkbox"', $checkbox);
        $this->assertStringContainsString('Por rueda', $checkbox);
        $this->assertStringContainsString('Pide posición', $checkbox);
    }

    public function test_opciones_es_un_radio_por_valor_con_la_elegida_pintada_por_peer_checked(): void
    {
        $html = Blade::render('<x-ui.opciones nombre="permiso-campos" wire:model="permisos.campos" :opciones="\App\Enums\Nivel::cases()" />');

        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertStringContainsString('data-opciones="permiso-campos"', $html);
        $this->assertSame(3, substr_count($html, 'type="radio"'));
        $this->assertMatchesRegularExpression('/<input type="radio" name="permiso-campos" value="ver"[^>]*class="peer sr-only"[^>]*wire:model="permisos\.campos"/', $html);
        $this->assertStringContainsString('peer-checked:bg-slate-700 peer-checked:text-white', $html);
        $this->assertStringContainsString('Sin acceso', $html);
        $this->assertStringContainsString('>Editar</span>', $html);
        // El wire:model va en los radios, no en el contenedor.
        $this->assertDoesNotMatchRegularExpression('/<div[^>]*wire:model/', $html);

        $deshabilitado = Blade::render('<x-ui.opciones :opciones="[\'a\' => \'A\', \'b\' => \'B\']" :disabled="true" />');
        $this->assertSame(2, substr_count($deshabilitado, ' disabled'));
    }

    public function test_solapas_marcan_la_activa_con_el_acento_y_llaman_la_accion(): void
    {
        $html = Blade::render('<x-ui.solapas :opciones="[\'campo\' => \'Campo\', \'lote\' => \'Lote\']" activa="lote" />');

        $this->assertStringContainsString('wire:click="cambiarSolapa(\'campo\')"', $html);
        $this->assertMatchesRegularExpression('/aria-selected="true"[^>]*data-solapa="lote"[^>]*class="[^"]*border-slate-700 text-slate-700/', $html);
        $this->assertMatchesRegularExpression('/aria-selected="false"[^>]*data-solapa="campo"[^>]*class="[^"]*border-transparent/', $html);
    }

    public function test_vacio_muestra_icono_y_texto(): void
    {
        $html = Blade::render('<x-ui.vacio icono="fa-tags">Nada por acá</x-ui.vacio>');

        $this->assertStringContainsString('data-vacio', $html);
        $this->assertStringContainsString('fa-solid fa-tags', $html);
        $this->assertStringContainsString('Nada por acá', $html);
    }

    public function test_un_enlace_interno_navega_con_livewire_y_uno_externo_no(): void
    {
        // D31: la navegación entre pantallas no recarga la página.
        $this->assertStringContainsString('wire:navigate', Blade::render('<x-ui.boton href="/admin/usuarios">Ir</x-ui.boton>'));
        $this->assertStringContainsString('wire:navigate', Blade::render('<x-ui.boton :href="route(\'admin.usuarios\')">Ir</x-ui.boton>'));

        // Los que tienen que salir de la aplicación, no.
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.boton href="https://www.argentina.gob.ar">Ir</x-ui.boton>'));
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.boton href="/recorrida.pdf" target="_blank">Bajar</x-ui.boton>'));
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.boton href="/admin/usuarios" :navegar="false">Ir</x-ui.boton>'));

        // La acción de fila sigue la misma regla: un PDF en otra pestaña no navega con Livewire.
        $this->assertStringContainsString('wire:navigate', Blade::render('<x-ui.accion icono="fa-eye" etiqueta="Ver" href="/admin/usuarios" />'));
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.accion icono="fa-file-pdf" etiqueta="PDF" href="/recorridas/1/pdf" target="_blank" />'));
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.accion icono="fa-eye" etiqueta="Ver" href="/admin/usuarios" :navegar="false" />'));
        $this->assertStringNotContainsString('wire:navigate', Blade::render('<x-ui.boton wire:click="nueva">Nueva</x-ui.boton>'));
    }

    public function test_accion_es_solo_el_icono_con_la_etiqueta_en_title_y_aria_label(): void
    {
        // D33: en la fila va el icono; el rótulo queda para el tooltip y el lector.
        $html = Blade::render('<x-ui.accion icono="fa-trash" color="peligro" etiqueta="Eliminar" wire:click="borrar(1)" />');

        $this->assertStringContainsString('<button type="button"', $html);
        $this->assertStringContainsString('title="Eliminar"', $html);
        $this->assertStringContainsString('aria-label="Eliminar"', $html);
        $this->assertStringContainsString('class="fa-solid fa-trash"', $html);
        $this->assertStringContainsString('wire:click="borrar(1)"', $html);
        $this->assertStringContainsString('text-red-600', $html);
        // El texto no se escribe al lado del icono.
        $this->assertStringNotContainsString('>Eliminar<', $html);
        // Área de toque usable en el teléfono.
        $this->assertStringContainsString('w-9 h-9', $html);

        $neutra = Blade::render('<x-ui.accion icono="fa-eye" etiqueta="Ver" wire:click="ver(1)" />');
        $this->assertStringContainsString('text-gray-500', $neutra);
        $this->assertStringNotContainsString('text-red-600', $neutra);

        $enlace = Blade::render('<x-ui.accion icono="fa-eye" etiqueta="Ver" href="/recorridas/1" />');
        $this->assertStringContainsString('<a href="/recorridas/1" wire:navigate', $enlace);
    }

    public function test_ningun_enlace_interno_quedo_sin_wire_navigate(): void
    {
        // D31: se revisan los <a href> escritos a mano; los de <x-ui.boton> y
        // <x-ui.accion> lo ponen solos. Quedan afuera tel:, mailto:, externos,
        // las anclas de la misma página (#seccion, P25) y los que salen del panel a otra aplicación del mismo servidor
        // (`data-fuera-del-panel`, como la app de campo en /app, P12).
        $sinNavegar = [];
        $vistas = array_merge(
            glob(resource_path('views/livewire/**/*.blade.php')),
            glob(resource_path('views/components/ui/*.blade.php')),
            glob(resource_path('views/layouts/*.blade.php')),
        );

        foreach ($vistas as $archivo) {
            preg_match_all('~<a\s+href="([^"]*)"([^>]*)>~s', file_get_contents($archivo), $coincidencias, PREG_SET_ORDER);

            foreach ($coincidencias as [$etiqueta, $href, $resto]) {
                $externo = str_starts_with($href, 'http') || str_starts_with($href, 'tel:') || str_starts_with($href, 'mailto:')
                    || str_starts_with($href, '#')
                    || str_contains($resto, 'data-fuera-del-panel');

                if (! $externo && ! str_contains($resto, 'wire:navigate') && ! str_contains($resto, '$navegaConLivewire')) {
                    $sinNavegar[] = basename($archivo).': '.$etiqueta;
                }
            }
        }

        $this->assertSame([], $sinNavegar, 'Enlaces internos sin wire:navigate (D31)');
    }

    public function test_ninguna_accion_de_fila_quedo_con_texto_al_lado_del_icono(): void
    {
        // D33: en las tablas y cards las acciones son <x-ui.accion>. Un
        // <x-ui.boton> con icono y wire:click adentro de un listado es la
        // forma vieja; los botones con texto que quedan son de encabezado de
        // pantalla y de footer de modal.
        $sospechosos = [];

        foreach (glob(resource_path('views/livewire/**/*.blade.php')) as $archivo) {
            $contenido = file_get_contents($archivo);

            preg_match_all(
                '~<x-ui\.boton\s+variante="(?:enlace|peligro)"\s+icono="fa-[\w-]+"[^>]*wire:click="(?:editar|eliminar|reactivar|ver|confirmarBaja|verDetalle|verHistorial|quitar|confirmarQuitar|confirmarEliminar)~',
                $contenido,
                $coincidencias,
            );

            foreach ($coincidencias[0] as $coincidencia) {
                $sospechosos[] = basename($archivo).': '.trim($coincidencia);
            }
        }

        $this->assertSame([], $sospechosos, 'Acciones de fila que todavía usan <x-ui.boton> con texto');
    }

    public function test_ninguna_pieza_escribe_dark(): void
    {
        foreach (glob(resource_path('views/components/ui/*.blade.php')) as $archivo) {
            $this->assertStringNotContainsString('dark:', file_get_contents($archivo), basename($archivo));
        }
    }
}
