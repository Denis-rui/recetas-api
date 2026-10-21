<?php

use App\Models\Categoria;
use App\Models\Receta;
use App\Models\Subcategoria;
use App\Models\User;
use Database\Seeders\CategoriaSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('el seeder de categorias no crea categorias fijas y deja el catalogo vacio', function () {
    $this->seed(CategoriaSeeder::class);

    expect(Categoria::query()->count())->toBe(0);
    expect(Subcategoria::query()->count())->toBe(0);
});

test('permite crear categorias sin subcategorias, exige nombres unicos de categoria y permite repetir nombres de subcategoria', function () {
    $platos = Categoria::factory()->create(['nombre' => 'Platos de fondo']);
    $entradas = Categoria::factory()->create(['nombre' => 'Entradas']);

    expect($platos->subcategorias)->toBeEmpty();

    expect(fn () => Categoria::factory()->create(['nombre' => 'Platos de fondo']))
        ->toThrow(QueryException::class);

    $sub1 = Subcategoria::factory()->create([
        'categoria_id' => $platos->id,
        'nombre' => 'Marina',
    ]);
    $sub2 = Subcategoria::factory()->create([
        'categoria_id' => $entradas->id,
        'nombre' => 'Marina',
    ]);

    expect($sub1->categoria->is($platos))->toBeTrue();
    expect($sub2->categoria->is($entradas))->toBeTrue();
    expect($platos->subcategorias()->pluck('id')->all())->toBe([$sub1->id]);
});

test('asocia una receta a una categoria y una subcategoria de esa misma categoria', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Postres']);
    $subcategoria = Subcategoria::factory()->create([
        'categoria_id' => $categoria->id,
        'nombre' => 'Fríos',
    ]);

    $receta = Receta::factory()->create([
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
    ]);

    expect($receta->categoria->is($categoria))->toBeTrue();
    expect($receta->subcategoria->is($subcategoria))->toBeTrue();
    expect($categoria->recetas()->pluck('id')->all())->toBe([$receta->id]);
    expect($subcategoria->recetas()->pluck('id')->all())->toBe([$receta->id]);
});

test('impide que una receta nueva quede sin categoria o sin subcategoria', function (array $atributos) {
    $categoria = Categoria::factory()->create();
    $subcategoria = Subcategoria::factory()->create(['categoria_id' => $categoria->id]);

    $datos = array_replace([
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
    ], $atributos);

    expect(fn () => Receta::factory()->create($datos))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'sin categoria ni subcategoria (privada)' => [['categoria_id' => null, 'subcategoria_id' => null, 'publicada_en' => null]],
    'sin categoria ni subcategoria (publicada nueva)' => [['categoria_id' => null, 'subcategoria_id' => null, 'publicada_en' => '2026-10-20 12:00:00']],
    'solo con categoria' => [['subcategoria_id' => null]],
    'solo con subcategoria' => [['categoria_id' => null]],
]);

