<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The language comes from the URL (/en/… is English). API calls say which language their page is
 * in: POSTs with an X-Locale header, cacheable GETs with ?lang= (so each language caches apart).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->segment(1) === 'en'
            || $request->header('X-Locale') === 'en'
            || ($request->is('api/*') && $request->query('lang') === 'en')
            ? 'en' : 'bn';

        app()->setLocale($locale);

        return $next($request);
    }
}
