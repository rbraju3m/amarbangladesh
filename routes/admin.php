<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BalanceController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\QuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/balance', BalanceController::class)->name('balance');

        Route::resource('questions', QuestionController::class)->except('show');
        Route::post('/questions/{question}/move/{direction}', [QuestionController::class, 'move'])
            ->whereIn('direction', ['up', 'down'])->name('questions.move');

        Route::resource('locations', LocationController::class)->only(['index', 'edit', 'update']);
    });
});
