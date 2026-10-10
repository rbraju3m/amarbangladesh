<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\BalanceController;
use App\Http\Controllers\Admin\CommunityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\LocationController;
use App\Http\Controllers\Admin\PlayController;
use App\Http\Controllers\Admin\QuestionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'show'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    });

    Route::middleware('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
        Route::put('/password', [AuthController::class, 'updatePassword'])->middleware('throttle:6,1')->name('password.update');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('/plays', PlayController::class)->name('plays');
        Route::get('/balance', BalanceController::class)->name('balance');
        Route::get('/export/{type}.csv', ExportController::class)->whereIn('type', ['results', 'daily'])->name('export');

        Route::post('/questions/reorder', [QuestionController::class, 'reorder'])->name('questions.reorder');
        Route::resource('questions', QuestionController::class)->except('show');
        Route::post('/questions/{question}/move/{direction}', [QuestionController::class, 'move'])
            ->whereIn('direction', ['up', 'down'])->name('questions.move');

        Route::resource('locations', LocationController::class)->only(['index', 'edit', 'update']);

        Route::get('/community', [CommunityController::class, 'index'])->name('community');
        Route::get('/community/log', [CommunityController::class, 'log'])->name('community.log');
        Route::post('/community/{type}/{id}/{action}', [CommunityController::class, 'moderate'])
            ->whereIn('type', ['post', 'answer'])->whereNumber('id')->whereIn('action', ['hide', 'restore', 'remove', 'dismiss'])->name('community.moderate');
        Route::post('/community/members/{member:id}/{action}', [CommunityController::class, 'block'])
            ->whereIn('action', ['block', 'unblock'])->name('community.block');
    });
});
