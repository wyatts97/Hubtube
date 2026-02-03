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
        // Skip age verification if disabled in config
        if (!config('hubtube.age_verification_required')) {
            return $next($request);
        }

        // Check if age is verified using multiple methods
        $ageVerified = $this->isAgeVerified($request);

        if (!$ageVerified) {
            // Store intended URL for redirect after verification
            try {
                $request->session()->put('url.intended', $request->url());
            } catch (\Exception $e) {
                // Ignore session errors
            }
            
            // For Inertia requests, use Inertia::location to force a full page redirect
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('age.verify'));
            }

            return redirect()->route('age.verify');
        }

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
