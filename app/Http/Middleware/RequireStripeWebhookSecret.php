<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cashier only verifies Stripe signatures when a webhook secret is set, so
 * without one a forged "subscription active" event would grant Pro. Refuse
 * webhooks outright until the secret is configured, as CCBill already does.
 */
class RequireStripeWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('cashier.webhook.secret')) {
            Log::error('Stripe webhook rejected: no webhook secret configured in Payment Settings.', [
                'ip' => $request->ip(),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
