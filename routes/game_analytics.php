<?php

use App\Http\Controllers\Api\GameAnalytics\EventController;
use App\Http\Controllers\Api\GameAnalytics\ReportController;
use App\Http\Middleware\GameAnalyticsAdmin;
use App\Http\Middleware\GameAnalyticsIngress;
use App\Http\Middleware\UpdateLastActiveAt;
use Illuminate\Support\Facades\Route;

Route::prefix('game-analytics')->withoutMiddleware(UpdateLastActiveAt::class)->group(function () {
    Route::post('events', [EventController::class, 'store'])
        ->middleware(['throttle:60,1,game-analytics-ingest', GameAnalyticsIngress::class]);
    Route::middleware(['auth:sanctum', GameAnalyticsAdmin::class, 'throttle:60,1,game-analytics-read'])->group(function () {
        Route::get('summary', [ReportController::class, 'summary']);
        Route::get('events', [ReportController::class, 'events']);
    });
});
