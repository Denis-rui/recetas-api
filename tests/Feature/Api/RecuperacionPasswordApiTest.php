<?php

use App\Mail\CodigoRecuperacionMail;
use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('recup_cooldown:usuario@ejemplo.com');
    RateLimiter::clear('recup_cooldown:noexiste@ejemplo.com');
    RateLimiter::clear('recup_cooldown:deshabilitado@ejemplo.com');
    RateLimiter::clear('recup_email:usuario@ejemplo.com');
    RateLimiter::clear('recup_ip:127.0.0.1');
    RateLimiter::clear('recup_verificar_ip:127.0.0.1');
});

test('solicitar codigo con cuenta activa encola correo y responde 200 con mensaje generico sin exponer codigo', function () {
    Mail::fake();

    $usuario = User::factory()->create([
        'email' => 'usuario@ejemplo.com',
        'activo' => true,
    ]);

    $response = $this->postJson('/api/v1/auth/recuperacion/solicitar', [
        'email' => 'usuario@ejemplo.com',
    ]);

    $response->assertOk()
        ->assertJson([
            'mensaje' => 'Si el correo corresponde a una cuenta habilitada, recibirás un código para restablecer tu contraseña.',
        ])
        ->assertJsonMissing(['codigo']);

    // Se encoló el correo
    Mail::assertQueued(CodigoRecuperacionMail::class, function ($mail) use ($usuario) {
        return $mail->hasTo($usuario->email) && strlen($mail->codigo) === 6;
    });

    // Se guardó el registro en la base de datos con hash
    $this->assertDatabaseHas('recuperaciones_password', [
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'intentos' => 0,
    ]);
});

test('solicitar codigo con cuenta inexistente o deshabilitada responde identico sin encolar ni guardar en bd', function () {
    Mail::fake();

    User::factory()->create([
        'email' => 'deshabilitado@ejemplo.com',
        'activo' => false,
    ]);

    // Correo inexistente
    $respInexistente = $this->postJson('/api/v1/auth/recuperacion/solicitar', [
        'email' => 'noexiste@ejemplo.com',
    ]);

    $respInexistente->assertOk()
        ->assertJson([
            'mensaje' => 'Si el correo corresponde a una cuenta habilitada, recibirás un código para restablecer tu contraseña.',
        ]);

    // Correo deshabilitado
    $respDeshabilitado = $this->postJson('/api/v1/auth/recuperacion/solicitar', [
        'email' => 'deshabilitado@ejemplo.com',
    ]);

    $respDeshabilitado->assertOk()
        ->assertJson([
            'mensaje' => 'Si el correo corresponde a una cuenta habilitada, recibirás un código para restablecer tu contraseña.',
        ]);

    Mail::assertNothingQueued();
    expect(RecuperacionPassword::where('email', 'noexiste@ejemplo.com')->count())->toBe(0)
        ->and(RecuperacionPassword::where('email', 'deshabilitado@ejemplo.com')->count())->toBe(0);
});

test('cooldown de 60 segundos bloquea reenvios tanto para correos existentes como inexistentes con 429', function () {
    Mail::fake();

    User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);

    // Primera solicitud (exitosa)
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'usuario@ejemplo.com'])
        ->assertOk();

    // Segunda solicitud inmediata (bloqueada por cooldown de 60s)
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'usuario@ejemplo.com'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    // Correo inexistente: primera solicitud
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'noexiste@ejemplo.com'])
        ->assertOk();

    // Correo inexistente: segunda solicitud inmediata también recibe 429
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'noexiste@ejemplo.com'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('reenviar codigo despues del cooldown invalida el codigo anterior de la cuenta', function () {
    Mail::fake();
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);

    // Primer código
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'usuario@ejemplo.com']);
    $primerRegistro = RecuperacionPassword::where('user_id', $usuario->id)->first();
    expect($primerRegistro->invalidado_en)->toBeNull();

    // Simular el paso de los 60 segundos de cooldown
    $this->travel(65)->seconds();

    // Segundo envío
    $this->postJson('/api/v1/auth/recuperacion/solicitar', ['email' => 'usuario@ejemplo.com'])
        ->assertOk();

    $primerRegistro->refresh();
    expect($primerRegistro->invalidado_en)->not->toBeNull();

    // El segundo registro está activo
    $segundoRegistro = RecuperacionPassword::where('user_id', $usuario->id)->latest('id')->first();
    expect($segundoRegistro->id)->not->toBe($primerRegistro->id)
        ->and($segundoRegistro->invalidado_en)->toBeNull();
});

