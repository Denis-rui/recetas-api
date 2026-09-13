<?php

use App\Actions\Recetas\ProcesarRevision;
use App\Models\SolicitudRevision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

test('la policy protege cada operacion fuera del middleware', function (string $rol, bool $activo, bool $permitido) {
    $solicitud = SolicitudRevision::factory()->create();
    $usuario = User::factory()->create(['rol' => $rol, 'activo' => $activo]);

    expect(Gate::forUser($usuario)->allows('viewAny', SolicitudRevision::class))->toBe($permitido);
    expect(Gate::forUser($usuario)->allows('view', $solicitud))->toBe($permitido);
    expect(Gate::forUser($usuario)->allows('decidir', $solicitud))->toBe($permitido);
})->with([['administrador', true, true], ['administrador', false, false], ['usuario', true, false], ['usuario', false, false]]);

test('una solicitud no autoriza a inspeccionar una receta privada de otro autor', function () {
    $solicitud = SolicitudRevision::factory()->create(['solicitado_por' => User::factory()]);
    $admin = User::factory()->create(['rol' => 'administrador']);

    $this->actingAs($admin)->get('/revision-recetas')->assertDontSee($solicitud->contenido['nombre']);
    $this->get("/revision-recetas/{$solicitud->id}")->assertForbidden();
    $this->get("/revision-recetas/{$solicitud->id}/imagen/propuesta")->assertForbidden();
    $this->post("/revision-recetas/{$solicitud->id}/aprobar")->assertForbidden();
    expect(fn () => app(ProcesarRevision::class)->ejecutar($admin, $solicitud, 'aprobar'))->toThrow(AuthorizationException::class);
});

test('una imagen referenciada no permite leer archivos ajenos ni contenido activo', function (string $ruta, string $contenido) {
    Storage::fake('local');
    $solicitud = SolicitudRevision::factory()->create();
    $propuesta = $solicitud->contenido;
    $propuesta['imagen'] = str_replace('{id}', (string) $solicitud->receta_id, $ruta);
    Storage::disk('local')->put($propuesta['imagen'], $contenido);
    $solicitud->update(['contenido' => $propuesta]);

    $this->actingAs(User::factory()->create(['rol' => 'administrador']))
        ->get("/revision-recetas/{$solicitud->id}/imagen/propuesta")->assertNotFound();
})->with([
    ['recetas/99999/ajena.png', 'datos privados'],
    ['recetas/{id}/script.png', '<script>alert(1)</script>'],
    ['recetas/{id}/imagen.svg', '<svg onload="alert(1)"></svg>'],
]);

test('escapa texto enviado por autores y no carga urls externas de imagen', function () {
    $solicitud = SolicitudRevision::factory()->create(['contenido' => ['nombre' => '<script>alert(1)</script>', 'imagen' => 'https://example.com/espia.png']]);

    $this->actingAs(User::factory()->create(['rol' => 'administrador']))->get('/revision-recetas')
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    $this->get("/revision-recetas/{$solicitud->id}")
        ->assertSee('Propuesta no válida')->assertDontSee('https://example.com/espia.png');
});

test('el listado pagina y mantiene los filtros sin consultas por fila', function () {
    SolicitudRevision::factory()->count(17)->create();
    $admin = User::factory()->create(['rol' => 'administrador']);
    DB::enableQueryLog();

    $response = $this->actingAs($admin)->get('/revision-recetas?estado=pendiente&tipo=publicacion');

    $response->assertViewHas('solicitudes', fn ($solicitudes) => $solicitudes->count() === 15 && $solicitudes->total() === 17);
    $response->assertSee('page=2', false);
    expect(count(DB::getQueryLog()))->toBeLessThan(8);
    DB::disableQueryLog();
});

test('un filtro invalido redirige a pendientes sin bucle de redireccion', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $this->actingAs($admin)->from('/revision-recetas?estado=invalido')
        ->get('/revision-recetas?estado=invalido')->assertRedirect('/revision-recetas')->assertSessionHasErrors('estado');
});
