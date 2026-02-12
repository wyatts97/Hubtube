<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AgeVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $required = (bool) Setting::get('age_verification_required', true);
        } catch (\Throwable $e) {
            return $next($request);
        }

        if ($required && $this->shouldEnforce($request) && !$this->isAgeVerified($request)) {
            return response()->json([
                'error' => 'Age verification required.',
            ], 451);
        }

        // Age verification for web views is handled by the frontend modal
        return $next($request);
    }

    protected function shouldEnforce(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
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
