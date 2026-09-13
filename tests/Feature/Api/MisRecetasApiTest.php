<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->usuario = User::factory()->create([
        'name' => 'Carlos Autor',
        'email' => 'carlos@ejemplo.com',
        'rol' => 'usuario',
        'activo' => true,
    ]);
    $this->token = $this->usuario->createToken('Movil')->plainTextToken;

    $this->categoria1 = Categoria::factory()->create(['nombre' => 'Comidas']);
    $this->categoria2 = Categoria::factory()->create(['nombre' => 'Bebidas']);
    $this->ingrediente1 = Ingrediente::factory()->create(['nombre' => 'Arroz']);
    $this->ingrediente2 = Ingrediente::factory()->create(['nombre' => 'Pollo']);
    $this->ingrediente3 = Ingrediente::factory()->create(['nombre' => 'Cebolla']);
    $this->ingrediente4 = Ingrediente::factory()->create(['nombre' => 'Ajo']);
});

function payloadRecetaValida(array $overrides = []): array
{
    return array_merge([
        'nombre' => 'Arroz con Pollo Criollo',
        'descripcion' => 'Clásico arroz con pollo peruano paso a paso.',
        'porciones' => 4,
        'tiempo_preparacion' => 45,
        'tips' => 'Dejar dorar bien el pollo antes del arroz.',
        'categorias' => [1],
        'ingredientes' => [
            [
                'ingrediente_id' => 1,
                'cantidad' => 2.0,
                'unidad' => 'tazas',
                'notas' => 'Lavado y escurrido',
                'orden' => 1,
            ],
            [
                'ingrediente_id' => 2,
                'cantidad' => 500,
                'unidad' => 'gramos',
                'notas' => 'En presas',
                'orden' => 2,
            ],
        ],
        'pasos' => [
            [
                'orden' => 1,
                'instruccion' => 'Sellar las presas de pollo en una olla caliente.',
            ],
        ],
    ], $overrides);
}

function fakePngUpload(string $name = 'receta.png'): UploadedFile
{
    $pngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII=');

    return UploadedFile::fake()->createWithContent($name, $pngContent);
}

function fakeJpgUpload(string $name = 'receta.jpg'): UploadedFile
{
    $jpgContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');

    return UploadedFile::fake()->createWithContent($name, $jpgContent);
}

test('requiere autenticacion para todas las operaciones de mis recetas', function () {
    $this->getJson('/api/v1/mis-recetas')->assertUnauthorized();
    $this->postJson('/api/v1/mis-recetas', [])->assertUnauthorized();
    $this->getJson('/api/v1/mis-recetas/1')->assertUnauthorized();
    $this->putJson('/api/v1/mis-recetas/1', [])->assertUnauthorized();
    $this->deleteJson('/api/v1/mis-recetas/1')->assertUnauthorized();
    $this->getJson('/api/v1/mis-recetas/1/imagen')->assertUnauthorized();
    $this->postJson('/api/v1/mis-recetas/1/imagen', [])->assertUnauthorized();
});

