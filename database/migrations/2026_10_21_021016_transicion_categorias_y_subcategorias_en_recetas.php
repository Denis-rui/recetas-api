<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategorias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('categoria_id')
                ->constrained('categorias')
                ->cascadeOnDelete();

            $table->string('nombre', 100);
            $table->timestamps();

            $table->unique(['id', 'categoria_id'], 'subcategorias_id_categoria_unique');
            $table->index(['categoria_id', 'nombre'], 'subcategorias_categoria_nombre_index');
        });

        Schema::table('recetas', function (Blueprint $table) {
            $table->foreignId('categoria_id')
                ->nullable()
                ->after('tiempo_preparacion')
                ->constrained('categorias')
                ->restrictOnDelete();

            $table->foreignId('subcategoria_id')
                ->nullable()
                ->after('categoria_id')
                ->constrained('subcategorias')
                ->restrictOnDelete();

            $table->foreign(['subcategoria_id', 'categoria_id'])
                ->references(['id', 'categoria_id'])
                ->on('subcategorias')
                ->restrictOnDelete();
        });

        Schema::dropIfExists('categoria_receta');

        DB::table('categorias')->delete();
    }

    public function down(): void
    {
        $ahora = now();
        $asociaciones = DB::table('recetas')
            ->whereNotNull('categoria_id')
            ->select(['id as receta_id', 'categoria_id'])
            ->get()
            ->map(fn (object $fila): array => [
                'receta_id' => $fila->receta_id,
                'categoria_id' => $fila->categoria_id,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ])
            ->all();

        Schema::table('recetas', function (Blueprint $table) {
            $table->dropForeign(['subcategoria_id', 'categoria_id']);
            $table->dropConstrainedForeignId('subcategoria_id');
            $table->dropConstrainedForeignId('categoria_id');
        });

        Schema::dropIfExists('subcategorias');

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

        if ($asociaciones !== []) {
            DB::table('categoria_receta')->insert($asociaciones);
        }
    }
};
