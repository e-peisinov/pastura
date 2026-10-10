<?php

namespace Tests\Feature\Roles;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El rol (D9, D49): dos valores, default recorredor. Ya no es un permiso —
 * eso está en PermisosTest —, pero sigue diciendo de quién es cada cosa.
 */
class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_usuario_nuevo_nace_recorredor_por_defecto(): void
    {
        $usuario = User::factory()->create();

        $this->assertSame(Rol::Recorredor, $usuario->fresh()->rol);
        $this->assertTrue($usuario->esRecorredor());
        $this->assertTrue($usuario->activo);
    }

    public function test_el_rol_tiene_etiqueta_en_castellano(): void
    {
        $this->assertSame('Administrador', Rol::Admin->etiqueta());
        $this->assertSame('Recorredor', Rol::Recorredor->etiqueta());
    }
}
