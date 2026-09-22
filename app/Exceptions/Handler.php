<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * An unauthenticated call to the ChatGPT API must be a 401, not a redirect.
     *
     * The default behaviour redirects to a `login` route, which this app does
     * not have — so a missing or invalid token surfaces as a 500 with a full
     * stack trace whenever the caller does not send `Accept: application/json`.
     *
     * Deliberately scoped to `api/chatgpt/*` only. The same problem affects
     * the other authenticated API routes (/api/me, /api/user), but fixing it
     * there would change the status code those endpoints have always
     * returned, so it is left alone until that change can be made on purpose.
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->is('api/chatgpt/*')) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return parent::unauthenticated($request, $exception);
    }
}
