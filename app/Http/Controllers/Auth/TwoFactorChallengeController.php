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

        // Get the intended URL from session, or use default based on user role.
        // `intended` originates from an attacker-controllable POST field on the login
        // form, so it is only honoured when it resolves to this application's host.
        $intended = $this->safeIntendedUrl($request->session()->pull('two_factor.intended'));

        if ($user->is_admin) {
            AdminLogger::auth('Admin login (2FA)', ['ip' => $request->ip()]);

            return redirect($intended ?? url('/admin'))->with('success', 'Welcome back!');
        }

        return redirect($intended ?? '/')->with('success', 'Welcome back!');
    }

    /**
     * Restrict a post-login redirect target to this application.
     *
     * Accepts only a root-relative path, or an absolute URL whose host matches
     * the app host. Anything else (another origin, a protocol-relative "//evil",
     * a javascript: URI) is discarded in favour of the caller's default.
     */
    protected function safeIntendedUrl(mixed $intended): ?string
    {
        if (! is_string($intended) || $intended === '') {
            return null;
        }

        // Reject protocol-relative and backslash-obfuscated forms outright.
        if (str_starts_with($intended, '//') || str_starts_with($intended, '\\')) {
            return null;
        }

        if (str_starts_with($intended, '/')) {
            return $intended;
        }

        $host = parse_url($intended, PHP_URL_HOST);

        return $host !== null && $host === parse_url(config('app.url'), PHP_URL_HOST)
            ? $intended
            : null;
    }
}
