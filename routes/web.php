<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// The chapter report is intentionally public and read-only. Management stays in /admin/manage.
Route::get('/admin', \App\Http\Controllers\PublicChapterMonitorController::class)->name('chapter-monitor.public');
Route::get('/admin/chapter-monitor', \App\Http\Controllers\PublicChapterMonitorController::class);
Route::redirect('/admin/login', '/admin');
