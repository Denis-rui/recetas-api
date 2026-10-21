<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategoriaController;
use App\Http\Controllers\Web\PerfilController;
use App\Http\Controllers\Web\RecetaPublicadaController;
use App\Http\Controllers\Web\RevisionRecetaController;
use App\Http\Controllers\Web\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::pattern('usuario', '[1-9][0-9]*');
Route::pattern('solicitud', '[1-9][0-9]*');
Route::pattern('categoria', '[1-9][0-9]*');
Route::pattern('subcategoria', '[1-9][0-9]*');
Route::pattern('receta', '[1-9][0-9]*');

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
    Route::get('/revision-recetas', [RevisionRecetaController::class, 'index'])->name('revision-recetas.index');
    Route::get('/revision-recetas/{solicitud}', [RevisionRecetaController::class, 'show'])->name('revision-recetas.show');
    Route::get('/revision-recetas/{solicitud}/imagen/{version}', [RevisionRecetaController::class, 'imagen'])->whereIn('version', ['publicada', 'propuesta'])->name('revision-recetas.imagen');
    Route::post('/revision-recetas/{solicitud}/aprobar', [RevisionRecetaController::class, 'aprobar'])->name('revision-recetas.aprobar');
    Route::post('/revision-recetas/{solicitud}/rechazar', [RevisionRecetaController::class, 'rechazar'])->name('revision-recetas.rechazar');

    // Gestión de categorías y subcategorías
    Route::get('/categorias', [CategoriaController::class, 'index'])->name('categorias.index');
    Route::post('/categorias', [CategoriaController::class, 'store'])->name('categorias.store');
    Route::match(['put', 'patch'], '/categorias/{categoria}', [CategoriaController::class, 'update'])->name('categorias.update');
    Route::delete('/categorias/{categoria}', [CategoriaController::class, 'destroy'])->name('categorias.destroy');
    Route::post('/categorias/{categoria}/subcategorias', [CategoriaController::class, 'storeSubcategoria'])->name('categorias.subcategorias.store');
    Route::match(['put', 'patch'], '/categorias/{categoria}/subcategorias/{subcategoria}', [CategoriaController::class, 'updateSubcategoria'])->name('categorias.subcategorias.update');
    Route::delete('/categorias/{categoria}/subcategorias/{subcategoria}', [CategoriaController::class, 'destroySubcategoria'])->name('categorias.subcategorias.destroy');

    // Editor administrativo de recetas publicadas y vigentes
    Route::get('/recetas', [RecetaPublicadaController::class, 'index'])
        ->middleware('throttle:listado-recetas')
        ->name('recetas.index');
    Route::get('/recetas/{receta}', [RecetaPublicadaController::class, 'show'])->name('recetas.show');
    Route::get('/recetas/{receta}/editar', [RecetaPublicadaController::class, 'edit'])->name('recetas.edit');
    Route::match(['put', 'patch'], '/recetas/{receta}', [RecetaPublicadaController::class, 'update'])->name('recetas.update');
    Route::get('/recetas/{receta}/imagen', [RecetaPublicadaController::class, 'imagen'])->name('recetas.imagen');

    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'cambiarPassword'])->name('perfil.password');
});
