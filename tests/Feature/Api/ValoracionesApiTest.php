<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->usuario = User::factory()->create([
        'name' => 'Diego Comensal',
        'email' => 'diego@ejemplo.com',
        'rol' => 'usuario',
        'activo' => true,
    ]);
    $this->token = $this->usuario->createToken('Movil')->plainTextToken;

    $this->admin = User::factory()->create([
        'name' => 'Admin Chef',
        'email' => 'adminchef@ejemplo.com',
        'rol' => 'administrador',
        'activo' => true,
    ]);
    $this->tokenAdmin = $this->admin->createToken('AdminToken')->plainTextToken;
});

function crearRecetaPublicaValoraciones(User $autor): Receta
{
    $receta = Receta::factory()->publicada()->create([
        'creado_por' => $autor->id,
        'nombre' => 'Ají de Gallina Clásico',
        'descripcion' => 'Cremoso ají de gallina con pecanas y queso.',
        'porciones' => 4,
        'tiempo_preparacion' => 40,
        'imagen' => 'recetas/1/demo.png',
        'version' => 1,
    ]);
    $receta->imagen = 'recetas/'.$receta->id.'/demo.png';
    $receta->save();

    Storage::disk('local')->put($receta->imagen, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));

    $cat = Categoria::first() ?? Categoria::factory()->create();
    $ing = Ingrediente::first() ?? Ingrediente::factory()->create();

    $receta->categorias()->attach($cat->id);
    $receta->ingredientes()->attach($ing->id, ['cantidad' => 300, 'unidad' => 'gramos', 'notas' => 'Deshilachado', 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Licuar ají amarillo con pan y leche.']);

    return $receta;
}

test('requiere autenticacion y cuenta activa para consultar y registrar valoracion propia', function () {
    $receta = crearRecetaPublicaValoraciones($this->usuario);

    $this->getJson("/api/v1/recetas/{$receta->id}/valoracion")->assertUnauthorized();
    $this->postJson("/api/v1/recetas/{$receta->id}/valoracion", ['puntuacion' => 5])->assertUnauthorized();

    $this->usuario->update(['activo' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/recetas/{$receta->id}/valoracion")
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", ['puntuacion' => 5])
        ->assertForbidden();
});

test('consultar valoracion propia devuelve null si aun no ha votado y el puntaje cuando ya voto', function () {
    $receta = crearRecetaPublicaValoraciones($this->usuario);

    // Antes de votar
    $resAntes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/recetas/{$receta->id}/valoracion");

    $resAntes->assertOk()
        ->assertJsonPath('data.receta_id', $receta->id)
        ->assertJsonPath('data.puntuacion', null)
        ->assertJsonPath('data.valoracion_promedio', null)
        ->assertJsonPath('data.cantidad_valoraciones', 0);

    // Registrar un voto previo
    Valoracion::forceCreate([
        'usuario_id' => $this->usuario->id,
        'receta_id' => $receta->id,
        'puntuacion' => 4,
    ]);

    // Después de votar
    $resDespues = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/recetas/{$receta->id}/valoracion");

    $resDespues->assertOk()
        ->assertJsonPath('data.receta_id', $receta->id)
        ->assertJsonPath('data.puntuacion', 4)
        ->assertJsonPath('data.valoracion_promedio', 4)
        ->assertJsonPath('data.cantidad_valoraciones', 1);
});

test('registrar puntuacion entre 1 y 5 actualiza promedios y modificarla no duplica votos', function () {
    $autor = User::factory()->create();
    $receta = crearRecetaPublicaValoraciones($autor);

    // Primer voto con puntuación 5
    $res1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", [
            'puntuacion' => 5,
        ]);

    $res1->assertOk()
        ->assertJsonPath('mensaje', 'Valoración registrada exitosamente.')
        ->assertJsonPath('data.puntuacion', 5)
        ->assertJsonPath('data.valoracion_promedio', 5)
        ->assertJsonPath('data.cantidad_valoraciones', 1);

    expect(Valoracion::where('receta_id', $receta->id)->count())->toBe(1);

    // Segundo usuario califica con 3
    $otroUsuario = User::factory()->create(['activo' => true]);
    $tokenOtro = $otroUsuario->createToken('Movil2')->plainTextToken;

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$tokenOtro)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", [
            'puntuacion' => 3,
        ]);

    expect(Valoracion::where('receta_id', $receta->id)->count())->toBe(2);

    // El primer usuario modifica su voto de 5 a 1
    app('auth')->forgetGuards();
    $resModificado = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", [
            'puntuacion' => 1,
        ]);

    $resModificado->assertOk()
        ->assertJsonPath('data.puntuacion', 1)
        // Promedio: (1 + 3) / 2 = 2.0
        ->assertJsonPath('data.valoracion_promedio', 2)
        ->assertJsonPath('data.cantidad_valoraciones', 2);

    // Sigue habiendo exactamente 2 valoraciones en la base de datos
    expect(Valoracion::where('receta_id', $receta->id)->count())->toBe(2);
});

