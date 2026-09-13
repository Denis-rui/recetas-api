<?php

use App\Actions\Recetas\ContenidoRevision;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
});

function recetaParaReintento(User $autor, bool $publicada = false): Receta
{
    $receta = Receta::factory()->create(['creado_por' => $autor->id, 'publicada_en' => $publicada ? now() : null]);
    $receta->imagen = "recetas/{$receta->id}/original.png";
    $receta->save();
    Storage::disk('local')->put($receta->imagen, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));
    $receta->categorias()->attach(Categoria::factory()->create());
    $receta->ingredientes()->attach(Ingrediente::factory()->create(), ['cantidad' => 2, 'unidad' => 'tazas', 'notas' => null, 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Cocinar el arroz.']);

    return $receta;
}

test('recupera publicacion directa sin repetir efectos ni crear revisiones', function () {
    $autor = User::factory()->create(['rol' => 'administrador']);
    $receta = recetaParaReintento($autor);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid()];
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)->assertOk();

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)
        ->assertOk()->assertJsonPath('receta.id', $receta->id);

    expect($receta->fresh()->version)->toBe(2);
    $this->assertDatabaseCount('solicitudes_revision', 0);
});

test('recupera solicitud de publicacion despues de cambiar su estado y contenido vigente', function (string $estado) {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid()];
    $id = $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)->assertCreated()->json('solicitud.id');
    SolicitudRevision::whereKey($id)->update(['estado' => $estado]);
    $receta->nombre = 'Redacción posterior';
    $receta->version = 3;
    $receta->publicada_en = $estado === 'aprobada' ? now() : null;
    $receta->save();

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)
        ->assertOk()->assertJsonPath('solicitud.id', $id)->assertJsonPath('solicitud.estado', $estado);

    $this->assertDatabaseCount('solicitudes_revision', 1);
    expect($receta->fresh()->version)->toBe(3);
})->with(['aprobada', 'rechazada', 'cancelada']);

test('envia tambien la correccion administrativa a revision y recupera su envio original', function () {
    $autor = User::factory()->create(['rol' => 'administrador']);
    $receta = recetaParaReintento($autor, true);
    Sanctum::actingAs($autor);
    $contenido = app(ContenidoRevision::class)->vigente($receta);
    unset($contenido['imagen']);
    $payload = ['clave_idempotencia' => (string) Str::uuid(), 'version_base' => 1, 'contenido' => [...$contenido, 'descripcion' => 'Arroz casero con verduras.']];
    $id = $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)->assertCreated()->json('solicitud.id');
    expect($receta->fresh()->version)->toBe(1);
    expect($receta->fresh()->descripcion)->toBe('Arroz casero con verduras frescas.');
    SolicitudRevision::whereKey($id)->update(['estado' => 'aprobada']);
    $receta->refresh();
    $receta->descripcion = 'Redacción posterior.';
    $receta->version = 3;
    $receta->save();

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)->assertOk()->assertJsonPath('solicitud.id', $id);

    expect($receta->fresh()->version)->toBe(3);
    expect($receta->fresh()->descripcion)->toBe('Redacción posterior.');
    expect($receta->fresh()->imagen)->toBe("recetas/{$receta->id}/original.png");
    $this->assertDatabaseCount('solicitudes_revision', 1);
});

test('recupera correccion aprobada con su version original y rechaza cambiar el contenido de la misma clave', function () {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor, true);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid(), 'version_base' => 1, 'contenido' => app(ContenidoRevision::class)->vigente($receta)];
    $id = $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)->assertCreated()->json('solicitud.id');
    SolicitudRevision::whereKey($id)->update(['estado' => 'aprobada']);
    $receta->version = 2;
    $receta->save();

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)
        ->assertOk()->assertJsonPath('solicitud.id', $id)->assertJsonPath('solicitud.estado', 'aprobada');
    $payload['contenido']['descripcion'] = 'Contenido diferente';
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
    $this->assertDatabaseCount('solicitudes_revision', 1);
});

test('rechaza usar la clave de publicacion para corregir o publicar otra receta', function () {
    $autor = User::factory()->create(['rol' => 'administrador']);
    $receta = recetaParaReintento($autor);
    $otra = recetaParaReintento($autor);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid()];
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)->assertOk();

    $this->postJson("/api/v1/mis-recetas/{$otra->id}/publicar", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", [...$payload, 'version_base' => 2, 'contenido' => app(ContenidoRevision::class)->vigente($receta->fresh())])
        ->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');
    expect($otra->fresh()->publicada_en)->toBeNull();
    expect($receta->fresh()->version)->toBe(2);
});

