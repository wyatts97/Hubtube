<?php

namespace Tests\Feature;

use App\Models\CCBillSubscription;
use App\Models\CCBillWebhookEvent;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\CCBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CCBillWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected CCBillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('ccbill_enabled', true, 'payments', 'boolean');
        Setting::set('monetization_enabled', true, 'site', 'boolean');
        Setting::set('ccbill_webhook_secret', 'hooksecret', 'payments', 'string');
        $this->service = app(CCBillService::class);
    }

    protected function plan(): Plan
    {
        return Plan::factory()->create([
            'slug' => 'pro-monthly',
            'interval' => 'month',
            'ccbill_initial_price' => 9.99,
            'ccbill_initial_period' => 30,
            'ccbill_recurring_price' => 9.99,
            'ccbill_recurring_period' => 30,
            'ccbill_num_rebills' => 99,
        ]);
    }

    public function test_new_sale_success_activates_pro(): void
    {
        $user = User::factory()->create(['is_pro' => false]);
        $plan = $this->plan();

        $response = $this->postJson('/ccbill/webhook', [
            'eventType' => 'NewSaleSuccess',
            'subscriptionId' => '0900000000000012345',
            'timestamp' => '2026-07-07 10:00:00',
            'secret' => 'hooksecret',
            'ht_uid' => $user->id,
            'ht_plan' => $plan->id,
            'ht_sig' => $this->service->passthroughSignature($user->id, $plan->id),
        ]);

        $response->assertOk();
        $this->assertTrue($user->fresh()->is_pro);
        $this->assertDatabaseHas('ccbill_subscriptions', [
            'user_id' => $user->id,
            'ccbill_subscription_id' => '0900000000000012345',
            'status' => 'active',
        ]);
    }

    public function test_webhook_rejects_bad_secret(): void
    {
        $user = User::factory()->create(['is_pro' => false]);

        $this->postJson('/ccbill/webhook', [
            'eventType' => 'NewSaleSuccess',
            'subscriptionId' => 'sub1',
            'secret' => 'wrong',
        ])->assertStatus(403);

        $this->assertFalse($user->fresh()->is_pro);
    }

    public function test_webhook_is_idempotent(): void
    {
        $user = User::factory()->create(['is_pro' => false]);
        $plan = $this->plan();

        $payload = [
            'eventType' => 'NewSaleSuccess',
            'subscriptionId' => 'sub-dup',
            'timestamp' => '2026-07-07 10:00:00',
            'secret' => 'hooksecret',
            'ht_uid' => $user->id,
            'ht_plan' => $plan->id,
            'ht_sig' => $this->service->passthroughSignature($user->id, $plan->id),
        ];

        $this->postJson('/ccbill/webhook', $payload)->assertOk();
        $this->postJson('/ccbill/webhook', $payload)->assertOk();

        $this->assertSame(1, CCBillWebhookEvent::where('event_type', 'NewSaleSuccess')->count());
        $this->assertSame(1, CCBillSubscription::where('ccbill_subscription_id', 'sub-dup')->count());
    }

    public function test_expiration_revokes_pro(): void
    {
        $user = User::factory()->create(['is_pro' => true]);
        $plan = $this->plan();

        CCBillSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'ccbill_subscription_id' => 'sub-exp',
            'status' => 'active',
            'subscription_type' => 'recurring',
            'current_period_end' => now()->addDays(30),
        ]);

        $this->postJson('/ccbill/webhook', [
            'eventType' => 'Expiration',
            'subscriptionId' => 'sub-exp',
            'timestamp' => '2026-07-07 11:00:00',
            'secret' => 'hooksecret',
        ])->assertOk();

        $this->assertFalse($user->fresh()->is_pro);
        $this->assertDatabaseHas('ccbill_subscriptions', [
            'ccbill_subscription_id' => 'sub-exp',
            'status' => 'expired',
        ]);
    }

    public function test_cancellation_keeps_pro_until_period_end(): void
    {
        $user = User::factory()->create(['is_pro' => true]);
        $plan = $this->plan();

        CCBillSubscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'ccbill_subscription_id' => 'sub-cancel',
            'status' => 'active',
            'subscription_type' => 'recurring',
            'current_period_end' => now()->addDays(15),
        ]);

        $this->postJson('/ccbill/webhook', [
            'eventType' => 'Cancellation',
            'subscriptionId' => 'sub-cancel',
            'timestamp' => '2026-07-07 12:00:00',
            'secret' => 'hooksecret',
        ])->assertOk();

        // Still Pro (access retained until current_period_end).
        $this->assertTrue($user->fresh()->is_pro);
        $this->assertDatabaseHas('ccbill_subscriptions', [
            'ccbill_subscription_id' => 'sub-cancel',
            'status' => 'cancelled',
        ]);
    }
}
