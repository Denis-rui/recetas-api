<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('registro limita por IP devuelve Retry-After y no crea la cuenta rechazada', function () {
    config(['api.registro_por_minuto' => 1]);
    $datos = ['name' => 'Cuenta Inicial', 'email' => 'uno@example.test', 'password' => 'ClaveSegura2026*', 'password_confirmation' => 'ClaveSegura2026*'];
    $this->postJson('/api/v1/auth/registro', $datos)->assertCreated();
    $datos['email'] = 'dos@example.test';
    $this->postJson('/api/v1/auth/registro', $datos)->assertTooManyRequests()
        ->assertHeader('Retry-After')->assertJsonStructure(['mensaje', 'retry_after']);
    expect(User::query()->count())->toBe(1);

    $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.20'])
        ->postJson('/api/v1/auth/registro', $datos)->assertCreated();
});

test('el limite horario del registro persiste al vencer la ventana por minuto', function () {
    config(['api.registro_por_minuto' => 10, 'api.registro_por_hora' => 1]);
    $this->postJson('/api/v1/auth/registro', [])->assertUnprocessable();
    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/registro', [])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(User::query()->count())->toBe(0);
});

test('restablecimiento y consulta publica de disponibilidad limitan intentos invalidos', function (string $ruta, string $configuracion) {
    config([$configuracion => 1]);
    $this->postJson($ruta, [])->assertUnprocessable();
    $this->postJson($ruta, [])->assertTooManyRequests()->assertHeader('Retry-After');
})->with([
    'recuperacion' => ['/api/v1/auth/recuperacion/restablecer', 'api.restablecer_por_minuto'],
    'disponibilidad' => ['/api/v1/recetas/verificar-disponibilidad', 'api.disponibilidad_por_minuto'],
]);

test('las escrituras se limitan por cuenta y permiten consultar y cerrar sesion al agotar la cuota', function () {
    config(['api.escrituras_por_minuto' => 1]);
    $usuario = User::factory()->create();
    $otro = User::factory()->create();
    $token = $usuario->createToken('Prueba')->plainTextToken;
    $otroToken = $otro->createToken('Prueba')->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/perfil', ['name' => 'Nombre Permitido'])->assertOk();
    $this->withToken($token)->patchJson('/api/v1/perfil', ['name' => 'Nombre Bloqueado'])->assertTooManyRequests();
    expect($usuario->fresh()->name)->toBe('Nombre Permitido');
    $this->withToken($token)->getJson('/api/v1/perfil')->assertOk();
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    app('auth')->forgetGuards();
    $this->withToken($otroToken)->patchJson('/api/v1/perfil', ['name' => 'Otra Cuenta'])->assertOk();
    expect($otro->fresh()->name)->toBe('Otra Cuenta');
});

test('el limite de imagenes se aplica a archivos y no consume cambios de texto', function () {
    config(['api.imagenes_por_minuto' => 1]);
    Storage::fake('public');
    $usuario = User::factory()->create();
    $token = $usuario->createToken('Prueba')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/perfil/foto', ['foto_perfil' => UploadedFile::fake()->createWithContent('uno.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4T8AAAAASUVORK5CYII='))])->assertOk();
    $rutaAnterior = $usuario->fresh()->foto_perfil;
    $this->withToken($token)->postJson('/api/v1/perfil/foto', ['foto_perfil' => UploadedFile::fake()->createWithContent('dos.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a4T8AAAAASUVORK5CYII='))])->assertTooManyRequests();
    expect($usuario->fresh()->foto_perfil)->toBe($rutaAnterior);
    Storage::disk('public')->assertExists($rutaAnterior);

    $this->withToken($token)->postJson('/api/v1/mis-recetas', [])->assertUnprocessable();
    $this->withToken($token)->postJson('/api/v1/mis-recetas', [])->assertUnprocessable();
});
