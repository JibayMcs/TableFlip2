<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets a baseline of HTTP security headers on every web response.
 *
 *   - X-Frame-Options: deny — block clickjacking via <iframe>.
 *   - X-Content-Type-Options: nosniff — prevent MIME sniffing.
 *   - Referrer-Policy: same-origin — don't leak full URLs to third parties.
 *   - Permissions-Policy: disable a few sensors we never use.
 *   - Strict-Transport-Security — only when the request is HTTPS, so local
 *     http://127.0.0.1 development isn't poisoned.
 *
 * Content-Security-Policy is intentionally NOT enforced here because Livewire
 * + Alpine + Vite inject inline event handlers and styles that would require
 * a much more elaborate nonce-based setup. Track that as a V2 item.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = $response->headers;

        $headers->set('X-Frame-Options', 'DENY', false);
        $headers->set('X-Content-Type-Options', 'nosniff', false);
        $headers->set('Referrer-Policy', 'same-origin', false);
        $headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=(), usb=()', false);

        if ($request->isSecure()) {
            // 1 year, include subdomains, preload-eligible.
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
