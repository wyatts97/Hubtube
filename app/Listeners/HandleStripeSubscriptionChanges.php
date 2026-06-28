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
                $user->forceFill(['is_pro' => false])->save();
            }
            return;
        }

        $shouldBePro = in_array($status, ['active', 'trialing', 'past_due'], true);

        if ($user->is_pro !== $shouldBePro) {
            $user->forceFill(['is_pro' => $shouldBePro])->save();
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
