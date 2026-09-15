<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogoCategoriaController;
use App\Http\Controllers\Api\V1\CatalogoIngredienteController;
use App\Http\Controllers\Api\V1\CatalogoRecetaController;
use App\Http\Controllers\Api\V1\FavoritoController;
use App\Http\Controllers\Api\V1\MisRecetasController;
use App\Http\Controllers\Api\V1\MisSolicitudesController;
use App\Http\Controllers\Api\V1\PerfilController;
use App\Http\Controllers\Api\V1\RecuperacionPasswordController;
use App\Http\Controllers\Api\V1\ValoracionController;
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
    Route::post('/recetas/verificar-disponibilidad', [FavoritoController::class, 'verificarDisponibilidad'])
        ->middleware('throttle:api-disponibilidad')->name('recetas.verificar-disponibilidad');
    Route::get('/recetas/{receta}', [CatalogoRecetaController::class, 'show'])->name('recetas.show');
    Route::get('/recetas/{receta}/imagen', [CatalogoRecetaController::class, 'imagen'])->name('recetas.imagen');

    // Rutas públicas de autenticación y recuperación de contraseña
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/registro', [AuthController::class, 'registro'])->middleware('throttle:api-registro')->name('registro');
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
                ->middleware('throttle:api-recuperacion-restablecer')
                ->name('restablecer');
        });
    });

    // Rutas protegidas para cuentas activas (tokens móviles de Sanctum)
    Route::middleware(['auth:sanctum', 'api.activo', 'throttle:api-escrituras'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::prefix('perfil')->name('perfil.')->group(function () {
            Route::get('/', [PerfilController::class, 'show'])->name('show');
            Route::patch('/', [PerfilController::class, 'update'])->name('update');
            Route::get('/foto', [PerfilController::class, 'foto'])->name('foto');
            Route::post('/foto', [PerfilController::class, 'actualizarFoto'])->middleware('throttle:api-imagenes')->name('foto.subir');
            Route::put('/password', [PerfilController::class, 'cambiarPassword'])->name('password');
        });

        Route::prefix('mis-recetas')->name('mis-recetas.')->group(function () {
            Route::get('/', [MisRecetasController::class, 'index'])->name('index');
            Route::post('/', [MisRecetasController::class, 'store'])->middleware('throttle:api-imagenes')->name('store');
            Route::get('/{receta}', [MisRecetasController::class, 'show'])->name('show');
            Route::match(['put', 'patch', 'post'], '/{receta}', [MisRecetasController::class, 'update'])->middleware('throttle:api-imagenes')->name('update');
            Route::delete('/{receta}', [MisRecetasController::class, 'destroy'])->name('destroy');
            Route::get('/{receta}/imagen', [MisRecetasController::class, 'imagen'])->name('imagen');
            Route::post('/{receta}/imagen', [MisRecetasController::class, 'subirImagen'])->middleware('throttle:api-imagenes')->name('subir-imagen');
            Route::post('/{receta}/publicar', [MisRecetasController::class, 'publicar'])->name('publicar');
            Route::post('/{receta}/corregir', [MisRecetasController::class, 'corregir'])->name('corregir');
        });

        Route::prefix('mis-solicitudes')->name('mis-solicitudes.')->group(function () {
            Route::get('/', [MisSolicitudesController::class, 'index'])->name('index');
            Route::get('/{solicitud}', [MisSolicitudesController::class, 'show'])->name('show');
            Route::get('/{solicitud}/imagen', [MisSolicitudesController::class, 'imagen'])->name('imagen');
            Route::post('/{solicitud}/cancelar', [MisSolicitudesController::class, 'cancelar'])->name('cancelar');
        });

        Route::prefix('favoritos')->name('favoritos.')->group(function () {
            Route::get('/', [FavoritoController::class, 'index'])->name('index');
            Route::post('/verificar-disponibilidad', [FavoritoController::class, 'verificarDisponibilidad'])->name('verificar-disponibilidad');
            Route::post('/{receta}', [FavoritoController::class, 'store'])->name('store');
            Route::delete('/{receta}', [FavoritoController::class, 'destroy'])->name('destroy');
            Route::get('/{receta}/estado', [FavoritoController::class, 'estado'])->name('estado');
        });

        Route::prefix('recetas/{receta}/valoracion')->name('recetas.valoracion.')->group(function () {
            Route::get('/', [ValoracionController::class, 'show'])->name('show');
            Route::match(['put', 'post'], '/', [ValoracionController::class, 'store'])->name('store');
        });
    });
});
