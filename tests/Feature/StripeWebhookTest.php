<?php

use App\Models\User;

function forgedSubscriptionEvent(User $user): array
{
    return [
        'id' => 'evt_forged',
        'type' => 'customer.subscription.created',
        'data' => ['object' => [
            'id' => 'sub_forged',
            'customer' => 'cus_forged',
            'status' => 'active',
            'items' => ['data' => []],
            'metadata' => ['user_id' => $user->id],
        ]],
    ];
}

test('stripe webhook is refused when no webhook secret is configured', function () {
    config(['cashier.webhook.secret' => null]);
    $user = User::factory()->create();
    $user->forceFill(['stripe_id' => 'cus_forged'])->save();

    $this->postJson(route('stripe.webhook'), forgedSubscriptionEvent($user))->assertForbidden();

    expect($user->fresh()->is_pro)->toBeFalsy();
});

test('stripe webhook with a bad signature is refused', function () {
    config(['cashier.webhook.secret' => 'whsec_test']);
    $user = User::factory()->create();

    $this->postJson(route('stripe.webhook'), forgedSubscriptionEvent($user), ['Stripe-Signature' => 't=1,v1=bad'])
        ->assertForbidden();
});
