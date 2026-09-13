<?php

use App\Actions\Recetas\ProcesarRevision;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function crearImagenValida(int $recetaId, string $archivo = 'portada.png'): string
{
    $rutaRelativa = "recetas/{$recetaId}/{$archivo}";
    Storage::disk('local')->put(
        $rutaRelativa,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=')
    );

    return $rutaRelativa;
}

beforeEach(function () {
    Storage::fake('local');
});

test('acceso publico sin autenticacion a categorias, ingredientes y recetas', function () {
    $categoria = Categoria::factory()->create(['nombre' => 'Comidas']);
    $ingrediente = Ingrediente::factory()->create(['nombre' => 'Arroz']);

    $receta = Receta::factory()->publicada()->create(['nombre' => 'Arroz con Mariscos']);
    $receta->categorias()->attach($categoria->id);
    $receta->ingredientes()->attach($ingrediente->id, ['orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Cocinar todo']);

    $imagen = crearImagenValida($receta->id);
    $receta->update(['imagen' => $imagen]);

    // Categorías sin sesión ni token
    $this->getJson('/api/v1/categorias')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'nombre']]]);

    // Ingredientes sin sesión ni token
    $this->getJson('/api/v1/ingredientes')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'nombre']], 'links', 'meta']);

    // Listado de recetas
    $this->getJson('/api/v1/recetas')
        ->assertStatus(200)
        ->assertJsonStructure(['data', 'links', 'meta']);

    // Detalle de receta
    $this->getJson("/api/v1/recetas/{$receta->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $receta->id);

    // Imagen vigente de receta
    $this->get("/api/v1/recetas/{$receta->id}/imagen")
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'image/png');
});

test('exclusion de recetas privadas y eliminadas en listado, busqueda y filtros', function () {
    $categoria = Categoria::factory()->create();

    // 1. Receta pública normal
    $publica = Receta::factory()->publicada()->create(['nombre' => 'Ceviche Mixto']);
    $publica->categorias()->attach($categoria->id);

    // 2. Receta privada (sin publicar)
    $privada = Receta::factory()->create(['nombre' => 'Ceviche Privado']);
    $privada->categorias()->attach($categoria->id);

    // 3. Receta pública pero eliminada (soft delete)
    $eliminada = Receta::factory()->publicada()->create(['nombre' => 'Ceviche Eliminado']);
    $eliminada->categorias()->attach($categoria->id);
    $eliminada->delete();

    // 4. Receta privada con solicitud de revision en curso
    $privadaConSolicitud = Receta::factory()->create(['nombre' => 'Ceviche En Revision']);
    $privadaConSolicitud->categorias()->attach($categoria->id);
    SolicitudRevision::factory()->create([
        'receta_id' => $privadaConSolicitud->id,
        'estado' => 'pendiente',
        'tipo' => 'publicacion',
    ]);

    $resp = $this->getJson('/api/v1/recetas');
    $resp->assertStatus(200);
    $nombres = collect($resp->json('data'))->pluck('nombre')->all();

    expect($nombres)->toContain('Ceviche Mixto')
        ->not->toContain('Ceviche Privado')
        ->not->toContain('Ceviche Eliminado')
        ->not->toContain('Ceviche En Revision');

    // Búsqueda específica no debe encontrar la privada
    $respBuscar = $this->getJson('/api/v1/recetas?buscar=Privado');
    $respBuscar->assertStatus(200);
    expect($respBuscar->json('data'))->toBeEmpty();
});

