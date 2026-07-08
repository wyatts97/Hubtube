<?php

namespace App\Http\Controllers;

use App\Models\CCBillSubscription;
use App\Models\CCBillWebhookEvent;
use App\Models\Plan;
use App\Models\User;
use App\Services\AdminLogger;
use App\Services\CCBillService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CCBillController extends Controller
{
    public function __construct(protected CCBillService $ccbill) {}

    /**
     * Post-payment landing page (informational only — entitlement is granted by the webhook,
     * never by this redirect, since the browser cannot be trusted).
     */
    public function success(): RedirectResponse
    {
        return redirect()->route('settings')
            ->with('success', 'Thanks! Your payment is being processed and Pro access will activate shortly.');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->route('pro.index')
            ->with('error', 'Checkout was cancelled.');
    }

    /**
     * Inbound CCBill webhook (Background Post / Webhooks).
     * Must be CSRF-exempt and outside auth/age gates.
     */
    public function webhook(Request $request): JsonResponse
    {
        if (! $this->ccbill->verifyWebhook($request)) {
            return response()->json(['error' => 'unauthorized'], 403);
        }

        $data = $request->all();
        $eventType = (string) ($data['eventType'] ?? $data['event_type'] ?? '');
        $subscriptionId = (string) ($data['subscriptionId'] ?? $data['subscription_id'] ?? '');
        $timestamp = (string) ($data['timestamp'] ?? '');

        if ($eventType === '') {
            return response()->json(['error' => 'missing eventType'], 422);
        }

        $fingerprint = hash('sha256', $subscriptionId . '|' . $eventType . '|' . $timestamp);

        // Idempotency: acknowledge duplicates without reprocessing.
        if (CCBillWebhookEvent::where('fingerprint', $fingerprint)->exists()) {
            return response()->json(['status' => 'duplicate']);
        }

        $event = CCBillWebhookEvent::create([
            'event_type' => $eventType,
            'ccbill_subscription_id' => $subscriptionId ?: null,
            'fingerprint' => $fingerprint,
            'payload' => $data,
        ]);

        try {
            $this->processEvent($eventType, $subscriptionId, $data);
            $event->update(['processed_at' => now()]);
        } catch (\Throwable $e) {
            Log::error('CCBill webhook processing failed', [
                'event' => $eventType,
                'subscription' => $subscriptionId,
                'error' => $e->getMessage(),
            ]);
            // Return 200 so CCBill doesn't hammer retries for a poison event; it's persisted for replay.
        }

        return response()->json(['status' => 'ok']);
    }

    protected function processEvent(string $eventType, string $subscriptionId, array $data): void
    {
        switch ($eventType) {
            case 'NewSaleSuccess':
                $this->handleNewSale($subscriptionId, $data);
                break;

            case 'RenewalSuccess':
                $this->handleRenewal($subscriptionId, $data);
                break;

            case 'RenewalFailure':
                $this->updateStatus($subscriptionId, CCBillSubscription::STATUS_PAST_DUE);
                break;

            case 'Cancellation':
                $this->handleCancellation($subscriptionId);
                break;

            case 'Expiration':
                $this->handleTermination($subscriptionId, CCBillSubscription::STATUS_EXPIRED);
                break;

            case 'Refund':
            case 'Return':
            case 'Void':
                $this->handleTermination($subscriptionId, CCBillSubscription::STATUS_REFUNDED);
                break;

            case 'Chargeback':
                $this->handleTermination($subscriptionId, CCBillSubscription::STATUS_CHARGEBACK);
                break;

            default:
                // UpgradeSuccess, CustomerDataUpdate, etc. — logged only for now.
                break;
        }
    }

    protected function handleNewSale(string $subscriptionId, array $data): void
    {
        $userId = (int) ($data['ht_uid'] ?? 0);
        $planId = (int) ($data['ht_plan'] ?? 0);
        $signature = (string) ($data['ht_sig'] ?? '');

        if (! $userId || ! $planId || ! $this->ccbill->verifyPassthrough($userId, $planId, $signature)) {
            Log::warning('CCBill NewSale rejected: invalid pass-through attribution', [
                'subscription' => $subscriptionId,
                'user' => $userId,
                'plan' => $planId,
            ]);
            return;
        }

        $user = User::find($userId);
        $plan = Plan::find($planId);

        if (! $user || ! $plan) {
            return;
        }

        $isRecurring = $plan->ccbill_recurring_period !== null;
        $periodDays = (int) ($plan->ccbill_recurring_period ?? $plan->ccbill_initial_period ?? 30);

        DB::transaction(function () use ($user, $plan, $subscriptionId, $isRecurring, $periodDays) {
            CCBillSubscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'ccbill_subscription_id' => $subscriptionId,
                ],
                [
                    'plan_id' => $plan->id,
                    'status' => CCBillSubscription::STATUS_ACTIVE,
                    'subscription_type' => $isRecurring ? 'recurring' : 'single',
                    'current_period_end' => now()->addDays($periodDays),
                    'cancelled_at' => null,
                    'expired_at' => null,
                ]
            );

            $this->grantPro($user);
        });

        AdminLogger::log('CCBill new sale activated', 'payments', [
            'user_id' => $user->id,
            'plan' => $plan->slug,
            'subscription' => $subscriptionId,
        ]);
    }

    protected function handleRenewal(string $subscriptionId, array $data): void
    {
        $subscription = $this->findSubscription($subscriptionId);
        if (! $subscription) {
            return;
        }

        $periodDays = (int) ($subscription->plan?->ccbill_recurring_period
            ?? $subscription->plan?->ccbill_initial_period
            ?? 30);

        $subscription->update([
            'status' => CCBillSubscription::STATUS_ACTIVE,
            'current_period_end' => now()->addDays($periodDays),
        ]);

        if ($subscription->user) {
            $this->grantPro($subscription->user);
        }
    }

    protected function handleCancellation(string $subscriptionId): void
    {
        $subscription = $this->findSubscription($subscriptionId);
        if (! $subscription) {
            return;
        }

        // Cancelled but retains access until the current period ends.
        $subscription->update([
            'status' => CCBillSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    protected function handleTermination(string $subscriptionId, string $status): void
    {
        $subscription = $this->findSubscription($subscriptionId);
        if (! $subscription) {
            return;
        }

        $subscription->update([
            'status' => $status,
            'expired_at' => now(),
            'current_period_end' => now(),
        ]);

        if ($subscription->user) {
            $this->revokeProIfNoOtherActive($subscription->user);
        }
    }

    protected function updateStatus(string $subscriptionId, string $status): void
    {
        $this->findSubscription($subscriptionId)?->update(['status' => $status]);
    }

    protected function findSubscription(string $subscriptionId): ?CCBillSubscription
    {
        if ($subscriptionId === '') {
            return null;
        }

        return CCBillSubscription::with(['user', 'plan'])
            ->where('ccbill_subscription_id', $subscriptionId)
            ->first();
    }

    protected function grantPro(User $user): void
    {
        if (! $user->is_pro) {
            $user->forceFill(['is_pro' => true])->save();
        }
    }

    /**
     * Only revoke Pro if the user has no other active entitlement (Stripe or another CCBill sub).
     */
    protected function revokeProIfNoOtherActive(User $user): void
    {
        $hasStripe = false;
        try {
            $hasStripe = $user->subscribed('pro');
        } catch (\Throwable) {
            // Cashier not configured / no stripe customer — ignore.
        }

        if (! $hasStripe && ! $user->fresh()->hasActiveCCBillSubscription()) {
            $user->forceFill(['is_pro' => false])->save();
        }
    }
}
