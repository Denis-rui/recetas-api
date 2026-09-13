<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('operaciones_receta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receta_id')->constrained('recetas')->restrictOnDelete();
            $table->uuid('clave_idempotencia');
            $table->string('operacion', 20);
            $table->char('huella_peticion', 64);
            $table->foreignId('solicitud_revision_id')->nullable()->constrained('solicitudes_revision')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['usuario_id', 'clave_idempotencia'], 'operaciones_receta_usuario_clave_unica');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones_receta');
    }
};
