<?php

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('incluye coincidencias completas y parciales y compara todos los ingredientes publicados', function () {
    $arroz = Ingrediente::factory()->create(['nombre' => 'Arroz']);
    $pollo = Ingrediente::factory()->create(['nombre' => 'Pollo']);
    $cebolla = Ingrediente::factory()->create(['nombre' => 'Cebolla']);
    $ajo = Ingrediente::factory()->create(['nombre' => 'Ajo']);
    $limon = Ingrediente::factory()->create(['nombre' => 'Limón']);
    $extra = Ingrediente::factory()->create(['nombre' => 'Chocolate']);
    $completa = Receta::factory()->publicada()->create(['nombre' => 'Arroz solo']);
    $completa->ingredientes()->attach($arroz->id, ['orden' => 1, 'cantidad' => 500, 'unidad' => 'g']);
    $parcial = Receta::factory()->publicada()->create(['nombre' => 'Arroz con pollo']);
    $parcial->ingredientes()->attach([
        $limon->id => ['orden' => 5], $pollo->id => ['orden' => 2],
        $ajo->id => ['orden' => 4], $arroz->id => ['orden' => 1], $cebolla->id => ['orden' => 3],
    ]);
    $ninguna = Receta::factory()->publicada()->create(['nombre' => 'Cebolla sola']);
    $ninguna->ingredientes()->attach($cebolla->id, ['orden' => 1]);
    Receta::factory()->publicada()->create(['nombre' => 'Sin ingredientes']);

    $respuesta = $this->getJson('/api/v1/recetas?'.http_build_query([
        'ingredientes' => [$arroz->id, $pollo->id, $ajo->id, $extra->id],
    ]));

    $respuesta->assertOk()->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.id', $completa->id)
        ->assertJsonPath('data.0.cantidad_coincidencias', 1)
        ->assertJsonPath('data.0.cantidad_faltantes', 0)
        ->assertJsonPath('data.0.ingredientes_faltantes', [])
        ->assertJsonPath('data.1.id', $parcial->id)
        ->assertJsonPath('data.1.cantidad_ingredientes', 5)
        ->assertJsonPath('data.1.cantidad_coincidencias', 3)
        ->assertJsonPath('data.1.cantidad_faltantes', 2)
        ->assertJsonPath('data.1.ingredientes_disponibles', [
            ['ingrediente_id' => $arroz->id, 'nombre' => 'Arroz'],
            ['ingrediente_id' => $pollo->id, 'nombre' => 'Pollo'],
            ['ingrediente_id' => $ajo->id, 'nombre' => 'Ajo'],
        ])
        ->assertJsonPath('data.1.ingredientes_faltantes', [
            ['ingrediente_id' => $cebolla->id, 'nombre' => 'Cebolla'],
            ['ingrediente_id' => $limon->id, 'nombre' => 'Limón'],
        ])
        ->assertJsonPath('data.1.ingredientes_resumen', [
            ['ingrediente_id' => $arroz->id, 'nombre' => 'Arroz'],
            ['ingrediente_id' => $pollo->id, 'nombre' => 'Pollo'],
            ['ingrediente_id' => $cebolla->id, 'nombre' => 'Cebolla'],
        ]);
});

test('ordena por menos faltantes luego fecha e id antes de paginar y conserva los filtros en los enlaces', function () {
    $arroz = Ingrediente::factory()->create();
    $pollo = Ingrediente::factory()->create();
    $cebolla = Ingrediente::factory()->create();
    $incompleta = Receta::factory()->publicada()->create(['publicada_en' => '2026-09-13 12:00:00']);
    $incompleta->ingredientes()->attach([$arroz->id => ['orden' => 1], $pollo->id => ['orden' => 2], $cebolla->id => ['orden' => 3]]);
    $antigua = Receta::factory()->publicada()->create(['publicada_en' => '2026-09-10 12:00:00']);
    $antigua->ingredientes()->attach($arroz->id, ['orden' => 1]);
    $menorId = Receta::factory()->publicada()->create(['publicada_en' => '2026-09-12 12:00:00']);
    $menorId->ingredientes()->attach($arroz->id, ['orden' => 1]);
    $mayorId = Receta::factory()->publicada()->create(['publicada_en' => '2026-09-12 12:00:00']);
    $mayorId->ingredientes()->attach($arroz->id, ['orden' => 1]);

    $primera = $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => [$arroz->id], 'per_page' => 2]));
    $primera->assertOk()->assertJsonPath('meta.total', 4)->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('data.0.id', $mayorId->id)->assertJsonPath('data.1.id', $menorId->id);
    parse_str(parse_url($primera->json('links.next'), PHP_URL_QUERY), $parametros);
    expect($parametros['ingredientes'])->toBe([(string) $arroz->id]);

    $this->getJson($primera->json('links.next'))->assertOk()
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('data.0.id', $antigua->id)->assertJsonPath('data.1.id', $incompleta->id);
});

