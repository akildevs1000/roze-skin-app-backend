<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * The app's Authenticate middleware redirects to a `login` route when the
 * caller does not explicitly ask for JSON — and that route does not exist, so
 * a missing token would surface as a 500 instead of a 401.
 *
 * Forcing the Accept header on this group makes every failure a clean JSON
 * status code, which is also what the ChatGPT connector expects.
 */
class ForceJsonRequest
{
    public function handle(Request $request, Closure $next)
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
