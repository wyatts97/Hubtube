<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Events\WebhookReceived;
use Tests\TestCase;

class ProSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::factory()->create(['slug' => 'pro-monthly', 'interval' => 'month', 'stripe_price_id' => 'price_monthly']);
        Plan::factory()->create(['slug' => 'pro-annual', 'interval' => 'year', 'stripe_price_id' => 'price_annual']);
    }

    protected function webhookPayload(string $type, string $customerId, string $status): array
    {
        return [
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'sub_' . uniqid(),
                    'customer' => $customerId,
                    'status' => $status,
                ],
            ],
        ];
    }

    public function test_subscription_created_event_marks_user_as_pro(): void
    {
        $user = User::factory()->create(['is_pro' => false, 'stripe_id' => 'cus_123']);

        event(new WebhookReceived($this->webhookPayload('customer.subscription.created', 'cus_123', 'active')));

        $this->assertTrue($user->fresh()->is_pro);
    }

    public function test_subscription_updated_event_syncs_pro_status(): void
    {
        $user = User::factory()->create(['is_pro' => true, 'stripe_id' => 'cus_123']);

        event(new WebhookReceived($this->webhookPayload('customer.subscription.updated', 'cus_123', 'canceled')));

        $this->assertFalse($user->fresh()->is_pro);
    }

    public function test_past_due_subscription_keeps_pro_status(): void
    {
        $user = User::factory()->create(['is_pro' => true, 'stripe_id' => 'cus_123']);

        event(new WebhookReceived($this->webhookPayload('customer.subscription.updated', 'cus_123', 'past_due')));

        $this->assertTrue($user->fresh()->is_pro);
    }

    public function test_subscription_deleted_event_removes_pro_status(): void
    {
        $user = User::factory()->create(['is_pro' => true, 'stripe_id' => 'cus_123']);

        event(new WebhookReceived($this->webhookPayload('customer.subscription.deleted', 'cus_123', 'canceled')));

        $this->assertFalse($user->fresh()->is_pro);
    }

    public function test_listener_is_idempotent(): void
    {
        $user = User::factory()->create(['is_pro' => true, 'stripe_id' => 'cus_123']);

        event(new WebhookReceived($this->webhookPayload('customer.subscription.created', 'cus_123', 'active')));
        event(new WebhookReceived($this->webhookPayload('customer.subscription.created', 'cus_123', 'active')));
        event(new WebhookReceived($this->webhookPayload('customer.subscription.updated', 'cus_123', 'active')));

        $this->assertTrue($user->fresh()->is_pro);
    }
}