test('combina ingredientes nombre literal y categoria mediante todos los filtros', function () {
    $arroz = Ingrediente::factory()->create();
    $pollo = Ingrediente::factory()->create();
    $comidas = Categoria::factory()->create();
    $postres = Categoria::factory()->create();
    $esperada = Receta::factory()->publicada()->create(['nombre' => 'Arroz 100% casero']);
    $esperada->ingredientes()->attach($arroz->id, ['orden' => 1]);
    $esperada->categorias()->attach($comidas->id);
    $otraCategoria = Receta::factory()->publicada()->create(['nombre' => 'Arroz 100% dulce']);
    $otraCategoria->ingredientes()->attach($arroz->id, ['orden' => 1]);
    $otraCategoria->categorias()->attach($postres->id);
    $otroNombre = Receta::factory()->publicada()->create(['nombre' => 'Arroz 100X casero']);
    $otroNombre->ingredientes()->attach($arroz->id, ['orden' => 1]);
    $otroNombre->categorias()->attach($comidas->id);
    $otroIngrediente = Receta::factory()->publicada()->create(['nombre' => 'Pollo 100% casero']);
    $otroIngrediente->ingredientes()->attach($pollo->id, ['orden' => 1]);
    $otroIngrediente->categorias()->attach($comidas->id);

    $this->getJson('/api/v1/recetas?'.http_build_query([
        'ingredientes' => [$arroz->id], 'buscar' => '100%', 'categoria_id' => $comidas->id,
    ]))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $esperada->id);
});

test('sin coincidencias devuelve una coleccion vacia con metadatos coherentes', function () {
    $arroz = Ingrediente::factory()->create();
    $pollo = Ingrediente::factory()->create();
    $receta = Receta::factory()->publicada()->create();
    $receta->ingredientes()->attach($pollo->id, ['orden' => 1]);

    $this->getJson('/api/v1/recetas?ingredientes[]='.$arroz->id)->assertOk()
        ->assertJsonPath('data', [])->assertJsonPath('meta.total', 0)
        ->assertJsonPath('meta.current_page', 1)->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.from', null)->assertJsonPath('meta.to', null)->assertJsonPath('links.next', null);
});

test('la seleccion vacia conserva la busqueda normal sin comparacion', function () {
    $categoria = Categoria::factory()->create();
    $receta = Receta::factory()->publicada()->create(['nombre' => 'Arroz']);
    $receta->categorias()->attach($categoria->id);
    $filtros = ['buscar' => 'Arroz', 'categoria_id' => $categoria->id, 'per_page' => 1];
    $normal = $this->call('GET', '/api/v1/recetas', $filtros);

    $vacia = $this->call('GET', '/api/v1/recetas', [...$filtros, 'ingredientes' => []]);

    $vacia->assertOk()->assertJsonPath('data', $normal->json('data'))->assertJsonPath('meta.total', 1);
    expect($vacia->json('data.0'))->not->toHaveKeys(['ingredientes_disponibles', 'ingredientes_faltantes', 'cantidad_coincidencias', 'cantidad_faltantes']);
});

