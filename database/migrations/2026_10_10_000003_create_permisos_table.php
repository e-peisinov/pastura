<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Permisos por usuario y por módulo (D49): una fila por par, con el
        // nivel. El módulo es la clave de App\Support\Modulos; sin fila, vale
        // el nivel por omisión del rol del usuario.
        Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('modulo', 50);
            $table->enum('nivel', ['sin_acceso', 'ver', 'editar']);
            $table->timestamps();

            $table->unique(['user_id', 'modulo']);
        });

        // Baja lógica del usuario (D49): las recorridas conservan
        // quién los hizo; un usuario inactivo no puede ingresar.
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('rol');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activo');
        });

        Schema::dropIfExists('permisos');
    }
};
