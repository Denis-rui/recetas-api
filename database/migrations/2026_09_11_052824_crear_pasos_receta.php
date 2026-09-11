<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pasos_receta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receta_id')
                ->constrained('recetas')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('orden');
            $table->text('instruccion');
            $table->timestamps();

            $table->unique(['receta_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pasos_receta');
    }
};
