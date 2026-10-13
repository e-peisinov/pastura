<?php

namespace App\Livewire\Instrucciones;

use App\Enums\Rol;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Instrucciones (P25): cómo se usa GeoGrass. Es un módulo libre: la ve todo
 * usuario con sesión, sin fila en la matriz. Las secciones de la app son
 * para todos; las del panel aparecen si el usuario puede ver ese módulo, y el
 * administrador las ve todas. Los textos están en lang/es/instrucciones.php.
 */
#[Layout('layouts.app')]
class Instrucciones extends Component
{
    /** id (ancla y clave de lang) => [grupo, icono, módulo que pide Ver o null]. El panel, en el orden del sidebar. */
    public const SECCIONES = [
        'como-funciona' => ['app', 'fa-lightbulb', null],
        'instalar' => ['app', 'fa-mobile-screen-button', null],
        'recorrida' => ['app', 'fa-person-walking', null],
        'calibrar' => ['app', 'fa-ruler-vertical', null],
        'senal' => ['app', 'fa-signal', null],
        'tablero' => ['panel', 'fa-gauge-high', 'tablero'],
        'campos' => ['panel', 'fa-map-location-dot', 'campos'],
        'curvas' => ['panel', 'fa-chart-line', 'curvas'],
        'panel-recorridas' => ['panel', 'fa-route', 'recorridas'],
        'reportes' => ['panel', 'fa-chart-column', 'reportes'],
        'usuarios' => ['panel', 'fa-user-gear', 'usuarios'],
        'configuracion' => ['panel', 'fa-gear', 'configuracion'],
        'auditoria' => ['panel', 'fa-clipboard-check', 'auditoria'],
        'problemas' => ['ayuda', 'fa-screwdriver-wrench', null],
    ];

    /** @return array<string, list<array{id: string, icono: string}>> por grupo, en orden */
    private function seccionesVisibles(): array
    {
        $usuario = auth()->user();
        $todo = $usuario->rol === Rol::Admin;
        $grupos = [];

        foreach (self::SECCIONES as $id => [$grupo, $icono, $modulo]) {
            if ($todo || $modulo === null || Gate::allows('puedeVer', $modulo)) {
                $grupos[$grupo][] = ['id' => $id, 'icono' => $icono];
            }
        }

        return $grupos;
    }

    public function render()
    {
        return view('livewire.instrucciones.instrucciones', [
            'grupos' => $this->seccionesVisibles(),
            // La dirección de la app, para la guía de instalación.
            'urlApp' => rtrim(preg_replace('#^https?://#', '', asset('app/')), '/'),
        ]);
    }
}
