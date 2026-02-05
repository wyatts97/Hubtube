<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AgeVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        // Age verification is now handled by the frontend modal
        // This middleware just ensures the cookie/session check is available
        // The modal component checks the cookie client-side
        return $next($request);
    }

    /**
     * Check if age verification has been completed
     */
    protected function isAgeVerified(Request $request): bool
    {
        // Method 1: Check PHP's $_COOKIE superglobal (most reliable)
        if (isset($_COOKIE['age_verified']) && $_COOKIE['age_verified'] === 'true') {
            return true;
        }

        // Method 2: Check Laravel's request cookie
        $laravelCookie = $request->cookie('age_verified');
        if ($laravelCookie === 'true' || $laravelCookie === true) {
            return true;
        }

        // Method 3: Check session
        try {
            if ($request->session()->get('age_verified', false)) {
                return true;
            }
        } catch (\Exception $e) {
            // Session might not be available
        }

        return false;
    }
}
