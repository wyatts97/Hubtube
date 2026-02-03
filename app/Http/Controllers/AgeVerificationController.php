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
        $request->session()->put('age_verified', true);

        return redirect()->intended(route('home'));
    }

    public function decline(): RedirectResponse
    {
        return redirect()->away('https://www.google.com');
    }
}
