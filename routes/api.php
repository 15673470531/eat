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
    Route::post('user/logout', [UserController::class, 'logout']);
});
