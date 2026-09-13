<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Storage::fake('local');
    $this->usuario = User::factory()->create([
        'name' => 'Luis Cocinero',
        'email' => 'luis@ejemplo.com',
        'rol' => 'usuario',
        'activo' => true,
    ]);
    $this->token = $this->usuario->createToken('Movil')->plainTextToken;

    $this->admin = User::factory()->create([
        'name' => 'Admin Central',
        'email' => 'admin@ejemplo.com',
        'rol' => 'administrador',
        'activo' => true,
    ]);
    $this->tokenAdmin = $this->admin->createToken('AdminMovil')->plainTextToken;

    $this->categoria = Categoria::factory()->create(['nombre' => 'Postres']);
    $this->ingrediente = Ingrediente::factory()->create(['nombre' => 'Azúcar']);
});

function crearRecetaCompleta(User $autor, bool $publicada = false): Receta
{
    $receta = Receta::factory()->create([
        'creado_por' => $autor->id,
        'nombre' => 'Mazamorra Morada',
        'descripcion' => 'Postre tradicional limeño.',
        'imagen' => 'recetas/1/demo.png',
        'porciones' => 6,
        'tiempo_preparacion' => 40,
        'tips' => 'Añadir canela entera al hervir el maíz.',
        'version' => 1,
        'publicada_en' => $publicada ? now() : null,
    ]);
    $receta->imagen = 'recetas/'.$receta->id.'/demo.png';
    $receta->save();

    // Guardar imagen física fake en disco local
    Storage::disk('local')->put($receta->imagen, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));

    $cat = Categoria::first() ?? Categoria::factory()->create();
    $ing = Ingrediente::first() ?? Ingrediente::factory()->create();

    $receta->categorias()->attach($cat->id);
    $receta->ingredientes()->attach($ing->id, ['cantidad' => 200, 'unidad' => 'gramos', 'notas' => 'Blanca', 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Hervir maíz morado con especias.']);

    return $receta;
}

test('requiere autenticacion y cuenta activa para mis-solicitudes', function () {
    $this->getJson('/api/v1/mis-solicitudes')->assertUnauthorized();
    $this->getJson('/api/v1/mis-solicitudes/1')->assertUnauthorized();
    $this->postJson('/api/v1/mis-solicitudes/1/cancelar')->assertUnauthorized();

    $this->usuario->update(['activo' => false]);
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/mis-solicitudes')
        ->assertForbidden();
});

test('un usuario normal solicita publicacion y crea SolicitudRevision pendiente visible en la web', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: false);
    $uuid = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", [
            'clave_idempotencia' => $uuid,
        ]);

    $response->assertCreated()
        ->assertJsonPath('mensaje', 'Solicitud de publicación enviada a revisión exitosamente.')
        ->assertJsonPath('solicitud.receta_id', $receta->id)
        ->assertJsonPath('solicitud.tipo', 'publicacion')
        ->assertJsonPath('solicitud.estado', 'pendiente');

    $solicitudId = $response->json('solicitud.id');

    // La receta permanece privada hasta la aprobación
    expect($receta->fresh()->publicada_en)->toBeNull();

    // La solicitud aparece en la web de revisión para los administradores
    $this->actingAs($this->admin)->get('/revision-recetas')
        ->assertOk()
        ->assertSee($receta->nombre);

    // El administrador aprueba la solicitud desde la web
    $this->actingAs($this->admin)->post("/revision-recetas/{$solicitudId}/aprobar")
        ->assertRedirect("/revision-recetas/{$solicitudId}")
        ->assertSessionHas('exito');

    // Ahora la receta está publicada con su versión incrementada
    $recetaActualizada = $receta->fresh();
    expect($recetaActualizada->publicada_en)->not->toBeNull();
    expect($recetaActualizada->version)->toBe(2);

    // Y el catálogo público ahora incluye la receta
    $catalogo = $this->getJson('/api/v1/recetas');
    $catalogo->assertOk();
    expect(collect($catalogo->json('data'))->pluck('id'))->toContain($receta->id);
});

test('idempotencia en publicacion responde existente con misma clave y rechaza clave repetida con otro envio', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: false);
    $uuid = (string) Str::uuid();

    // Primer intento
    $res1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", [
            'clave_idempotencia' => $uuid,
        ])
        ->assertCreated();

    $solicitudId = $res1->json('solicitud.id');

    // Reintento idéntico con la misma clave
    $res2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", [
            'clave_idempotencia' => $uuid,
        ])
        ->assertOk();

    expect($res2->json('solicitud.id'))->toBe($solicitudId);

    // Intento con otra receta usando la misma clave de idempotencia
    $otraReceta = crearRecetaCompleta($this->usuario, publicada: false);
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-recetas/{$otraReceta->id}/publicar", [
            'clave_idempotencia' => $uuid,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('clave_idempotencia');
});

