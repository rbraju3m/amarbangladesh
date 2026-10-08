<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Community writes identify the member by the token their browser keeps, sent as a header. No cookie
 * is involved, so there is no ambient credential to forge (no CSRF) and public pages stay cacheable.
 */
class ResolveMember
{
    public const HEADER = 'X-Member-Token';

    /** `member` accepts any known token; `member:account` needs a signed-in account (all community writes). */
    public function handle(Request $request, Closure $next, ?string $need = null): Response
    {
        $member = Member::fromToken($request->header(self::HEADER));

        if (! $member || ($need === 'account' && ! $member->hasAccount())) {
            return response()->json(['message' => __('এটা করতে লগইন করুন।'), 'login' => true], 401);
        }
        if ($member->isBlocked()) {
            return response()->json(['message' => __('এই অ্যাকাউন্ট থেকে লেখা বন্ধ করা হয়েছে।')], 403);
        }

        $request->attributes->set('member', $member);

        return $next($request);
    }
}
