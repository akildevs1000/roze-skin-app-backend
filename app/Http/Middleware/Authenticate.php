<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        // The ChatGPT API never redirects — it answers 401 (see
        // App\Exceptions\Handler::unauthenticated). Returning early also
        // avoids route('login') below, which throws RouteNotFoundException
        // because this app has no such route.
        //
        // Scoped to that group on purpose: every other route keeps the
        // behaviour it has always had.
        if ($request->is('api/chatgpt/*')) {
            return null;
        }

        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
