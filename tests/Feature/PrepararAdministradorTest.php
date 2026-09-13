<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

test('el seeder general no crea cuentas con credenciales predefinidas', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->count())->toBe(0);
});

test('el primer administrador se crea con una clave indicada de forma interactiva', function () {
    $this->artisan('app:crear-primer-administrador')
        ->expectsQuestion('Nombre completo', 'Administración Inicial')
        ->expectsQuestion('Correo electrónico', 'inicial@example.test')
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveUnicaSegura2026*')
        ->expectsQuestion('Confirmar contraseña', 'ClaveUnicaSegura2026*')
        ->expectsOutput('Administrador inicial creado. Inicie sesión con las credenciales indicadas.')
        ->assertSuccessful();

    $usuario = User::query()->sole();
    expect($usuario->rol)->toBe('administrador');
    expect($usuario->activo)->toBeTrue();
    expect(Hash::check('ClaveUnicaSegura2026*', $usuario->password))->toBeTrue();
    expect($usuario->email_verified_at)->toBeNull();
});

test('la preparacion no modifica una cuenta existente con el correo solicitado', function () {
    $usuario = User::factory()->create(['email' => 'existente@example.test']);
    $antes = $usuario->fresh()->getAttributes();

    $this->artisan('app:crear-primer-administrador')
        ->expectsQuestion('Nombre completo', 'Nuevo Nombre')
        ->expectsQuestion('Correo electrónico', $usuario->email)
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', 'ClaveUnicaSegura2026*')
        ->expectsQuestion('Confirmar contraseña', 'ClaveUnicaSegura2026*')
        ->assertFailed();

    expect($usuario->fresh()->getAttributes())->toBe($antes);
    expect(User::query()->count())->toBe(1);
});

test('la preparacion no crea otra cuenta si ya existe un administrador', function () {
    $usuario = User::factory()->create(['rol' => 'administrador', 'activo' => false]);
    $antes = $usuario->fresh()->getAttributes();

    $this->artisan('app:crear-primer-administrador')
        ->expectsOutput('Ya existe un administrador. Gestione las cuentas desde el panel.')
        ->assertFailed();

    expect($usuario->fresh()->getAttributes())->toBe($antes);
    expect(User::query()->count())->toBe(1);
});

test('la preparacion exige una clave larga y confirmada', function (string $password, string $confirmacion) {
    $this->artisan('app:crear-primer-administrador')
        ->expectsQuestion('Nombre completo', 'Administración Inicial')
        ->expectsQuestion('Correo electrónico', 'inicial@example.test')
        ->expectsQuestion('Contraseña (mínimo 12 caracteres)', $password)
        ->expectsQuestion('Confirmar contraseña', $confirmacion)
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
})->with([
    'corta' => ['corta', 'corta'],
    'confirmacion incorrecta' => ['ClaveUnicaSegura2026*', 'OtraClaveSegura2026*'],
]);

test('la preparacion sin interaccion no inventa credenciales', function () {
    $this->artisan('app:crear-primer-administrador', ['--no-interaction' => true])->assertFailed();

    expect(User::query()->count())->toBe(0);
});
