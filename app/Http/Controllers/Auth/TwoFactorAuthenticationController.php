<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TwoFactorAuthenticationController extends Controller
{
    public function __construct(protected TwoFactorAuthenticationService $twoFactor)
    {
    }

    /**
     * Generate (or regenerate) an unconfirmed secret and return the QR code
     * for the user to scan in their authenticator app.
     */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $secret = $this->twoFactor->generateSecretKey();

            $user->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();

            return response()->json([
                'qr_code_svg' => $this->twoFactor->qrCodeSvg($user, $secret),
                'secret' => $secret,
            ]);
        } catch (\Throwable $e) {
            Log::error('2FA enable failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Two-factor setup could not be started. Please ensure the server dependencies are installed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirm 2FA setup by verifying a TOTP code, then issue recovery codes.
     */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            throw ValidationException::withMessages([
                'code' => 'Please start the two-factor setup process first.',
            ]);
        }

        if (!$this->twoFactor->verify($user->two_factor_secret, $request->string('code'))) {
            throw ValidationException::withMessages([
                'code' => 'The provided code is invalid.',
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return response()->json([
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Disable two-factor authentication (requires current password).
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        $user = $request->user();

        if (!Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'The provided password is incorrect.',
            ]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return response()->json(['success' => true]);
    }

    /**
     * Regenerate recovery codes (invalidates the previous set).
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            throw ValidationException::withMessages([
                'code' => 'Two-factor authentication is not enabled.',
            ]);
        }

        $recoveryCodes = $this->twoFactor->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $recoveryCodes])->save();

        return response()->json(['recovery_codes' => $recoveryCodes]);
    }

    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'enabled' => $request->user()->hasTwoFactorEnabled(),
        ]);
    }
}
