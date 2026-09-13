<?php

use App\Actions\Recetas\ProcesarRevision;
use App\Models\Categoria;
use App\Models\Ingrediente;
use App\Models\PasoReceta;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

function ejemploRevision(bool $correccion = false): SolicitudRevision
{
    Storage::fake('local');
    $solicitud = ($correccion ? SolicitudRevision::factory()->correccion() : SolicitudRevision::factory())->create();
    Storage::disk('local')->put($solicitud->contenido['imagen'], base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a8V8AAAAASUVORK5CYII='));

    return $solicitud;
}

function adminRevision(): User
{
    return User::factory()->create(['rol' => 'administrador', 'activo' => true]);
}

test('publica el contenido completo y registra la decision una sola vez', function () {
    $this->travelTo(now()->startOfSecond());
    $solicitud = ejemploRevision();
    $admin = adminRevision();

    $this->actingAs($admin)->post("/revision-recetas/{$solicitud->id}/aprobar")
        ->assertRedirect("/revision-recetas/{$solicitud->id}")->assertSessionHas('exito');

    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'nombre' => 'Arroz con verduras mejorado', 'version' => 2, 'porciones' => 3]);
    expect($solicitud->receta->fresh()->publicada_en->equalTo(now()))->toBeTrue();
    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'aprobada', 'revisado_por' => $admin->id, 'revisada_en' => now()]);
    $this->assertDatabaseHas('ingrediente_receta', ['receta_id' => $solicitud->receta_id, 'cantidad' => 1.5, 'unidad' => 'tazas', 'notas' => 'Lavado', 'orden' => 1]);
    $this->assertDatabaseHas('categoria_receta', ['receta_id' => $solicitud->receta_id, 'categoria_id' => $solicitud->contenido['categorias'][0]]);
    $this->assertDatabaseHas('pasos_receta', ['receta_id' => $solicitud->receta_id, 'orden' => 1, 'instruccion' => 'Cocinar a fuego lento.']);
});

test('rechaza el acceso web sin cuenta administradora activa', function (string $rol, bool $activo) {
    $solicitud = ejemploRevision();
    $usuario = User::factory()->create(['rol' => $rol, 'activo' => $activo]);

    foreach (['/revision-recetas', "/revision-recetas/{$solicitud->id}", "/revision-recetas/{$solicitud->id}/imagen/propuesta"] as $url) {
        $this->actingAs($usuario)->get($url)->assertRedirect('/login');
    }
    foreach (['aprobar', 'rechazar'] as $decision) {
        $this->actingAs($usuario)->post("/revision-recetas/{$solicitud->id}/{$decision}", ['motivo_rechazo' => 'Revisar'])->assertRedirect('/login');
    }
    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
})->with([['usuario', true], ['administrador', false]]);

test('exige autenticacion para consultar solicitudes', function () {
    $this->get('/revision-recetas')->assertRedirect('/login');
});

test('no procesa estados terminados ni decisiones repetidas', function (string $estado, string $decision) {
    $solicitud = ejemploRevision();
    $solicitud->forceFill(['estado' => $estado])->save();

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/{$decision}", ['motivo_rechazo' => 'Cambiar las cantidades'])
        ->assertRedirect("/revision-recetas/{$solicitud->id}")->assertSessionHasErrors('revision');

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => $estado, 'revisado_por' => null]);
    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'version' => 1, 'publicada_en' => null]);
})->with(['cancelada', 'aprobada', 'rechazada'])->with(['aprobar', 'rechazar']);

test('mantiene pendiente la solicitud de un autor deshabilitado', function () {
    $solicitud = ejemploRevision();
    $solicitud->solicitante->update(['activo' => false]);

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHasErrors('revision');

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'publicada_en' => null]);
    $this->get("/revision-recetas/{$solicitud->id}")->assertSee('autor está deshabilitado');
});

test('no aprueba recetas eliminadas o versiones desactualizadas', function (string $caso) {
    $solicitud = ejemploRevision();
    if ($caso === 'eliminada') {
        $solicitud->receta->delete();
    } else {
        $solicitud->receta->forceFill(['version' => 2])->save();
    }

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHasErrors('revision');

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
})->with(['eliminada', 'desactualizada']);

