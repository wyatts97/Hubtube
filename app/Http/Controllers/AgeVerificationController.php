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
        $request->session()->put('age_verified', true);
        
        // Also store in a cookie as backup (lasts 24 hours)
        $cookie = Cookie::make('age_verified', 'true', 60 * 24, '/', null, false, true);

        return redirect()->intended(route('home'))->withCookie($cookie);
    }

    public function decline(): RedirectResponse
    {
        return redirect()->away('https://www.google.com');
    }
}
