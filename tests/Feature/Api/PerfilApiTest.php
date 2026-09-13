<?php

use App\Models\RecuperacionPassword;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->usuario = User::factory()->create([
        'name' => 'Ana Torres',
        'email' => 'ana.torres@ejemplo.com',
        'password' => Hash::make('PasswordSegura2026*'),
        'rol' => 'usuario',
        'activo' => true,
    ]);
    $this->token = $this->usuario->createToken('Movil')->plainTextToken;
});

test('perfil devuelve unicamente los datos de la cuenta autenticada', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/perfil');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => ['id', 'name', 'email', 'foto_perfil_url', 'rol', 'activo'],
        ])
        ->assertJsonPath('data.id', $this->usuario->id)
        ->assertJsonPath('data.name', 'Ana Torres')
        ->assertJsonPath('data.email', 'ana.torres@ejemplo.com')
        ->assertJsonPath('data.rol', 'usuario')
        ->assertJsonPath('data.activo', true)
        ->assertJsonMissing(['password', 'remember_token']);
});

test('perfil no acepta identificador externo para consultar cuentas ajenas', function () {
    $otroUsuario = User::factory()->create(['name' => 'Otro Usuario']);

    // La ruta no recibe {id} en la URL y solo responde con la cuenta del token
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/perfil?id='.$otroUsuario->id);

    $response->assertOk()
        ->assertJsonPath('data.id', $this->usuario->id)
        ->assertJsonPath('data.name', 'Ana Torres');
});

test('actualizacion parcial de nombre conserva el correo intacto', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/perfil', [
            'name' => 'Ana Sofia Torres',
        ]);

    $response->assertOk()
        ->assertJsonPath('usuario.name', 'Ana Sofia Torres')
        ->assertJsonPath('usuario.email', 'ana.torres@ejemplo.com');

    $this->usuario->refresh();
    expect($this->usuario->name)->toBe('Ana Sofia Torres')
        ->and($this->usuario->email)->toBe('ana.torres@ejemplo.com');
});

test('cambio de correo exige contrasena actual y se rechaza si es incorrecta', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/perfil', [
            'email' => 'nuevo.correo@ejemplo.com',
            'current_password' => 'PasswordIncorrecta*',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('cambio de correo sin contrasena actual es rechazado', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/perfil', [
            'email' => 'nuevo.correo@ejemplo.com',
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['current_password']);
});

test('cambio de correo exitoso invalida codigos de recuperacion previos y no marca verificado', function () {
    // Código previo pendiente
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $this->usuario->id,
        'email' => 'ana.torres@ejemplo.com',
        'codigo_hash' => Hash::make('123456'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/perfil', [
            'email' => 'ana.nuevo@ejemplo.com',
            'current_password' => 'PasswordSegura2026*',
        ]);

    $response->assertOk()
        ->assertJsonPath('usuario.email', 'ana.nuevo@ejemplo.com');

    $this->usuario->refresh();
    expect($this->usuario->email)->toBe('ana.nuevo@ejemplo.com')
        ->and($this->usuario->email_verified_at)->toBeNull();

    $recuperacion->refresh();
    expect($recuperacion->invalidado_en)->not->toBeNull();
});

test('patch perfil no permite modificar rol ni activo', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/perfil', [
            'name' => 'Ana Administradora',
            'rol' => 'administrador',
            'activo' => false,
        ]);

    $response->assertOk();

    $this->usuario->refresh();
    expect($this->usuario->rol)->toBe('usuario')
        ->and($this->usuario->activo)->toBeTrue();
});

test('subida exitosa de fotografia devuelve url publica absoluta y limpia imagen previa', function () {
    Storage::disk('public')->put('perfiles/antigua.jpg', 'antigua');
    $this->usuario->update(['foto_perfil' => 'perfiles/antigua.jpg']);

    $foto = UploadedFile::fake()->create('avatar.png', 300, 'image/png');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/perfil/foto', [
            'foto_perfil' => $foto,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['mensaje', 'foto_perfil_url']);

    $this->usuario->refresh();
    expect($this->usuario->foto_perfil)->not->toBe('perfiles/antigua.jpg')
        ->and(Storage::disk('public')->exists($this->usuario->foto_perfil))->toBeTrue()
        ->and(Storage::disk('public')->exists('perfiles/antigua.jpg'))->toBeFalse();

    expect($response->json('foto_perfil_url'))->toContain('http');
});

test('subida de foto rechaza formatos no permitidos y tamano mayor a 2mb', function () {
    $archivoPdf = UploadedFile::fake()->create('documento.pdf', 500, 'application/pdf');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/perfil/foto', [
            'foto_perfil' => $archivoPdf,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['foto_perfil']);

    $archivoPesado = UploadedFile::fake()->create('pesada.jpg', 3000, 'image/jpeg');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/perfil/foto', [
            'foto_perfil' => $archivoPesado,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['foto_perfil']);
});

test('cambio de contrasena revoca tokens sesiones web remember_token e invalida recuperaciones', function () {
    // 1. Token adicional en otro dispositivo
    $otroToken = $this->usuario->createToken('Tablet')->plainTextToken;

    // 2. Sesión web simulada
    DB::table('sessions')->insert([
        'id' => 'sesion_123',
        'user_id' => $this->usuario->id,
        'payload' => 'payload_dummy',
        'last_activity' => time(),
    ]);

    // 3. Recordarme
    $this->usuario->setRememberToken('token_recordarme_123');
    $this->usuario->save();

    // 4. Recuperación previa
    $recuperacion = RecuperacionPassword::create([
        'user_id' => $this->usuario->id,
        'email' => $this->usuario->email,
        'codigo_hash' => Hash::make('654321'),
        'intentos' => 0,
        'codigo_expira_en' => now()->addMinutes(10),
    ]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->putJson('/api/v1/perfil/password', [
            'current_password' => 'PasswordSegura2026*',
            'password' => 'NuevaPasswordSegurisima2026*',
            'password_confirmation' => 'NuevaPasswordSegurisima2026*',
        ]);

    $response->assertOk()
        ->assertJson([
            'mensaje' => 'Contraseña actualizada exitosamente. Todas las sesiones y tokens han sido revocados. Debe iniciar sesión nuevamente.',
        ]);

    $this->usuario->refresh();
    expect(Hash::check('NuevaPasswordSegurisima2026*', $this->usuario->password))->toBeTrue()
        ->and($this->usuario->remember_token)->toBeNull();

    // Sesión web eliminada
    expect(DB::table('sessions')->where('user_id', $this->usuario->id)->count())->toBe(0);

    // Tokens revocados: ni el actual ni el secundario deben funcionar
    expect($this->usuario->tokens()->count())->toBe(0);

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/perfil')
        ->assertStatus(401);

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', 'Bearer '.$otroToken)
        ->getJson('/api/v1/perfil')
        ->assertStatus(401);

    // Recuperación anterior invalidada
    $recuperacion->refresh();
    expect($recuperacion->invalidado_en)->not->toBeNull();
});
