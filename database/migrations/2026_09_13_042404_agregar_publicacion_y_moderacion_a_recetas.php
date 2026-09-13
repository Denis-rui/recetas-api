<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            $table->text('tips')->nullable();

            $table->timestamp('publicada_en')->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->foreignId('eliminado_por')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('tipo_eliminacion', [
                'autor',
                'administracion',
            ])->nullable();

            $table->text('motivo_eliminacion')->nullable();

            $table->index(
                ['deleted_at', 'publicada_en'],
                'recetas_catalogo_indice'
            );
        });
    }

    public function down(): void
    {
        Schema::table('recetas', function (Blueprint $table) {
            $table->dropIndex('recetas_catalogo_indice');

            $table->dropConstrainedForeignId('eliminado_por');

            $table->dropColumn([
                'tips',
                'publicada_en',
                'version',
                'tipo_eliminacion',
                'motivo_eliminacion',
            ]);
        });
    }
};