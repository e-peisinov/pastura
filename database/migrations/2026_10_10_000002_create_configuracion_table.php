<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fila única (singleton): identidad de la organización para la marca blanca (D8).
        Schema::create('configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('logo_path')->nullable();
            $table->enum('color_acento', ['slate', 'zinc', 'blue', 'teal', 'green'])->default('green');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion');
    }
};
