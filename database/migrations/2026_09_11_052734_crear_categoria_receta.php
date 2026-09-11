<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categoria_receta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receta_id')
                ->constrained('recetas')
                ->cascadeOnDelete();

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->timestamps();

            $table->unique(['receta_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categoria_receta');
    }
};
