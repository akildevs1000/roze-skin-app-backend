<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Belt-and-braces guard for the ChatGPT integration.
 *
 * The routes themselves are GET-only and the token carries a `read` ability,
 * but this rejects anything that is not a plain read before it reaches a
 * controller — so a route accidentally registered as POST later on still
 * cannot be called.
 */
class EnforceReadOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            Log::channel('chatgpt')->warning('write attempt blocked', [
                'method' => $request->method(),
                'path'   => $request->path(),
                'ip'     => $request->ip(),
            ]);

            return response()->json([
                'error' => 'This API is read-only.',
            ], 403);
        }

        return $next($request);
    }
}
