<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use PragmaRX\Google2FAQRCode\Google2FA;

class TwoFactorAuthenticationService
{
    protected Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    /**
     * Generate a new random secret (not yet persisted).
     */
    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    /**
     * Build the otpauth:// QR code SVG markup for the given user + secret.
     */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $companyName = config('app.name', 'HubTube');

        return $this->engine->getQRCodeInline(
            $companyName,
            $user->email,
            $secret
        );
    }

    /**
     * Verify a 6-digit TOTP code against the given secret.
     */
    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        return $this->engine->verifyKey($secret, $code, 4);
    }

    /**
     * Get the current valid TOTP for a secret (used in tests/tooling).
     */
    public function getCurrentOtp(string $secret): string
    {
        return $this->engine->getCurrentOtp($secret);
    }

    /**
     * Generate a fresh batch of one-time recovery codes (plaintext, to be
     * shown to the user once and stored hashed/encrypted).
     *
     * @return array<int, string>
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect(range(1, $count))
            ->map(fn () => Str::random(10) . '-' . Str::random(10))
            ->all();
    }
}
