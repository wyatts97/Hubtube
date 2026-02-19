<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a CSP nonce and register it with Vite so that @vite
        // tags include nonce="..." attributes automatically.
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        // Content Security Policy
        // - script-src: nonce replaces 'unsafe-inline'. 'unsafe-eval' kept
        //   because Vue's runtime compiler and some ad scripts require it.
        // - style-src: 'unsafe-inline' kept because Filament/Livewire and
        //   Vue transitions inject inline styles dynamically.
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-inline' 'unsafe-eval' https: http:",
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
