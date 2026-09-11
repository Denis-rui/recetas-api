<?php

use App\Models\User;

test('se puede visualizar la pantalla de inicio de sesion', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertSee('Iniciar sesión');
});

test('un administrador activo puede iniciar sesion correctamente', function () {
    $admin = User::create([
        'name' => 'Admin Prueba',
        'email' => 'admin.test@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $response = $this->post(route('login'), [
        'email' => 'admin.test@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
    ]);

    $response->assertRedirect(route('usuarios.index'));
    $this->assertAuthenticatedAs($admin);
});

test('se rechazan credenciales incorrectas', function () {
    User::create([
        'name' => 'Admin Prueba',
        'email' => 'admin.test@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => 'admin.test@quecocinamos.com',
        'password' => 'PasswordIncorrecta*',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('un usuario normal no puede acceder al panel web aunque sus credenciales sean validas', function () {
    User::create([
        'name' => 'Usuario Normal',
        'email' => 'user.normal@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'usuario',
        'activo' => true,
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => 'user.normal@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('una cuenta deshabilitada no puede acceder al panel web', function () {
    User::create([
        'name' => 'Admin Deshabilitado',
        'email' => 'admin.bloqueado@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => false,
    ]);

    $response = $this->from(route('login'))->post(route('login'), [
        'email' => 'admin.bloqueado@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('un administrador puede cerrar sesion correctamente', function () {
    $admin = User::create([
        'name' => 'Admin Prueba',
        'email' => 'admin.logout@quecocinamos.com',
        'password' => 'PasswordSegura2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $response = $this->actingAs($admin)->post(route('logout'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
