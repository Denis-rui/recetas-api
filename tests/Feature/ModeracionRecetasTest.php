<?php

use App\Actions\Recetas\EliminarRecetaAdministrativamente;
use App\Actions\Recetas\ProcesarRevision;
use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use App\Models\Valoracion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->create(['rol' => 'administrador', 'activo' => true]);
});

test('retirar una publicacion cancela solo sus revisiones pendientes y conserva referencias', function () {
    $solicitud = SolicitudRevision::factory()->correccion()->create();
    $receta = $solicitud->receta;
    $anterior = SolicitudRevision::factory()->create([
        'receta_id' => $receta->id, 'tipo' => 'correccion', 'estado' => 'rechazada',
    ]);
    $otra = SolicitudRevision::factory()->correccion()->create();
    $voto = Valoracion::factory()->create(['receta_id' => $receta->id]);
    $receta->usuariosQueLaGuardaron()->attach($voto->usuario_id);
    $votoAntes = $voto->getAttributes();
    $favoritoAntes = DB::table('favoritos')->where('receta_id', $receta->id)->first();
    $this->actingAs($this->admin)->delete('/moderacion-recetas/'.$receta->id, [
        'motivo_eliminacion' => '  Esta preparación ya existe en el catálogo.  ',
    ])->assertRedirect('/moderacion-recetas')->assertSessionHas('exito');

    $this->assertSoftDeleted($receta);
    $this->assertDatabaseHas('recetas', ['id' => $receta->id, 'eliminado_por' => $this->admin->id,
        'tipo_eliminacion' => 'administracion', 'motivo_eliminacion' => 'Esta preparación ya existe en el catálogo.']);
    expect($solicitud->fresh()->estado)->toBe('cancelada');
    expect($solicitud->fresh()->cancelada_en)->not->toBeNull();
    expect($anterior->fresh()->estado)->toBe('rechazada');
    expect($otra->fresh()->estado)->toBe('pendiente');
    expect($voto->fresh()->getAttributes())->toBe($votoAntes);
    expect(DB::table('favoritos')->where('receta_id', $receta->id)->first())->toEqual($favoritoAntes);
    expect(fn () => app(ProcesarRevision::class)->ejecutar($this->admin, $solicitud->fresh(), 'aprobar', null, true))
        ->toThrow(ValidationException::class);
    $this->getJson('/api/v1/recetas/'.$receta->id)->assertNotFound();
    $this->getJson('/api/v1/recetas')->assertJsonMissing(['id' => $receta->id]);
});

test('el autor consulta el motivo y quienes guardaron solo reciben el aviso administrativo', function () {
    $autor = User::factory()->create();
    $lector = User::factory()->create();
    $receta = Receta::factory()->publicada()->create(['creado_por' => $autor->id]);
    $lector->favoritos()->attach($receta);
    $motivo = 'Duplicado confirmado de una preparación existente.';
    $this->actingAs($this->admin)->delete('/moderacion-recetas/'.$receta->id, ['motivo_eliminacion' => $motivo])->assertSessionHas('exito');

    $this->actingAs($autor)->getJson('/api/v1/mis-recetas/'.$receta->id)
        ->assertOk()->assertJsonPath('data.motivo_eliminacion', $motivo);
    $this->actingAs($lector)->getJson('/api/v1/favoritos')->assertOk()
        ->assertJsonPath('data.0.visibilidad', 'eliminada')->assertJsonPath('data.0.mensaje', 'Esta receta fue eliminada')
        ->assertJsonMissingPath('data.0.descripcion')->assertJsonMissingPath('data.0.motivo_eliminacion');
    $this->getJson('/api/v1/recetas/'.$receta->id.'/valoracion')->assertNotFound();
    auth()->forgetGuards();
    $this->postJson('/api/v1/recetas/verificar-disponibilidad', ['ids' => [$receta->id]])
        ->assertOk()->assertJsonPath('data.0.estado', 'eliminada_administracion')->assertDontSee($motivo);
});

test('la moderacion exige administrador activo', function (string $rol, bool $activo) {
    $usuario = User::factory()->create(['rol' => $rol, 'activo' => $activo]);
    $receta = Receta::factory()->publicada()->create();
    foreach (['/moderacion-recetas', '/moderacion-recetas/'.$receta->id] as $url) {
        $this->actingAs($usuario)->get($url)->assertRedirect('/login');
    }
    $this->actingAs($usuario)->delete('/moderacion-recetas/'.$receta->id, ['motivo_eliminacion' => 'Motivo'])
        ->assertRedirect('/login');
    expect($receta->fresh()->trashed())->toBeFalse();
})->with([['usuario', true], ['administrador', false]]);

