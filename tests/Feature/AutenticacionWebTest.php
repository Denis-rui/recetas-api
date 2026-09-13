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

test('deshabilitar una cuenta invalida el acceso persistente de recordarme y reactivar exige nuevo inicio de sesion', function () {
    $adminA = User::create([
        'name' => 'Admin Uno',
        'email' => 'admin.uno@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    $adminB = User::create([
        'name' => 'Admin Dos',
        'email' => 'admin.dos@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'rol' => 'administrador',
        'activo' => true,
    ]);

    // 1. Admin B inicia sesión con «Recordarme»
    $loginResponse = $this->post(route('login'), [
        'email' => 'admin.dos@quecocinamos.com',
        'password' => 'PasswordValida2026*',
        'remember' => '1',
    ]);

    $loginResponse->assertRedirect(route('usuarios.index'));
    $this->assertAuthenticatedAs($adminB);

    $recallerName = Auth::guard('web')->getRecallerName();
    $recallerCookie = $loginResponse->getCookie($recallerName);
    expect($recallerCookie)->not->toBeNull();

    // 2. Admin A deshabilita la cuenta de Admin B
    $this->actingAs($adminA)->patch(route('usuarios.deshabilitar', $adminB));
    $adminB->refresh();
    expect($adminB->estaActivo())->toBeFalse();

    // 3. Admin A reactiva la cuenta de Admin B
    $this->actingAs($adminA)->patch(route('usuarios.reactivar', $adminB));
    $adminB->refresh();
    expect($adminB->estaActivo())->toBeTrue();

    // 4. Se intenta acceder usando la cookie persistente antigua de Admin B en una nueva petición no autenticada
    // Vaciamos la sesión y reseteamos el guard para simular que no hay sesión activa pero sí la cookie guardada en el navegador
    $this->flushSession();
    Auth::guard('web')->forgetUser();

    $responseAccesoAntiguo = $this->withCookie($recallerCookie->getName(), $recallerCookie->getValue())
        ->get(route('usuarios.index'));

    $responseAccesoAntiguo->assertRedirect(route('login'));
    $this->assertGuest();
});
