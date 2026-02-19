<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Content Security Policy
        // NOTE: No nonce is used. When a nonce is present in script-src,
        // browsers ignore 'unsafe-inline' per the CSP spec — meaning every
        // inline script without the nonce (ad networks, popunder, interstitial,
        // all Blade-injected ad scripts) gets silently blocked.
        // 'unsafe-inline' + 'unsafe-eval' + https: covers all ad network needs.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https: http:",
            "style-src 'self' 'unsafe-inline' https: http:",
            "img-src 'self' data: blob: https: http:",
            "media-src 'self' blob: https: http:",
            "font-src 'self' data: https: http:",
            "worker-src 'self' blob:",
            "connect-src 'self' wss: ws: https: http:",
            "frame-src 'self' https: http:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(self), geolocation=(), payment=()');

        return $response;
    }
}
