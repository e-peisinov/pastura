<?php

namespace Tests\Feature\Modelos;

use App\Enums\AccionAuditada;
use App\Enums\EntidadAuditada;
use App\Enums\Nivel;
use App\Enums\Rol;
use App\Models\Auditoria;
use App\Models\Campo;
use App\Models\Configuracion;
use App\Models\Medicion;
use App\Models\Potrero;
use App\Models\Recorrida;
use App\Models\TipoPastura;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auditoría de lo sensible (P18): una fila por acción, escrita desde los
 * eventos del modelo, con una foto que alcanza para leerla en una frase
 * aunque la entidad ya no exista. No se audita todo: ni las altas de
 * recorridas y puntos (las subidas) ni los cambios que no importan.
 */
class AuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $juan;

    private Campo $campo;

    private Potrero $lote4;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create(['name' => 'Marta Admin']);
        $this->juan = User::factory()->create(['name' => 'Juan Pérez', 'email' => 'juan@pastura.test', 'rol' => Rol::Recorredor]);
        $this->campo = Campo::factory()->create(['nombre' => 'La Esperanza']);
        $this->lote4 = Potrero::factory()->for($this->campo)->create(['nombre' => 'Lote 4']);
    }

    private function ultima(): Auditoria
    {
        return Auditoria::query()->latest('id')->firstOrFail();
    }

    private function recorrida(): Recorrida
    {
        return Recorrida::factory()->for($this->lote4)->for($this->juan)->create(['iniciada_en' => '2026-10-05 08:00:00']);
    }

    public function test_subir_una_recorrida_con_sus_puntos_no_deja_nada(): void
    {
        $recorrida = $this->recorrida();
        Medicion::factory()->for($recorrida)->count(3)->create();
        $recorrida->update(['observaciones' => 'Viento fuerte']);

        $this->assertSame(0, Auditoria::query()->count());
    }

    public function test_anular_y_reactivar_una_recorrida(): void
    {
        $recorrida = $this->recorrida();
        $this->actingAs($this->admin);

        $recorrida->update(['activo' => false]);

        $fila = $this->ultima();
        $this->assertSame(EntidadAuditada::Recorrida, $fila->entidad);
        $this->assertSame(AccionAuditada::Modificado, $fila->accion);
        $this->assertSame($recorrida->id, $fila->entidad_id);
        $this->assertSame($this->admin->id, $fila->user_id);
        $this->assertSame('Marta Admin', $fila->nombreUsuario());
        $this->assertSame('Modificó la recorrida de La Esperanza · Lote 4 del 05/10/2026 (Juan Pérez): estado de válida a anulada.', $fila->descripcion());

        $recorrida->update(['activo' => true]);
        $this->assertStringEndsWith('estado de anulada a válida.', $this->ultima()->descripcion());
    }

    public function test_mover_una_recorrida_de_potrero_sin_sesion_queda_a_nombre_del_dueno(): void
    {
        $recorrida = $this->recorrida();
        $bajo = Potrero::factory()->for($this->campo)->create(['nombre' => 'El Bajo']);

        // La API mueve la recorrida (P11); sin usuario en sesión queda quien figura en la recorrida.
        $recorrida->update(['potrero_id' => $bajo->id]);

        $fila = $this->ultima();
        $this->assertSame($this->juan->id, $fila->user_id);
        $this->assertSame('Modificó la recorrida de La Esperanza · El Bajo del 05/10/2026 (Juan Pérez): potrero de La Esperanza · Lote 4 a La Esperanza · El Bajo.', $fila->descripcion());
    }

    public function test_descartar_un_punto(): void
    {
        $punto = Medicion::factory()->for($this->recorrida())->create(['tomada_en' => '2026-10-05 08:05:00', 'altura_cm' => 12.5]);
        $this->actingAs($this->admin);

        $punto->update(['activo' => false]);

        $this->assertSame(EntidadAuditada::Medicion, $this->ultima()->entidad);
        $this->assertSame('Modificó el punto de las 08:05 (12,5 cm) de la recorrida de La Esperanza · Lote 4 del 05/10/2026: estado de activo a descartado.', $this->ultima()->descripcion());
    }

    public function test_el_contorno_y_el_tipo_de_un_potrero_y_su_baja(): void
    {
        $alfalfa = TipoPastura::factory()->create(['nombre' => 'Alfalfa']);
        $this->actingAs($this->admin);

        $this->lote4->update([
            'contorno' => Potrero::factory()->conContorno()->make()->contorno,
            'tipo_pastura_id' => $alfalfa->id,
            'observaciones' => 'No se audita',
        ]);

        $this->assertSame('Modificó el potrero La Esperanza · Lote 4: tipo de pastura de — a Alfalfa, superficie de sin contorno a 103,8 ha.', $this->ultima()->descripcion());

        $this->lote4->update(['activo' => false]);
        $this->assertSame('Modificó el potrero La Esperanza · Lote 4: estado de activo a inactivo.', $this->ultima()->descripcion());

        $id = $this->lote4->id;
        $this->lote4->delete();
        $fila = $this->ultima();
        $this->assertSame(AccionAuditada::Eliminado, $fila->accion);
        $this->assertSame($id, $fila->entidad_id);
        $this->assertSame('Eliminó el potrero La Esperanza · Lote 4.', $fila->descripcion());
    }

    public function test_desactivar_y_borrar_un_campo(): void
    {
        $this->actingAs($this->admin);

        $this->campo->update(['activo' => false, 'localidad' => 'No se audita']);
        $this->assertSame('Modificó el campo «La Esperanza»: estado de activo a inactivo.', $this->ultima()->descripcion());

        $nuevo = Campo::factory()->create(['nombre' => 'San José']);
        $this->assertSame(1, Auditoria::query()->count(), 'el alta de un campo no se audita');
        $nuevo->delete();
        $this->assertSame('Eliminó el campo «San José».', $this->ultima()->descripcion());
    }

    public function test_rol_y_estado_de_un_usuario_pero_no_su_nombre(): void
    {
        $this->actingAs($this->admin);

        $this->juan->update(['name' => 'Juan A. Pérez']);
        $this->assertSame(0, Auditoria::query()->count());

        $this->juan->update(['rol' => Rol::Admin, 'activo' => false]);

        $this->assertSame(EntidadAuditada::Usuario, $this->ultima()->entidad);
        $this->assertSame('Modificó el usuario Juan A. Pérez (juan@pastura.test): rol de Recorredor a Administrador, estado de activo a inactivo.', $this->ultima()->descripcion());
    }

    public function test_los_permisos_se_auditan_solo_si_cambian(): void
    {
        $this->actingAs($this->admin);
        $antes = Auditoria::query()->count();

        // Recorredor: Campos nace en Ver y Recorridas en Editar. Como en la
        // pantalla, el usuario se busca de nuevo (recién creado, no se audita).
        $juan = $this->juan->fresh();
        $juan->asignarNiveles(['campos' => Nivel::Editar, 'recorridas' => Nivel::Editar]);

        $this->assertSame($antes + 1, Auditoria::query()->count());
        $this->assertSame('Modificó el usuario Juan Pérez (juan@pastura.test): permiso en Campos de Ver a Editar.', $this->ultima()->descripcion());

        $juan->asignarNiveles(['campos' => Nivel::Editar]);
        $this->assertSame($antes + 1, Auditoria::query()->count());
    }

    public function test_la_contrasena_solo_cuando_la_cambia_otro_y_sin_guardarla(): void
    {
        $this->actingAs($this->juan);
        $this->juan->update(['password' => 'otra-clave-1']);
        $this->assertSame(0, Auditoria::query()->count());

        $this->actingAs($this->admin);
        $this->juan->update(['password' => 'otra-clave-2']);

        $fila = $this->ultima();
        $this->assertSame('Modificó el usuario Juan Pérez (juan@pastura.test): contraseña nueva.', $fila->descripcion());
        $this->assertStringNotContainsString('otra-clave', json_encode($fila->datos));
        $this->assertSame([null, null], $fila->datos['cambios']['password']);
    }

    public function test_tipos_de_pastura_alta_cambios_y_baja(): void
    {
        $this->actingAs($this->admin);

        $tipo = TipoPastura::factory()->create(['nombre' => 'Alfalfa', 'altura_ingreso_cm' => 20, 'altura_salida_cm' => 7, 'biomasa_a' => 25, 'biomasa_b' => 0]);
        $this->assertSame('Creó el tipo de pastura «Alfalfa».', $this->ultima()->descripcion());

        $tipo->update(['altura_ingreso_cm' => 22.5, 'biomasa_b' => -100]);
        $this->assertSame('Modificó el tipo de pastura «Alfalfa»: altura de ingreso (cm) de 20 a 22,5, B de la fórmula de 0 a -100.', $this->ultima()->descripcion());

        $tipo->update(['activo' => false]);
        $this->assertSame('Modificó el tipo de pastura «Alfalfa»: estado de activo a inactivo.', $this->ultima()->descripcion());

        $tipo->delete();
        $this->assertSame('Eliminó el tipo de pastura «Alfalfa».', $this->ultima()->descripcion());
    }

    public function test_los_parametros_y_no_la_marca(): void
    {
        $configuracion = Configuracion::factory()->create();
        $this->actingAs($this->admin);

        $configuracion->update(['nombre' => 'Estancia La Paz']);
        $this->assertSame(0, Auditoria::query()->count());

        $configuracion->update(['dias_sin_medir' => 10, 'puntos_minimos' => 25]);
        $this->assertSame('Modificó los parámetros del seguimiento: días para quedar sin medir de 7 a 10, puntos mínimos por recorrida de 20 a 25.', $this->ultima()->descripcion());
    }

    public function test_la_fila_sobrevive_al_borrado_y_el_enlace_respeta_el_permiso(): void
    {
        $this->actingAs($this->admin);
        $tipo = TipoPastura::factory()->create(['nombre' => 'Moha']);
        $tipo->delete();

        $fila = $this->ultima();
        $this->assertSame('Eliminó el tipo de pastura «Moha».', $fila->descripcion());
        $this->assertSame(route('configuracion'), $fila->enlace());

        $this->actingAs($this->juan);
        $this->assertNull($fila->enlace(), 'el recorredor no ve Configuración');

        $recorrida = $this->recorrida();
        $this->actingAs($this->admin);
        $recorrida->update(['activo' => false]);
        $this->assertSame(
            route('recorridas', ['potrero' => $this->lote4->id, 'desde' => '2026-10-05', 'hasta' => '2026-10-05', 'estado' => 'todas']),
            $this->ultima()->enlace(),
        );
    }
}
