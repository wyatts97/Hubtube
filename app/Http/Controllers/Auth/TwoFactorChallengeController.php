<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdminLogger;
use App\Services\TwoFactorAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(protected TwoFactorAuthenticationService $twoFactor)
    {
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if (!$request->session()->has('two_factor.user_id')) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('two_factor.user_id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $throttleKey = 'two-factor-challenge:' . $userId . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'code' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $request->validate(['code' => ['required', 'string']]);

        $user = User::find($userId);

        if (!$user) {
            return redirect()->route('login');
        }

        $code = trim($request->string('code'));
        $verified = $this->twoFactor->verify($user->two_factor_secret, $code)
            || $user->consumeRecoveryCode($code);

        if (!$verified) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'code' => 'The provided code is invalid.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $remember = $request->session()->pull('two_factor.remember', false);
        $request->session()->forget('two_factor.user_id');

        Auth::login($user, $remember);
        $request->session()->regenerate();

        $user->update(['last_active_at' => now()]);

        if ($user->is_admin) {
            AdminLogger::auth('Admin login (2FA)', ['ip' => $request->ip()]);

            return redirect()->intended(url('/admin'))->with('success', 'Welcome back!');
        }

        return redirect()->intended(route('home'))->with('success', 'Welcome back!');
    }
}
