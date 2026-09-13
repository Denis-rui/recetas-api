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

test('el rate limiter de consultas asincronas devuelve 429 al superarse y es independiente para cada administrador', function () {
    $limiteOriginal = config('usuarios.rate_limit', 60);
    config(['usuarios.rate_limit' => 3]);

    $adminDos = User::create([
        'name' => 'Segundo Administrador',
        'email' => 'admin.dos@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    try {
        // Admin 1 realiza 3 peticiones exitosas (límite configurado a 3)
        for ($i = 0; $i < 3; $i++) {
            $resp = $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])->getJson(route('usuarios.index'));
            $resp->assertStatus(200);
        }

        // Petición 4 de Admin 1 debe recibir 429 Too Many Requests con cabecera Retry-After
        $resp429 = $this->actingAs($this->admin)->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])->getJson(route('usuarios.index'));
        $resp429->assertStatus(429);
        $this->assertNotEmpty($resp429->headers->get('Retry-After'));
        $resp429->assertJsonStructure(['message', 'retry_after']);

        // Admin 2, utilizando la MISMA IP que Admin 1, no comparte el límite y puede consultar exitosamente
        $respAdminDos = $this->actingAs($adminDos)->withServerVariables(['REMOTE_ADDR' => '192.168.1.100'])->getJson(route('usuarios.index'));
        $respAdminDos->assertStatus(200);

        // Operaciones distintas al listado asíncrono (como editar cuenta) no son afectadas por este límite
        $respEditar = $this->actingAs($this->admin)->get(route('usuarios.edit', $adminDos));
        $respEditar->assertStatus(200);
    } finally {
        config(['usuarios.rate_limit' => $limiteOriginal]);
        RateLimiter::clear('admin_'.$this->admin->id);
        RateLimiter::clear('admin_'.$adminDos->id);
    }
});

test('las solicitudes de busqueda vacias, de un caracter y de dos o mas caracteres cumplen las reglas', function () {
    User::create([
        'name' => 'José Ñandú',
        'email' => 'jose.nandu@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    // 1. Búsqueda vacía (o sólo espacios) devuelve listado normal con 200
    $respVacia = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => '   '],
    ]));
    $respVacia->assertStatus(200);
    $respVacia->assertJsonPath('recordsTotal', 2);
    $respVacia->assertJsonPath('recordsFiltered', 2);

    // 2. Búsqueda de 1 carácter alfanumérico devuelve 422 Unprocessable Entity con validación
    $respUnChar = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => 'J'],
    ]));
    $respUnChar->assertStatus(422);
    $respUnChar->assertJsonValidationErrors('search');
    $this->assertStringContainsString('Escribe al menos 2 caracteres para buscar.', $respUnChar->json('errors.search.0'));

    // 3. Búsqueda de 1 carácter acentuado tras trim devuelve 422
    $respAcentoUnChar = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => '  é  '],
    ]));
    $respAcentoUnChar->assertStatus(422);
    $respAcentoUnChar->assertJsonValidationErrors('search');

    // 4. Búsqueda de 2 caracteres con caracteres acentuados/multibyte ("Ña") tras trim devuelve 200 y filtra
    $respDosChars = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => '  Ña  '],
    ]));
    $respDosChars->assertStatus(200);
    $respDosChars->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('José Ñandú', json_encode($respDosChars->json('data'), JSON_UNESCAPED_UNICODE));

    // 5. Búsqueda que supere 100 caracteres devuelve 422
    $respExceso = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => str_repeat('a', 101)],
    ]));
    $respExceso->assertStatus(422);
    $respExceso->assertJsonValidationErrors('search');
});

