<?php

use App\Http\Controllers\Site\Community\AnswerController;
use App\Http\Controllers\Site\Community\AuthController;
use App\Http\Controllers\Site\Community\MemberController;
use App\Http\Controllers\Site\Community\NotificationController;
use App\Http\Controllers\Site\Community\PageController;
use App\Http\Controllers\Site\Community\PostController;
use App\Http\Controllers\Site\Community\SignalController;
use App\Http\Controllers\Site\Community\SocialAuthController;
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
    // Every page twice: Bangla at /…, English at /en/… (names prefixed "en."). See App\Support\Lang.
    $pages = function () {
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
            Route::get('/notifications', [PageController::class, 'notifications'])->name('notifications');
            Route::get('/auth/done', [PageController::class, 'authDone'])->name('auth.done');
            Route::get('/reset-password', [PageController::class, 'resetPassword'])->name('password.reset.page');
        });

        // From notification emails: a signed link, no login. GET asks first (mail scanners open
        // links); POST turns the emails off, also as the one-click List-Unsubscribe target.
        Route::match(['get', 'post'], '/notifications/unsubscribe/{member}', [NotificationController::class, 'unsubscribe'])
            ->middleware('signed')->name('notifications.unsubscribe');
    };
    $pages();
    Route::prefix('en')->name('en.')->group($pages);

    Route::prefix('api')->group(function () {
        Route::get('/feed', [PageController::class, 'feedItems'])->middleware('cache.headers:public;max_age=0;s_maxage=20;etag')->name('feed.items');
        Route::prefix('auth')->group(function () {
            Route::post('/email/register', [AuthController::class, 'emailRegister'])->middleware('throttle:auth')->name('auth.email.register');
            Route::post('/email/login', [AuthController::class, 'emailLogin'])->middleware('throttle:auth')->name('auth.email.login');
            Route::post('/email/forgot', [AuthController::class, 'emailForgot'])->middleware('throttle:auth-mail')->name('auth.email.forgot');
            Route::post('/email/reset', [AuthController::class, 'emailReset'])->middleware('throttle:auth')->name('auth.email.reset');
            Route::post('/phone/send', [AuthController::class, 'phoneSend'])->middleware('throttle:auth-sms')->name('auth.phone.send');
            Route::post('/phone/verify', [AuthController::class, 'phoneVerify'])->middleware('throttle:auth')->name('auth.phone.verify');
            Route::post('/link', [AuthController::class, 'link'])->middleware(['member', 'throttle:community'])->name('auth.link');
            Route::post('/logout', [AuthController::class, 'logout'])->middleware('member')->name('auth.logout');
        });

        Route::post('/results', [ResultController::class, 'store'])->middleware('throttle:results')->name('results.store');
        Route::patch('/results/{result}/name', [ResultController::class, 'updateName'])->middleware('throttle:results')->name('results.name');
        Route::post('/events', [EventController::class, 'store'])->middleware('throttle:events')->name('events.store');

        Route::get('/members/me', [MemberController::class, 'show'])->middleware('member')->name('members.me');
        Route::post('/mine', [MemberController::class, 'mine'])->middleware(['member', 'throttle:community'])->name('mine');

        // Every community write needs a signed-in account.
        Route::middleware('member:account')->group(function () {
            Route::patch('/members/me', [MemberController::class, 'update'])->middleware('throttle:community')->name('members.update');
            Route::post('/posts', [PostController::class, 'store'])->middleware('throttle:posts')->name('posts.store');
            Route::delete('/posts/{post}', [PostController::class, 'destroy'])->middleware('throttle:community')->name('posts.destroy');
            Route::post('/posts/{post}/accept', [PostController::class, 'accept'])->middleware('throttle:community')->name('posts.accept');
            Route::post('/posts/{post}/answers', [AnswerController::class, 'store'])->middleware('throttle:answers')->name('answers.store');
            Route::delete('/answers/{answer}', [AnswerController::class, 'destroy'])->middleware('throttle:community')->name('answers.destroy');
            Route::post('/helpful', [SignalController::class, 'helpful'])->middleware('throttle:community')->name('helpful');
            Route::post('/reports', [SignalController::class, 'report'])->middleware('throttle:reports')->name('reports.store');

            Route::get('/notifications/unread', [NotificationController::class, 'unread'])->name('notifications.unread');
            Route::get('/notifications', [NotificationController::class, 'index'])->middleware('throttle:community')->name('notifications.index');
            Route::post('/notifications/read', [NotificationController::class, 'read'])->middleware('throttle:community')->name('notifications.read');
            Route::patch('/notifications/settings', [NotificationController::class, 'settings'])->middleware('throttle:community')->name('notifications.settings');
        });

    });
});

// Google / Facebook sign-in: the only public routes with a session (OAuth state), never cached.
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->whereIn('provider', ['google', 'facebook'])->name('auth.social');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->whereIn('provider', ['google', 'facebook'])->name('auth.social.callback');

require __DIR__.'/admin.php';
