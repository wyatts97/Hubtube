<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Require confirmed two-factor authentication for admin panel access.
 *
 * The panel exposes settings pages that assemble shell commands and hold live
 * payment credentials, so a stolen admin password should not be sufficient on
 * its own.
 *
 * This deliberately REDIRECTS to the 2FA setup page rather than returning 403.
 * Blocking outright would lock an existing buyer out of their own panel the
 * moment they enabled the setting, with no way back in.
 */
class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $required = (bool) Setting::get('admin_require_2fa', false);
        } catch (Throwable $e) {
            // Settings table unavailable (pre-install, mid-migration) — don't lock out.
            return $next($request);
        }

        $user = $request->user();

        if (! $required || ! $user?->is_admin || $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Two-factor authentication is required for admin access.',
                'setup_url' => route('settings.two-factor.status'),
            ], 403);
        }

        return redirect()
            ->route('settings.two-factor.status')
            ->with('warning', 'Two-factor authentication is required for admin access. '
                . 'Please enable it to continue.');
    }
}
