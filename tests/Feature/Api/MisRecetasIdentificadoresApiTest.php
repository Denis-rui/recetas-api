<?php

use App\Actions\Recetas\ActualizarRecetaPrivada;
use App\Actions\Recetas\CrearRecetaPrivada;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
});

test('listas propias validan pagina y limite de cincuenta', function (string $campo, mixed $valor) {
    Sanctum::actingAs(User::factory()->create());
    $consulta = http_build_query([$campo => $valor]);

    $this->getJson('/api/v1/mis-recetas?'.$consulta)->assertUnprocessable()->assertJsonValidationErrors($campo);
    $this->getJson('/api/v1/mis-solicitudes?'.$consulta)->assertUnprocessable()->assertJsonValidationErrors($campo);
})->with([
    ['page', -1], ['page', '1.9'], ['page', PHP_INT_MAX],
    ['per_page', 51], ['per_page', 0], ['per_page', -1],
]);

test('listas propias admiten segunda pagina con limite de cincuenta', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/mis-recetas?per_page=50&page=2')
        ->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.current_page', 2);
    $this->getJson('/api/v1/mis-solicitudes?per_page=50&page=2')
        ->assertOk()->assertJsonPath('meta.per_page', 50)->assertJsonPath('meta.current_page', 2);
});

test('rutas propias rechazan ids impropios sin eliminar receta ni cancelar solicitud', function (string $id) {
    $usuario = User::factory()->create();
    $receta = Receta::factory()->create(['id' => 1, 'creado_por' => $usuario->id]);
    $solicitud = SolicitudRevision::factory()->create(['id' => 1, 'receta_id' => $receta->id]);
    Sanctum::actingAs($usuario);
    $id = rawurlencode($id);

    $this->getJson("/api/v1/mis-recetas/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/mis-recetas/{$id}")->assertNotFound();
    $this->getJson("/api/v1/mis-solicitudes/{$id}")->assertNotFound();
    $this->postJson("/api/v1/mis-solicitudes/{$id}/cancelar")->assertNotFound();
    expect($receta->fresh()->trashed())->toBeFalse();
    expect($solicitud->fresh()->estado)->toBe('pendiente');
})->with(['1.9', '+1', ' 1 ', '1e0', '9223372036854775808']);

test('crear y actualizar validan los ids anidados antes de escribir', function (mixed $id) {
    $usuario = User::factory()->create();
    Categoria::factory()->create(['id' => 1]);
    Ingrediente::factory()->create(['id' => 1]);
    $receta = Receta::factory()->create(['creado_por' => $usuario->id]);
    Sanctum::actingAs($usuario);
    $datos = [
        'nombre' => 'Receta cambiada', 'descripcion' => 'Descripción de prueba.',
        'imagen' => 'pendiente.png', 'version' => 1, 'porciones' => 2, 'tiempo_preparacion' => 10,
        'categorias' => [$id],
        'ingredientes' => [['ingrediente_id' => $id, 'cantidad' => 1, 'unidad' => 'taza', 'notas' => null, 'orden' => 1]],
        'pasos' => [['orden' => 1, 'instruccion' => 'Cocinar el arroz.']],
    ];

    $this->json('POST', '/api/v1/mis-recetas', $datos, options: JSON_PRESERVE_ZERO_FRACTION)
        ->assertUnprocessable()->assertJsonValidationErrors(['categorias.0', 'ingredientes.0.ingrediente_id']);
    $this->json('PUT', "/api/v1/mis-recetas/{$receta->id}", $datos, options: JSON_PRESERVE_ZERO_FRACTION)
        ->assertUnprocessable()->assertJsonValidationErrors(['categorias.0', 'ingredientes.0.ingrediente_id']);
    $this->assertDatabaseCount('recetas', 1);
    expect($receta->fresh()->version)->toBe(1);
})->with(['+1', ' 1 ', '1.9', '1e0', '9223372036854775808', 1.0]);

test('crear valida ids dentro de campos multipart codificados como json', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->post('/api/v1/mis-recetas', [
        'categorias' => '[" 1 "]',
        'ingredientes' => '[{"ingrediente_id":"+1","cantidad":1,"unidad":"taza","notas":null,"orden":1}]',
    ], ['Accept' => 'application/json'])->assertUnprocessable()
        ->assertJsonValidationErrors(['categorias.0', 'ingredientes.0.ingrediente_id']);
    $this->assertDatabaseCount('recetas', 0);
});

test('acciones de recetas privadas rechazan ids impropios cuando se invocan sin controlador', function () {
    $usuario = User::factory()->create();
    $receta = Receta::factory()->create(['creado_por' => $usuario->id]);
    $datos = [
        'nombre' => 'Arroz', 'descripcion' => 'Arroz cocido.',
        'version' => 1, 'porciones' => 2, 'tiempo_preparacion' => 10,
        'categorias' => ['+1'],
        'ingredientes' => [['ingrediente_id' => '+1', 'cantidad' => 1, 'unidad' => 'taza', 'notas' => null, 'orden' => 1]],
        'pasos' => [['orden' => 1, 'instruccion' => 'Cocinar el arroz.']],
    ];

    expect(fn () => app(CrearRecetaPrivada::class)->ejecutar($usuario, $datos, null))
        ->toThrow(ValidationException::class, 'identificador entero positivo válido');
    expect(fn () => app(ActualizarRecetaPrivada::class)->ejecutar($usuario, $receta, $datos))
        ->toThrow(ValidationException::class, 'identificador entero positivo válido');
    $this->assertDatabaseCount('recetas', 1);
    expect($receta->fresh()->version)->toBe(1);
});
