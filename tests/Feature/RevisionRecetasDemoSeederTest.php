<?php

use App\Models\Receta;
use App\Models\SolicitudRevision;
use App\Models\User;
use Database\Seeders\RevisionRecetasDemoSeeder;
use Illuminate\Support\Facades\Storage;

test('carga ejemplos coherentes y repetirlos no duplica ni modifica cuentas reales', function () {
    Storage::fake('local');
    $real = User::factory()->create();
    $antes = $real->fresh()->getAttributes();
    $this->seed(RevisionRecetasDemoSeeder::class);
    $conteos = [User::count(), Receta::count(), SolicitudRevision::count()];
    expect($conteos[2])->toBe(6);
    $pendiente = SolicitudRevision::where('estado', 'pendiente')->firstOrFail();

    $this->seed(RevisionRecetasDemoSeeder::class);

    expect([User::count(), Receta::count(), SolicitudRevision::count()])->toBe($conteos);
    expect($real->fresh()->getAttributes())->toBe($antes);
    $this->assertDatabaseHas('solicitudes_revision', ['estado' => 'cancelada']);
    $this->assertDatabaseHas('solicitudes_revision', ['estado' => 'rechazada']);
    $this->assertDatabaseHas('solicitudes_revision', ['estado' => 'aprobada']);
    $this->assertDatabaseCount('favoritos', 1);
    $this->assertDatabaseCount('valoraciones', 1);
    Storage::disk('local')->assertExists($pendiente->contenido['imagen']);
});

test('el seeder se niega a ejecutarse en produccion incluso con force', function () {
    $this->app->detectEnvironment(fn () => 'production');

    expect(fn () => app(RevisionRecetasDemoSeeder::class)->run())->toThrow(RuntimeException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('recetas', 0);
});

test('el seeder no se apropia de una cuenta existente con el correo reservado', function () {
    $real = User::factory()->create(['email' => 'admin@revision-demo.invalid']);
    $antes = $real->fresh()->getAttributes();

    expect(fn () => $this->seed(RevisionRecetasDemoSeeder::class))->toThrow(RuntimeException::class);

    expect($real->fresh()->getAttributes())->toBe($antes);
    $this->assertDatabaseCount('recetas', 0);
});
