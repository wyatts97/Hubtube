<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class AgeVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip age verification if disabled in config
        if (!config('hubtube.age_verification_required')) {
            return $next($request);
        }

        // Skip age verification for admin panel (has its own auth)
        if ($request->is('admin') || $request->is('admin/*')) {
            return $next($request);
        }

        // Skip for API routes
        if ($request->is('api/*')) {
            return $next($request);
        }

        // Skip for age verification routes themselves
        if ($request->is('age-verify') || $request->is('age-verify/*')) {
            return $next($request);
        }

        // Primary check: Use cookie (most reliable across requests)
        // The cookie is NOT encrypted (excluded in bootstrap/app.php)
        $cookieValue = $_COOKIE['age_verified'] ?? null;
        $cookieVerified = $cookieValue === 'true';

        // Secondary check: Session (may not persist with Redis issues)
        $sessionVerified = false;
        try {
            $sessionVerified = $request->session()->get('age_verified', false);
        } catch (\Exception $e) {
            // Session might not be available, continue with cookie check
        }

        $ageVerified = $cookieVerified || $sessionVerified;

        if (!$ageVerified) {
            // Store intended URL for redirect after verification
            try {
                $request->session()->put('url.intended', $request->url());
            } catch (\Exception $e) {
                // Ignore session errors
            }
            
            if ($request->wantsJson() || $request->expectsJson()) {
                return response()->json(['error' => 'Age verification required'], 403);
            }

            return redirect()->route('age.verify');
        }

        // Sync cookie to session if needed
        if ($cookieVerified && !$sessionVerified) {
            try {
                $request->session()->put('age_verified', true);
            } catch (\Exception $e) {
                // Ignore session errors
            }
        }

        return $next($request);
    }
}
