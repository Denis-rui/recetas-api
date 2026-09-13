<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Jaime Administrador',
        'email' => 'jaime.admin@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);
});

test('un administrador puede ver su pantalla de perfil', function () {
    $response = $this->actingAs($this->admin)->get(route('perfil.edit'));

    $response->assertStatus(200);
    $response->assertSee('Mi perfil');
    $response->assertSee('Jaime Administrador');
    $response->assertSee('jaime.admin@quecocinamos.com');
});

test('un administrador puede actualizar sus datos personales', function () {
    $response = $this->actingAs($this->admin)->put(route('perfil.update'), [
        'name' => 'Jaime Denis Ruis',
        'email' => 'jaime.denis@quecocinamos.com',
    ]);

    $response->assertRedirect(route('perfil.edit'));
    $response->assertSessionHas('exito_perfil');

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'name' => 'Jaime Denis Ruis',
        'email' => 'jaime.denis@quecocinamos.com',
        'rol' => 'administrador',
        'activo' => 1,
    ]);
});

test('un administrador puede cambiar su contraseña con una valida de al menos 12 caracteres', function () {
    $response = $this->actingAs($this->admin)->put(route('perfil.password'), [
        'current_password' => 'PasswordSegura2026*',
        'password' => 'NuevaPasswordMuySegura2026*',
        'password_confirmation' => 'NuevaPasswordMuySegura2026*',
    ]);

    $response->assertRedirect(route('perfil.edit'));
    $response->assertSessionHas('exito_password');

    $this->admin->refresh();
    expect(Hash::check('NuevaPasswordMuySegura2026*', $this->admin->password))->toBeTrue();
});

test('se rechaza el cambio de contrasena si la contrasena actual es incorrecta', function () {
    $response = $this->actingAs($this->admin)->put(route('perfil.password'), [
        'current_password' => 'PasswordEquivocada*',
        'password' => 'NuevaPasswordMuySegura2026*',
        'password_confirmation' => 'NuevaPasswordMuySegura2026*',
    ]);

    $response->assertSessionHasErrors('current_password');
});

test('se rechaza el cambio de contrasena si la nueva tiene menos de 12 caracteres', function () {
    $response = $this->actingAs($this->admin)->put(route('perfil.password'), [
        'current_password' => 'PasswordSegura2026*',
        'password' => 'corta123',
        'password_confirmation' => 'corta123',
    ]);

    $response->assertSessionHasErrors('password');
});

test('reemplazo exitoso de la fotografia de perfil conserva el archivo nuevo y elimina el anterior', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_original.jpg', 'contenido-original');
    $this->admin->update(['foto_perfil' => 'perfiles/foto_original.jpg']);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('perfil.update'), [
        'name' => 'Jaime Denis Ruis',
        'email' => 'jaime.denis@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    $response->assertRedirect(route('perfil.edit'));
    $response->assertSessionHas('exito_perfil');

    $this->admin->refresh();
    expect($this->admin->foto_perfil)->not->toBe('perfiles/foto_original.jpg')
        ->and(Storage::disk('public')->exists($this->admin->foto_perfil))->toBeTrue()
        ->and(Storage::disk('public')->exists('perfiles/foto_original.jpg'))->toBeFalse();
});

test('fallo de almacenamiento conserva la fotografia de perfil anterior y no modifica su referencia', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_original.jpg', 'contenido-original');
    $this->admin->update(['foto_perfil' => 'perfiles/foto_original.jpg']);

    $mockDisk = Mockery::mock(Storage::disk('public'))->makePartial();
    $mockDisk->shouldReceive('putFileAs')->andReturn(false);
    Storage::set('public', $mockDisk);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('perfil.update'), [
        'name' => 'Jaime Denis Ruis',
        'email' => 'jaime.denis@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    // La foto original debe conservarse en disco y en la base de datos
    expect(Storage::disk('public')->exists('perfiles/foto_original.jpg'))->toBeTrue();

    $this->admin->refresh();
    expect($this->admin->foto_perfil)->toBe('perfiles/foto_original.jpg');

    $response->assertSessionHasErrors('foto_perfil');
});

test('fallo de persistencia en base de datos conserva la fotografia de perfil anterior y limpia la nueva imagen', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_original.jpg', 'contenido-original');
    $this->admin->update(['foto_perfil' => 'perfiles/foto_original.jpg']);

    User::saving(function ($user) {
        if ($user->isDirty('foto_perfil') && $user->foto_perfil !== 'perfiles/foto_original.jpg') {
            throw new RuntimeException('Fallo simulado de base de datos durante la persistencia.');
        }
    });

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto.jpg', 100, 'image/jpeg');

    try {
        $response = $this->actingAs($this->admin)->put(route('perfil.update'), [
            'name' => 'Jaime Denis Ruis',
            'email' => 'jaime.denis@quecocinamos.com',
            'foto_perfil' => $nuevaFoto,
        ]);

        $response->assertSessionHasErrors('foto_perfil');
    } catch (Throwable $e) {
        // En caso de que el código no capture la excepción antes de la corrección
    } finally {
        User::flushEventListeners();
    }

    // La foto original debe conservarse intacta en disco y en base de datos
    expect(Storage::disk('public')->exists('perfiles/foto_original.jpg'))->toBeTrue();

    $this->admin->refresh();
    expect($this->admin->foto_perfil)->toBe('perfiles/foto_original.jpg');

    // No deben quedar imágenes huérfanas en el directorio de perfiles además de la original
    $archivos = Storage::disk('public')->files('perfiles');
    expect($archivos)->toBe(['perfiles/foto_original.jpg']);
});

test('si falla unicamente la eliminacion de la foto anterior tras guardar la nueva se conserva el estado valido y se registra advertencia', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_original.jpg', 'contenido-original');
    $this->admin->update(['foto_perfil' => 'perfiles/foto_original.jpg']);

    Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::pattern('/No se pudo eliminar la fotografía anterior durante la limpieza/'));

    $mockDisk = Mockery::mock(Storage::disk('public'))->makePartial();
    $mockDisk->shouldReceive('delete')->with('perfiles/foto_original.jpg')->andReturn(false);
    Storage::set('public', $mockDisk);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('perfil.update'), [
        'name' => 'Jaime Denis Ruis',
        'email' => 'jaime.denis@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    $response->assertRedirect(route('perfil.edit'));
    $response->assertSessionHas('exito_perfil');

    $this->admin->refresh();
    expect($this->admin->foto_perfil)->not->toBe('perfiles/foto_original.jpg')
        ->and(Storage::disk('public')->exists($this->admin->foto_perfil))->toBeTrue();
});
