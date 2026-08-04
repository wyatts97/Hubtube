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

        if ($type === 'customer.subscription.deleted') {
            if ($user->is_pro) {
                // Preserve any still-active points-granted Pro period.
                if ($user->pro_source === 'points' && $user->pro_expires_at?->isFuture()) {
                    return;
                }

                $user->forceFill(['is_pro' => false, 'pro_source' => null, 'pro_expires_at' => null])->save();
            }
            return;
        }

        $shouldBePro = in_array($status, ['active', 'trialing', 'past_due'], true);

        if ($user->is_pro !== $shouldBePro || ($shouldBePro && $user->pro_source !== 'stripe')) {
            $user->forceFill([
                'is_pro' => $shouldBePro,
                'pro_source' => $shouldBePro ? 'stripe' : $user->pro_source,
                'pro_expires_at' => $shouldBePro ? null : $user->pro_expires_at,
            ])->save();
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