test('exige un motivo con contenido real para rechazar', function (?string $motivo) {
    $solicitud = ejemploRevision();

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/rechazar", ['motivo_rechazo' => $motivo])
        ->assertSessionHasErrors('motivo_rechazo');

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
})->with([null, '', "  \t\n", "\u{00A0}\u{200B}"]);

test('rechazar conserva todos los campos y relaciones vigentes', function (bool $correccion) {
    $solicitud = ejemploRevision($correccion);
    $receta = $solicitud->receta;
    $ingrediente = Ingrediente::factory()->create();
    $receta->ingredientes()->attach($ingrediente, ['cantidad' => 2, 'orden' => 1]);
    $receta->categorias()->attach(Categoria::factory()->create());
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso original']);
    $antes = $receta->load('ingredientes', 'categorias', 'pasos')->toArray();

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/rechazar", ['motivo_rechazo' => '  Explica mejor los pasos.  '])
        ->assertSessionHas('exito');

    expect($receta->fresh()->load('ingredientes', 'categorias', 'pasos')->toArray())->toEqual($antes);
    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'rechazada', 'motivo_rechazo' => 'Explica mejor los pasos.']);
})->with([false, true]);

test('una correccion reemplaza relaciones y conserva favoritos y valoraciones', function () {
    $solicitud = ejemploRevision(true);
    $receta = $solicitud->receta;
    $publicadaEn = $receta->publicada_en;
    $anterior = Ingrediente::factory()->create();
    $receta->ingredientes()->attach($anterior, ['cantidad' => 2, 'orden' => 1]);
    $categoriaAnterior = Categoria::factory()->create();
    $receta->categorias()->attach($categoriaAnterior);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Paso original']);
    $valoracion = Valoracion::factory()->create(['receta_id' => $receta->id]);
    $receta->usuariosQueLaGuardaron()->attach($valoracion->usuario_id);
    $favorito = DB::table('favoritos')->where('receta_id', $receta->id)->first();

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHas('exito');

    expect($receta->fresh()->publicada_en->equalTo($publicadaEn))->toBeTrue();
    expect($valoracion->fresh()->toArray())->toEqual($valoracion->toArray());
    expect(DB::table('favoritos')->where('receta_id', $receta->id)->first())->toEqual($favorito);
    $this->assertDatabaseMissing('ingrediente_receta', ['receta_id' => $receta->id, 'ingrediente_id' => $anterior->id]);
    $this->assertDatabaseMissing('categoria_receta', ['receta_id' => $receta->id, 'categoria_id' => $categoriaAnterior->id]);
    $this->assertDatabaseMissing('pasos_receta', ['receta_id' => $receta->id, 'instruccion' => 'Paso original']);
});

test('valida el JSON y referencias antes de cambiar la receta', function (string $campo, mixed $valor) {
    $solicitud = ejemploRevision();
    $contenido = $solicitud->contenido;
    data_set($contenido, $campo, $valor);
    $solicitud->update(['contenido' => $contenido]);

    $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHasErrors();

    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'nombre' => 'Arroz con verduras', 'version' => 1]);
    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
    $this->assertDatabaseCount('ingrediente_receta', 0);
})->with([
    'campo administrativo' => ['creado_por', 123],
    'nombre vacio' => ['nombre', '   '],
    'categoria inexistente' => ['categorias', [999999]],
    'ingrediente inexistente' => ['ingredientes.0.ingrediente_id', 999999],
    'cantidad negativa' => ['ingredientes.0.cantidad', -2],
    'orden invalido' => ['pasos.0.orden', 0],
    'ruta privada ajena' => ['imagen', 'recetas/999999/privada.png'],
    'ruta transversal' => ['imagen', '../.env'],
    'url remota' => ['imagen', 'https://example.com/privada.jpg'],
    'pasos corruptos' => ['pasos', 'invalido'],
]);