test('rechaza acceso con 403 si la cuenta esta deshabilitada', function () {
    $this->usuario->update(['activo' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-recetas')
        ->assertForbidden();
});

test('crea receta privada exitosamente con imagen y no aparece en el catalogo publico', function () {
    $imagen = fakeJpgUpload('arroz_pollo.jpg');

    $payload = payloadRecetaValida([
        'categorias' => [$this->categoria1->id, $this->categoria2->id],
        'ingredientes' => [
            [
                'ingrediente_id' => $this->ingrediente1->id,
                'cantidad' => 2.5,
                'unidad' => 'tazas',
                'notas' => 'Largo grano',
                'orden' => 1,
            ],
            [
                'ingrediente_id' => $this->ingrediente2->id,
                'cantidad' => 600,
                'unidad' => 'gramos',
                'notas' => 'Pechuga',
                'orden' => 2,
            ],
        ],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->post('/api/v1/mis-recetas', [
            ...$payload,
            'imagen' => $imagen,
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.nombre', 'Arroz con Pollo Criollo')
        ->assertJsonPath('data.visibilidad', 'privada')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.porciones', 4)
        ->assertJsonPath('data.tiempo_preparacion', 45)
        ->assertJsonPath('data.tips', 'Dejar dorar bien el pollo antes del arroz.')
        ->assertJsonCount(2, 'data.categorias')
        ->assertJsonCount(2, 'data.ingredientes')
        ->assertJsonCount(1, 'data.pasos');

    $recetaId = $response->json('data.id');

    // Verificar en la base de datos
    $this->assertDatabaseHas('recetas', [
        'id' => $recetaId,
        'nombre' => 'Arroz con Pollo Criollo',
        'creado_por' => $this->usuario->id,
        'publicada_en' => null,
        'version' => 1,
    ]);

    // Verificar que la imagen existe en el disco local
    $recetaModel = Receta::find($recetaId);
    expect(Storage::disk('local')->exists($recetaModel->imagen))->toBeTrue();

    // Comprobar que NO aparece en el catálogo público
    $catalogo = $this->getJson('/api/v1/recetas');
    $catalogo->assertOk();
    expect(collect($catalogo->json('data'))->pluck('id'))->not->toContain($recetaId);

    // Comprobar que el endpoint público de imagen responde 404
    $this->getJson("/api/v1/recetas/{$recetaId}/imagen")->assertNotFound();

    // Comprobar que el autor SÍ puede descargar la imagen autenticado
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->get("/api/v1/mis-recetas/{$recetaId}/imagen")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');
});

test('un usuario no puede acceder a recetas privadas ajenas ni un administrador a traves de mis-recetas', function () {
    $otroUsuario = User::factory()->create(['rol' => 'usuario', 'activo' => true]);
    $recetaAjena = Receta::factory()->create([
        'creado_por' => $otroUsuario->id,
        'publicada_en' => null,
        'nombre' => 'Receta Secreta Ajena',
    ]);

    // Usuario normal intentando ver la receta ajena
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/mis-recetas/{$recetaAjena->id}")
        ->assertForbidden();

    // Intento de edición de receta ajena
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$recetaAjena->id}", payloadRecetaValida(['version' => 1]))
        ->assertForbidden();

    // Intento de eliminación de receta ajena
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/v1/mis-recetas/{$recetaAjena->id}")
        ->assertForbidden();

    // Un administrador tampoco puede ver recetas privadas ajenas por estas API
    $admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
    $tokenAdmin = $admin->createToken('AdminToken')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$tokenAdmin)
        ->getJson("/api/v1/mis-recetas/{$recetaAjena->id}")
        ->assertForbidden();
});

test('listado de recetas propias filtra correctamente por privadas, publicadas y eliminadas', function () {
    // 1 privada
    $privada = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Ceviche Privado',
        'publicada_en' => null,
    ]);

    // 1 publicada
    $publicada = Receta::factory()->publicada()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Lomo Saltado Publicado',
    ]);

    // 1 eliminada
    $eliminada = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Causa Eliminada',
        'tipo_eliminacion' => 'autor',
        'eliminado_por' => $this->usuario->id,
    ]);
    $eliminada->delete();

    // Consulta con filtro 'privadas'
    $resPrivadas = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-recetas?filtro=privadas')
        ->assertOk();
    $idsPrivadas = collect($resPrivadas->json('data'))->pluck('id');
    expect($idsPrivadas)->toContain($privada->id);
    expect($idsPrivadas)->not->toContain($publicada->id);
    expect($idsPrivadas)->not->toContain($eliminada->id);

    // Consulta con filtro 'publicadas'
    $resPublicadas = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-recetas?filtro=publicadas')
        ->assertOk();
    $idsPublicadas = collect($resPublicadas->json('data'))->pluck('id');
    expect($idsPublicadas)->toContain($publicada->id);
    expect($idsPublicadas)->not->toContain($privada->id);
    expect($idsPublicadas)->not->toContain($eliminada->id);

    // Consulta con filtro 'eliminadas'
    $resEliminadas = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-recetas?filtro=eliminadas')
        ->assertOk();
    $idsEliminadas = collect($resEliminadas->json('data'))->pluck('id');
    expect($idsEliminadas)->toContain($eliminada->id);
    expect($idsEliminadas)->not->toContain($privada->id);
    expect($idsEliminadas)->not->toContain($publicada->id);

    // Búsqueda por nombre
    $resBusqueda = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-recetas?buscar=Ceviche')
        ->assertOk();
    expect(collect($resBusqueda->json('data'))->pluck('id'))->toContain($privada->id);
    expect(collect($resBusqueda->json('data'))->pluck('id'))->not->toContain($publicada->id);
});

test('detalle de receta eliminada devuelve metadatos de eliminacion segun Opcion A', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Receta Para Borrar',
        'tipo_eliminacion' => 'administracion',
        'motivo_eliminacion' => 'Contenido inapropiado detectado.',
        'eliminado_por' => User::factory()->create(['rol' => 'administrador'])->id,
    ]);
    $receta->delete();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/mis-recetas/{$receta->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $receta->id)
        ->assertJsonPath('data.nombre', 'Receta Para Borrar')
        ->assertJsonPath('data.visibilidad', 'eliminada')
        ->assertJsonPath('data.tipo_eliminacion', 'administracion')
        ->assertJsonPath('data.motivo_eliminacion', 'Contenido inapropiado detectado.')
        ->assertJsonMissing(['ingredientes', 'pasos', 'tips']);
});