test('soporta codigos con cero inicial tanto en almacenamiento como en verificacion', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);

    // Crear manualmente un registro con código con ceros iniciales '007123'
    $codigoConCeros = '007123';
    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make($codigoConCeros),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'usuario@ejemplo.com',
        'codigo' => '007123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['mensaje', 'token_recuperacion']);
});

test('verificar con codigo incorrecto persiste el contador de intentos y se invalida al quinto intento', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $expiracionOriginal = $recuperacion->codigo_expira_en->toDateTimeString();

    // 1 intento fallido
    $response = $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'usuario@ejemplo.com',
        'codigo' => '999999',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['codigo']);

    $recuperacion->refresh();
    expect($recuperacion->intentos)->toBe(1);
    expect($recuperacion->codigo_expira_en->toDateTimeString())->toBe($expiracionOriginal);

    // Intentos 2, 3, 4
    for ($i = 2; $i <= 4; $i++) {
        $this->postJson('/api/v1/auth/recuperacion/verificar', [
            'email' => 'usuario@ejemplo.com',
            'codigo' => '999999',
        ])->assertStatus(422);

        $recuperacion->refresh();
        expect($recuperacion->intentos)->toBe($i);
    }

    // 5to intento fallido
    $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'usuario@ejemplo.com',
        'codigo' => '999999',
    ])->assertStatus(422);

    $recuperacion->refresh();
    expect($recuperacion->intentos)->toBe(5)
        ->and($recuperacion->invalidado_en)->not->toBeNull();

    // 6to intento incluso con el código correcto es rechazado porque ya se invalidó
    $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'usuario@ejemplo.com',
        'codigo' => '123456',
    ])->assertStatus(422);
});

test('verificar codigo vencido mayor a 10 minutos es rechazado con error generico', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);

    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'intentos' => 0,
        'codigo_expira_en' => now()->subMinute(), // Vencido
    ]);

    $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'usuario@ejemplo.com',
        'codigo' => '123456',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['codigo']);
});

test('verificar codigo para correo no registrado devuelve error generico 422', function () {
    $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'desconocido@ejemplo.com',
        'codigo' => '123456',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['codigo']);
});

test('imposibilidad de usar token_recuperacion como token Bearer de acceso', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);
    $tokenPlano = 'token_recuperacion_aleatorio_64_caracteres_123456789012345678901234';

    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(15),
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    // Enviar el token_recuperacion en cabecera Bearer a una ruta protegida
    $response = $this->withHeader('Authorization', 'Bearer '.$tokenPlano)
        ->getJson('/api/v1/perfil');

    $response->assertStatus(401);
});

