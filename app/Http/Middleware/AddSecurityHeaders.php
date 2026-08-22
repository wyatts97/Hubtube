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
        // 'unsafe-inline' + 'unsafe-eval' + https: covers ad-network needs since
        // ad creatives (VideoAd HTML/VAST/VPAID) are admin-configured and can
        // point at any HTTPS origin — a static domain allowlist would break that
        // admin feature — and JuicyAds' own jads.js script calls eval() directly
        // (confirmed in production: without 'unsafe-eval' its CSP violation
        // blocks ad rendering entirely). Plain http: origins are NOT needed by
        // any ad format in use, so those are still dropped in production: this
        // forces every script, stylesheet, media, websocket, and frame source to
        // be loaded over TLS, closing off cleartext MITM/downgrade injection.
        // Migrating to a nonce-based policy (removing 'unsafe-inline'/'unsafe-eval')
        // is tracked as future work but is blocked on ad-network compatibility.
        // Local/non-production environments keep http:/ws: so `php artisan serve`
        // and a non-TLS Reverb dev server keep working.
        $isProduction = app()->environment('production');
        $httpOrigin = $isProduction ? '' : ' http:';
        $wsOrigin = $isProduction ? '' : ' ws:';

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' data: https:{$httpOrigin} https://poweredby.jads.co https://*.jads.co",
            "style-src 'self' 'unsafe-inline' https:{$httpOrigin}",
            "img-src 'self' data: blob: https:{$httpOrigin}",
            "media-src 'self' blob: https:{$httpOrigin}",
            "font-src 'self' data: https:{$httpOrigin}",
            "worker-src 'self' blob: https://poweredby.jads.co https://*.jads.co",
            "connect-src 'self' wss:{$wsOrigin} https:{$httpOrigin}",
            "frame-src 'self' https:{$httpOrigin}",
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
