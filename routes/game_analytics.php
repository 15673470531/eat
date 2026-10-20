<?php

use App\Http\Controllers\Api\GameAnalytics\EventController;
use App\Http\Controllers\Api\GameAnalytics\ReportController;
use App\Http\Middleware\GameAnalyticsAdmin;
use App\Http\Middleware\GameAnalyticsIngress;
use App\Http\Middleware\UpdateLastActiveAt;
use Illuminate\Support\Facades\Route;

Route::prefix('game-analytics')->withoutMiddleware(UpdateLastActiveAt::class)->group(function () {
    Route::post('exploration', [\App\Http\Controllers\Api\GameAnalytics\ExplorationController::class, 'store'])->middleware(['throttle:60,1,game-exploration', GameAnalyticsIngress::class]);
    Route::post('events', [EventController::class, 'store'])
        ->middleware(['throttle:60,1,game-analytics-ingest', GameAnalyticsIngress::class]);
    Route::middleware(['auth:sanctum', GameAnalyticsAdmin::class, 'throttle:60,1,game-analytics-read'])->group(function () {
        Route::get('chapter-monitor/summary', [\App\Http\Controllers\Api\GameMonitor\ChapterReportController::class, 'summary']);
        Route::get('chapter-monitor/runs', [\App\Http\Controllers\Api\GameMonitor\ChapterReportController::class, 'runs']);
        Route::get('chapter-monitor/players/{id}', [\App\Http\Controllers\Api\GameMonitor\ChapterReportController::class, 'player'])->whereNumber('id');
        Route::get('chapter-monitor/runs/{id}', [\App\Http\Controllers\Api\GameMonitor\ChapterReportController::class, 'timeline'])->whereNumber('id');
        Route::get('summary', [ReportController::class, 'summary']);
        Route::get('events', [ReportController::class, 'events']);
    });
});
