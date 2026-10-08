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

    public function handle(Request $request, Closure $next): Response
    {
        $member = Member::fromToken($request->header(self::HEADER));

        if (! $member) {
            return response()->json(['message' => 'আগে নিজের একটা নাম দাও, তারপর আবার চেষ্টা করো।'], 401);
        }
        if ($member->isBlocked()) {
            return response()->json(['message' => 'এই পরিচয় থেকে লেখা বন্ধ করা হয়েছে।'], 403);
        }

        $request->attributes->set('member', $member);

        return $next($request);
    }
}
