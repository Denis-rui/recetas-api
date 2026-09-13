<?php

use App\Models\Receta;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('favoritos rechaza paginacion invalida con 422', function (string $campo, mixed $valor) {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/favoritos?'.http_build_query([$campo => $valor]))
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    ['per_page', 100000], ['per_page', -1], ['per_page', 0], ['per_page', '1.9'],
    ['page', 0], ['page', -1], ['page', '1e2'], ['page', '9223372036854775808'],
]);

test('favoritos pagina con limite de cincuenta y conserva avisos de eliminacion', function () {
    $usuario = User::factory()->create();
    $recetas = Receta::factory()->publicada()->count(51)->create(['creado_por' => $usuario->id]);
    $usuario->favoritos()->attach($recetas->modelKeys());
    $recetas->first()->update(['tipo_eliminacion' => 'autor']);
    $recetas->first()->delete();
    Sanctum::actingAs($usuario);

    $this->getJson('/api/v1/favoritos?per_page=50&page=1')
        ->assertOk()->assertJsonCount(50, 'data')->assertJsonPath('meta.total', 51);
    $this->getJson('/api/v1/favoritos?per_page=50&page=2')
        ->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recetas->first()->id)
        ->assertJsonPath('data.0.visibilidad', 'eliminada');
});

test('estado de favorito oculta privadas ajenas igual que identificadores inexistentes', function () {
    config(['app.debug' => false]);
    $privada = Receta::factory()->create();
    Sanctum::actingAs(User::factory()->create());

    $desconocida = $this->getJson('/api/v1/favoritos/999999/estado')->assertNotFound();
    $this->getJson("/api/v1/favoritos/{$privada->id}/estado")
        ->assertNotFound()->assertExactJson($desconocida->json());
    $privada->delete();
    $this->getJson("/api/v1/favoritos/{$privada->id}/estado")
        ->assertNotFound()->assertExactJson($desconocida->json());
});

test('estado reconoce favoritos propios eliminados y permite quitarlos', function () {
    $usuario = User::factory()->create();
    $receta = Receta::factory()->publicada()->create();
    $usuario->favoritos()->attach($receta);
    $receta->delete();
    Sanctum::actingAs($usuario);

    $this->getJson("/api/v1/favoritos/{$receta->id}/estado")
        ->assertOk()->assertJsonPath('es_favorito', true);
    $this->deleteJson("/api/v1/favoritos/{$receta->id}")->assertOk();
    $this->assertDatabaseMissing('favoritos', ['usuario_id' => $usuario->id, 'receta_id' => $receta->id]);
});

test('estado de favorito conserva acceso del autor a su receta privada', function () {
    $usuario = User::factory()->create();
    $receta = Receta::factory()->create(['creado_por' => $usuario->id]);
    Sanctum::actingAs($usuario);

    $this->getJson("/api/v1/favoritos/{$receta->id}/estado")
        ->assertOk()->assertExactJson(['receta_id' => $receta->id, 'es_favorito' => false]);
});

test('invitado distingue eliminacion confirmada de disponibilidad desconocida sin contenido privado', function () {
    $vigente = Receta::factory()->publicada()->create(['creado_por' => User::factory()->create(['activo' => false])->id]);
    $autor = Receta::factory()->publicada()->create(['tipo_eliminacion' => 'autor']);
    $admin = Receta::factory()->publicada()->create(['tipo_eliminacion' => 'administracion', 'motivo_eliminacion' => 'Motivo reservado']);
    $privada = Receta::factory()->create();
    $privadaEliminada = Receta::factory()->create(['tipo_eliminacion' => 'autor']);
    $autor->delete();
    $admin->delete();
    $privadaEliminada->delete();

    $this->postJson('/api/v1/recetas/verificar-disponibilidad', [
        'ids' => [$vigente->id, $autor->id, $admin->id, $privada->id, $privadaEliminada->id, 999999],
    ])->assertOk()->assertExactJson(['data' => [
        ['id' => $vigente->id, 'estado' => 'disponible'],
        ['id' => $autor->id, 'estado' => 'eliminada_autor', 'mensaje' => 'Esta receta fue eliminada por su autor'],
        ['id' => $admin->id, 'estado' => 'eliminada_administracion', 'mensaje' => 'Esta receta fue eliminada'],
        ['id' => $privada->id, 'estado' => 'no_disponible'],
        ['id' => $privadaEliminada->id, 'estado' => 'no_disponible'],
        ['id' => 999999, 'estado' => 'no_disponible'],
    ]]);
});

test('consulta publica valida lista acotada sin exigir existencia de cada receta', function (array $datos, string $campo) {
    $this->json('POST', '/api/v1/recetas/verificar-disponibilidad', $datos, options: JSON_PRESERVE_ZERO_FRACTION)
        ->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    [[], 'ids'], [['ids' => []], 'ids'], [['ids' => range(1, 51)], 'ids'],
    [['ids' => [1, 1]], 'ids.0'], [['ids' => ['1.9']], 'ids.0'],
    [['ids' => ['1e0']], 'ids.0'], [['ids' => ['+1']], 'ids.0'],
    [['ids' => [' 1 ']], 'ids.0'], [['ids' => [1.0]], 'ids.0'],
    [['ids' => ['9223372036854775808']], 'ids.0'],
]);
