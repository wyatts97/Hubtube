<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProController extends Controller
{
    public function index(): Response
    {
        if (!Setting::get('pro_enabled', true)) {
            throw new NotFoundHttpException();
        }

        $plans = Plan::active()->orderBy('interval')->get()->keyBy('interval');
        $monthly = $plans->get('month');
        $annual = $plans->get('year');
        $annualSavings = null;

        if ($monthly && $annual) {
            $annualDiscount = $annual->annual_discount_percent;
            $annualMonthlyEquivalent = $annual->amount_cents / 12;
            $monthlyAmount = $monthly->amount_cents;
            $annualSavings = max(0, round((($monthlyAmount - $annualMonthlyEquivalent) / $monthlyAmount) * 100, 1));
        }

        $user = auth()->user();
        $subscription = null;
        if ($user) {
            $sub = $user->subscriptions()->active()->first();
            if ($sub) {
                $subscription = [
                    'plan' => $sub->type,
                    'ends_at' => $sub->ends_at?->toDateString(),
                    'current_period_end' => $sub->current_period_end?->toDateString(),
                    'stripe_status' => $sub->stripe_status,
                ];
            }
        }

        return Inertia::render('Pro/Index', [
            'plans' => [
                'monthly' => $monthly?->toArray(),
                'annual' => $annual?->toArray(),
            ],
            'annualSavings' => $annualSavings,
            'subscription' => $subscription,
        ]);
    }

    public function checkout(Request $request): RedirectResponse
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!Setting::get('pro_enabled', true)) {
            throw new NotFoundHttpException();
        }

        $interval = $request->input('plan', 'monthly');
        $plan = Plan::active()->where('slug', $interval === 'annual' ? 'pro-annual' : 'pro-monthly')->first();

        if (!$plan || !$plan->stripe_price_id) {
            return back()->with('error', 'Pro plan is not configured yet. Please contact support.');
        }

        $checkout = auth()->user()->newSubscription('pro', $plan->stripe_price_id)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => URL::route('pro.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => URL::route('pro.index'),
            ]);

        return redirect($checkout->url);
    }

    public function success(Request $request): RedirectResponse
    {
        $sessionId = $request->query('session_id');

        if ($sessionId) {
            try {
                auth()->user()->stripe()->checkout->sessions->retrieve($sessionId);
            } catch (\Throwable $e) {
                return redirect()->route('pro.index')->with('error', 'Unable to verify your checkout session.');
            }
        }

        return redirect()->route('settings')
            ->with('success', 'Welcome to Pro! Your subscription is active.');
    }

    public function portal(): RedirectResponse
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        if (!auth()->user()->stripe_id) {
            return redirect()->route('pro.index')->with('error', 'No active subscription found.');
        }

        $portal = auth()->user()->billingPortalUrl(URL::route('settings'));

        return redirect($portal);
    }
}
