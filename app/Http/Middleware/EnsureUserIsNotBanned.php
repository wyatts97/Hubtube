<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out banned and suspended accounts.
 *
 * Runs for every web request, including the admin panel and any session that
 * was already open when the ban landed, so there is no path that keeps working
 * because it does not go through the login form.
 */
class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isBlocked()) {
            return $next($request);
        }

        $message = $user->blockMessage();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['error' => $message, 'message' => $message], 403);
        }

        return redirect()->route('login')->with('error', $message);
    }
}
