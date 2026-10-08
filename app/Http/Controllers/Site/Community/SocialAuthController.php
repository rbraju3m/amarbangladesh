<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Accounts;
use App\Http\Controllers\Controller;
use App\Support\Bangla;
use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Google and Facebook sign-in. These two routes use a session (OAuth's `state` check against
 * login CSRF); the result is handed to the cookieless site as a token in the URL fragment of
 * /auth/done, which never reaches a server log.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, Accounts::providers(), true), 404);
        session(['auth.lang' => request()->query('lang') === 'en' ? 'en' : 'bn']);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, Accounts::providers(), true), 404);
        $done = session()->pull('auth.lang') === 'en' ? '/en/auth/done' : '/auth/done';

        try {
            $user = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect($done.'#error=1');
        }

        $member = Accounts::signIn($provider, (string) $user->getId(), Bangla::cleanName($user->getName()));
        if ($member->isBlocked()) {
            return redirect($done.'#error=blocked');
        }

        return redirect($done.'#t='.$member->issueToken());
    }
}
