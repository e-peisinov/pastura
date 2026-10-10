<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditoría de lo sensible (P18; molde: D64 del kit): quién anuló una
 * recorrida o descartó un punto, cambió un contorno, dio de baja un potrero
 * o un campo, cambió el rol, los permisos o la contraseña de un usuario, o
 * tocó los tipos de pastura y los parámetros. Una fila por acción con una
 * foto que alcanza para leerla después (la entidad puede borrarse). La
 * escriben los eventos de los modelos, con el trait Auditable. Solo
 * `created_at`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            // Null cuando lo hizo el sistema (seeder, comando), no una persona.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // recorrida | medicion | potrero | campo | usuario | tipo_pastura | configuracion
            $table->string('entidad', 30);
            // El id de esa entidad; sin FK: la fila sobrevive al borrado.
            $table->unsignedBigInteger('entidad_id');
            // creado | modificado | eliminado
            $table->string('accion', 20);
            // La foto: la referencia (potrero, fecha, nombre) y, en una
            // modificación, cada campo con antes y después.
            $table->json('datos');
            $table->timestamp('created_at');

            $table->index(['created_at']);
            $table->index(['entidad', 'entidad_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
