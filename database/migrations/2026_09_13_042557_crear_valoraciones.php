<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('receta_id')
                ->constrained('recetas')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('puntuacion');

            $table->timestamps();

            $table->unique(
                ['usuario_id', 'receta_id'],
                'valoraciones_usuario_receta_unica'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valoraciones');
    }
};