test('restablecimiento exitoso de contrasena revoca tokens sesiones web y remember_token', function () {
    $usuario = User::factory()->create([
        'email' => 'usuario@ejemplo.com',
        'password' => Hash::make('PasswordAntigua123*'),
        'activo' => true,
    ]);

    $tokenMovil = $usuario->createToken('Movil')->plainTextToken;
    $usuario->setRememberToken('remember_token_antiguo');
    $usuario->save();

    DB::table('sessions')->insert([
        'id' => 'sesion_web_99',
        'user_id' => $usuario->id,
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $tokenPlano = 'token_recuperacion_autorizado_64_caracteres_abcdef1234567890abcdef';
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(15),
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/recuperacion/restablecer', [
        'token_recuperacion' => $tokenPlano,
        'password' => 'NuevaPasswordSegura2026*',
        'password_confirmation' => 'NuevaPasswordSegura2026*',
    ]);

    $response->assertOk()
        ->assertJson([
            'mensaje' => 'Contraseña restablecida exitosamente. Ya puede iniciar sesión con su nueva contraseña.',
        ]);

    $usuario->refresh();
    expect(Hash::check('NuevaPasswordSegura2026*', $usuario->password))->toBeTrue()
        ->and($usuario->remember_token)->toBeNull();

    // Sesiones y tokens anteriores eliminados
    expect(DB::table('sessions')->where('user_id', $usuario->id)->count())->toBe(0)
        ->and($usuario->tokens()->count())->toBe(0);

    // Autorización consumida
    $recuperacion->refresh();
    expect($recuperacion->usado_en)->not->toBeNull();

    // El token móvil previo dejó de funcionar
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$tokenMovil)
        ->getJson('/api/v1/perfil')
        ->assertStatus(401);
});

test('token de recuperacion vencido mayor a 15 minutos es rechazado con 422', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);
    $tokenPlano = 'token_vencido_12345678901234567890123456789012345678901234567890';

    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now()->subMinutes(20),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->subMinute(), // Vencido (hace más de 15 min de emitido)
        'codigo_expira_en' => now()->subMinutes(10),
    ]);

    $response = $this->postJson('/api/v1/auth/recuperacion/restablecer', [
        'token_recuperacion' => $tokenPlano,
        'password' => 'NuevaPasswordSegura2026*',
        'password_confirmation' => 'NuevaPasswordSegura2026*',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token_recuperacion']);
});

test('imposibilidad de reutilizar un token_recuperacion ya consumido', function () {
    $usuario = User::factory()->create(['email' => 'usuario@ejemplo.com', 'activo' => true]);
    $tokenPlano = 'token_ya_usado_12345678901234567890123456789012345678901234567890';

    RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'usuario@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now()->subMinutes(2),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(13),
        'codigo_expira_en' => now()->addMinutes(8),
        'usado_en' => now()->subMinute(), // Ya consumido
    ]);

    $response = $this->postJson('/api/v1/auth/recuperacion/restablecer', [
        'token_recuperacion' => $tokenPlano,
        'password' => 'NuevaPasswordSegura2026*',
        'password_confirmation' => 'NuevaPasswordSegura2026*',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token_recuperacion']);
});

