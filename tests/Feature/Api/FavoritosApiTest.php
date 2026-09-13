<?php

namespace Tests\Feature\Api;

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->usuario = User::factory()->create([
        'name' => 'María Usuaria',
        'email' => 'maria@ejemplo.com',
        'rol' => 'usuario',
        'activo' => true,
    ]);
    $this->token = $this->usuario->createToken('Movil')->plainTextToken;

    $this->categoria = Categoria::factory()->create(['nombre' => 'Comidas']);
    $this->ingrediente = Ingrediente::factory()->create(['nombre' => 'Pescado']);
});

function crearRecetaPublicaFavoritos(string $nombre = 'Ceviche Mixto'): Receta
{
    $autor = User::factory()->create(['rol' => 'usuario', 'activo' => true]);
    $receta = Receta::factory()->publicada()->create([
        'creado_por' => $autor->id,
        'nombre' => $nombre,
        'descripcion' => 'Ceviche fresco con ají limo.',
        'porciones' => 3,
        'tiempo_preparacion' => 20,
        'imagen' => 'recetas/1/demo.png',
        'version' => 1,
    ]);
    $receta->imagen = 'recetas/'.$receta->id.'/demo.png';
    $receta->save();

    Storage::disk('local')->put($receta->imagen, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));

    $cat = Categoria::first() ?? Categoria::factory()->create();
    $ing = Ingrediente::first() ?? Ingrediente::factory()->create();

    $receta->categorias()->attach($cat->id);
    $receta->ingredientes()->attach($ing->id, ['cantidad' => 400, 'unidad' => 'gramos', 'notas' => 'Fresco', 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Cortar pescado y mezclar con limón.']);

    return $receta;
}

test('requiere autenticacion y cuenta activa para operar con favoritos', function () {
    $receta = crearRecetaPublicaFavoritos();

    $this->getJson('/api/v1/favoritos')->assertUnauthorized();
    $this->postJson("/api/v1/favoritos/{$receta->id}")->assertUnauthorized();
    $this->deleteJson("/api/v1/favoritos/{$receta->id}")->assertUnauthorized();
    $this->getJson("/api/v1/favoritos/{$receta->id}/estado")->assertUnauthorized();
    $this->postJson('/api/v1/favoritos/verificar-disponibilidad', ['ids' => [$receta->id]])->assertUnauthorized();

    $this->usuario->update(['activo' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/favoritos')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/favoritos/{$receta->id}")
        ->assertForbidden();
});

test('agrega una receta publicada a favoritos e impide duplicados por repeticion', function () {
    $receta = crearRecetaPublicaFavoritos();

    // Primer agregado
    $res1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/favoritos/{$receta->id}");

    $res1->assertCreated()
        ->assertJsonPath('mensaje', 'Receta agregada a favoritos exitosamente.')
        ->assertJsonPath('receta_id', $receta->id)
        ->assertJsonPath('es_favorito', true);

    $this->assertDatabaseHas('favoritos', [
        'usuario_id' => $this->usuario->id,
        'receta_id' => $receta->id,
    ]);

    // Segundo intento con la misma receta (idempotencia)
    $res2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/favoritos/{$receta->id}");

    $res2->assertCreated()
        ->assertJsonPath('es_favorito', true);

    // Debe existir exactamente una fila en la base de datos
    expect(DB::table('favoritos')->where('usuario_id', $this->usuario->id)->where('receta_id', $receta->id)->count())->toBe(1);
});

test('no permite agregar recetas privadas ni eliminadas ni inexistentes', function () {
    // Receta privada
    $recetaPrivada = Receta::factory()->create([
        'publicada_en' => null,
        'creado_por' => User::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/favoritos/{$recetaPrivada->id}")
        ->assertNotFound();

    // Receta inexistente
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/favoritos/999999')
        ->assertNotFound();

    // Receta eliminada
    $recetaEliminada = crearRecetaPublicaFavoritos('Receta Eliminada');
    $recetaEliminada->delete();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson("/api/v1/favoritos/{$recetaEliminada->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('receta');
});

test('listado de favoritos expone datos completos para disponibles y aviso sin motivo para eliminadas', function () {
    // 1 receta vigente con valoración
    $recetaVigente = crearRecetaPublicaFavoritos('Tiradito Tradicional');
    Valoracion::factory()->create(['receta_id' => $recetaVigente->id, 'puntuacion' => 5]);

    // 1 receta eliminada por autor
    $recetaEliminadaAutor = crearRecetaPublicaFavoritos('Arroz con Mariscos Borrado');
    $recetaEliminadaAutor->tipo_eliminacion = 'autor';
    $recetaEliminadaAutor->eliminado_por = $recetaEliminadaAutor->creado_por;
    $recetaEliminadaAutor->save();
    $recetaEliminadaAutor->delete();

    // 1 receta eliminada por administracion con motivo confidencial
    $recetaEliminadaAdmin = crearRecetaPublicaFavoritos('Receta Retirada por Admin');
    $recetaEliminadaAdmin->tipo_eliminacion = 'administracion';
    $recetaEliminadaAdmin->motivo_eliminacion = 'Contenido plagiado y no conforme.';
    $recetaEliminadaAdmin->eliminado_por = User::factory()->create(['rol' => 'administrador'])->id;
    $recetaEliminadaAdmin->save();
    $recetaEliminadaAdmin->delete();

    // Asociar a favoritos de la usuaria
    $this->usuario->favoritos()->attach([
        $recetaVigente->id => ['created_at' => now()->subDays(3)],
        $recetaEliminadaAutor->id => ['created_at' => now()->subDays(2)],
        $recetaEliminadaAdmin->id => ['created_at' => now()->subDay()],
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/favoritos');

    $response->assertOk()
        ->assertJsonCount(3, 'data');

    $datos = collect($response->json('data'))->keyBy('id');

    // Comprobar receta vigente: datos de catálogo completos
    $vigente = $datos->get($recetaVigente->id);
    expect($vigente['visibilidad'])->toBe('publicada');
    expect($vigente['nombre'])->toBe('Tiradito Tradicional');
    expect($vigente['descripcion'])->toBe('Ceviche fresco con ají limo.');
    expect($vigente['imagen_url'])->toContain("/api/v1/recetas/{$recetaVigente->id}/imagen");
    expect($vigente['valoracion_promedio'])->toEqual(5);
    expect($vigente['cantidad_valoraciones'])->toBe(1);
    expect($vigente['agregado_en'])->not->toBeNull();

    // Comprobar receta eliminada por autor: aviso específico, sin contenido ni imagen
    $elimAutor = $datos->get($recetaEliminadaAutor->id);
    expect($elimAutor['visibilidad'])->toBe('eliminada');
    expect($elimAutor['mensaje'])->toBe('Esta receta fue eliminada por su autor');
    expect($elimAutor)->not->toHaveKey('descripcion');
    expect($elimAutor)->not->toHaveKey('imagen_url');
    expect($elimAutor)->not->toHaveKey('ingredientes');

    // Comprobar receta eliminada por administracion: aviso general sin filtrar motivo confidencial
    $elimAdmin = $datos->get($recetaEliminadaAdmin->id);
    expect($elimAdmin['visibilidad'])->toBe('eliminada');
    expect($elimAdmin['mensaje'])->toBe('Esta receta fue eliminada');
    expect($elimAdmin)->not->toHaveKey('motivo_eliminacion');
    expect(json_encode($elimAdmin))->not->toContain('Contenido plagiado');
});

test('quitar receta de favoritos funciona para vigentes y eliminadas de forma idempotente', function () {
    $recetaVigente = crearRecetaPublicaFavoritos('Piqueo Criollo');
    $recetaEliminada = crearRecetaPublicaFavoritos('Piqueo Eliminado');
    $recetaEliminada->delete();

    $this->usuario->favoritos()->attach([
        $recetaVigente->id,
        $recetaEliminada->id,
    ]);

    // Quitar receta vigente
    $res1 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/v1/favoritos/{$recetaVigente->id}");
    $res1->assertOk()->assertJsonPath('es_favorito', false);

    $this->assertDatabaseMissing('favoritos', [
        'usuario_id' => $this->usuario->id,
        'receta_id' => $recetaVigente->id,
    ]);

    // Quitar receta eliminada (con withTrashed)
    $res2 = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/v1/favoritos/{$recetaEliminada->id}");
    $res2->assertOk()->assertJsonPath('es_favorito', false);

    $this->assertDatabaseMissing('favoritos', [
        'usuario_id' => $this->usuario->id,
        'receta_id' => $recetaEliminada->id,
    ]);

    // Reintento idempotente al quitar
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson("/api/v1/favoritos/{$recetaVigente->id}")
        ->assertOk()
        ->assertJsonPath('es_favorito', false);
});

test('consulta de estado de favorito responde es_favorito booleano', function () {
    $receta = crearRecetaPublicaFavoritos();

    $resAntes = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/favoritos/{$receta->id}/estado");
    $resAntes->assertOk()->assertJsonPath('es_favorito', false);

    $this->usuario->favoritos()->attach($receta->id);

    $resDespues = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/favoritos/{$receta->id}/estado");
    $resDespues->assertOk()->assertJsonPath('es_favorito', true);
});

test('verificacion de disponibilidad acotada reconoce estados sin revelar recetas privadas', function () {
    $vigente = crearRecetaPublicaFavoritos('Receta Vigente');

    $elimAutor = crearRecetaPublicaFavoritos('Borrada por Autor');
    $elimAutor->tipo_eliminacion = 'autor';
    $elimAutor->eliminado_por = $elimAutor->creado_por;
    $elimAutor->save();
    $elimAutor->delete();

    $elimAdmin = crearRecetaPublicaFavoritos('Borrada por Admin');
    $elimAdmin->tipo_eliminacion = 'administracion';
    $elimAdmin->eliminado_por = User::factory()->create(['rol' => 'administrador'])->id;
    $elimAdmin->save();
    $elimAdmin->delete();

    $privada = Receta::factory()->create(['publicada_en' => null]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/favoritos/verificar-disponibilidad', [
            'ids' => [$vigente->id, $elimAutor->id, $elimAdmin->id, $privada->id, 999999],
        ]);

    $response->assertOk()
        ->assertJsonCount(5, 'data');

    $resultados = collect($response->json('data'))->keyBy('id');

    expect($resultados->get($vigente->id)['estado'])->toBe('disponible');
    expect($resultados->get($elimAutor->id)['estado'])->toBe('eliminada_autor');
    expect($resultados->get($elimAutor->id)['mensaje'])->toBe('Esta receta fue eliminada por su autor');
    expect($resultados->get($elimAdmin->id)['estado'])->toBe('eliminada_administracion');
    expect($resultados->get($elimAdmin->id)['mensaje'])->toBe('Esta receta fue eliminada');

    // La receta privada y la inexistente responden 'no_disponible' sin revelar si existe
    expect($resultados->get($privada->id)['estado'])->toBe('no_disponible');
    expect($resultados->get(999999)['estado'])->toBe('no_disponible');
});

test('aislamiento: un usuario no puede consultar ni alterar favoritos de otro usuario', function () {
    $otroUsuario = User::factory()->create(['rol' => 'usuario', 'activo' => true]);
    $receta = crearRecetaPublicaFavoritos();

    $otroUsuario->favoritos()->attach($receta->id);

    // La usuaria autenticada consulta sus propios favoritos y no ve los del otro usuario
    $res = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/favoritos');

    $res->assertOk()->assertJsonCount(0, 'data');

    // Su estado de favorito responde false
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson("/api/v1/favoritos/{$receta->id}/estado")
        ->assertOk()
        ->assertJsonPath('es_favorito', false);
});
