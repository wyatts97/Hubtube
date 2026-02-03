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

        $ageVerified = $request->session()->get('age_verified', false);

        if (!$ageVerified) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Age verification required'], 403);
            }

            return redirect()->route('age.verify');
        }

        return $next($request);
    }
}
