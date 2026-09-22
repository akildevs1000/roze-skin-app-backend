<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Swaps the default database connection to `pgsql_readonly` for the duration
 * of the request, so that every model AND every eager-loaded relation goes
 * through the SELECT-only Postgres role — not just the models we remembered
 * to call `::on('pgsql_readonly')` on.
 *
 * Must run AFTER auth:sanctum, because Sanctum writes `last_used_at` to the
 * token row and that write has to happen on the normal connection.
 */
class UseReadOnlyConnection
{
    public function handle(Request $request, Closure $next)
    {
        // Force the token to resolve (and its last_used_at write to happen)
        // while the writable connection is still the default.
        $request->user();

        if (! config('database.connections.pgsql_readonly.password')) {
            // The read-only Postgres role has not been provisioned yet. The
            // request is still safe — the routes are GET-only and guarded by
            // EnforceReadOnly — but the second layer of protection is absent.
            Log::channel('chatgpt')->warning('pgsql_readonly not configured; falling back to the default connection');

            return $next($request);
        }

        $original = config('database.default');

        DB::setDefaultConnection('pgsql_readonly');

        try {
            return $next($request);
        } finally {
            DB::setDefaultConnection($original);
        }
    }
}
