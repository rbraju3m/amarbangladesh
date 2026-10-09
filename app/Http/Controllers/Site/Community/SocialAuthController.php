<?php

namespace App\Http\Controllers\Site\Community;

use App\Community\Accounts;
use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Support\Bangla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
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
        session(['auth.lang' => request()->query('lang') === 'en' ? 'en' : 'bn', 'auth.visitor' => is_string(request()->query('v')) ? request()->query('v') : null]);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, Accounts::providers(), true), 404);
        $locale = session()->pull('auth.lang') === 'en' ? 'en' : 'bn';
        $done = $locale === 'en' ? '/en/auth/done' : '/auth/done';

        try {
            $user = Socialite::driver($provider)->user();
        } catch (Throwable) {
            return redirect($done.'#error=1');
        }

        // The provider-verified address is kept (never shown) to email answer notifications.
        $email = filter_var($user->getEmail(), FILTER_VALIDATE_EMAIL) ? Str::lower($user->getEmail()) : null;
        $member = Accounts::signIn($provider, (string) $user->getId(), Bangla::cleanName($user->getName()), [], $email);
        if ($member->isBlocked()) {
            return redirect($done.'#error=blocked');
        }

        if ($member->wasRecentlyCreated) {
            AnalyticsEvent::server('signed_up', request(), ['method' => $provider], session()->pull('auth.visitor'));
        }
        if ($member->locale !== $locale) {
            $member->update(['locale' => $locale]);
        }

        return redirect($done.'#t='.$member->issueToken());
    }
}