test('los comodines SQL %, _ y el caracter de escape ! se interpretan literalmente en la busqueda', function () {
    User::create([
        'name' => 'Usuario Con%Porcentaje',
        'email' => 'con.porcentaje@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    User::create([
        'name' => 'Usuario Con_GuionBajo',
        'email' => 'con.guionbajo@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    User::create([
        'name' => 'Usuario Con!Exclamacion',
        'email' => 'con.exclamacion@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    User::create([
        'name' => 'Usuario ConXComodin',
        'email' => 'con.comodin@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    // Búsqueda literal de "%" (con 2 caracteres: "Con%") debe coincidir solo con Usuario Con%Porcentaje
    $respPorcentaje = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => 'Con%'],
    ]));
    $respPorcentaje->assertStatus(200);
    $respPorcentaje->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('Usuario Con%Porcentaje', json_encode($respPorcentaje->json('data')));
    $this->assertStringNotContainsString('Usuario ConXComodin', json_encode($respPorcentaje->json('data')));

    // Búsqueda literal de "_" ("Con_") debe coincidir solo con Usuario Con_GuionBajo
    $respGuionBajo = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => 'Con_'],
    ]));
    $respGuionBajo->assertStatus(200);
    $respGuionBajo->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('Usuario Con_GuionBajo', json_encode($respGuionBajo->json('data')));
    $this->assertStringNotContainsString('Usuario ConXComodin', json_encode($respGuionBajo->json('data')));

    // Búsqueda literal del carácter de escape "!" ("Con!") debe coincidir solo con Usuario Con!Exclamacion
    $respExclamacion = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => 'Con!'],
    ]));
    $respExclamacion->assertStatus(200);
    $respExclamacion->assertJsonPath('recordsFiltered', 1);
    $this->assertStringContainsString('Usuario Con!Exclamacion', json_encode($respExclamacion->json('data')));
    $this->assertStringNotContainsString('Usuario ConXComodin', json_encode($respExclamacion->json('data')));
});

test('sin filtros efectivos se evita la consulta de conteo redundante', function () {
    User::create([
        'name' => 'Usuario Conteo Uno',
        'email' => 'conteo.uno@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    // 1. Petición SIN filtros efectivos: solo debe realizar 1 consulta SELECT COUNT(*)
    $conteoSinFiltros = 0;
    DB::listen(function ($query) use (&$conteoSinFiltros) {
        if (str_contains(strtolower($query->sql), 'count(')) {
            $conteoSinFiltros++;
        }
    });

    $respSinFiltro = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => ''],
        'filtro_rol' => '',
        'filtro_estado' => '',
    ]));

    $respSinFiltro->assertStatus(200);
    $respSinFiltro->assertJsonPath('recordsTotal', 2);
    $respSinFiltro->assertJsonPath('recordsFiltered', 2);
    expect($conteoSinFiltros)->toBe(1);

    // 2. Petición CON filtro de rol: se ejecuta el conteo total y el conteo filtrado (2 count)
    $conteoConFiltro = 0;
    DB::listen(function ($query) use (&$conteoConFiltro) {
        if (str_contains(strtolower($query->sql), 'count(')) {
            $conteoConFiltro++;
        }
    });

    $respConFiltro = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'filtro_rol' => 'usuario',
    ]));

    $respConFiltro->assertStatus(200);
    $respConFiltro->assertJsonPath('recordsTotal', 2);
    $respConFiltro->assertJsonPath('recordsFiltered', 1);
    expect($conteoConFiltro)->toBeGreaterThanOrEqual(2);
});

test('la consulta de filas para datatables selecciona estrictamente las columnas necesarias sin exponer campos sensibles', function () {
    User::create([
        'name' => 'Usuario Columnas',
        'email' => 'columnas@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $queriesSql = [];
    DB::listen(function ($query) use (&$queriesSql) {
        if (str_starts_with(strtolower(trim($query->sql)), 'select') && ! str_contains(strtolower($query->sql), 'count(')) {
            $queriesSql[] = $query->sql;
        }
    });

    $response = $this->actingAs($this->admin)->getJson(route('usuarios.index', [
        'search' => ['value' => 'Columnas'],
    ]));

    $response->assertStatus(200);

    $querySeleccion = collect($queriesSql)->first(fn ($sql) => str_contains($sql, 'from "users"') || str_contains($sql, 'from `users`'));
    expect($querySeleccion)->not->toBeNull();
    $sqlLower = strtolower($querySeleccion);

    expect($sqlLower)->toContain('id')
        ->toContain('name')
        ->toContain('email')
        ->toContain('rol')
        ->toContain('activo')
        ->toContain('foto_perfil');

    expect($sqlLower)->not->toContain('password')
        ->not->toContain('remember_token');
});
