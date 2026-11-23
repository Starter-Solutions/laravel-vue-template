<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified as VerifyEmail;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    public function __construct(private readonly VerifyEmail $verifyEmail) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $redirectToRoute = null): ?Response
    {
        if (! Features::enabled(Features::emailVerification())) {
            return $next($request);
        }

        return $this->verifyEmail->handle($request, $next, $redirectToRoute);
    }
}