test('publicacion directa para administrador activo no genera solicitud ficticia', function () {
    $recetaAdmin = crearRecetaCompleta($this->admin, publicada: false);
    $uuid = (string) Str::uuid();

    $cantidadSolicitudesAntes = SolicitudRevision::count();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenAdmin)
        ->postJson("/api/v1/mis-recetas/{$recetaAdmin->id}/publicar", [
            'clave_idempotencia' => $uuid,
        ]);

    $response->assertOk()
        ->assertJsonPath('mensaje', 'Receta publicada directamente exitosamente.')
        ->assertJsonPath('receta.id', $recetaAdmin->id)
        ->assertJsonPath('receta.visibilidad', 'publicada');

    // Comprobar que NO se creó ninguna solicitud en solicitudes_revision
    expect(SolicitudRevision::count())->toBe($cantidadSolicitudesAntes);

    // La receta está publicada en la base de datos
    $recetaActualizada = $recetaAdmin->fresh();
    expect($recetaActualizada->publicada_en)->not->toBeNull();
    expect($recetaActualizada->version)->toBe(2);
});

test('cancelar solicitud propia pendiente mantiene receta privada y permite volver a editarla', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: false);

    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'tipo' => 'publicacion',
        'estado' => 'pendiente',
        'contenido' => [
            'nombre' => $receta->nombre,
            'descripcion' => $receta->descripcion,
            'imagen' => $receta->imagen,
            'porciones' => $receta->porciones,
            'tiempo_preparacion' => $receta->tiempo_preparacion,
            'tips' => $receta->tips,
            'categorias' => [$this->categoria->id],
            'ingredientes' => [['ingrediente_id' => $this->ingrediente->id, 'cantidad' => 100, 'unidad' => 'g', 'notas' => null, 'orden' => 1]],
            'pasos' => [['orden' => 1, 'instruccion' => 'Paso 1']],
        ],
    ]);

    // Comprobar que mientras esté pendiente no se puede editar
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$receta->id}", [
            'version' => 1,
            'nombre' => 'Edicion Bloqueada',
            'descripcion' => $receta->descripcion,
            'porciones' => 4,
            'tiempo_preparacion' => 30,
            'categorias' => [$this->categoria->id],
            'ingredientes' => [['ingrediente_id' => $this->ingrediente->id, 'cantidad' => 100, 'unidad' => 'g', 'notas' => null, 'orden' => 1]],
            'pasos' => [['orden' => 1, 'instruccion' => 'Paso 1']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receta');

    // Cancelar la solicitud
    $resCancel = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-solicitudes/{$solicitud->id}/cancelar");

    $resCancel->assertOk()
        ->assertJsonPath('mensaje', 'Solicitud cancelada exitosamente.')
        ->assertJsonPath('solicitud.estado', 'cancelada');

    $solicitud->refresh();
    expect($solicitud->estado)->toBe('cancelada');
    expect($solicitud->cancelada_en)->not->toBeNull();

    // Reintento idempotente de cancelación
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-solicitudes/{$solicitud->id}/cancelar")
        ->assertOk()
        ->assertJsonPath('solicitud.estado', 'cancelada');

    // Ahora la receta privada puede editarse nuevamente
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson("/api/v1/mis-recetas/{$receta->id}", [
            'version' => 1,
            'nombre' => 'Edicion Desbloqueada Tras Cancelar',
            'descripcion' => $receta->descripcion,
            'porciones' => 4,
            'tiempo_preparacion' => 30,
            'categorias' => [$this->categoria->id],
            'ingredientes' => [['ingrediente_id' => $this->ingrediente->id, 'cantidad' => 100, 'unidad' => 'g', 'notas' => null, 'orden' => 1]],
            'pasos' => [['orden' => 1, 'instruccion' => 'Paso 1']],
        ])
        ->assertOk()
        ->assertJsonPath('data.nombre', 'Edicion Desbloqueada Tras Cancelar');
});

test('rechaza cancelacion de solicitud que ya fue aprobada o rechazada', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: false);

    $solicitudAprobada = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'estado' => 'aprobada',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-solicitudes/{$solicitudAprobada->id}/cancelar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('solicitud');

    $solicitudRechazada = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'estado' => 'rechazada',
        'motivo_rechazo' => 'Faltan detalles en la preparación.',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-solicitudes/{$solicitudRechazada->id}/cancelar")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('solicitud');
});

