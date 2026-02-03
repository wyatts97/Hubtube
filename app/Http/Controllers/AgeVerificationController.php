<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        // Store in session (may not work with Redis issues)
        try {
            $request->session()->put('age_verified', true);
            $request->session()->save();
        } catch (\Exception $e) {
            // Continue even if session fails
        }
        
        // Set cookie directly using PHP's setcookie for maximum reliability
        // This bypasses Laravel's cookie encryption and queuing
        $expires = time() + (60 * 60 * 24); // 24 hours
        setcookie('age_verified', 'true', [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',  // Empty string = current domain only
            'secure' => false,  // Allow HTTP (not just HTTPS)
            'httponly' => false,  // Allow JavaScript access if needed
            'samesite' => 'Lax',  // Prevent CSRF but allow normal navigation
        ]);

        // Get intended URL or default to home
        $intended = $request->session()->get('url.intended', route('home'));
        
        return redirect($intended);
    }

    public function decline(): RedirectResponse
    {
        return redirect()->away('https://www.google.com');
    }
}
