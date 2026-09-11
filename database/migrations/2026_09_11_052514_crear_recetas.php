<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion');
            $table->string('imagen', 2048);
            $table->unsignedSmallInteger('porciones');
            $table->unsignedSmallInteger('tiempo_preparacion');

            $table->foreignId('creado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('actualizado_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};