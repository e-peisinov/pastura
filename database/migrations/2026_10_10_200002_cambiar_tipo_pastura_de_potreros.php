<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El tipo de pastura del potrero pasa de texto libre a la tabla tipos_pastura
 * (P16). Cada texto distinto que había se convierte en un tipo, sin alturas
 * ni fórmula (se completan en Configuración); los que solo difieren en
 * mayúsculas o espacios quedan en uno solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('potreros', function (Blueprint $table) {
            $table->foreignId('tipo_pastura_id')->nullable()->after('nombre')
                ->constrained('tipos_pastura')->restrictOnDelete();
        });

        // Los tipos que ya existen se reusan (la migración se puede deshacer y volver a correr).
        $tipos = DB::table('tipos_pastura')->pluck('id', 'nombre')
            ->mapWithKeys(fn (int $id, string $nombre) => [mb_strtolower($nombre) => $id])
            ->all();
        $ahora = now();

        DB::table('potreros')->whereNotNull('tipo_pastura')->orderBy('id')->get(['id', 'tipo_pastura'])
            ->each(function (object $potrero) use (&$tipos, $ahora) {
                $nombre = trim(preg_replace('/\s+/u', ' ', $potrero->tipo_pastura));

                if ($nombre === '') {
                    return;
                }

                $clave = mb_strtolower($nombre);
                $tipos[$clave] ??= DB::table('tipos_pastura')->insertGetId([
                    'nombre' => $nombre,
                    'activo' => true,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);

                DB::table('potreros')->where('id', $potrero->id)->update(['tipo_pastura_id' => $tipos[$clave]]);
            });

        Schema::table('potreros', function (Blueprint $table) {
            $table->dropColumn('tipo_pastura');
        });
    }

    public function down(): void
    {
        Schema::table('potreros', function (Blueprint $table) {
            $table->string('tipo_pastura', 100)->nullable()->after('nombre');
        });

        DB::table('potreros')->whereNotNull('tipo_pastura_id')->get(['id', 'tipo_pastura_id'])
            ->each(fn (object $potrero) => DB::table('potreros')->where('id', $potrero->id)->update([
                'tipo_pastura' => DB::table('tipos_pastura')->where('id', $potrero->tipo_pastura_id)->value('nombre'),
            ]));

        Schema::table('potreros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_pastura_id');
        });
    }
};
