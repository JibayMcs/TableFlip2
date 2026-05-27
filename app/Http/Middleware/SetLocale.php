<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active locale on every request.
 *
 * Priority order :
 *   1. Authenticated web user's saved `locale` column.
 *   2. Accept-Language HTTP header, intersected with the supported set.
 *   3. config('app.fallback_locale').
 */
class SetLocale
{
    private const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->fromAuth()
            ?? $this->fromAcceptLanguage($request)
            ?? (string) config('app.fallback_locale', 'en');

        App::setLocale($locale);

        return $next($request);
    }

    private function fromAuth(): ?string
    {
        if (! Auth::guard('web')->check()) {
            return null;
        }
        $candidate = (string) (Auth::guard('web')->user()->locale ?? '');

        return in_array($candidate, self::SUPPORTED, true) ? $candidate : null;
    }

    private function fromAcceptLanguage(Request $request): ?string
    {
        foreach ($request->getLanguages() as $lang) {
            $short = strtolower(substr((string) $lang, 0, 2));
            if (in_array($short, self::SUPPORTED, true)) {
                return $short;
            }
        }

        return null;
    }
}
