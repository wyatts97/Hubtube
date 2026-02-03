<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgeVerification
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('hubtube.age_verification_required')) {
            return $next($request);
        }

        // Check session first, then cookie as fallback
        $ageVerified = $request->session()->get('age_verified', false) 
                    || $request->cookie('age_verified') === 'true';

        if (!$ageVerified) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Age verification required'], 403);
            }

            return redirect()->route('age.verify');
        }

        // If verified via cookie but not in session, sync to session
        if (!$request->session()->get('age_verified') && $request->cookie('age_verified') === 'true') {
            $request->session()->put('age_verified', true);
        }

        return $next($request);
    }
}