test('rechaza puntuaciones fuera de rango o no enteras con 422', function (mixed $puntuacionInvalida) {
    $receta = crearRecetaPublicaValoraciones($this->usuario);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", [
            'puntuacion' => $puntuacionInvalida,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('puntuacion');
})->with([0, 6, -1, 4.5, 'excelente', null, '']);

test('no permite calificar recetas privadas ni eliminadas ni inexistentes', function () {
    // Receta privada
    $recetaPrivada = Receta::factory()->create(['publicada_en' => null]);
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$recetaPrivada->id}/valoracion", ['puntuacion' => 5])
        ->assertNotFound();

    // Receta inexistente
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/recetas/999999/valoracion', ['puntuacion' => 5])
        ->assertNotFound();

    // Receta eliminada
    $recetaEliminada = crearRecetaPublicaValoraciones($this->usuario);
    $recetaEliminada->delete();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$recetaEliminada->id}/valoracion", ['puntuacion' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receta');
});

test('el autor de la receta y un administrador pueden calificar sus propias recetas', function () {
    // El autor califica su propia receta
    $recetaPropia = crearRecetaPublicaValoraciones($this->usuario);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$recetaPropia->id}/valoracion", ['puntuacion' => 5])
        ->assertOk()
        ->assertJsonPath('data.puntuacion', 5);

    // Un administrador califica su propia receta
    $recetaAdmin = crearRecetaPublicaValoraciones($this->admin);

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$this->tokenAdmin)
        ->postJson("/api/v1/recetas/{$recetaAdmin->id}/valoracion", ['puntuacion' => 4])
        ->assertOk()
        ->assertJsonPath('data.puntuacion', 4);
});

test('el catalogo publico refleja los promedios y cantidad de valoraciones actualizados', function () {
    $receta = crearRecetaPublicaValoraciones($this->usuario);

    // Voto 1: 5
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", ['puntuacion' => 5]);

    // Voto 2: 4
    $u2 = User::factory()->create(['activo' => true]);
    $t2 = $u2->createToken('M2')->plainTextToken;

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$t2)
        ->postJson("/api/v1/recetas/{$receta->id}/valoracion", ['puntuacion' => 4]);

    // Consultar catálogo público sin autenticación (visitante)
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $response = $this->getJson("/api/v1/recetas/{$receta->id}");

    $response->assertOk()
        // Promedio: (5 + 4) / 2 = 4.5
        ->assertJsonPath('data.valoracion_promedio', 4.5)
        ->assertJsonPath('data.cantidad_valoraciones', 2);
});

test('aprobacion de correccion en revision web conserva las valoraciones intactas', function () {
    $receta = crearRecetaPublicaValoraciones($this->usuario);

    // Voto previo de 5
    Valoracion::forceCreate([
        'usuario_id' => $this->usuario->id,
        'receta_id' => $receta->id,
        'puntuacion' => 5,
    ]);

    // Crear propuesta de corrección en SolicitudRevision
    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id,
        'solicitado_por' => $this->usuario->id,
        'tipo' => 'correccion',
        'estado' => 'pendiente',
        'version_base' => 1,
        'contenido' => [
            'nombre' => 'Ají de Gallina Mejorado y Más Cremoso',
            'descripcion' => $receta->descripcion,
            'imagen' => $receta->imagen,
            'porciones' => $receta->porciones,
            'tiempo_preparacion' => $receta->tiempo_preparacion,
            'tips' => $receta->tips,
            'categorias' => [Categoria::first()->id],
            'ingredientes' => [
                [
                    'ingrediente_id' => Ingrediente::first()->id,
                    'cantidad' => 350,
                    'unidad' => 'gramos',
                    'notas' => 'Pechuga deshilachada fina',
                    'orden' => 1,
                ],
            ],
            'pasos' => [
                ['orden' => 1, 'instruccion' => 'Licuar ají amarillo con pan remojado en caldo.'],
            ],
        ],
    ]);

    // El administrador aprueba la corrección desde la web
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $this->actingAs($this->admin)->post("/revision-recetas/{$solicitud->id}/aprobar", ['confirmar_correccion_menor' => '1'])
        ->assertSessionHas('exito');

    // Comprobar que la valoración sigue existiendo intacta
    expect(Valoracion::where('receta_id', $receta->id)->count())->toBe(1);
    expect(Valoracion::where('receta_id', $receta->id)->first()->puntuacion)->toBe(5);

    // Y el catálogo público muestra el nuevo nombre conservando el promedio
    app('auth')->forgetGuards();
    $this->flushHeaders();
    $catalogo = $this->getJson("/api/v1/recetas/{$receta->id}")->assertOk();
    expect($catalogo->json('data.nombre'))->toBe('Ají de Gallina Mejorado y Más Cremoso');
    expect((float) $catalogo->json('data.valoracion_promedio'))->toEqual(5.0);
    expect($catalogo->json('data.cantidad_valoraciones'))->toBe(1);
});