test('conserva imagen propia omitida y rechaza referencias a imagen ajena', function () {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor, true);
    $otra = recetaParaReintento(User::factory()->create(), true);
    Sanctum::actingAs($autor);
    $contenido = app(ContenidoRevision::class)->vigente($receta);
    $contenido['imagen'] = $otra->imagen;
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", ['clave_idempotencia' => (string) Str::uuid(), 'version_base' => 1, 'contenido' => $contenido])
        ->assertUnprocessable()->assertJsonValidationErrors('contenido.imagen');
    $this->getJson("/api/v1/mis-recetas/{$receta->id}")->assertOk()->assertJsonMissingPath('data.imagen');
    unset($contenido['imagen']);

    $id = $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", ['clave_idempotencia' => (string) Str::uuid(), 'version_base' => 1, 'contenido' => $contenido])
        ->assertCreated()->json('solicitud.id');

    expect(SolicitudRevision::findOrFail($id)->contenido['imagen'])->toBe($receta->imagen);
    expect($receta->fresh()->version)->toBe(1);
});

test('recupera claves de solicitudes anteriores al registro de operaciones', function (string $tipo) {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor, true);
    $contenido = app(ContenidoRevision::class)->vigente($receta);
    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id, 'solicitado_por' => $autor->id,
        'tipo' => $tipo, 'estado' => 'aprobada', 'version_base' => 1, 'contenido' => $contenido,
    ]);
    $receta->version = 2;
    $receta->save();
    Sanctum::actingAs($autor);
    $ruta = $tipo === 'publicacion' ? 'publicar' : 'corregir';

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/{$ruta}", [
        'clave_idempotencia' => $solicitud->clave_idempotencia, 'version_base' => 1, 'contenido' => $contenido,
    ])->assertOk()->assertJsonPath('solicitud.id', $solicitud->id);

    $this->assertDatabaseCount('solicitudes_revision', 1);
    $this->assertDatabaseCount('operaciones_receta', 0);
})->with(['publicacion', 'correccion']);

test('la clave de una correccion no admite cambiar su version base', function () {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor, true);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid(), 'version_base' => 1, 'contenido' => app(ContenidoRevision::class)->vigente($receta)];
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", $payload)->assertCreated();

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", [...$payload, 'version_base' => 2])
        ->assertUnprocessable()->assertJsonValidationErrors('clave_idempotencia');

    $this->assertDatabaseCount('solicitudes_revision', 1);
});

test('reintentar publicacion eliminada recupera el resultado sin resucitarla', function () {
    $autor = User::factory()->create(['rol' => 'administrador']);
    $receta = recetaParaReintento($autor);
    Sanctum::actingAs($autor);
    $payload = ['clave_idempotencia' => (string) Str::uuid()];
    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)->assertOk();
    $receta->delete();
    Storage::disk('local')->delete($receta->imagen);

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/publicar", $payload)->assertOk();

    $this->assertSoftDeleted($receta);
    $this->assertDatabaseCount('solicitudes_revision', 0);
    $this->assertDatabaseCount('operaciones_receta', 1);
});

test('la validacion de contenido no normaliza identificadores mal formados antes de comprobarlos', function () {
    $receta = recetaParaReintento(User::factory()->create(), true);
    $contenido = app(ContenidoRevision::class)->vigente($receta);
    $contenido['categorias'] = [' '.$contenido['categorias'][0].' '];

    expect(fn () => app(ContenidoRevision::class)->validar($contenido, $receta))
        ->toThrow(ValidationException::class);
});

test('recupera correccion historica aunque el envio omita tips y use categorias en otro orden o como cadenas', function () {
    $autor = User::factory()->create(['rol' => 'usuario']);
    $receta = recetaParaReintento($autor, true);
    $categoriaAdicional = Categoria::factory()->create();
    $receta->categorias()->attach($categoriaAdicional);
    $receta->ingredientes()->attach(Ingrediente::factory()->create(), ['cantidad' => 1, 'unidad' => 'taza', 'notas' => null, 'orden' => 2]);
    $receta->pasos()->create(['orden' => 2, 'instruccion' => 'Servir caliente.']);
    $contenidoGuardado = app(ContenidoRevision::class)->vigente($receta);
    $solicitud = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id, 'solicitado_por' => $autor->id,
        'tipo' => 'correccion', 'estado' => 'aprobada', 'version_base' => 1,
        'contenido' => $contenidoGuardado,
    ]);
    $contenidoEnviado = $contenidoGuardado;
    unset($contenidoEnviado['tips']);
    $contenidoEnviado['categorias'] = array_map('strval', array_reverse($contenidoGuardado['categorias']));
    $contenidoEnviado['ingredientes'] = array_reverse($contenidoGuardado['ingredientes']);
    $contenidoEnviado['pasos'] = array_reverse($contenidoGuardado['pasos']);
    $receta->version = 2;
    $receta->save();
    Storage::disk('local')->delete($receta->imagen);
    Sanctum::actingAs($autor);

    $this->postJson("/api/v1/mis-recetas/{$receta->id}/corregir", [
        'clave_idempotencia' => $solicitud->clave_idempotencia,
        'version_base' => 1, 'contenido' => $contenidoEnviado,
    ])->assertOk()->assertJsonPath('solicitud.id', $solicitud->id);

    $this->assertDatabaseCount('solicitudes_revision', 1);
    expect($receta->fresh()->version)->toBe(2);
});
