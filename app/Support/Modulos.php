<?php

namespace App\Support;

use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * La única lista de módulos del sistema (D24, estandares-ux.md §6). El
 * sidebar muestra solo los que ya tienen ruta (Route::has) y sobre los que
 * el usuario tiene al menos Ver (D49). Sumar un módulo es agregar una fila
 * acá; la vista no se toca.
 *
 * El orden es el del sidebar. Las rutas son las de docs/dominio.md, con el
 * tablero en /dashboard (D25). El cuarto argumento es el nivel por omisión
 * del recorredor (Ver si no se indica); el administrador nace con Editar.
 */
final class Modulos
{
    /** @return list<Modulo> */
    public static function todos(): array
    {
        // El quinto argumento es la letra de Alt+letra (D55). Se evitan D y E,
        // que en Windows abren la barra de direcciones y el menú del navegador.
        return [
            new Modulo('tablero', 'dashboard', 'fa-gauge-high', tecla: 't'),
            new Modulo('campos', 'campos', 'fa-map-location-dot', tecla: 'c'),
            new Modulo('curvas', 'curvas', 'fa-chart-line', tecla: 'b'),
            new Modulo('recorridas', 'recorridas', 'fa-route', Nivel::Editar, tecla: 'r'),
            new Modulo('reportes', 'reportes', 'fa-chart-column', Nivel::SinAcceso, tecla: 'p'),
            new Modulo('usuarios', 'admin.usuarios', 'fa-user-gear', Nivel::SinAcceso, tecla: 'u'),
            new Modulo('configuracion', 'configuracion', 'fa-gear', Nivel::SinAcceso, tecla: 'g'),
            new Modulo('auditoria', 'admin.auditoria', 'fa-clipboard-check', Nivel::SinAcceso, tecla: 'i'),
        ];
    }

    /**
     * Los módulos anunciados (D66): existen como promesa, no como pantalla.
     * El sidebar los dibuja al final, apagados y sin enlace, para todo
     * usuario con sesión. No están en todos(): sin permisos, sin atajo, sin
     * fila en la matriz de usuarios. Cuando el módulo se construye, la fila
     * pasa a todos() con su ruta y su letra, y sale de acá.
     *
     * @return list<Modulo>
     */
    public static function anunciados(): array
    {
        return [];
    }

    /**
     * Los módulos libres (P25): con ruta y atajo, pero sin permisos. Los ve
     * todo usuario con sesión, al final del sidebar; no están en todos(), así
     * que no tienen fila en la matriz de usuarios ni nivel por omisión.
     *
     * @return list<Modulo>
     */
    public static function libres(): array
    {
        return [
            new Modulo('instrucciones', 'instrucciones', 'fa-circle-question', tecla: 'n'),
        ];
    }

    /** @return list<string> */
    public static function nombres(): array
    {
        return array_map(fn (Modulo $modulo) => $modulo->nombre, self::todos());
    }

    public static function porNombre(string $nombre): ?Modulo
    {
        foreach (self::todos() as $modulo) {
            if ($modulo->nombre === $nombre) {
                return $modulo;
            }
        }

        return null;
    }

    /**
     * La matriz con la que nace un usuario de ese rol (D49).
     *
     * @return array<string, Nivel>
     */
    public static function nivelesPorOmision(Rol $rol): array
    {
        $niveles = [];

        foreach (self::todos() as $modulo) {
            $niveles[$modulo->nombre] = $modulo->porOmision($rol);
        }

        return $niveles;
    }

    /**
     * Los módulos que ve un usuario: con ruta registrada y al menos Ver, y
     * después los libres, que no piden permiso (P25).
     *
     * @param  list<Modulo>|null  $lista  por omisión, todos(); inyectable para tests
     * @return list<Modulo>
     */
    public static function visibles(?User $usuario, ?array $lista = null): array
    {
        // Dado de baja no ve nada, tampoco los libres.
        if ($usuario === null || ! $usuario->activo) {
            return [];
        }

        $conPermiso = array_filter(
            $lista ?? self::todos(),
            fn (Modulo $modulo) => $modulo->tieneRuta()
                && Gate::forUser($usuario)->allows('puedeVer', $modulo->nombre),
        );
        $libres = $lista === null
            ? array_filter(self::libres(), fn (Modulo $modulo) => $modulo->tieneRuta())
            : [];

        return array_values([...$conPermiso, ...$libres]);
    }
}
