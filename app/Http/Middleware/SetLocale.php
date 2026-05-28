<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolve the active locale on every request.
 *
 * Priority order :
 *   1. `tableflip_locale` cookie set when the user picks a language in
 *      the UI.
 *   2. Accept-Language HTTP header, intersected with the supported set.
 *   3. config('app.fallback_locale').
 */
class SetLocale
{
    private const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->fromCookie($request)
            ?? $this->fromAcceptLanguage($request)
            ?? (string) config('app.fallback_locale', 'en');

        App::setLocale($locale);

        return $next($request);
    }

    private function fromCookie(Request $request): ?string
    {
        $candidate = (string) ($request->cookie('tableflip_locale') ?? '');

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
