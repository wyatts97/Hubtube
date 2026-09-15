<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks unverified accounts from creating content while "Require Email
 * Verification" is on.
 *
 * Laravel's own `verified` middleware is unconditional, and registration
 * already honours the admin toggle, so this applies the same toggle to the
 * actions that matter: uploading and commenting. Browsing is never blocked.
 * Admins are exempt so an unverified installer account cannot lock itself out.
 */
class EnsureEmailIsVerified
{
    public static function required(): bool
    {
        // Stored as a real boolean by the settings page, but older rows can
        // hold the string form.
        return filter_var(Setting::get('email_verification_required', false), FILTER_VALIDATE_BOOLEAN);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_admin || $user->hasVerifiedEmail() || ! static::required()) {
            return $next($request);
        }

        $message = 'Please verify your email address first.';

        // Chunk uploads and other fetch() calls want JSON, not a redirect they
        // cannot follow. Inertia visits are XHR too, but follow redirects.
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'error' => $message,
                'message' => $message,
                'verification_required' => true,
            ], 403);
        }

        return redirect()->route('verification.notice')->with('error', $message);
    }
}
