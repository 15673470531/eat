<?php
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;
Route::get('about', [\App\Http\Controllers\Api\CatalogController::class, 'about']);
Route::get('catalog', [\App\Http\Controllers\Api\CatalogController::class, 'index']);
Route::post('user/login', [UserController::class, 'login'])->middleware('throttle:10,1');
Route::middleware('auth:sanctum')->group(function () {
    Route::get('kitchen', [\App\Http\Controllers\Api\KitchenController::class, 'show']);
    Route::put('kitchen', [\App\Http\Controllers\Api\KitchenController::class, 'update'])->middleware('throttle:120,1');
    Route::get('user/info', [UserController::class, 'info']);
    Route::post('user/update-name', [UserController::class, 'updateName']);
    Route::post('user/avatar', [UserController::class, 'avatar'])->middleware('throttle:30,1');
    Route::post('user/logout', [UserController::class, 'logout']);
    Route::prefix('admin')->middleware(\App\Http\Middleware\RequireAdmin::class)->group(function () {
        Route::get('catalog', [\App\Http\Controllers\Api\AdminCatalogController::class, 'index']);
        Route::post('ingredients', [\App\Http\Controllers\Api\AdminCatalogController::class, 'ingredient']);
        Route::put('ingredients/{id}', [\App\Http\Controllers\Api\AdminCatalogController::class, 'ingredient']);
        Route::post('recipes', [\App\Http\Controllers\Api\AdminCatalogController::class, 'recipe']);
        Route::put('recipes/{id}', [\App\Http\Controllers\Api\AdminCatalogController::class, 'recipe']);
        Route::patch('recipes/{id}/status', [\App\Http\Controllers\Api\AdminCatalogController::class, 'status']);
    });
});

// Independent mini-game telemetry; existing business routes stay unchanged.
require __DIR__.'/game_analytics.php';
