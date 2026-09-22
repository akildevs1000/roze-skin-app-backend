<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Full audit trail of everything the ChatGPT integration reads, so the owner
 * can always answer "what did it look at?".
 */
class LogChatGptRequest
{
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate(Request $request, $response): void
    {
        try {
            $token = optional($request->user())->currentAccessToken();

            Log::channel('chatgpt')->info('read', [
                'path'      => $request->path(),
                'query'     => $request->query(),
                'status'    => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'token'     => optional($token)->name,
                'token_id'  => optional($token)->id,
                'abilities' => optional($token)->abilities,
                'ip'        => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // Never let logging break a read.
        }
    }
}