test('respuesta 404 identica para recetas no publicas, eliminadas o inexistentes', function () {
    $privada = Receta::factory()->create();
    $eliminada = Receta::factory()->publicada()->create();
    $eliminada->delete();

    $casos = [
        99999,           // Inexistente
        $privada->id,    // Privada
        $eliminada->id,  // Eliminada
    ];

    foreach ($casos as $id) {
        $respDetalle = $this->getJson("/api/v1/recetas/{$id}");
        $respDetalle->assertStatus(404)
            ->assertJson(['message' => 'Receta no encontrada.']);

        $respImagen = $this->getJson("/api/v1/recetas/{$id}/imagen");
        $respImagen->assertStatus(404)
            ->assertJson(['message' => 'Receta no encontrada.']);
    }

    // Parámetros no numéricos o menores o iguales a cero
    foreach (['abc', '0', '-5'] as $invalido) {
        $this->getJson("/api/v1/recetas/{$invalido}")
            ->assertStatus(404)
            ->assertJson(['message' => 'Receta no encontrada.']);
    }
});

test('permanencia de recetas publicas de autores deshabilitados', function () {
    $autorDeshabilitado = User::factory()->create(['activo' => false]);
    $receta = Receta::factory()->publicada()->create([
        'nombre' => 'Lomo Saltado Clásico',
        'creado_por' => $autorDeshabilitado->id,
    ]);
    $imagen = crearImagenValida($receta->id);
    $receta->update(['imagen' => $imagen]);

    $this->getJson('/api/v1/recetas')
        ->assertStatus(200)
        ->assertJsonFragment(['nombre' => 'Lomo Saltado Clásico']);

    $this->getJson("/api/v1/recetas/{$receta->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.nombre', 'Lomo Saltado Clásico');

    $this->get("/api/v1/recetas/{$receta->id}/imagen")
        ->assertStatus(200);
});

test('conservacion del contenido vigente cuando hay una correccion pendiente', function () {
    $receta = Receta::factory()->publicada()->create([
        'nombre' => 'Seco de Res Tradicional',
        'descripcion' => 'Descripción vigente publicada',
    ]);

    // Solicitud de corrección con datos distintos en el JSON
    SolicitudRevision::factory()->correccion()->create([
        'receta_id' => $receta->id,
        'estado' => 'pendiente',
        'contenido' => [
            'nombre' => 'Seco de Res Modificado Propuesto',
            'descripcion' => 'Descripción propuesta pendiente de aprobación',
            'imagen' => 'recetas/'.$receta->id.'/propuesta.png',
            'porciones' => 6,
            'tiempo_preparacion' => 90,
            'tips' => 'Tip nuevo',
            'categorias' => [],
            'ingredientes' => [],
            'pasos' => [],
        ],
    ]);

    // El catálogo público debe reflejar el contenido vigente, jamás el borrador de la solicitud
    $respListado = $this->getJson('/api/v1/recetas');
    $respListado->assertStatus(200)
        ->assertJsonFragment(['nombre' => 'Seco de Res Tradicional'])
        ->assertJsonMissing(['nombre' => 'Seco de Res Modificado Propuesto']);

    $respDetalle = $this->getJson("/api/v1/recetas/{$receta->id}");
    $respDetalle->assertStatus(200)
        ->assertJsonPath('data.nombre', 'Seco de Res Tradicional')
        ->assertJsonPath('data.descripcion', 'Descripción vigente publicada');
});

test('busqueda por nombre tratando caracteres comodin como literales y filtro por categoria', function () {
    $cat1 = Categoria::factory()->create(['nombre' => 'Comidas']);
    $cat2 = Categoria::factory()->create(['nombre' => 'Postres']);

    $rPorcentaje = Receta::factory()->publicada()->create(['nombre' => 'Arroz 100% Casero']);
    $rPorcentaje->categorias()->attach($cat1->id);

    $rEquis = Receta::factory()->publicada()->create(['nombre' => 'Arroz 100X Casero']);
    $rEquis->categorias()->attach($cat1->id);

    $rGuionBajo = Receta::factory()->publicada()->create(['nombre' => 'Sopa_Criolla']);
    $rGuionBajo->categorias()->attach($cat1->id);

    $rGuionEquis = Receta::factory()->publicada()->create(['nombre' => 'SopaXCriolla']);
    $rGuionEquis->categorias()->attach($cat1->id);

    $rExclamacion = Receta::factory()->publicada()->create(['nombre' => 'Tarta!Especial']);
    $rExclamacion->categorias()->attach($cat2->id);

    $rExclamacionEquis = Receta::factory()->publicada()->create(['nombre' => 'TartaXEspecial']);
    $rExclamacionEquis->categorias()->attach($cat2->id);

    // 1. Comodín % tratado como texto literal
    $respPorcentaje = $this->getJson('/api/v1/recetas?buscar=100%');
    $respPorcentaje->assertStatus(200);
    $nombresPorc = collect($respPorcentaje->json('data'))->pluck('nombre')->all();
    expect($nombresPorc)->toContain('Arroz 100% Casero')
        ->not->toContain('Arroz 100X Casero');

    // 2. Comodín _ tratado como texto literal
    $respGuion = $this->getJson('/api/v1/recetas?buscar=Sopa_');
    $respGuion->assertStatus(200);
    $nombresGuion = collect($respGuion->json('data'))->pluck('nombre')->all();
    expect($nombresGuion)->toContain('Sopa_Criolla')
        ->not->toContain('SopaXCriolla');

    // 3. Carácter escape ! tratado como texto literal
    $respExcl = $this->getJson('/api/v1/recetas?buscar=Tarta!');
    $respExcl->assertStatus(200);
    $nombresExcl = collect($respExcl->json('data'))->pluck('nombre')->all();
    expect($nombresExcl)->toContain('Tarta!Especial')
        ->not->toContain('TartaXEspecial');

    // 4. Filtro por categoria
    $respCat = $this->getJson("/api/v1/recetas?categoria_id={$cat2->id}");
    $respCat->assertStatus(200);
    $nombresCat = collect($respCat->json('data'))->pluck('nombre')->all();
    expect($nombresCat)->toContain('Tarta!Especial')
        ->toContain('TartaXEspecial')
        ->not->toContain('Arroz 100% Casero');

    // 5. Combinación de búsqueda y categoría
    $respComb = $this->getJson("/api/v1/recetas?categoria_id={$cat2->id}&buscar=Tarta!");
    $respComb->assertStatus(200);
    expect($respComb->json('data'))->toHaveCount(1);
    expect($respComb->json('data.0.nombre'))->toBe('Tarta!Especial');

    // 6. Categoría válida sin coincidencias devuelve lista vacía con 200 OK
    $catVacia = Categoria::factory()->create(['nombre' => 'Bebidas']);
    $respVacia = $this->getJson("/api/v1/recetas?categoria_id={$catVacia->id}");
    $respVacia->assertStatus(200);
    expect($respVacia->json('data'))->toBeEmpty();
});

test('paginacion, limites y parametros invalidos', function () {
    Receta::factory()->publicada()->count(25)->create();

    // Paginación por defecto (15 por página)
    $respDef = $this->getJson('/api/v1/recetas');
    $respDef->assertStatus(200)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.total', 25);
    expect($respDef->json('data'))->toHaveCount(15);

    // Límite mínimo (per_page = 1)
    $respMin = $this->getJson('/api/v1/recetas?per_page=1');
    $respMin->assertStatus(200);
    expect($respMin->json('data'))->toHaveCount(1);

    // Límite máximo (per_page = 50)
    $respMax = $this->getJson('/api/v1/recetas?per_page=50');
    $respMax->assertStatus(200);
    expect($respMax->json('data'))->toHaveCount(25);

    // Búsqueda con solo espacios equivale a no buscar
    $respEspacios = $this->getJson('/api/v1/recetas?buscar=%20%20%20');
    $respEspacios->assertStatus(200);
    expect($respEspacios->json('meta.total'))->toBe(25);

    // Parámetros inválidos responden 422 JSON
    $this->getJson('/api/v1/recetas?per_page=0')->assertStatus(422);
    $this->getJson('/api/v1/recetas?per_page=51')->assertStatus(422);
    $this->getJson('/api/v1/recetas?page=0')->assertStatus(422);
    $this->getJson('/api/v1/recetas?categoria_id=99999')->assertStatus(422);
    $this->getJson('/api/v1/recetas?buscar='.str_repeat('a', 101))->assertStatus(422);
});

test('los errores de la api devuelven JSON incluso sin cabecera Accept application/json', function () {
    // 404 sin Accept JSON
    $resp404 = $this->get('/api/v1/recetas/99999');
    $resp404->assertStatus(404)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJson(['message' => 'Receta no encontrada.']);

    // 422 sin Accept JSON
    $resp422 = $this->get('/api/v1/recetas?per_page=999');
    $resp422->assertStatus(422)
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonStructure(['message', 'errors']);
});

test('orden de ingredientes y pasos en el detalle de la receta', function () {
    $receta = Receta::factory()->publicada()->create();
    $i1 = Ingrediente::factory()->create(['nombre' => 'Cebolla']);
    $i2 = Ingrediente::factory()->create(['nombre' => 'Ajo']);
    $i3 = Ingrediente::factory()->create(['nombre' => 'Tomate']);

    // Adjuntar desordenados
    $receta->ingredientes()->attach([
        $i1->id => ['cantidad' => 2.500, 'unidad' => 'unidades', 'notas' => 'Picada fina', 'orden' => 3],
        $i2->id => ['cantidad' => null, 'unidad' => null, 'notas' => null, 'orden' => 1],
        $i3->id => ['cantidad' => 1.000, 'unidad' => 'kg', 'notas' => 'Maduro', 'orden' => 2],
    ]);

    // Crear pasos desordenados
    $receta->pasos()->createMany([
        ['orden' => 2, 'instruccion' => 'Dorar la cebolla y tomate.'],
        ['orden' => 1, 'instruccion' => 'Picar el ajo finamente.'],
    ]);

    $resp = $this->getJson("/api/v1/recetas/{$receta->id}");
    $resp->assertStatus(200);

    // Ingredientes deben estar ordenados por orden (1: Ajo, 2: Tomate, 3: Cebolla)
    $ingredientes = $resp->json('data.ingredientes');
    expect($ingredientes)->toHaveCount(3);
    expect($ingredientes[0]['nombre'])->toBe('Ajo');
    expect($ingredientes[0]['orden'])->toBe(1);
    expect($ingredientes[0]['cantidad'])->toBeNull();
    expect($ingredientes[0]['unidad'])->toBeNull();
    expect($ingredientes[0]['notas'])->toBeNull();

    expect($ingredientes[1]['nombre'])->toBe('Tomate');
    expect($ingredientes[1]['orden'])->toBe(2);
    expect($ingredientes[1]['cantidad'])->toEqual(1.0);

    expect($ingredientes[2]['nombre'])->toBe('Cebolla');
    expect($ingredientes[2]['orden'])->toBe(3);
    expect($ingredientes[2]['cantidad'])->toBe(2.5);

    // Pasos deben estar ordenados por orden (1, 2)
    $pasos = $resp->json('data.pasos');
    expect($pasos)->toHaveCount(2);
    expect($pasos[0]['orden'])->toBe(1);
    expect($pasos[0]['instruccion'])->toBe('Picar el ajo finamente.');
    expect($pasos[1]['orden'])->toBe(2);
    expect($pasos[1]['instruccion'])->toBe('Dorar la cebolla y tomate.');
});

test('promedio y cantidad de valoraciones incluyendo cero votos', function () {
    $rSinVotos = Receta::factory()->publicada()->create();
    $rConVotos = Receta::factory()->publicada()->create();

    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $u3 = User::factory()->create();

    Valoracion::factory()->create(['receta_id' => $rConVotos->id, 'usuario_id' => $u1->id, 'puntuacion' => 4]);
    Valoracion::factory()->create(['receta_id' => $rConVotos->id, 'usuario_id' => $u2->id, 'puntuacion' => 4]);
    Valoracion::factory()->create(['receta_id' => $rConVotos->id, 'usuario_id' => $u3->id, 'puntuacion' => 5]);

    // Receta sin votos
    $respSinVotos = $this->getJson("/api/v1/recetas/{$rSinVotos->id}");
    $respSinVotos->assertStatus(200)
        ->assertJsonPath('data.valoracion_promedio', null)
        ->assertJsonPath('data.cantidad_valoraciones', 0);

    // Receta con votos: promedio (4 + 4 + 5) / 3 = 4.3333... -> 4.33
    $respConVotos = $this->getJson("/api/v1/recetas/{$rConVotos->id}");
    $respConVotos->assertStatus(200)
        ->assertJsonPath('data.valoracion_promedio', 4.33)
        ->assertJsonPath('data.cantidad_valoraciones', 3);

    // En el listado también se reflejan correctamente
    $respListado = $this->getJson('/api/v1/recetas');
    $items = collect($respListado->json('data'))->keyBy('id');
    expect($items[$rSinVotos->id]['valoracion_promedio'])->toBeNull();
    expect($items[$rSinVotos->id]['cantidad_valoraciones'])->toBe(0);
    expect($items[$rConVotos->id]['valoracion_promedio'])->toBe(4.33);
    expect($items[$rConVotos->id]['cantidad_valoraciones'])->toBe(3);
});

test('proyeccion exacta de campos sin datos internos ni sensibles', function () {
    $receta = Receta::factory()->publicada()->create(['tips' => 'Servir caliente']);
    $cat = Categoria::factory()->create();
    $ing = Ingrediente::factory()->create();
    $receta->categorias()->attach($cat->id);
    $receta->ingredientes()->attach($ing->id, ['orden' => 1, 'cantidad' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso 1']);

    // Listado
    $respListado = $this->getJson('/api/v1/recetas');
    $item = $respListado->json('data.0');

    $llavesListadoEsperadas = [
        'id',
        'nombre',
        'descripcion',
        'imagen_url',
        'porciones',
        'tiempo_preparacion',
        'categorias',
        'valoracion_promedio',
        'cantidad_valoraciones',
        'ingredientes_resumen',
        'cantidad_ingredientes',
    ];
    sort($llavesListadoEsperadas);
    $llavesObtenidasListado = array_keys($item);
    sort($llavesObtenidasListado);
    expect($llavesObtenidasListado)->toBe($llavesListadoEsperadas);

    // Detalle
    $respDetalle = $this->getJson("/api/v1/recetas/{$receta->id}");
    $detalle = $respDetalle->json('data');

    $llavesDetalleEsperadas = [
        'id',
        'nombre',
        'descripcion',
        'imagen_url',
        'porciones',
        'tiempo_preparacion',
        'categorias',
        'valoracion_promedio',
        'cantidad_valoraciones',
        'ingredientes_resumen',
        'cantidad_ingredientes',
        'tips',
        'ingredientes',
        'pasos',
    ];
    sort($llavesDetalleEsperadas);
    $llavesObtenidasDetalle = array_keys($detalle);
    sort($llavesObtenidasDetalle);
    expect($llavesObtenidasDetalle)->toBe($llavesDetalleEsperadas);

    // Ninguno expone datos sensibles
    $jsonCompleto = json_encode($detalle);
    expect($jsonCompleto)->not->toContain('creado_por')
        ->not->toContain('actualizado_por')
        ->not->toContain('eliminado_por')
        ->not->toContain('motivo_eliminacion')
        ->not->toContain('password')
        ->not->toContain('email')
        ->not->toContain('solicitudes_revision');
});

test('proteccion de imagenes privadas, pendientes y archivos ajenos', function () {
    $recetaPublica = Receta::factory()->publicada()->create();
    $imagenValida = crearImagenValida($recetaPublica->id);
    $recetaPublica->update(['imagen' => $imagenValida]);

    // 1. Imagen válida de receta pública se entrega con cabecera no-store
    $respImg = $this->get("/api/v1/recetas/{$recetaPublica->id}/imagen");
    $respImg->assertStatus(200)
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    // 2. Receta privada con imagen en disco devuelve 404
    $recetaPrivada = Receta::factory()->create();
    $imgPrivada = crearImagenValida($recetaPrivada->id);
    $recetaPrivada->update(['imagen' => $imgPrivada]);

    $this->get("/api/v1/recetas/{$recetaPrivada->id}/imagen")
        ->assertStatus(404);

    // 3. Receta con archivo no existente en disco devuelve 404
    $recetaSinArchivo = Receta::factory()->publicada()->create([
        'imagen' => "recetas/{$recetaPublica->id}/inexistente.png",
    ]);
    $this->get("/api/v1/recetas/{$recetaSinArchivo->id}/imagen")
        ->assertStatus(404);

    // 4. Intento de referenciar archivo fuera de su directorio permitido devuelve 404
    $recetaTrampa = Receta::factory()->publicada()->create([
        'imagen' => '../../passwords.png',
    ]);
    $this->get("/api/v1/recetas/{$recetaTrampa->id}/imagen")
        ->assertStatus(404);
});

test('ausencia de consultas N+1 en el listado de recetas', function () {
    $categoria = Categoria::factory()->create();
    $ingrediente = Ingrediente::factory()->create();

    // Crear 15 recetas con categorías e ingredientes
    for ($i = 0; $i < 15; $i++) {
        $r = Receta::factory()->publicada()->create();
        $r->categorias()->attach($categoria->id);
        $r->ingredientes()->attach($ingrediente->id, ['orden' => 1]);
    }

    $queriesSql = [];
    DB::listen(function ($query) use (&$queriesSql) {
        $queriesSql[] = $query->sql;
    });

    $resp = $this->getJson('/api/v1/recetas?per_page=15');
    $resp->assertStatus(200);
    expect($resp->json('data'))->toHaveCount(15);

    // Número de consultas constante para 15 recetas:
    // 1. count(*) total de recetas
    // 2. select recetas con subconsultas de valoraciones e ingredientes
    // 3. eager load de categorias
    // 4. eager load de ingredientes
    expect(count($queriesSql))->toBeLessThanOrEqual(5);
});

test('recorrido: aprobar una publicacion mediante la logica existente y consultarla en el catalogo publico', function () {
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $autor = User::factory()->create(['rol' => 'usuario', 'activo' => true]);
    $categoria = Categoria::factory()->create(['nombre' => 'Comidas']);
    $arroz = Ingrediente::factory()->create(['nombre' => 'Arroz']);

    // Crear receta privada inicial con solicitud de publicación
    $receta = Receta::factory()->create([
        'creado_por' => $autor->id,
        'nombre' => 'Arroz con Pollo Criollo',
        'publicada_en' => null,
    ]);
    $imagen = crearImagenValida($receta->id, 'propuesta.png');

    $contenido = [
        'nombre' => 'Arroz con Pollo Criollo',
        'descripcion' => 'Plato típico peruano con culantro y ají amarillo.',
        'imagen' => $imagen,
        'porciones' => 4,
        'tiempo_preparacion' => 45,
        'tips' => 'Usa culantro fresco.',
        'categorias' => [$categoria->id],
        'ingredientes' => [
            ['ingrediente_id' => $arroz->id, 'cantidad' => 2, 'unidad' => 'tazas', 'notas' => 'Lavado', 'orden' => 1],
        ],
        'pasos' => [
            ['orden' => 1, 'instruccion' => 'Sellar el pollo y reservar.'],
            ['orden' => 2, 'instruccion' => 'Cocinar el arroz con el caldo y verduras.'],
        ],
    ];

    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $autor->id,
        'tipo' => 'publicacion',
        'estado' => 'pendiente',
        'contenido' => $contenido,
    ]);

    // Antes de aprobar: NO está en el catálogo público
    $this->getJson('/api/v1/recetas')
        ->assertJsonMissing(['nombre' => 'Arroz con Pollo Criollo']);
    $this->getJson("/api/v1/recetas/{$receta->id}")
        ->assertStatus(404);

    // El administrador aprueba la publicación mediante la acción de negocio del proyecto
    app(ProcesarRevision::class)->ejecutar($admin, $solicitud, 'aprobar');

    // Después de aprobar: Aparece inmediatamente en el catálogo público
    $respListado = $this->getJson('/api/v1/recetas');
    $respListado->assertStatus(200)
        ->assertJsonFragment(['nombre' => 'Arroz con Pollo Criollo']);

    $respDetalle = $this->getJson("/api/v1/recetas/{$receta->id}");
    $respDetalle->assertStatus(200)
        ->assertJsonPath('data.nombre', 'Arroz con Pollo Criollo')
        ->assertJsonPath('data.descripcion', 'Plato típico peruano con culantro y ají amarillo.')
        ->assertJsonPath('data.tips', 'Usa culantro fresco.')
        ->assertJsonPath('data.porciones', 4)
        ->assertJsonPath('data.tiempo_preparacion', 45)
        ->assertJsonPath('data.ingredientes.0.nombre', 'Arroz')
        ->assertJsonPath('data.ingredientes.0.cantidad', 2)
        ->assertJsonPath('data.pasos.0.instruccion', 'Sellar el pollo y reservar.')
        ->assertJsonPath('data.pasos.1.instruccion', 'Cocinar el arroz con el caldo y verduras.');

    // La imagen vigente está accesible
    $this->get("/api/v1/recetas/{$receta->id}/imagen")
        ->assertStatus(200)
        ->assertHeader('Content-Type', 'image/png');
});

test('catalogo de categorias e ingredientes con orden y busqueda', function () {
    Categoria::factory()->create(['nombre' => 'Zanahorias dulces']);
    Categoria::factory()->create(['nombre' => 'Bebidas']);
    Categoria::factory()->create(['nombre' => 'Almuerzos']);

    $respCat = $this->getJson('/api/v1/categorias');
    $respCat->assertStatus(200);
    $nombresCat = collect($respCat->json('data'))->pluck('nombre')->all();
    expect($nombresCat)->toBe(['Almuerzos', 'Bebidas', 'Zanahorias dulces']);

    // Ingredientes
    Ingrediente::factory()->create(['nombre' => 'Pimienta Negra']);
    Ingrediente::factory()->create(['nombre' => 'Pimienta Blanca']);
    Ingrediente::factory()->create(['nombre' => 'Comino']);

    $respIng = $this->getJson('/api/v1/ingredientes?buscar=Pimienta');
    $respIng->assertStatus(200);
    $nombresIng = collect($respIng->json('data'))->pluck('nombre')->all();
    expect($nombresIng)->toContain('Pimienta Blanca')
        ->toContain('Pimienta Negra')
        ->not->toContain('Comino');
});

test('autenticacion como autor o administrador no amplia la visibilidad publica', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $autor = User::factory()->create(['rol' => 'usuario']);

    $recetaPrivada = Receta::factory()->create([
        'creado_por' => $autor->id,
        'nombre' => 'Mi Receta Secreta',
    ]);

    // Como administrador autenticado
    $this->actingAs($admin)->getJson('/api/v1/recetas')
        ->assertJsonMissing(['nombre' => 'Mi Receta Secreta']);
    $this->actingAs($admin)->getJson("/api/v1/recetas/{$recetaPrivada->id}")
        ->assertStatus(404);
    $this->actingAs($admin)->get("/api/v1/recetas/{$recetaPrivada->id}/imagen")
        ->assertStatus(404);

    // Como el autor autenticado
    $this->actingAs($autor)->getJson('/api/v1/recetas')
        ->assertJsonMissing(['nombre' => 'Mi Receta Secreta']);
    $this->actingAs($autor)->getJson("/api/v1/recetas/{$recetaPrivada->id}")
        ->assertStatus(404);
    $this->actingAs($autor)->get("/api/v1/recetas/{$recetaPrivada->id}/imagen")
        ->assertStatus(404);
});
