<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ingrediente_receta', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receta_id')
                ->constrained('recetas')
                ->cascadeOnDelete();

            $table->foreignId('ingrediente_id')
                ->constrained('ingredientes')
                ->restrictOnDelete();

            $table->decimal('cantidad', 10, 3)->nullable();
            $table->string('unidad', 50)->nullable();
            $table->text('notas')->nullable();
            $table->unsignedSmallInteger('orden');
            $table->timestamps();

            $table->unique(['receta_id', 'ingrediente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingrediente_receta');
    }
};