test('rechaza con 422 la estructura o los tipos invalidos de la seleccion', function (mixed $seleccion, string $campo) {
    $this->call('GET', '/api/v1/recetas', ['ingredientes' => $seleccion])
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'texto' => ['arroz', 'ingredientes'],
    'numero' => [1, 'ingredientes'],
    'null' => [null, 'ingredientes'],
    'objeto' => [['arroz' => 1], 'ingredientes'],
    'indices discontinuos' => [[1 => 1, 3 => 2], 'ingredientes'],
    'anidado' => [[[1]], 'ingredientes.0'],
    'nombre' => [['arroz'], 'ingredientes.0'],
    'elemento vacio' => [[''], 'ingredientes.0'],
    'elemento null' => [[null], 'ingredientes.0'],
    'booleano' => [[true], 'ingredientes.0'],
    'decimal entero' => [[1.0], 'ingredientes.0'],
    'decimal' => [[1.5], 'ingredientes.0'],
    'cero' => [[0], 'ingredientes.0'],
    'negativo' => [[-1], 'ingredientes.0'],
    'inyeccion' => [['1 OR 1=1'], 'ingredientes.0'],
]);

test('rechaza con 422 ingredientes duplicados aunque mezclen entero y texto', function () {
    $ingrediente = Ingrediente::factory()->create();

    $this->call('GET', '/api/v1/recetas', ['ingredientes' => [$ingrediente->id, (string) $ingrediente->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('ingredientes.0')
        ->assertJson(['errors' => ['ingredientes.0' => ['No repitas ingredientes en la selección.']]]);
});

test('rechaza con 422 referencias a ingredientes que dejaron de existir', function () {
    $vigente = Ingrediente::factory()->create();
    $eliminado = Ingrediente::factory()->create();
    $eliminado->delete();

    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => [$vigente->id, $eliminado->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('ingredientes')
        ->assertJsonPath('errors.ingredientes.0', 'Uno o más ingredientes seleccionados ya no existen.');
});

test('acepta 50 ingredientes y rechaza con 422 una seleccion de 51 sin consultar sus referencias', function () {
    $ids = Ingrediente::factory()->count(51)->create()->modelKeys();
    $receta = Receta::factory()->publicada()->create();
    $receta->ingredientes()->attach($ids[0], ['orden' => 1]);

    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => array_slice($ids, 0, 50)]))
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.cantidad_coincidencias', 1);
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = $consulta->sql;
    });
    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => $ids]))
        ->assertUnprocessable()->assertJsonValidationErrors('ingredientes')
        ->assertJsonPath('errors.ingredientes.0', 'Puedes seleccionar como máximo 50 ingredientes.');
    expect($consultas)->toBeEmpty();
});

test('mantiene la validacion de filtros y paginacion con ingredientes', function (array $parametros, string $campo) {
    $ingrediente = Ingrediente::factory()->create();

    $this->getJson('/api/v1/recetas?'.http_build_query([...$parametros, 'ingredientes' => [$ingrediente->id]]))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    'pagina cero' => [['page' => 0], 'page'],
    'pagina texto' => [['page' => 'abc'], 'page'],
    'limite cero' => [['per_page' => 0], 'per_page'],
    'limite excesivo' => [['per_page' => 51], 'per_page'],
    'nombre largo' => [['buscar' => str_repeat('a', 101)], 'buscar'],
    'categoria ausente' => [['categoria_id' => 999999], 'categoria_id'],
]);

test('excluye privadas eliminadas y publicaciones iniciales pendientes y conserva autores deshabilitados', function () {
    $ingrediente = Ingrediente::factory()->create();
    $autor = User::factory()->create(['activo' => false]);
    $publica = Receta::factory()->publicada()->create(['creado_por' => $autor->id]);
    $privada = Receta::factory()->create();
    $pendiente = Receta::factory()->create();
    $eliminada = Receta::factory()->publicada()->create();
    foreach ([$publica, $privada, $pendiente, $eliminada] as $receta) {
        $receta->ingredientes()->attach($ingrediente->id, ['orden' => 1]);
    }
    $eliminada->delete();
    SolicitudRevision::factory()->create(['receta_id' => $pendiente->id]);

    $this->getJson('/api/v1/recetas?ingredientes[]='.$ingrediente->id)->assertOk()
        ->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $publica->id)
        ->assertJsonPath('data.0.cantidad_coincidencias', 1);
});

