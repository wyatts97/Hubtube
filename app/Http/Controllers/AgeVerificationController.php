<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Inertia\Response;

class AgeVerificationController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('AgeVerification');
    }

    public function verify(Request $request): RedirectResponse
    {
        // Store in session
        try {
            $request->session()->put('age_verified', true);
            $request->session()->save();
        } catch (\Exception $e) {
            \Log::error('Session save failed: ' . $e->getMessage());
        }

        // Get intended URL or default to home
        $intended = route('home');
        try {
            $intended = $request->session()->get('url.intended', route('home'));
        } catch (\Exception $e) {
            // Use default
        }
        
        // Create cookie that lasts 24 hours
        // Using Cookie::make with explicit parameters
        $cookie = cookie(
            'age_verified',     // name
            'true',             // value
            60 * 24,            // minutes (24 hours)
            '/',                // path
            null,               // domain (null = current domain)
            false,              // secure (false = allow HTTP)
            false               // httpOnly (false = allow JS access)
        );

        \Log::debug('Age verification confirmed, redirecting to: ' . $intended);
        
        return redirect($intended)->withCookie($cookie);
    }

    public function decline(): RedirectResponse
    {
        return redirect()->away('https://www.google.com');
    }
}
