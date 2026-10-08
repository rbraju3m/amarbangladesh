<?php

use App\Http\Controllers\Site\Community\AnswerController;
use App\Http\Controllers\Site\Community\MemberController;
use App\Http\Controllers\Site\Community\PageController;
use App\Http\Controllers\Site\Community\PostController;
use App\Http\Controllers\Site\Community\SignalController;
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
 * because they act on no ambient credentials (name edits require the result's owner token;
 * community writes send the member token in a header).
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

    // Community pages. Short shared-cache lifetimes: new posts and answers should show up quickly.
    Route::middleware('cache.headers:public;max_age=0;s_maxage=20;etag')->group(function () {
        Route::get('/feed', [PageController::class, 'feed'])->name('feed');
        Route::get('/p/{post}', [PageController::class, 'show'])->whereNumber('post')->name('posts.show');
        Route::get('/u/{member}', [PageController::class, 'member'])->name('members.show');
    });
    Route::middleware('cache.headers:public;max_age=300;s_maxage=600;etag')->group(function () {
        Route::get('/ask', [PageController::class, 'ask'])->name('ask');
        Route::get('/me', [PageController::class, 'me'])->name('me');
    });

    Route::prefix('api')->group(function () {
        Route::get('/feed', [PageController::class, 'feedItems'])->middleware('cache.headers:public;max_age=0;s_maxage=20;etag')->name('feed.items');
        Route::post('/members', [MemberController::class, 'store'])->middleware('throttle:members')->name('members.store');

        Route::middleware('member')->group(function () {
            Route::get('/members/me', [MemberController::class, 'show'])->name('members.me');
            Route::patch('/members/me', [MemberController::class, 'update'])->middleware('throttle:community')->name('members.update');
            Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
            Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('throttle:community')->name('posts.destroy');
            Route::post('/posts/{post}/accept', [PostController::class, 'accept'])->middleware('throttle:community')->name('posts.accept');
            Route::post('/posts/{post}/answers', [AnswerController::class, 'store'])->middleware('throttle:answers')->name('answers.store');
            Route::delete('/answers/{answer}', [AnswerController::class, 'destroy'])->middleware('throttle:community')->name('answers.destroy');
            Route::post('/helpful', [SignalController::class, 'helpful'])->middleware('throttle:community')->name('helpful');
            Route::post('/reports', [SignalController::class, 'report'])->middleware('throttle:reports')->name('reports.store');
        });

        Route::post('/results', [ResultController::class, 'store'])->middleware('throttle:results')->name('results.store');
        Route::patch('/results/{result}/name', [ResultController::class, 'updateName'])->middleware('throttle:results')->name('results.name');
        Route::post('/events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');
    });
});

require __DIR__.'/admin.php';
