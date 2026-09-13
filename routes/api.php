<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogoCategoriaController;
use App\Http\Controllers\Api\V1\CatalogoIngredienteController;
use App\Http\Controllers\Api\V1\CatalogoRecetaController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\RecuperacionPasswordController;
use App\Http\Resources\Api\V1\PerfilResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Ruta protegida anterior ajustada para validar cuenta activa y no exponer atributos internos
Route::get('/user', function (Request $request) {
    return response()->json([
        'data' => new PerfilResource($request->user()),
    ]);
})->middleware(['auth:sanctum', 'api.activo']);

Route::prefix('v1')->name('api.v1.')->group(function () {
    // Catálogo público accesible sin autenticación
    Route::get('/categorias', [CatalogoCategoriaController::class, 'index'])->name('categorias.index');
    Route::get('/ingredientes', [CatalogoIngredienteController::class, 'index'])->name('ingredientes.index');
    Route::get('/recetas', [CatalogoRecetaController::class, 'index'])->name('recetas.index');
    Route::get('/recetas/{receta}', [CatalogoRecetaController::class, 'show'])->name('recetas.show');
    Route::get('/recetas/{receta}/imagen', [CatalogoRecetaController::class, 'imagen'])->name('recetas.imagen');

    // Rutas públicas de autenticación y recuperación de contraseña
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/registro', [AuthController::class, 'registro'])->name('registro');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:api-login')
            ->name('login');

        Route::prefix('recuperacion')->name('recuperacion.')->group(function () {
            Route::post('/solicitar', [RecuperacionPasswordController::class, 'solicitar'])
                ->middleware('throttle:api-recuperacion-solicitar')
                ->name('solicitar');
            Route::post('/verificar', [RecuperacionPasswordController::class, 'verificar'])
                ->middleware('throttle:api-recuperacion-verificar')
                ->name('verificar');
            Route::post('/restablecer', [RecuperacionPasswordController::class, 'restablecer'])
                ->name('restablecer');
        });
    });

    // Rutas protegidas para cuentas activas (tokens móviles de Sanctum)
    Route::middleware(['auth:sanctum', 'api.activo'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('perfil')->name('perfil.')->group(function () {
            Route::get('/', [PerfilController::class, 'show'])->name('show');
            Route::patch('/', [PerfilController::class, 'update'])->name('update');
            Route::post('/foto', [PerfilController::class, 'actualizarFoto'])->name('foto');
            Route::put('/password', [PerfilController::class, 'cambiarPassword'])->name('password');
        });
    });
});