test('revierte incluso campos y pivotes si falla la escritura de pasos', function () {
    $solicitud = ejemploRevision(true);
    $receta = $solicitud->receta;
    $receta->ingredientes()->attach(Ingrediente::factory()->create(), ['cantidad' => 2, 'orden' => 1]);
    $receta->pasos()->create(['orden' => 1, 'instruccion' => 'Conservar este paso']);
    $antes = $receta->load('ingredientes', 'categorias', 'pasos')->toArray();
    PasoReceta::creating(function () {
        throw new RuntimeException('Fallo de escritura simulado');
    });

    try {
        $this->actingAs(adminRevision())->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHas('error');
    } finally {
        PasoReceta::flushEventListeners();
    }

    expect($receta->fresh()->load('ingredientes', 'categorias', 'pasos')->toArray())->toEqual($antes);
    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente', 'revisado_por' => null]);
});

test('revalida permisos del administrador aunque el objeto en memoria siga activo', function () {
    $solicitud = ejemploRevision();
    $admin = adminRevision();
    User::whereKey($admin->id)->update(['activo' => false]);

    expect(fn () => app(ProcesarRevision::class)->ejecutar($admin, $solicitud, 'aprobar'))->toThrow(AuthorizationException::class);

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
});

test('muestra pendientes con filtros y excluye recetas privadas sin solicitud', function () {
    $solicitud = ejemploRevision();
    $privada = Receta::factory()->create(['nombre' => 'Secreto privado']);
    SolicitudRevision::factory()->create(['estado' => 'rechazada', 'contenido' => ['nombre' => 'Solicitud rechazada']]);

    $this->actingAs(adminRevision())->get('/revision-recetas')->assertSee('Arroz con verduras mejorado')
        ->assertDontSee('Secreto privado')->assertDontSee('Solicitud rechazada');
    $this->get('/revision-recetas?estado=rechazada&tipo=publicacion')->assertSee('Solicitud rechazada')->assertDontSee('Arroz con verduras mejorado');
    $this->get('/revision-recetas/999999')->assertNotFound();
    $this->get("/recetas/{$privada->id}/editar")->assertNotFound();
});

test('compara el contenido publicado y la propuesta sin cambiar la receta', function () {
    $solicitud = ejemploRevision(true);

    $this->actingAs(adminRevision())->get("/revision-recetas/{$solicitud->id}")
        ->assertSee('Versión publicada')->assertSee('Propuesta enviada')->assertSee('Modificado')
        ->assertSee('Arroz con verduras mejorado')->assertSee('Arroz con verduras');

    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'nombre' => 'Arroz con verduras', 'version' => 1]);
});

test('las imagenes pendientes requieren autorizacion y no tienen enlaces publicos', function () {
    $solicitud = ejemploRevision();

    $this->actingAs(adminRevision())->get("/revision-recetas/{$solicitud->id}/imagen/propuesta")
        ->assertOk()->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');
    $this->get("/revision-recetas/{$solicitud->id}")->assertDontSee('/storage/recetas/')->assertDontSee($solicitud->contenido['imagen']);
    $this->get("/revision-recetas/{$solicitud->id}/imagen/publicada")->assertNotFound();
});

test('un reintento de aprobacion no sustituye al primer revisor ni vuelve a incrementar la version', function () {
    $solicitud = ejemploRevision();
    $primero = adminRevision();
    $segundo = adminRevision();
    $this->actingAs($primero)->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHas('exito');
    $decisionOriginal = $solicitud->fresh()->toArray();

    $this->actingAs($segundo)->post("/revision-recetas/{$solicitud->id}/aprobar")->assertSessionHasErrors('revision');

    expect($solicitud->fresh()->toArray())->toEqual($decisionOriginal);
    $this->assertDatabaseHas('recetas', ['id' => $solicitud->receta_id, 'version' => 2]);
    $this->get("/revision-recetas/{$solicitud->id}")->assertSee('Aprobada')->assertDontSee('Decisión de revisión');
});

test('la ruta de decision conserva la comprobacion CSRF del grupo web', function () {
    $solicitud = ejemploRevision();
    $admin = adminRevision();
    $this->app->detectEnvironment(fn () => 'local');

    $this->actingAs($admin)->post("/revision-recetas/{$solicitud->id}/aprobar")->assertStatus(419);

    $this->assertDatabaseHas('solicitudes_revision', ['id' => $solicitud->id, 'estado' => 'pendiente']);
});
