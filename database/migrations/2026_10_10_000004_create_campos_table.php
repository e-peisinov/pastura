<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El establecimiento (glosario): un conjunto de potreros.
        Schema::create('campos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('localidad', 100)->nullable();
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campos');
    }
};
