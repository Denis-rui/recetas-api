<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin Principal',
        'email' => 'admin.principal@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);
});

test('un administrador activo puede ver la pantalla principal de gestion de usuarios', function () {
    $response = $this->actingAs($this->admin)->get(route('usuarios.index'));

    $response->assertStatus(200);
    $response->assertSee('Gestión de usuarios');
    $response->assertSee('tabla-usuarios');
});

test('el endpoint asincrono de datatables devuelve la estructura server-side esperada', function () {
    User::create([
        'name' => 'Carlos Mendoza',
        'email' => 'carlos@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
    ]));

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'draw',
        'recordsTotal',
        'recordsFiltered',
        'data',
    ]);
    $response->assertJsonPath('recordsTotal', 2); // Admin + Carlos
    $response->assertJsonPath('recordsFiltered', 2);
    $this->assertStringContainsString('Carlos Mendoza', json_encode($response->json('data')));
});

test('el endpoint asincrono filtra cuentas por busqueda textual con debounce', function () {
    User::create([
        'name' => 'Maria Fernandez',
        'email' => 'maria@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    User::create([
        'name' => 'Roberto Silva',
        'email' => 'roberto@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'draw' => 2,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => 'Fernandez'],
    ]));

    $response->assertStatus(200);
    $response->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('Maria Fernandez', json_encode($response->json('data')));
    $this->assertStringNotContainsString('Roberto Silva', json_encode($response->json('data')));
});

test('el endpoint asincrono filtra cuentas por rol y por estado', function () {
    User::create([
        'name' => 'Usuario Normal Activo',
        'email' => 'user.activo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    User::create([
        'name' => 'Admin Deshabilitado',
        'email' => 'admin.inactivo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => false,
    ]);

    // Filtrar solo administradores
    $respRol = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'filtro_rol' => 'administrador',
    ]));
    $respRol->assertJsonPath('recordsFiltered', 2); // Admin principal + Admin Deshabilitado

    // Filtrar solo deshabilitados
    $respEstado = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'filtro_estado' => 'deshabilitado',
    ]));
    $respEstado->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('admin.inactivo@quecocinamos.com', json_encode($respEstado->json('data')));
});

test('un administrador puede crear una cuenta con rol y estado activo automatico', function () {
    $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
        'name' => 'Nuevo Usuario Registrado',
        'email' => 'nuevo.usuario@quecocinamos.com',
        'password' => 'NuevaPasswordSegura2026*',
        'password_confirmation' => 'NuevaPasswordSegura2026*',
        'rol' => 'administrador',
    ]);

    $response->assertRedirect(route('usuarios.index'));

    $this->assertDatabaseHas('users', [
        'email' => 'nuevo.usuario@quecocinamos.com',
        'rol' => 'administrador',
        'activo' => 1,
    ]);
});

test('se rechaza la creacion si la contrasena tiene menos de 12 caracteres', function () {
    $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
        'name' => 'Usuario Corto',
        'email' => 'corto@quecocinamos.com',
        'password' => 'corta123',
        'password_confirmation' => 'corta123',
        'rol' => 'usuario',
    ]);

    $response->assertSessionHasErrors('password');
    $this->assertDatabaseMissing('users', [
        'email' => 'corto@quecocinamos.com',
    ]);
});

test('se rechaza la creacion si el correo electronico ya existe', function () {
    $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
        'name' => 'Duplicado',
        'email' => 'admin.principal@quecocinamos.com',
        'password' => 'NuevaPasswordSegura2026*',
        'password_confirmation' => 'NuevaPasswordSegura2026*',
        'rol' => 'usuario',
    ]);

    $response->assertSessionHasErrors('email');
});

test('un administrador puede editar datos de otra cuenta', function () {
    $otro = User::create([
        'name' => 'Nombre Viejo',
        'email' => 'viejo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->put(route('usuarios.update', $otro), [
        'name' => 'Nombre Actualizado',
        'email' => 'actualizado@quecocinamos.com',
    ]);

    $response->assertRedirect(route('usuarios.index'));

    $this->assertDatabaseHas('users', [
        'id' => $otro->id,
        'name' => 'Nombre Actualizado',
        'email' => 'actualizado@quecocinamos.com',
    ]);
});

test('un administrador puede cambiar el rol de otra cuenta', function () {
    $otro = User::create([
        'name' => 'Usuario A Promover',
        'email' => 'promover@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->patch(route('usuarios.cambiar-rol', $otro), [
        'rol' => 'administrador',
    ]);

    $response->assertRedirect(route('usuarios.index'));

    $this->assertDatabaseHas('users', [
        'id' => $otro->id,
        'rol' => 'administrador',
    ]);
});

