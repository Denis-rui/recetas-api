<?php

use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

test('identificadores de recetas no aceptan representaciones numericas impropias', function (string $id) {
    Storage::fake('local');
    $usuario = User::factory()->create();
    $receta = Receta::factory()->publicada()->create(['id' => 1, 'imagen' => 'recetas/1/portada.png']);
    Storage::disk('local')->put($receta->imagen, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));
    $usuario->favoritos()->attach($receta);
    Sanctum::actingAs($usuario);
    $id = rawurlencode($id);

    $this->getJson("/api/v1/recetas/{$id}")->assertNotFound();
    $this->getJson("/api/v1/recetas/{$id}/imagen")->assertNotFound();
    $this->getJson("/api/v1/favoritos/{$id}/estado")->assertNotFound();
    $this->postJson("/api/v1/favoritos/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/favoritos/{$id}")->assertNotFound();
    $this->getJson("/api/v1/recetas/{$id}/valoracion")->assertNotFound();
    $this->putJson("/api/v1/recetas/{$id}/valoracion", ['puntuacion' => 5])->assertNotFound();
    $this->assertDatabaseHas('favoritos', ['usuario_id' => $usuario->id, 'receta_id' => 1]);
    $this->assertDatabaseCount('valoraciones', 0);
})->with(['1.9', '1.0', '1e0', '+1', ' 1 ', '01', '0', '-1', '9223372036854775808']);

test('catalogo no normaliza identificadores de categoria e ingredientes en consulta', function (string $id) {
    Categoria::factory()->create(['id' => 1]);
    Ingrediente::factory()->create(['id' => 1]);

    $this->getJson('/api/v1/recetas?'.http_build_query(['categoria_id' => $id]))
        ->assertUnprocessable()->assertJsonValidationErrors('categoria_id');
    $this->getJson('/api/v1/recetas?'.http_build_query(['ingredientes' => [$id]]))
        ->assertUnprocessable()->assertJsonValidationErrors('ingredientes.0');
})->with(['+1', ' 1 ', '1.0', '1e0', '9223372036854775808']);

test('listados publicos rechazan pagina cuyo desplazamiento excede el rango entero', function (string $ruta) {
    $this->getJson($ruta.'?'.http_build_query(['page' => PHP_INT_MAX, 'per_page' => 50]))
        ->assertUnprocessable()->assertJsonValidationErrors('page');
})->with(['/api/v1/recetas', '/api/v1/ingredientes']);