test('edicion exitosa de receta privada incrementa la version a 2', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Version 1 Inicial',
        'porciones' => 2,
        'tiempo_preparacion' => 20,
        'publicada_en' => null,
        'version' => 1,
    ]);
    $receta->categorias()->attach($this->categoria1->id);
    $receta->ingredientes()->attach($this->ingrediente1->id, ['cantidad' => 1, 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso uno']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$receta->id}", payloadRecetaValida([
            'version' => 1,
            'nombre' => 'Version 2 Editada',
            'porciones' => 5,
            'categorias' => [$this->categoria2->id],
            'ingredientes' => [
                [
                    'ingrediente_id' => $this->ingrediente2->id,
                    'cantidad' => 300,
                    'unidad' => 'gramos',
                    'notas' => 'Fresco',
                    'orden' => 1,
                ],
            ],
            'pasos' => [
                [
                    'orden' => 1,
                    'instruccion' => 'Nuevo paso 1 modificado.',
                ],
            ],
        ]));

    $response->assertOk()
        ->assertJsonPath('data.nombre', 'Version 2 Editada')
        ->assertJsonPath('data.porciones', 5)
        ->assertJsonPath('data.version', 2);

    $this->assertDatabaseHas('recetas', [
        'id' => $receta->id,
        'nombre' => 'Version 2 Editada',
        'version' => 2,
        'actualizado_por' => $this->usuario->id,
    ]);
});

test('rechaza edicion con HTTP 409 Conflict si la version enviada no coincide', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'version' => 2,
        'publicada_en' => null,
    ]);
    $receta->categorias()->attach($this->categoria1->id);
    $receta->ingredientes()->attach($this->ingrediente1->id, ['cantidad' => 1, 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso uno']);

    // Cliente envía version 1 en lugar de la version vigente 2
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$receta->id}", payloadRecetaValida([
            'version' => 1,
            'nombre' => 'Intento con version desactualizada',
            'categorias' => [$this->categoria1->id],
            'ingredientes' => [
                ['ingrediente_id' => $this->ingrediente1->id, 'cantidad' => 1, 'unidad' => 'u', 'notas' => null, 'orden' => 1],
            ],
            'pasos' => [
                ['orden' => 1, 'instruccion' => 'Paso'],
            ],
        ]));

    $response->assertStatus(409);
});

test('bloquea edicion de receta privada si existe una solicitud de publicacion pendiente', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'publicada_en' => null,
        'version' => 1,
    ]);
    $receta->categorias()->attach($this->categoria1->id);
    $receta->ingredientes()->attach($this->ingrediente1->id, ['cantidad' => 1, 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso uno']);

    SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'tipo' => 'publicacion',
        'estado' => 'pendiente',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$receta->id}", payloadRecetaValida([
            'version' => 1,
            'nombre' => 'Intento de edicion mientras esta pendiente',
            'categorias' => [$this->categoria1->id],
            'ingredientes' => [
                ['ingrediente_id' => $this->ingrediente1->id, 'cantidad' => 1, 'unidad' => 'u', 'notas' => null, 'orden' => 1],
            ],
            'pasos' => [
                ['orden' => 1, 'instruccion' => 'Paso'],
            ],
        ]));

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('receta');
});

test('elimina logicamente la receta propia y cancela automaticamente solicitudes pendientes', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'nombre' => 'Receta Para Eliminar',
        'publicada_en' => null,
    ]);

    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'estado' => 'pendiente',
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/v1/mis-recetas/{$receta->id}");

    $response->assertOk()
        ->assertJsonPath('mensaje', 'Receta eliminada exitosamente.');

    // Verificar soft delete y auditoría
    $this->assertSoftDeleted('recetas', ['id' => $receta->id]);
    $recetaActualizada = Receta::withTrashed()->find($receta->id);
    expect($recetaActualizada->tipo_eliminacion)->toBe('autor');
    expect($recetaActualizada->eliminado_por)->toBe($this->usuario->id);

    // Verificar que la solicitud abierta se canceló automáticamente (Opción A)
    $solicitudActualizada = SolicitudRevision::find($solicitud->id);
    expect($solicitudActualizada->estado)->toBe('cancelada');
    expect($solicitudActualizada->cancelada_en)->not->toBeNull();
});

test('subida de imagen aislada en endpoint dedicado valida tipo y tamano', function () {
    $receta = Receta::factory()->create([
        'creado_por' => $this->usuario->id,
        'publicada_en' => null,
    ]);

    // Archivo no válido (e.g. archivo de texto con extensión .txt)
    $archivoTxt = UploadedFile::fake()->create('documento.txt', 100);
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->post("/api/v1/mis-recetas/{$receta->id}/imagen", [
            'imagen' => $archivoTxt,
        ])
        ->assertUnprocessable();

    // Archivo de imagen válido
    $imagenOk = fakePngUpload('nueva_foto.png');
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->post("/api/v1/mis-recetas/{$receta->id}/imagen", [
            'imagen' => $imagenOk,
        ]);

    $response->assertOk()
        ->assertJsonPath('mensaje', 'Imagen subida exitosamente.');

    $ruta = $response->json('imagen');
    expect(Storage::disk('local')->exists($ruta))->toBeTrue();
});