test('impide asociar una subcategoria que no pertenezca a la categoria de la receta en el modelo y en la base de datos', function () {
    $catComidas = Categoria::factory()->create(['nombre' => 'Comidas']);
    $catBebidas = Categoria::factory()->create(['nombre' => 'Bebidas']);
    $subRefrescos = Subcategoria::factory()->create([
        'categoria_id' => $catBebidas->id,
        'nombre' => 'Refrescos',
    ]);
    $autor = User::factory()->create();

    expect(fn () => Receta::factory()->create([
        'creado_por' => $autor->id,
        'categoria_id' => $catComidas->id,
        'subcategoria_id' => $subRefrescos->id,
    ]))->toThrow(InvalidArgumentException::class);

    // Incluso omitiendo eventos de Eloquent, la clave foránea compuesta bloquea la inconsistencia en BD
    expect(fn () => DB::table('recetas')->insert([
        'nombre' => 'Receta inconsistente',
        'descripcion' => 'Intento directo por SQL',
        'imagen' => 'pendiente.png',
        'porciones' => 2,
        'tiempo_preparacion' => 15,
        'categoria_id' => $catComidas->id,
        'subcategoria_id' => $subRefrescos->id,
        'creado_por' => $autor->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('impide retirar la clasificacion de una receta ya clasificada', function () {
    $receta = Receta::factory()->publicada()->create();

    expect(fn () => $receta->update([
        'categoria_id' => null,
        'subcategoria_id' => null,
    ]))->toThrow(InvalidArgumentException::class);
});

test('elimina una categoria sin recetas junto con sus subcategorias', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Sopas']);
    $sub1 = Subcategoria::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Cremas']);
    $sub2 = Subcategoria::factory()->create(['categoria_id' => $categoria->id, 'nombre' => 'Caldos']);

    expect($categoria->tieneRecetasAsociadas())->toBeFalse();

    $categoria->delete();

    $this->assertDatabaseMissing('categorias', ['id' => $categoria->id]);
    $this->assertDatabaseMissing('subcategorias', ['id' => $sub1->id]);
    $this->assertDatabaseMissing('subcategorias', ['id' => $sub2->id]);
});

test('bloquea la eliminacion de categoria o subcategoria si existe una receta asociada en cualquier estado', function (string $estado) {
    $categoria = Categoria::factory()->create();
    $subcategoria = Subcategoria::factory()->create(['categoria_id' => $categoria->id]);

    $receta = Receta::factory()->create([
        'categoria_id' => $categoria->id,
        'subcategoria_id' => $subcategoria->id,
        'publicada_en' => $estado === 'privada' ? null : now(),
    ]);

    if ($estado === 'eliminada') {
        $receta->delete();
    }

    expect($categoria->tieneRecetasAsociadas())->toBeTrue();
    expect($subcategoria->tieneRecetasAsociadas())->toBeTrue();

    expect(fn () => $subcategoria->delete())->toThrow(QueryException::class);
    expect(fn () => $categoria->delete())->toThrow(QueryException::class);

    $this->assertDatabaseHas('categorias', ['id' => $categoria->id]);
    $this->assertDatabaseHas('subcategorias', ['id' => $subcategoria->id]);
})->with([
    'publicada vigente' => ['publicada'],
    'privada' => ['privada'],
    'eliminada con soft delete' => ['eliminada'],
]);

test('la migracion de transicion limpia categorias y asociaciones previas conservando todas las recetas y su visibilidad al avanzar y revertirse', function () {
    $migracion = require database_path('migrations/2026_10_21_021016_transicion_categorias_y_subcategorias_en_recetas.php');

    // 1. Revertir al estado previo a la transición para simular datos históricos reales
    $migracion->down();

    expect(Schema::hasTable('categoria_receta'))->toBeTrue();
    expect(Schema::hasTable('subcategorias'))->toBeFalse();
    expect(Schema::hasColumns('recetas', ['categoria_id', 'subcategoria_id']))->toBeFalse();

    $autor = User::factory()->create();
    $catAntiguaId = DB::table('categorias')->insertGetId([
        'nombre' => 'Categoría Legada',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $publicadaId = DB::table('recetas')->insertGetId([
        'nombre' => 'Ceviche Histórico Publicado',
        'descripcion' => 'Receta publicada antes de la transición.',
        'imagen' => 'recetas/1/portada.png',
        'porciones' => 4,
        'tiempo_preparacion' => 20,
        'creado_por' => $autor->id,
        'publicada_en' => now(),
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $privadaId = DB::table('recetas')->insertGetId([
        'nombre' => 'Borrador Histórico Privado',
        'descripcion' => 'Receta privada antes de la transición.',
        'imagen' => 'pendiente.png',
        'porciones' => 2,
        'tiempo_preparacion' => 15,
        'creado_por' => $autor->id,
        'publicada_en' => null,
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $eliminadaId = DB::table('recetas')->insertGetId([
        'nombre' => 'Receta Histórica Eliminada',
        'descripcion' => 'Receta eliminada antes de la transición.',
        'imagen' => 'recetas/3/portada.png',
        'porciones' => 3,
        'tiempo_preparacion' => 30,
        'creado_por' => $autor->id,
        'publicada_en' => now()->subDay(),
        'deleted_at' => now(),
        'version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('categoria_receta')->insert([
        ['receta_id' => $publicadaId, 'categoria_id' => $catAntiguaId, 'created_at' => now(), 'updated_at' => now()],
        ['receta_id' => $privadaId, 'categoria_id' => $catAntiguaId, 'created_at' => now(), 'updated_at' => now()],
    ]);

    // 2. Ejecutar la transición (up)
    $migracion->up();

    expect(Schema::hasTable('categoria_receta'))->toBeFalse();
    expect(Schema::hasTable('subcategorias'))->toBeTrue();
    expect(Categoria::query()->count())->toBe(0);

    // Todas las recetas existentes se conservan intactas
    expect(Receta::withTrashed()->count())->toBe(3);

    $recetaPublicada = Receta::query()->findOrFail($publicadaId);
    expect($recetaPublicada->categoria_id)->toBeNull();
    expect($recetaPublicada->subcategoria_id)->toBeNull();
    expect($recetaPublicada->estaPublicada())->toBeTrue();

    // Sigue visible en el scope de recetas publicadas y permite actualizar otros campos temporalmente sin clasificación
    expect(Receta::query()->publicada()->pluck('id')->all())->toContain($publicadaId);
    $recetaPublicada->update(['tips' => 'Servir con camote y cancha.']);
    expect($recetaPublicada->fresh()->tips)->toBe('Servir con camote y cancha.');

    // 3. Crear una categoría, subcategoría y receta clasificada y verificar que down() conserva las recetas y traslada la categoría al pivote
    $nuevaCategoria = Categoria::factory()->create(['nombre' => 'Platos Marinos']);
    $nuevaSubcategoria = Subcategoria::factory()->create([
        'categoria_id' => $nuevaCategoria->id,
        'nombre' => 'Ceviches',
    ]);
    $nuevaReceta = Receta::factory()->publicada()->create([
        'creado_por' => $autor->id,
        'categoria_id' => $nuevaCategoria->id,
        'subcategoria_id' => $nuevaSubcategoria->id,
    ]);

    $migracion->down();

    expect(Schema::hasTable('categoria_receta'))->toBeTrue();
    expect(Schema::hasTable('subcategorias'))->toBeFalse();
    expect(Schema::hasColumns('recetas', ['categoria_id', 'subcategoria_id']))->toBeFalse();
    expect(DB::table('recetas')->count())->toBe(4);
    $this->assertDatabaseHas('categoria_receta', [
        'receta_id' => $nuevaReceta->id,
        'categoria_id' => $nuevaCategoria->id,
    ]);
});