test('propuesta de correccion mantiene inalterada la receta publicada hasta su aprobacion conservando valoraciones', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: true);
    $nombreOriginal = $receta->nombre;

    // Crear valoración previa
    $valoracion = Valoracion::factory()->create([
        'receta_id' => $receta->id,
        'puntuacion' => 5,
    ]);

    // Crear favorito previo
    $receta->usuariosQueLaGuardaron()->attach($valoracion->usuario_id);

    $propuesta = [
        'nombre' => 'Mazamorra Morada Mejorada',
        'descripcion' => 'Descripción corregida con tips adicionales.',
        'imagen' => $receta->imagen,
        'porciones' => 8,
        'tiempo_preparacion' => 50,
        'tips' => 'Añadir manzana picada y membrillo.',
        'categorias' => [$this->categoria->id],
        'ingredientes' => [
            [
                'ingrediente_id' => $this->ingrediente->id,
                'cantidad' => 250,
                'unidad' => 'gramos',
                'notas' => 'Rubia',
                'orden' => 1,
            ],
        ],
        'pasos' => [
            [
                'orden' => 1,
                'instruccion' => 'Cocer con frutas y colar.',
            ],
        ],
    ];

    $uuid = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", [
            'clave_idempotencia' => $uuid,
            'version_base' => 1,
            'contenido' => $propuesta,
        ]);

    $response->assertCreated()
        ->assertJsonPath('mensaje', 'Propuesta de corrección enviada a revisión exitosamente.')
        ->assertJsonPath('solicitud.tipo', 'correccion')
        ->assertJsonPath('solicitud.estado', 'pendiente');

    $solicitudId = $response->json('solicitud.id');

    // Comprobar que en el catálogo público sigue mostrándose la versión original aprobada
    $catalogoItem = $this->getJson("/api/v1/recetas/{$receta->id}")->assertOk();
    expect($catalogoItem->json('data.nombre'))->toBe($nombreOriginal);

    // Administrador aprueba la corrección desde la web
    $this->actingAs($this->admin)->post("/revision-recetas/{$solicitudId}/aprobar")
        ->assertSessionHas('exito');

    // Ahora la receta publicada tiene el nuevo nombre
    $recetaActualizada = $receta->fresh();
    expect($recetaActualizada->nombre)->toBe('Mazamorra Morada Mejorada');
    expect($recetaActualizada->version)->toBe(2);

    // Comprobar que se conservaron la valoración y el favorito
    expect(Valoracion::where('receta_id', $receta->id)->count())->toBe(1);
    expect(DB::table('favoritos')->where('receta_id', $receta->id)->count())->toBe(1);
});

test('administrador activo aplica correccion menor directa sobre su receta publicada', function () {
    $recetaAdmin = crearRecetaCompleta($this->admin, publicada: true);

    $propuesta = [
        'nombre' => 'Mazamorra Admin Corregida Directa',
        'descripcion' => $recetaAdmin->descripcion,
        'imagen' => $recetaAdmin->imagen,
        'porciones' => 5,
        'tiempo_preparacion' => 35,
        'tips' => 'Servir tibio con arroz con leche.',
        'categorias' => [$this->categoria->id],
        'ingredientes' => [
            [
                'ingrediente_id' => $this->ingrediente->id,
                'cantidad' => 150,
                'unidad' => 'gramos',
                'notas' => null,
                'orden' => 1,
            ],
        ],
        'pasos' => [
            [
                'orden' => 1,
                'instruccion' => 'Instrucción corregida directamente.',
            ],
        ],
    ];

    $uuid = (string) Str::uuid();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenAdmin)
        ->postJson("/api/v1/mis-recetas/{$recetaAdmin->id}/corregir", [
            'clave_idempotencia' => $uuid,
            'version_base' => 1,
            'contenido' => $propuesta,
        ]);

    $response->assertOk()
        ->assertJsonPath('mensaje', 'Corrección menor aplicada directamente sobre la receta publicada.')
        ->assertJsonPath('receta.nombre', 'Mazamorra Admin Corregida Directa');

    $recetaActualizada = $recetaAdmin->fresh();
    expect($recetaActualizada->nombre)->toBe('Mazamorra Admin Corregida Directa');
    expect($recetaActualizada->version)->toBe(2);
});

test('detalle de solicitud propia resuelve nombres de ingredientes y categorias sin exponer datos del revisor', function () {
    $receta = crearRecetaCompleta($this->usuario, publicada: false);

    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'revisado_por' => $this->admin->id,
        'tipo' => 'publicacion',
        'estado' => 'rechazada',
        'motivo_rechazo' => 'Ampliar las instrucciones del paso 1.',
        'contenido' => [
            'nombre' => 'Receta Para Rechazar',
            'descripcion' => 'Descripción breve.',
            'imagen' => $receta->imagen,
            'porciones' => 2,
            'tiempo_preparacion' => 15,
            'tips' => null,
            'categorias' => [$this->categoria->id],
            'ingredientes' => [
                [
                    'ingrediente_id' => $this->ingrediente->id,
                    'cantidad' => 50,
                    'unidad' => 'gramos',
                    'notas' => null,
                    'orden' => 1,
                ],
            ],
            'pasos' => [
                ['orden' => 1, 'instruccion' => 'Mezclar todo.'],
            ],
        ],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/mis-solicitudes/{$solicitud->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $solicitud->id)
        ->assertJsonPath('data.motivo_rechazo', 'Ampliar las instrucciones del paso 1.')
        ->assertJsonPath('data.contenido.categorias.0.nombre', 'Postres')
        ->assertJsonPath('data.contenido.ingredientes.0.nombre', 'Azúcar')
        ->assertJsonMissing(['revisado_por', 'revisor']);
});
