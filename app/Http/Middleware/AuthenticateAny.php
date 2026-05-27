<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateAny
{
    /** @var list<string> */
    private const SUPPORTED_GUARDS = ['web', 'db_session'];

    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = $guards === [] ? self::SUPPORTED_GUARDS : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);

                return $next($request);
            }
        }

        throw new AuthenticationException(
            'Unauthenticated.',
            $guards,
            route('login'),
        );
    }
}
