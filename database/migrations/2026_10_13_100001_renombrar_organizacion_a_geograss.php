<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El sistema pasa a llamarse GeoGrass (P22). Solo se renombra la
 * organización que conserva el nombre anterior: uno elegido en
 * Configuración no se toca.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('configuracion')->where('nombre', 'Pasturómetro')->update(['nombre' => 'GeoGrass']);
    }

    public function down(): void
    {
        DB::table('configuracion')->where('nombre', 'GeoGrass')->update(['nombre' => 'Pasturómetro']);
    }
};