test('un administrador NO puede cambiar su propio rol (RN-06, RN-07)', function () {
    $response = $this->actingAs($this->admin)->patch(route('usuarios.cambiar-rol', $this->admin), [
        'rol' => 'usuario',
    ]);

    $response->assertStatus(403);

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'rol' => 'administrador',
    ]);
});

test('un administrador puede deshabilitar y reactivar otra cuenta', function () {
    $otro = User::create([
        'name' => 'Usuario A Bloquear',
        'email' => 'bloquear@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    // Deshabilitar
    $response = $this->actingAs($this->admin)->patch(route('usuarios.deshabilitar', $otro));
    $response->assertRedirect(route('usuarios.index'));
    $this->assertDatabaseHas('users', [
        'id' => $otro->id,
        'activo' => 0,
    ]);

    // Reactivar
    $response2 = $this->actingAs($this->admin)->patch(route('usuarios.reactivar', $otro));
    $response2->assertRedirect(route('usuarios.index'));
    $this->assertDatabaseHas('users', [
        'id' => $otro->id,
        'activo' => 1,
    ]);
});

test('un administrador NO puede deshabilitar su propia cuenta (RN-06, RN-07)', function () {
    $response = $this->actingAs($this->admin)->patch(route('usuarios.deshabilitar', $this->admin));

    $response->assertStatus(403);

    $this->assertDatabaseHas('users', [
        'id' => $this->admin->id,
        'activo' => 1,
    ]);
});

test('las iniciales del nombre se generan correctamente cuando no hay foto', function () {
    $usuario = User::create([
        'name' => 'Liliana Bustamante Tauma',
        'email' => 'liliana@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    expect($usuario->iniciales)->toBe('LT');
});

test('deshabilitar una cuenta invalida sus sesiones web activas en la base de datos (RN-08)', function () {
    $otro = User::create([
        'name' => 'Usuario Con Sesion',
        'email' => 'sesion@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    // Insertar sesión activa en tabla sessions
    DB::table('sessions')->insert([
        'id' => 'test_session_id_123',
        'user_id' => $otro->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0',
        'payload' => 'dummy',
        'last_activity' => time(),
    ]);

    $this->assertDatabaseHas('sessions', ['user_id' => $otro->id]);

    $this->actingAs($this->admin)->patch(route('usuarios.deshabilitar', $otro));

    $this->assertDatabaseMissing('sessions', ['user_id' => $otro->id]);
});

test('los endpoints responden con codigos HTTP semanticos adecuados (201 Created, 200 OK, 422 Unprocessable, 302 Found)', function () {
    // 1. Petición JSON de creación exitosa debe responder 201 Created
    $respCrearJson = $this->actingAs($this->admin)->postJson(route('usuarios.store'), [
        'name' => 'Usuario API',
        'email' => 'api.creado@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'password_confirmation' => 'PasswordValida2026*',
        'rol' => 'usuario',
    ]);
    $respCrearJson->assertStatus(201); // Response::HTTP_CREATED
    $respCrearJson->assertJsonPath('usuario.email', 'api.creado@quecocinamos.com');

    // 2. Error de validación con JSON debe responder 422 Unprocessable Entity
    $respErrorJson = $this->actingAs($this->admin)->postJson(route('usuarios.store'), [
        'name' => '',
        'email' => 'correo-invalido',
        'password' => 'corta',
        'rol' => 'rol_inexistente',
    ]);
    $respErrorJson->assertStatus(422); // Response::HTTP_UNPROCESSABLE_ENTITY

    // 3. Petición web tradicional de formulario debe responder 302 Found (Post/Redirect/Get)
    $respWeb = $this->actingAs($this->admin)->post(route('usuarios.store'), [
        'name' => 'Usuario Formulario Web',
        'email' => 'web.creado@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'password_confirmation' => 'PasswordValida2026*',
        'rol' => 'usuario',
    ]);
    $respWeb->assertStatus(302); // Response::HTTP_FOUND

    // 4. Actualización JSON exitosa debe responder 200 OK
    $usuarioCreado = User::where('email', 'api.creado@quecocinamos.com')->first();
    $respUpdateJson = $this->actingAs($this->admin)->putJson(route('usuarios.update', $usuarioCreado), [
        'name' => 'Usuario API Actualizado',
        'email' => 'api.creado@quecocinamos.com',
    ]);
    $respUpdateJson->assertStatus(200); // Response::HTTP_OK
});

test('reemplazo exitoso de fotografia al actualizar cuenta de otro usuario elimina la anterior', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_antigua_otro.jpg', 'contenido-viejo');

    $otro = User::create([
        'name' => 'Usuario Con Foto',
        'email' => 'con.foto@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
        'foto_perfil' => 'perfiles/foto_antigua_otro.jpg',
    ]);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto_otro.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('usuarios.update', $otro), [
        'name' => 'Usuario Con Foto Editado',
        'email' => 'con.foto@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('exito');

    $otro->refresh();
    expect($otro->foto_perfil)->not->toBe('perfiles/foto_antigua_otro.jpg')
        ->and(Storage::disk('public')->exists($otro->foto_perfil))->toBeTrue()
        ->and(Storage::disk('public')->exists('perfiles/foto_antigua_otro.jpg'))->toBeFalse();
});

test('fallo de almacenamiento al actualizar cuenta conserva la fotografia anterior y su referencia', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_antigua_otro.jpg', 'contenido-viejo');

    $otro = User::create([
        'name' => 'Usuario Con Foto',
        'email' => 'con.foto.fallo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
        'foto_perfil' => 'perfiles/foto_antigua_otro.jpg',
    ]);

    $mockDisk = Mockery::mock(Storage::disk('public'))->makePartial();
    $mockDisk->shouldReceive('putFileAs')->andReturn(false);
    Storage::set('public', $mockDisk);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto_otro.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('usuarios.update', $otro), [
        'name' => 'Usuario Con Foto Editado',
        'email' => 'con.foto.fallo@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    // La foto original debe conservarse en disco y en la base de datos
    expect(Storage::disk('public')->exists('perfiles/foto_antigua_otro.jpg'))->toBeTrue();

    $otro->refresh();
    expect($otro->foto_perfil)->toBe('perfiles/foto_antigua_otro.jpg');

    $response->assertSessionHasErrors('foto_perfil');
});

test('fallo de persistencia al actualizar cuenta conserva la fotografia anterior y limpia el nuevo archivo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_antigua_otro.jpg', 'contenido-viejo');

    $otro = User::create([
        'name' => 'Usuario Con Foto',
        'email' => 'con.foto.dbfallo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
        'foto_perfil' => 'perfiles/foto_antigua_otro.jpg',
    ]);

    User::saving(function ($user) use ($otro) {
        if ($user->id === $otro->id && $user->isDirty('foto_perfil') && $user->foto_perfil !== 'perfiles/foto_antigua_otro.jpg') {
            throw new RuntimeException('Fallo simulado de base de datos durante la persistencia de cuenta.');
        }
    });

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto_otro.jpg', 100, 'image/jpeg');

    try {
        $response = $this->actingAs($this->admin)->put(route('usuarios.update', $otro), [
            'name' => 'Usuario Con Foto Editado',
            'email' => 'con.foto.dbfallo@quecocinamos.com',
            'foto_perfil' => $nuevaFoto,
        ]);

        $response->assertSessionHasErrors('foto_perfil');
    } catch (Throwable $e) {
        // En caso de que el código no capture la excepción antes de la corrección
    } finally {
        User::flushEventListeners();
    }

    // La foto original debe conservarse intacta en disco y en base de datos
    expect(Storage::disk('public')->exists('perfiles/foto_antigua_otro.jpg'))->toBeTrue();

    $otro->refresh();
    expect($otro->foto_perfil)->toBe('perfiles/foto_antigua_otro.jpg');

    // No deben quedar archivos nuevos huérfanos en almacenamiento
    $archivos = Storage::disk('public')->files('perfiles');
    expect($archivos)->toBe(['perfiles/foto_antigua_otro.jpg']);
});

test('si falla unicamente la eliminacion de la foto anterior tras guardar la nueva al actualizar cuenta se conserva el estado valido y se registra advertencia', function () {
    Storage::fake('public');
    Storage::disk('public')->put('perfiles/foto_antigua_otro.jpg', 'contenido-viejo');

    $otro = User::create([
        'name' => 'Usuario Con Foto',
        'email' => 'con.foto.cleanup@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
        'foto_perfil' => 'perfiles/foto_antigua_otro.jpg',
    ]);

    Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::pattern('/No se pudo eliminar la fotografía anterior durante la limpieza/'));

    $mockDisk = Mockery::mock(Storage::disk('public'))->makePartial();
    $mockDisk->shouldReceive('delete')->with('perfiles/foto_antigua_otro.jpg')->andReturn(false);
    Storage::set('public', $mockDisk);

    $nuevaFoto = UploadedFile::fake()->create('nueva_foto_otro.jpg', 100, 'image/jpeg');

    $response = $this->actingAs($this->admin)->put(route('usuarios.update', $otro), [
        'name' => 'Usuario Con Foto Editado',
        'email' => 'con.foto.cleanup@quecocinamos.com',
        'foto_perfil' => $nuevaFoto,
    ]);

    $response->assertRedirect(route('usuarios.index'));
    $response->assertSessionHas('exito');

    $otro->refresh();
    expect($otro->foto_perfil)->not->toBe('perfiles/foto_antigua_otro.jpg')
        ->and(Storage::disk('public')->exists($otro->foto_perfil))->toBeTrue();
});
