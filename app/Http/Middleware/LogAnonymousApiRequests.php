<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Audit-only middleware. It NEVER blocks, rejects or alters a request — it
 * records which API routes are reached with no credentials at all, so we can
 * see who the real anonymous callers are before putting `auth:sanctum` in
 * front of anything.
 *
 * Deliberately cheap, because this runs on every API request:
 *   - requests that DO send a bearer token are skipped before any work, so
 *     there is no extra database query on normal authenticated traffic;
 *   - each (method, route, ip) is written at most once an hour, so the log
 *     cannot grow without bound on a busy or looping caller;
 *   - everything runs in terminate(), after the response has been sent, and
 *     is wrapped in a catch-all.
 *
 * Read the results with:  php artisan api:audit-report
 */
class LogAnonymousApiRequests
{
    /**
     * Paths that are anonymous by design — logging them adds noise only.
     */
    private const IGNORE = [
        'api',
        'api/login',
        'api/me',
        'api/logout',
        'api/top-menu',
        'api/side-menu',
    ];

    /**
     * One log line per (method, route, ip) per this many seconds.
     */
    private const THROTTLE_SECONDS = 3600;

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Runs after the response has been sent, so it cannot slow the request
     * down or change its outcome.
     */
    public function terminate(Request $request, $response): void
    {
        try {
            // A caller that sends a token is not what we are looking for.
            // Checked first, and without touching the database: verifying the
            // token here would add a query to every authenticated request.
            if ($request->bearerToken()) {
                return;
            }

            if ($request->isMethod('OPTIONS')) {
                return;
            }

            $path = trim($request->path(), '/');

            // The chatgpt/* group is already authenticated and has its own
            // dedicated log — no need to audit it here too.
            if (in_array($path, self::IGNORE, true)
                || str_starts_with($path, 'api/chatgpt/')
                || str_starts_with($path, 'api/generate_otp')
                || str_starts_with($path, 'api/check_otp')) {
                return;
            }

            $route = optional($request->route())->uri() ?: $path;

            if (! $this->shouldLog($request->method(), $route, $request->ip())) {
                return;
            }

            Log::channel('api_audit')->info('anonymous', [
                'method'     => $request->method(),
                'path'       => $path,
                'route'      => $route,
                'status'     => method_exists($response, 'getStatusCode') ? $response->getStatusCode() : null,
                'ip'         => $request->ip(),
                'origin'     => $request->header('Origin'),
                'referer'    => $request->header('Referer'),
                'user_agent' => substr((string) $request->userAgent(), 0, 180),
                // Keys only, never values — request bodies carry customer PII.
                'body_keys'  => $this->bodyKeys($request),
            ]);
        } catch (\Throwable $e) {
            // Auditing must never take the API down. Swallow and move on.
        }
    }

    /**
     * True the first time this route/ip combination is seen in the current
     * window. Any cache failure falls through to logging — losing an audit
     * line matters less than a silent blind spot.
     */
    private function shouldLog(string $method, string $route, ?string $ip): bool
    {
        try {
            $key = 'api_audit:' . md5($method . '|' . $route . '|' . $ip);

            return Cache::add($key, true, self::THROTTLE_SECONDS);
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Top-level input keys only, capped — never read a large upload body just
     * to name its fields.
     */
    private function bodyKeys(Request $request): array
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            return array_slice(array_keys($request->query()), 0, 20);
        }

        if ($request->getContentLength() > 65536) {
            return ['[body too large to enumerate]'];
        }

        return array_slice(
            array_diff(array_keys($request->input()), ['password', 'password_confirmation']),
            0,
            20
        );
    }
}
