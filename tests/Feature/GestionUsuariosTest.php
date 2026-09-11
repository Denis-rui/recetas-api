<?php

use App\Models\User;

beforeEach(function () {
    $this->admin = User::create([
        'name' => 'Admin Principal',
        'email' => 'admin.principal@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);
});

test('un administrador activo puede ver la tabla de usuarios', function () {
    User::create([
        'name' => 'Carlos Mendoza',
        'email' => 'carlos@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->get(route('usuarios.index'));

    $response->assertStatus(200);
    $response->assertSee('Gestión de usuarios');
    $response->assertSee('Carlos Mendoza');
    $response->assertSee('carlos@quecocinamos.com');
});

test('se pueden buscar cuentas por nombre o correo', function () {
    User::create([
        'name' => 'Maria Fernandez',
        'email' => 'maria@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->actingAs($this->admin)->get(route('usuarios.index', ['buscar' => 'Fernandez']));

    $response->assertStatus(200);
    $response->assertSee('Maria Fernandez');

    $responseVacio = $this->actingAs($this->admin)->get(route('usuarios.index', ['buscar' => 'InexistenteXYZ']));
    $responseVacio->assertSee('No se encontraron cuentas');
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
    \Illuminate\Support\Facades\DB::table('sessions')->insert([
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