test('la moderacion no revela privadas ni permite retirar recetas propias', function () {
    $privada = Receta::factory()->create(['nombre' => 'Privada reservada']);
    $propia = Receta::factory()->publicada()->create(['creado_por' => $this->admin->id, 'nombre' => 'Propia reservada']);
    $publica = Receta::factory()->publicada()->create(['nombre' => 'Publicación para revisar']);
    $this->actingAs($this->admin)->get('/moderacion-recetas')->assertOk()
        ->assertSee($publica->nombre)->assertDontSee($privada->nombre)->assertDontSee($propia->nombre);
    foreach ([$privada->id, 999999, $privada->id.'.0', '0'.$privada->id] as $id) {
        $this->get('/moderacion-recetas/'.$id)->assertNotFound();
        $this->delete('/moderacion-recetas/'.$id, ['motivo_eliminacion' => 'No autorizado'])->assertNotFound();
    }
    $this->delete('/moderacion-recetas/'.$propia->id, ['motivo_eliminacion' => 'Propia'])->assertForbidden();
    expect($privada->fresh()->trashed())->toBeFalse();
    expect($propia->fresh()->trashed())->toBeFalse();
});

test('el motivo es obligatorio con contenido real', function (mixed $motivo) {
    $receta = Receta::factory()->publicada()->create();
    $this->actingAs($this->admin)->delete('/moderacion-recetas/'.$receta->id, ['motivo_eliminacion' => $motivo])
        ->assertRedirect('/moderacion-recetas/'.$receta->id)->assertSessionHasErrors('motivo_eliminacion');
    expect($receta->fresh()->trashed())->toBeFalse();
})->with([null, '', " \t\n", "\u{00A0}\u{200B}", str_repeat('a', 16001)]);

test('puede moderar una publicacion de un autor deshabilitado y no sobrescribe una eliminacion anterior', function () {
    $receta = Receta::factory()->publicada()->create(['creado_por' => User::factory()->create(['activo' => false])->id]);
    $this->actingAs($this->admin)->get('/moderacion-recetas/'.$receta->id)->assertOk()
        ->assertSee('motivo_eliminacion')->assertSee('Eliminar receta');
    $this->delete('/moderacion-recetas/'.$receta->id, ['motivo_eliminacion' => 'Motivo original'])->assertSessionHas('exito');
    $this->delete('/moderacion-recetas/'.$receta->id, ['motivo_eliminacion' => 'Otro motivo'])->assertNotFound();
    expect($receta->fresh()->motivo_eliminacion)->toBe('Motivo original');
});

test('el listado de moderacion pagina y busca sin aceptar filtros invalidos', function () {
    Receta::factory()->publicada()->count(16)->create(['nombre' => 'Arroz disponible']);
    $this->actingAs($this->admin)->get('/moderacion-recetas?page=2')->assertOk()->assertViewHas('recetas', fn ($recetas) => $recetas->count() === 1);
    $this->get('/moderacion-recetas?buscar=inexistente')->assertOk()->assertViewHas('recetas', fn ($recetas) => $recetas->isEmpty());
    $this->get('/moderacion-recetas?page=0')->assertRedirect('/moderacion-recetas')->assertSessionHasErrors('page');
});

test('la accion revalida al administrador y revierte todo si falla la cancelacion', function () {
    $solicitud = SolicitudRevision::factory()->correccion()->create();
    $receta = $solicitud->receta;
    User::whereKey($this->admin->id)->update(['activo' => false]);
    expect(fn () => app(EliminarRecetaAdministrativamente::class)->ejecutar($this->admin, $receta, 'Motivo'))
        ->toThrow(AuthorizationException::class);
    User::whereKey($this->admin->id)->update(['activo' => true]);
    SolicitudRevision::updating(function () {
        throw new RuntimeException('Fallo de escritura simulado');
    });
    try {
        expect(fn () => app(EliminarRecetaAdministrativamente::class)->ejecutar($this->admin, $receta, 'Motivo'))
            ->toThrow(RuntimeException::class, 'Fallo de escritura simulado');
    } finally {
        SolicitudRevision::flushEventListeners();
    }
    expect($receta->fresh()->trashed())->toBeFalse();
    expect($solicitud->fresh()->estado)->toBe('pendiente');
    expect($receta->fresh()->eliminado_por)->toBeNull();
});