test('una correccion pendiente no cambia coincidencias faltantes ni detalle publicado', function () {
    $arroz = Ingrediente::factory()->create(['nombre' => 'Arroz']);
    $pollo = Ingrediente::factory()->create(['nombre' => 'Pollo']);
    $cebolla = Ingrediente::factory()->create(['nombre' => 'Cebolla']);
    $receta = Receta::factory()->publicada()->create(['nombre' => 'Arroz vigente', 'tips' => 'Consejo vigente']);
    $receta->ingredientes()->attach($arroz->id, ['orden' => 1, 'cantidad' => 500, 'unidad' => 'g', 'notas' => 'Lavado']);
    $receta->ingredientes()->attach($cebolla->id, ['orden' => 2]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Cocinar el arroz.']);
    SolicitudRevision::factory()->correccion()->create([
        'receta_id' => $receta->id,
        'contenido' => ['nombre' => 'Pollo propuesto', 'tips' => 'Consejo pendiente', 'imagen' => 'pendiente.jpg', 'ingredientes' => [['ingrediente_id' => $pollo->id, 'orden' => 1]], 'pasos' => [['orden' => 1, 'instruccion' => 'Paso pendiente']]],
    ]);

    $this->getJson('/api/v1/recetas?ingredientes[]='.$pollo->id)->assertOk()->assertJsonPath('data', []);
    $this->getJson('/api/v1/recetas?ingredientes[]='.$arroz->id)->assertOk()
        ->assertJsonPath('data.0.nombre', 'Arroz vigente')
        ->assertJsonPath('data.0.ingredientes_disponibles', [['ingrediente_id' => $arroz->id, 'nombre' => 'Arroz']])
        ->assertJsonPath('data.0.ingredientes_faltantes', [['ingrediente_id' => $cebolla->id, 'nombre' => 'Cebolla']])
        ->assertJsonPath('data.0.cantidad_coincidencias', 1)->assertJsonPath('data.0.cantidad_faltantes', 1);
    $this->getJson('/api/v1/recetas/'.$receta->id)->assertOk()
        ->assertJsonPath('data.tips', 'Consejo vigente')
        ->assertJsonPath('data.ingredientes.0.cantidad', 500)
        ->assertJsonPath('data.ingredientes.0.unidad', 'g')
        ->assertJsonPath('data.ingredientes.0.notas', 'Lavado')
        ->assertJsonPath('data.pasos.0.instruccion', 'Cocinar el arroz.');
});

test('agrega solo los cuatro campos acordados a la tarjeta normal', function () {
    $ingrediente = Ingrediente::factory()->create();
    $receta = Receta::factory()->publicada()->create(['descripcion' => str_repeat('Descripción completa. ', 30)]);
    $receta->ingredientes()->attach($ingrediente->id, ['orden' => 1]);
    $normal = $this->getJson('/api/v1/recetas')->json('data.0');

    $comparacion = $this->getJson('/api/v1/recetas?ingredientes[]='.$ingrediente->id)->assertOk()->json('data.0');

    expect(array_diff_key($comparacion, array_flip(['ingredientes_disponibles', 'ingredientes_faltantes', 'cantidad_coincidencias', 'cantidad_faltantes'])))->toBe($normal);
    expect($comparacion)->toHaveCount(count($normal) + 4);
});

test('la cantidad de consultas no crece por receta ni por ingrediente seleccionado', function () {
    $ids = Ingrediente::factory()->count(50)->create()->modelKeys();
    $recetas = Receta::factory()->publicada()->count(15)->create();
    foreach ($recetas as $receta) {
        $receta->ingredientes()->attach($ids[0], ['orden' => 1]);
    }
    $consultas = [];
    DB::listen(function ($consulta) use (&$consultas) {
        $consultas[] = $consulta->sql;
    });

    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => [$ids[0]], 'per_page' => 1]))
        ->assertOk()->assertJsonPath('data.0.cantidad_coincidencias', 1);
    $cantidadUna = count($consultas);
    $consultas = [];
    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => $ids, 'per_page' => 15]))
        ->assertOk()->assertJsonCount(15, 'data');

    expect(count($consultas))->toBe($cantidadUna)->toBeLessThanOrEqual(5);
});
