<?php

namespace Tests\Unit;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use App\Services\CCBillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CCBillServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CCBillService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CCBillService();
        // Use CCBill's documented example salt so digests match published vectors.
        Setting::set('ccbill_salt', '7d901dad245fd0ff6bc20d06', 'payments', 'string');
        Setting::set('currency', 'USD', 'payments', 'string');
    }

    /** Matches the non-recurring example on https://ccbill.com/doc/formdigest-value */
    public function test_single_form_digest_matches_ccbill_example(): void
    {
        $digest = $this->service->singleFormDigest('10.00', 30, 840);

        $this->assertSame('a7459445d0e5dc0963fe736dc5cf900b', $digest);
    }

    /** Matches the recurring example on https://ccbill.com/doc/formdigest-value */
    public function test_recurring_form_digest_matches_ccbill_example(): void
    {
        $digest = $this->service->recurringFormDigest('10.00', 30, '10.00', 30, 99, 840);

        $this->assertSame('48f0b12e4307e64edb781c479665c899', $digest);
    }

    public function test_currency_code_maps_alpha_to_numeric(): void
    {
        $this->assertSame(840, $this->service->currencyCode('USD'));
        $this->assertSame(978, $this->service->currencyCode('EUR'));
        $this->assertSame(826, $this->service->currencyCode('GBP'));
        $this->assertSame(840, $this->service->currencyCode('ZZZ')); // fallback
    }

    public function test_money_formats_two_decimals(): void
    {
        $this->assertSame('9.99', $this->service->money(9.99));
        $this->assertSame('10.00', $this->service->money(10));
        $this->assertSame('100.50', $this->service->money('100.5'));
    }

    public function test_build_checkout_url_contains_recurring_params_and_signature(): void
    {
        Setting::set('ccbill_enabled', true, 'payments', 'boolean');
        Setting::set('monetization_enabled', true, 'site', 'boolean');
        Setting::set('ccbill_subaccount', '0000', 'payments', 'string');
        Setting::set('ccbill_flex_id', 'abc-123', 'payments', 'string');
        Setting::set('ccbill_webhook_secret', 'hooksecret', 'payments', 'string');

        $plan = Plan::factory()->create([
            'ccbill_initial_price' => 9.99,
            'ccbill_initial_period' => 30,
            'ccbill_recurring_price' => 9.99,
            'ccbill_recurring_period' => 30,
            'ccbill_num_rebills' => 99,
        ]);
        $user = User::factory()->create();

        $url = $this->service->buildCheckoutUrl($plan, $user);

        $this->assertNotNull($url);
        $this->assertStringContainsString('flexforms/abc-123?', $url);
        $this->assertStringContainsString('initialPrice=9.99', $url);
        $this->assertStringContainsString('recurringPrice=9.99', $url);
        $this->assertStringContainsString('numRebills=99', $url);
        $this->assertStringContainsString('currencyCode=840', $url);
        $this->assertStringContainsString('ht_uid=' . $user->id, $url);
        $this->assertStringContainsString('ht_sig=', $url);
    }

    public function test_build_checkout_url_null_when_plan_missing_pricing(): void
    {
        Setting::set('ccbill_enabled', true, 'payments', 'boolean');
        Setting::set('ccbill_subaccount', '0000', 'payments', 'string');
        Setting::set('ccbill_flex_id', 'abc-123', 'payments', 'string');

        $plan = Plan::factory()->create();
        $user = User::factory()->create();

        $this->assertNull($this->service->buildCheckoutUrl($plan, $user));
    }

    public function test_passthrough_signature_verifies(): void
    {
        Setting::set('ccbill_webhook_secret', 'hooksecret', 'payments', 'string');

        $sig = $this->service->passthroughSignature(5, 2);

        $this->assertTrue($this->service->verifyPassthrough(5, 2, $sig));
        $this->assertFalse($this->service->verifyPassthrough(5, 3, $sig));
    }
}
