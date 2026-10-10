<?php

namespace App\Support;

use App\Enums\Nivel;
use App\Enums\Rol;
use Illuminate\Support\Facades\Route;

/**
 * Un ítem del sidebar (D24): nombre (clave de lang/es/layout.php), nombre de
 * ruta, icono Font Awesome y el nivel por omisión del recorredor (D49). El
 * administrador nace con Editar en todo; el recorredor, con lo que diga cada
 * fila (Ver si no se indica). Lo que manda después es la matriz del usuario.
 */
final readonly class Modulo
{
    public function __construct(
        public string $nombre,
        public string $ruta,
        public string $icono,
        public Nivel $recorredor = Nivel::Ver,
        /** Letra del atajo Alt+letra (D55), en minúscula. Null: sin atajo. */
        public ?string $tecla = null,
        /**
         * Anunciado (D66): se ve en el sidebar apagado y sin enlace, con el
         * title "Próximamente". Vive en Modulos::anunciados(), no en todos():
         * no tiene permisos, atajo ni fila en la matriz de usuarios.
         */
        public bool $anunciado = false,
    ) {}

    public function etiqueta(): string
    {
        return __('layout.modulos.'.$this->nombre);
    }

    /** Título del enlace: la etiqueta y, si tiene, el atajo. Anunciado: "próximamente". */
    public function titulo(): string
    {
        if ($this->anunciado) {
            return __('layout.anunciado', ['modulo' => $this->etiqueta()]);
        }

        return $this->tecla === null
            ? $this->etiqueta()
            : __('layout.atajos.modulo', ['modulo' => $this->etiqueta(), 'tecla' => strtoupper($this->tecla)]);
    }

    /** El nivel con el que nace la matriz de un usuario de ese rol (D49). */
    public function porOmision(Rol $rol): Nivel
    {
        return $rol === Rol::Admin ? Nivel::Editar : $this->recorredor;
    }

    /**
     * El número del circulito rojo del sidebar (D60): lo que espera a quien
     * puede editar el módulo. Null si el módulo no avisa nada o el usuario no
     * edita; 0 se imprime oculto, así el JS puede mostrarlo cuando llegue algo.
     */
    public function notificaciones(): ?int
    {
        if (! auth()->user()?->puedeEditar($this->nombre)) {
            return null;
        }

        return match ($this->nombre) {
            default => null,
        };
    }

    /** La ruta ya existe: sin esto el módulo no se muestra. */
    public function tieneRuta(): bool
    {
        return Route::has($this->ruta);
    }

    public function url(): string
    {
        return route($this->ruta);
    }

    /** Activo si la página actual es la ruta del módulo o una hija (`ruta.*`). */
    public function activo(): bool
    {
        return request()->routeIs($this->ruta, $this->ruta.'.*');
    }
}