test('cifrado nativo de Laravel en la cola garantiza que el codigo de 6 digitos no aparezca en texto plano en la tabla jobs', function () {
    $usuario = User::factory()->create(['email' => 'cifrado@ejemplo.com', 'activo' => true]);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'cifrado@ejemplo.com',
        'codigo_hash' => Hash::make('849201'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    // Limpiar tabla jobs y usar el driver database
    DB::table('jobs')->delete();
    Config::set('queue.default', 'database');

    $codigoSecreto = '849201';
    Mail::to($usuario->email)->queue(new CodigoRecuperacionMail($codigoSecreto, $recuperacion->id, 10));

    $job = DB::table('jobs')->first();
    expect($job)->not->toBeNull();

    // 1. El código en texto plano '849201' NO debe existir en el payload serializado de la base de datos
    expect(str_contains((string) $job->payload, $codigoSecreto))->toBeFalse();

    // 2. El comando en data está cifrado nativamente por Laravel (ShouldBeEncrypted)
    $payload = json_decode($job->payload, true);
    expect($payload['data']['commandName'])->toContain('SendQueuedMailable')
        ->and(is_string($payload['data']['command']))->toBeTrue();

    // 3. Al desencriptar el comando mediante el encrypter de la aplicación se recupera el mailable intacto
    $decryptedCommand = unserialize(app('encrypter')->decrypt($payload['data']['command']));
    expect($decryptedCommand->mailable->codigo)->toBe($codigoSecreto)
        ->and($decryptedCommand->mailable->recuperacionId)->toBe($recuperacion->id);
});

test('descarte seguro de envio en CodigoRecuperacionMail cuando el codigo ya fue verificado, vencio o cambio el correo', function () {
    $usuario = User::factory()->create(['email' => 'vigencia@ejemplo.com', 'activo' => true]);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'vigencia@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $mailable = new CodigoRecuperacionMail('123456', $recuperacion->id, 10);
    $mailerMock = Mockery::mock(Mailer::class);
    $mailerMock->shouldReceive('send')->never();

    // Caso 1: Código ya verificado (codigo_verificado_en no es null)
    $recuperacion->update(['codigo_verificado_en' => now()]);
    expect($mailable->send($mailerMock))->toBeNull();

    // Caso 2: Recuperación invalidada (invalidado_en no es null)
    $recuperacion->update(['codigo_verificado_en' => null, 'invalidado_en' => now()]);
    expect($mailable->send($mailerMock))->toBeNull();

    // Caso 3: Código expirado
    $recuperacion->update(['invalidado_en' => null, 'codigo_expira_en' => now()->subMinutes(1)]);
    expect($mailable->send($mailerMock))->toBeNull();

    // Caso 4: Cuenta desactivada
    $recuperacion->update(['codigo_expira_en' => now()->addMinutes(10)]);
    $usuario->update(['activo' => false]);
    expect($mailable->send($mailerMock))->toBeNull();

    // Caso 5: Correo del usuario modificado
    $usuario->update(['activo' => true, 'email' => 'nuevo.correo@ejemplo.com']);
    expect($mailable->send($mailerMock))->toBeNull();
});

test('verificar codigo falla e invalida la recuperacion si el usuario cambio su correo despues de solicitar el codigo', function () {
    $usuario = User::factory()->create(['email' => 'original@ejemplo.com', 'activo' => true]);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'original@ejemplo.com',
        'codigo_hash' => Hash::make('654321'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    // El usuario cambia su correo a través de su cuenta
    $usuario->email = 'modificado@ejemplo.com';
    $usuario->save();

    // Intentar verificar con el correo original
    $response = $this->postJson('/api/v1/auth/recuperacion/verificar', [
        'email' => 'original@ejemplo.com',
        'codigo' => '654321',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['codigo']);

    $recuperacion->refresh();
    // La recuperación queda invalidada y no se emitió token
    expect($recuperacion->invalidado_en)->not->toBeNull()
        ->and($recuperacion->codigo_verificado_en)->toBeNull();
});

test('restablecer con token falla e invalida la recuperacion si el usuario cambio su correo despues de verificar', function () {
    $usuario = User::factory()->create(['email' => 'original@ejemplo.com', 'activo' => true]);
    $tokenPlano = Str::random(64);

    $recuperacion = RecuperacionPassword::create([
        'user_id' => $usuario->id,
        'email' => 'original@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'codigo_verificado_en' => now(),
        'token_recuperacion_hash' => hash('sha256', $tokenPlano),
        'token_expira_en' => now()->addMinutes(15),
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    // El usuario cambia su correo antes de restablecer la contraseña
    $usuario->email = 'cambiado@ejemplo.com';
    $usuario->save();

    $response = $this->postJson('/api/v1/auth/recuperacion/restablecer', [
        'token_recuperacion' => $tokenPlano,
        'password' => 'NuevaPasswordValida123*',
        'password_confirmation' => 'NuevaPasswordValida123*',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['token_recuperacion']);

    $recuperacion->refresh();
    expect($recuperacion->invalidado_en)->not->toBeNull()
        ->and($recuperacion->usado_en)->toBeNull();
});
