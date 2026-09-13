<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('login_account:usuario@ejemplo.com');
    RateLimiter::clear('login_ip:127.0.0.1');
});

test('registro exitoso devuelve 201 y perfil resource con rol usuario y activo', function () {
    $response = $this->postJson('/api/v1/auth/registro', [
        'name' => 'Carlos Mendoza',
        'email' => 'carlos@ejemplo.com',
        'password' => 'PasswordSegura2026*',
        'password_confirmation' => 'PasswordSegura2026*',
    ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'mensaje',
            'usuario' => ['id', 'name', 'email', 'foto_perfil_url', 'rol', 'activo'],
        ])
        ->assertJsonPath('usuario.name', 'Carlos Mendoza')
        ->assertJsonPath('usuario.email', 'carlos@ejemplo.com')
        ->assertJsonPath('usuario.rol', 'usuario')
        ->assertJsonPath('usuario.activo', true);

    $this->assertDatabaseHas('users', [
        'email' => 'carlos@ejemplo.com',
        'rol' => 'usuario',
        'activo' => 1,
    ]);
});

test('registro rechaza correos duplicados con 422', function () {
    User::factory()->create(['email' => 'duplicado@ejemplo.com']);

    $response = $this->postJson('/api/v1/auth/registro', [
        'name' => 'Otro Nombre',
        'email' => 'duplicado@ejemplo.com',
        'password' => 'PasswordSegura2026*',
        'password_confirmation' => 'PasswordSegura2026*',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('registro no permite asignarse rol administrador ni modificar activo o email_verified_at', function () {
    $response = $this->postJson('/api/v1/auth/registro', [
        'name' => 'Intento Admin',
        'email' => 'intento@ejemplo.com',
        'password' => 'PasswordSegura2026*',
        'password_confirmation' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => false,
        'email_verified_at' => now()->toDateTimeString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('usuario.rol', 'usuario')
        ->assertJsonPath('usuario.activo', true);

    $user = User::where('email', 'intento@ejemplo.com')->first();
    expect($user->rol)->toBe('usuario')
        ->and($user->activo)->toBeTrue()
        ->and($user->email_verified_at)->toBeNull();
});

test('registro exige contrasena de al menos 12 caracteres y confirmacion', function () {
    $response = $this->postJson('/api/v1/auth/registro', [
        'name' => 'Usuario Corto',
        'email' => 'corto@ejemplo.com',
        'password' => 'corta123',
        'password_confirmation' => 'corta123',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);

    $responseMismatch = $this->postJson('/api/v1/auth/registro', [
        'name' => 'Usuario Desigual',
        'email' => 'desigual@ejemplo.com',
        'password' => 'PasswordValida123*',
        'password_confirmation' => 'OtraPassword123*',
    ]);

    $responseMismatch->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('login exitoso permite ingresar a usuario normal y administrador activo con token Bearer', function () {
    $usuario = User::factory()->create([
        'email' => 'usuario.normal@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $admin = User::factory()->create([
        'email' => 'admin.activo@ejemplo.com',
        'password' => Hash::make('PasswordAdmin2026*'),
        'rol' => 'administrador',
        'activo' => true,
    ]);

    // Login usuario normal
    $respUser = $this->postJson('/api/v1/auth/login', [
        'email' => 'usuario.normal@ejemplo.com',
        'password' => 'PasswordSegura2026*',
        'dispositivo' => 'Pixel 7',
    ]);

    $respUser->assertOk()
        ->assertJsonStructure([
            'mensaje',
            'token',
            'token_type',
            'usuario' => ['id', 'name', 'email', 'foto_perfil_url', 'rol', 'activo'],
        ])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('usuario.rol', 'usuario');

    // Login administrador activo
    $respAdmin = $this->postJson('/api/v1/auth/login', [
        'email' => 'admin.activo@ejemplo.com',
        'password' => 'PasswordAdmin2026*',
        'dispositivo' => 'iPhone 15',
    ]);

    $respAdmin->assertOk()
        ->assertJsonPath('usuario.rol', 'administrador');
});

test('login rechaza credenciales incorrectas con 401 y mensaje generico', function () {
    User::factory()->create([
        'email' => 'usuario@ejemplo.com',
        'password' => Hash::make('PasswordCorrecta2026*'),
        'activo' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'usuario@ejemplo.com',
        'password' => 'PasswordIncorrecta*',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'mensaje' => 'Las credenciales proporcionadas son incorrectas o la cuenta se encuentra inactiva.',
        ]);
});

test('login rechaza cuentas inexistentes con 401 y el mismo mensaje generico', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'noexiste@ejemplo.com',
        'password' => 'CualquierPassword2026*',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'mensaje' => 'Las credenciales proporcionadas son incorrectas o la cuenta se encuentra inactiva.',
        ]);
});

test('login rechaza cuentas deshabilitadas con 401 y el mismo mensaje generico', function () {
    User::factory()->create([
        'email' => 'deshabilitado@ejemplo.com',
        'password' => Hash::make('PasswordValida2026*'),
        'activo' => false,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'deshabilitado@ejemplo.com',
        'password' => 'PasswordValida2026*',
    ]);

    $response->assertStatus(401)
        ->assertJson([
            'mensaje' => 'Las credenciales proporcionadas son incorrectas o la cuenta se encuentra inactiva.',
        ]);
});

test('login aplica limitacion de intentos por cuenta e ip respondiendo 429 con Retry-After', function () {
    User::factory()->create([
        'email' => 'usuario@ejemplo.com',
        'password' => Hash::make('PasswordReal2026*'),
        'activo' => true,
    ]);

    // Ejecutar 5 intentos fallidos
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'usuario@ejemplo.com',
            'password' => 'PasswordErronea*',
        ])->assertStatus(401);
    }

    // El 6to intento debe responder 429 con cabecera Retry-After
    $limitResponse = $this->postJson('/api/v1/auth/login', [
        'email' => 'usuario@ejemplo.com',
        'password' => 'PasswordErronea*',
    ]);

    $limitResponse->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('logout revoca unicamente el token actual sin cerrar sesiones de otros dispositivos', function () {
    $usuario = User::factory()->create(['activo' => true]);

    $token1 = $usuario->createToken('Dispositivo 1')->plainTextToken;
    $token2 = $usuario->createToken('Dispositivo 2')->plainTextToken;

    // Logout desde Dispositivo 1
    $response = $this->withHeader('Authorization', 'Bearer '.$token1)
        ->postJson('/api/v1/auth/logout');

    $response->assertOk()
        ->assertJson(['mensaje' => 'Sesión cerrada correctamente.']);

    // Dispositivo 1 ya no tiene acceso
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token1)
        ->getJson('/api/v1/perfil')
        ->assertStatus(401);

    // Dispositivo 2 sigue funcionando normalmente
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token2)
        ->getJson('/api/v1/perfil')
        ->assertOk();
});

test('token invalido o revocado no permite acceso a rutas protegidas', function () {
    $this->withHeader('Authorization', 'Bearer token_invalido_totalmente')
        ->getJson('/api/v1/perfil')
        ->assertStatus(401);
});

test('cuenta deshabilitada con token previo recibe 403 Forbidden', function () {
    $usuario = User::factory()->create(['activo' => true]);
    $token = $usuario->createToken('Movil')->plainTextToken;

    // Se deshabilita la cuenta directamente
    $usuario->update(['activo' => false]);

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/perfil');

    $response->assertStatus(403)
        ->assertJson([
            'mensaje' => 'Su cuenta se encuentra deshabilitada. Comuníquese con la administración.',
        ]);
});

test('ruta protegida anterior /api/user devuelve PerfilResource y valida cuenta activa', function () {
    $usuario = User::factory()->create([
        'name' => 'Usuario Antiguo',
        'email' => 'antiguo@ejemplo.com',
        'activo' => true,
    ]);
    $token = $usuario->createToken('Test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user');

    $response->assertOk()
        ->assertJsonPath('data.name', 'Usuario Antiguo')
        ->assertJsonPath('data.email', 'antiguo@ejemplo.com')
        ->assertJsonMissing(['password', 'remember_token']);

    // Si se deshabilita, /api/user responde 403
    $usuario->update(['activo' => false]);
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertStatus(403);
});
