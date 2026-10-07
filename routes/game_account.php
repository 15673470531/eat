<?php

use App\Http\Controllers\Api\GameAccount\AccountController;
use App\Http\Controllers\Api\GameAccount\SaveController;
use App\Http\Middleware\GamePlayerSession;
use App\Http\Middleware\UpdateLastActiveAt;
use Illuminate\Support\Facades\Route;

Route::prefix('game-account')->withoutMiddleware(UpdateLastActiveAt::class)->group(function () {
    Route::post('login', [AccountController::class, 'login'])->middleware('throttle:15,1,game-login');
    Route::middleware([GamePlayerSession::class, 'throttle:60,1,game-save'])->group(function () {
        Route::get('me', [AccountController::class, 'show']);
        Route::post('onboarding', [SaveController::class, 'onboarding']);
        Route::put('save', [SaveController::class, 'update']);
    });
});
