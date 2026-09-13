<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\PerfilController;
use App\Http\Controllers\Web\UsuarioController;
use Illuminate\Support\Facades\Route;

// Rutas de acceso (invitados)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'mostrarLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

// Rutas protegidas para administradores activos
Route::middleware(['auth', 'admin.activo'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', function () {
        return redirect()->route('usuarios.index');
    });

    // Gestión de cuentas de usuario
    Route::get('/usuarios', [UsuarioController::class, 'index'])
        ->middleware('throttle:listado-usuarios')
        ->name('usuarios.index');
    Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::patch('/usuarios/{usuario}/cambiar-rol', [UsuarioController::class, 'cambiarRol'])->name('usuarios.cambiar-rol');
    Route::patch('/usuarios/{usuario}/deshabilitar', [UsuarioController::class, 'deshabilitar'])->name('usuarios.deshabilitar');
    Route::patch('/usuarios/{usuario}/reactivar', [UsuarioController::class, 'reactivar'])->name('usuarios.reactivar');

    // Mi perfil
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'cambiarPassword'])->name('perfil.password');
});
