<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_revision', function (Blueprint $table) {
            $table->id();

            $table->foreignId('receta_id')
                ->constrained('recetas')
                ->restrictOnDelete();

            $table->foreignId('solicitado_por')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('tipo', [
                'publicacion',
                'correccion',
            ]);

            $table->enum('estado', [
                'pendiente',
                'aprobada',
                'rechazada',
                'cancelada',
            ])->default('pendiente');

            $table->unsignedInteger('version_base');

            $table->json('contenido');

            $table->uuid('clave_idempotencia');

            $table->foreignId('revisado_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->text('motivo_rechazo')->nullable();

            $table->timestamp('revisada_en')->nullable();

            $table->timestamp('cancelada_en')->nullable();

            $table->timestamps();

            $table->unique(
                ['solicitado_por', 'clave_idempotencia'],
                'solicitudes_envio_unico'
            );

            $table->index(
                ['estado', 'created_at'],
                'solicitudes_cola_indice'
            );

            $table->index(
                ['receta_id', 'estado'],
                'solicitudes_receta_estado_indice'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_revision');
    }
};
