<?php

use App\Actions\Auth\RestablecerPasswordConToken;
use App\Actions\Usuarios\CambiarPassword;
use App\Actions\Usuarios\DeshabilitarCuenta;
use App\Actions\Usuarios\ReactivarCuenta;
use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

test('simulacion determinista rechaza login si se revoca el acceso despues de comprobar la clave', function (string $operacion) {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $usuario = User::factory()->create(['password' => 'AnteriorSegura2026*']);
    $driver = Hash::driver();
    $tokenRecuperacion = 'autorizacion-unica-de-prueba';
    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => $usuario->email,
        'codigo_hash' => Hash::make('739281'),
        'codigo_expira_en' => now()->addMinutes(10),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenRecuperacion),
        'token_expira_en' => now()->addMinutes(15),
    ]);

    Hash::swap(Mockery::mock(Hash::getFacadeRoot())->makePartial());
    Hash::shouldReceive('check')->once()->andReturnUsing(function (string $password, string $hash) use ($driver, $operacion, $admin, $usuario, $tokenRecuperacion): bool {
        $correcta = $driver->check($password, $hash);
        match ($operacion) {
            'deshabilitar' => app(DeshabilitarCuenta::class)->ejecutar($admin, $usuario),
            'cambiar' => app(CambiarPassword::class)->ejecutar($usuario, 'NuevaSegura2026*'),
            'restablecer' => app(RestablecerPasswordConToken::class)->ejecutar($tokenRecuperacion, 'NuevaSegura2026*'),
        };

        return $correcta;
    });

    $this->postJson('/api/v1/auth/login', [
        'email' => $usuario->email,
        'password' => 'AnteriorSegura2026*',
    ])->assertUnauthorized()->assertJsonMissingPath('token');

    expect($usuario->tokens()->count())->toBe(0);
})->with(['deshabilitar', 'cambiar', 'restablecer'])->group('simulacion-determinista');

test('reactivar no permite reutilizar el token de un login previo a la deshabilitacion', function () {
    $admin = User::factory()->create(['rol' => 'administrador']);
    $usuario = User::factory()->create(['password' => 'AnteriorSegura2026*']);
    $token = $this->postJson('/api/v1/auth/login', [
        'email' => $usuario->email,
        'password' => 'AnteriorSegura2026*',
    ])->assertOk()->json('token');

    app(DeshabilitarCuenta::class)->ejecutar($admin, $usuario);
    app(ReactivarCuenta::class)->ejecutar($admin, $usuario);

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/perfil')->assertUnauthorized();
});

test('una solicitud de cambio con credenciales obsoletas no reemplaza una clave recien restablecida', function () {
    $usuario = User::factory()->create(['password' => 'AnteriorSegura2026*']);
    $obsoleto = User::findOrFail($usuario->id);
    app(CambiarPassword::class)->ejecutar($usuario, 'ClaveReciente2026*');

    expect(fn () => app(CambiarPassword::class)->ejecutar($obsoleto, 'ClaveObsoleta2026*'))
        ->toThrow(ValidationException::class);

    expect(Hash::check('ClaveReciente2026*', $usuario->fresh()->password))->toBeTrue();
});
