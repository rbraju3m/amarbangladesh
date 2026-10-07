<?php

use App\Http\Controllers\Site\EventController;
use App\Http\Controllers\Site\QuizController;
use App\Http\Controllers\Site\ResultController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * Public pages and APIs are cookieless and sessionless: nothing personal is stored in the
 * browser by the server, pages are CDN-cacheable, and the JSON endpoints need no CSRF token
 * because they act on no ambient credentials (name edits require the result's owner token).
 */
Route::withoutMiddleware([
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
    ShareErrorsFromSession::class, ValidateCsrfToken::class,
])->group(function () {
    Route::get('/', [QuizController::class, 'index'])
        ->middleware('cache.headers:public;max_age=300;s_maxage=600;etag')
        ->name('home');

    Route::get('/r/{result}', [QuizController::class, 'show'])
        ->middleware('cache.headers:public;max_age=60;s_maxage=120;etag')
        ->name('results.show');

    Route::prefix('api')->group(function () {
        Route::post('/results', [ResultController::class, 'store'])->middleware('throttle:results')->name('results.store');
        Route::patch('/results/{result}/name', [ResultController::class, 'updateName'])->middleware('throttle:results')->name('results.name');
        Route::post('/events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');
    });
});

require __DIR__.'/admin.php';
