<?php

use App\Http\Controllers\Api\V1\CatalogoCategoriaController;
use App\Http\Controllers\Api\V1\CatalogoIngredienteController;
use App\Http\Controllers\Api\V1\CatalogoRecetaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('/categorias', [CatalogoCategoriaController::class, 'index'])->name('categorias.index');
    Route::get('/ingredientes', [CatalogoIngredienteController::class, 'index'])->name('ingredientes.index');
    Route::get('/recetas', [CatalogoRecetaController::class, 'index'])->name('recetas.index');
    Route::get('/recetas/{receta}', [CatalogoRecetaController::class, 'show'])->name('recetas.show');
    Route::get('/recetas/{receta}/imagen', [CatalogoRecetaController::class, 'imagen'])->name('recetas.imagen');
});
