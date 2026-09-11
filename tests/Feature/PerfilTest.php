<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

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

