<?php

namespace App\Listeners;

use App\Models\User;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookReceived;

class HandleStripeSubscriptionChanges
{
    public function handle(WebhookReceived $event): void
    {
        $payload = $event->payload ?? [];
        $type = $payload['type'] ?? '';

        if (! in_array($type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ], true)) {
            return;
        }

        $data = $payload['data']['object'] ?? [];
        $customerId = $data['customer'] ?? null;
        $status = $data['status'] ?? null;

        if (! $customerId || ! $status) {
            return;
        }

        $user = $this->findUserByStripeCustomer($customerId);

        if (! $user) {
            return;
        }

        $shouldBePro = $type !== 'customer.subscription.deleted'
            && in_array($status, ['active', 'trialing', 'past_due'], true);

        if ($shouldBePro) {
            if (! $user->is_pro || $user->pro_source !== 'stripe') {
                $user->forceFill(['is_pro' => true, 'pro_source' => 'stripe', 'pro_expires_at' => null])->save();
            }

            return;
        }

        // A cancelled or lapsed Stripe subscription must not take Pro away from
        // an active CCBill subscription or points redemption.
        if ($user->is_pro) {
            $user->revokeProUnlessEntitled(checkStripe: false);
        }
    }

    protected function findUserByStripeCustomer(string $customerId): ?User
    {
        $model = Cashier::$customerModel;

        return $model::query()
            ->where('stripe_id', $customerId)
            ->first();
    }
}
