<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

        // Check session first
        $sessionVerified = $request->session()->get('age_verified', false);
        
        // Check cookie as fallback (handle both string and boolean)
        $cookieValue = $request->cookie('age_verified');
        $cookieVerified = $cookieValue === 'true' || $cookieValue === true || $cookieValue === '1' || $cookieValue === 1;

        $ageVerified = $sessionVerified || $cookieVerified;

        if (!$ageVerified) {
            // Store intended URL for redirect after verification
            $request->session()->put('url.intended', $request->url());
            
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Age verification required'], 403);
            }

            return redirect()->route('age.verify');
        }

        // If verified via cookie but not in session, sync to session
        if (!$sessionVerified && $cookieVerified) {
            $request->session()->put('age_verified', true);
            $request->session()->save();
        }

        return $next($request);
    }
}
