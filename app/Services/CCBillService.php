<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * CCBill FlexForms + Webhooks integration.
 *
 * All credentials are read from the DB-backed Setting model (never .env), matching
 * the app-wide convention. This service builds signed FlexForms "Dynamic Pricing"
 * checkout URLs and verifies inbound webhook posts.
 *
 * Docs:
 *  - FlexForms formDigest: https://ccbill.com/doc/formdigest-value
 *  - Dynamic Pricing:      https://ccbill.com/doc/dynamic-pricing-user-guide
 *  - Webhooks:             https://ccbill.com/doc/webhooks-overview
 */
class CCBillService
{
    /** FlexForms Dynamic Pricing base endpoint. */
    public const FLEXFORMS_BASE = 'https://api.ccbill.com/wap-frontflex/flexforms';

    /**
     * ISO-4217 alphabetic -> numeric currency codes accepted by CCBill.
     */
    protected const CURRENCY_MAP = [
        'USD' => 840,
        'EUR' => 978,
        'GBP' => 826,
        'CAD' => 124,
        'AUD' => 36,
        'JPY' => 392,
    ];

    public function isEnabled(): bool
    {
        return (bool) Setting::get('ccbill_enabled', false)
            && (bool) Setting::get('monetization_enabled', true);
    }

    /**
     * Whether CCBill should be the default Pro checkout gateway (over Stripe).
     */
    public function isPrimary(): bool
    {
        return $this->isEnabled() && (bool) Setting::get('ccbill_primary', false);
    }

    /**
     * Whether all required credentials are present to actually process a payment.
     */
    public function isConfigured(): bool
    {
        return $this->isEnabled()
            && $this->flexId() !== ''
            && $this->salt() !== ''
            && $this->subAccount() !== '';
    }

    public function account(): string
    {
        return (string) Setting::get('ccbill_account', '');
    }

    public function subAccount(): string
    {
        return (string) Setting::get('ccbill_subaccount', '');
    }

    public function flexId(): string
    {
        return (string) Setting::get('ccbill_flex_id', '');
    }

    public function salt(): string
    {
        return Setting::getDecrypted('ccbill_salt', '');
    }

    public function webhookSecret(): string
    {
        return Setting::getDecrypted('ccbill_webhook_secret', '');
    }

    /**
     * Numeric ISO-4217 currency code from the configured (or given) currency.
     */
    public function currencyCode(?string $alpha = null): int
    {
        $alpha = strtoupper($alpha ?? (string) Setting::get('currency', 'USD'));

        return self::CURRENCY_MAP[$alpha] ?? 840;
    }

    /**
     * Build the single-billing (non-recurring) formDigest.
     * md5(initialPrice . initialPeriod . currencyCode . salt)
     */
    public function singleFormDigest(string $initialPrice, int $initialPeriod, int $currencyCode): string
    {
        return md5($initialPrice . $initialPeriod . $currencyCode . $this->salt());
    }

    /**
     * Build the recurring formDigest.
     * md5(initialPrice . initialPeriod . recurringPrice . recurringPeriod . numRebills . currencyCode . salt)
     */
    public function recurringFormDigest(
        string $initialPrice,
        int $initialPeriod,
        string $recurringPrice,
        int $recurringPeriod,
        int $numRebills,
        int $currencyCode
    ): string {
        return md5(
            $initialPrice
            . $initialPeriod
            . $recurringPrice
            . $recurringPeriod
            . $numRebills
            . $currencyCode
            . $this->salt()
        );
    }

    /**
     * Build a signed FlexForms Dynamic Pricing checkout URL for the given plan/user.
     *
     * Returns null when CCBill is not fully configured or the plan lacks CCBill pricing.
     */
    public function buildCheckoutUrl(Plan $plan, User $user): ?string
    {
        if (! $this->isConfigured() || ! $plan->hasCCBillPricing()) {
            return null;
        }

        $currencyCode = $this->currencyCode();
        $initialPrice = $this->money($plan->ccbill_initial_price);
        $initialPeriod = (int) $plan->ccbill_initial_period;

        $params = [
            'clientSubacc' => $this->subAccount(),
            'initialPrice' => $initialPrice,
            'initialPeriod' => $initialPeriod,
            'currencyCode' => $currencyCode,
        ];

        $isRecurring = $plan->ccbill_recurring_price !== null && $plan->ccbill_recurring_period !== null;

        if ($isRecurring) {
            $recurringPrice = $this->money($plan->ccbill_recurring_price);
            $recurringPeriod = (int) $plan->ccbill_recurring_period;
            $numRebills = (int) ($plan->ccbill_num_rebills ?? 99);

            $params['recurringPrice'] = $recurringPrice;
            $params['recurringPeriod'] = $recurringPeriod;
            $params['numRebills'] = $numRebills;
            $params['formDigest'] = $this->recurringFormDigest(
                $initialPrice,
                $initialPeriod,
                $recurringPrice,
                $recurringPeriod,
                $numRebills,
                $currencyCode
            );
        } else {
            $params['formDigest'] = $this->singleFormDigest($initialPrice, $initialPeriod, $currencyCode);
        }

        // Signed pass-through fields so the webhook can trust who paid and for what.
        // CCBill echoes unknown custom variables back in webhook posts.
        $params['ht_uid'] = $user->id;
        $params['ht_plan'] = $plan->id;
        $params['ht_sig'] = $this->passthroughSignature($user->id, $plan->id);

        return self::FLEXFORMS_BASE . '/' . rawurlencode($this->flexId()) . '?' . http_build_query($params);
    }

    /**
     * Deterministic HMAC binding (user, plan) to the webhook secret. Used to attribute
     * webhook events to the correct user since legacy CCBill webhooks are unsigned.
     */
    public function passthroughSignature(int $userId, int $planId): string
    {
        return hash_hmac('sha256', $userId . '|' . $planId, $this->webhookSecret());
    }

    /**
     * Verify an inbound webhook request.
     *
     * Security layers (legacy Webhooks/Background-Post has no native signature):
     *  1. Shared secret token (query param `secret` or `ht_secret`), if configured.
     *  2. Optional source-IP allowlist (comma-separated setting `ccbill_webhook_ips`).
     */
    public function verifyWebhook(Request $request): bool
    {
        $secret = $this->webhookSecret();

        if ($secret !== '') {
            $provided = (string) ($request->input('secret', $request->input('ht_secret', '')));
            if (! hash_equals($secret, $provided)) {
                Log::warning('CCBill webhook rejected: bad secret', ['ip' => $request->ip()]);
                return false;
            }
        }

        $allowlist = trim((string) Setting::get('ccbill_webhook_ips', ''));
        if ($allowlist !== '') {
            $ips = array_filter(array_map('trim', explode(',', $allowlist)));
            if (! empty($ips) && ! in_array($request->ip(), $ips, true)) {
                Log::warning('CCBill webhook rejected: IP not allowlisted', ['ip' => $request->ip()]);
                return false;
            }
        }

        return true;
    }

    /**
     * Confirm the pass-through signature matches for the given user/plan.
     */
    public function verifyPassthrough(int $userId, int $planId, string $signature): bool
    {
        if ($this->webhookSecret() === '' || $signature === '') {
            return false;
        }

        return hash_equals($this->passthroughSignature($userId, $planId), $signature);
    }

    /**
     * Format a monetary value as the 2-decimal string CCBill expects (e.g. "9.99").
     */
    public function money(int|float|string $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
