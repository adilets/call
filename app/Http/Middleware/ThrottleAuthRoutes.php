<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers the password reset and registration POST routes without
 * throttling, so attach named rate limiters to them by route name.
 */
class ThrottleAuthRoutes
{
    private const LIMITERS = [
        'password.email' => 'password-reset',
        'password.update' => 'password-reset',
        'register.store' => 'register',
    ];

    public function __construct(private ThrottleRequests $throttle)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $limiter = self::LIMITERS[$request->route()?->getName()] ?? null;

        if ($limiter === null) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, $limiter);
    }
}
