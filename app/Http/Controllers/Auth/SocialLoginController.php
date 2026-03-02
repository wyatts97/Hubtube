<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\ChannelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialLoginController extends Controller
{
    protected array $supportedProviders = ['google', 'twitter', 'reddit'];

    public function redirect(string $provider): RedirectResponse
    {
        if (!$this->isProviderEnabled($provider)) {
            return redirect()->route('login')->with('error', 'This login method is not available.');
        }

        $driver = Socialite::driver($this->resolveDriverName($provider));

        // Reddit requires 'identity' scope and duration=permanent for refresh tokens
        if ($provider === 'reddit') {
            $driver->scopes(['identity']);
        }

        return $driver->redirect();
    }

    public function callback(string $provider): RedirectResponse
    {
        if (!$this->isProviderEnabled($provider)) {
            return redirect()->route('login')->with('error', 'This login method is not available.');
        }

        try {
            $socialUser = Socialite::driver($this->resolveDriverName($provider))->user();
        } catch (\Exception $e) {
            Log::warning("Social login callback failed for {$provider}", ['error' => $e->getMessage()]);
            return redirect()->route('login')->with('error', 'Authentication failed. Please try again.');
        }

        $providerId = $socialUser->getId();
        $email = $socialUser->getEmail();
        $name = $socialUser->getName() ?? $socialUser->getNickname() ?? '';
        $avatar = $socialUser->getAvatar();
        $token = $socialUser->token ?? null;
        $refreshToken = $socialUser->refreshToken ?? null;

        // 1. Check if this social account already exists
        $socialAccount = SocialAccount::where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        if ($socialAccount) {
            // Update token info
            $socialAccount->update([
                'provider_token' => $token,
                'provider_refresh_token' => $refreshToken,
                'avatar_url' => $avatar,
            ]);

            Auth::login($socialAccount->user, true);
            return redirect()->intended('/');
        }

        // 2. If user is already logged in, link the social account
        if (Auth::check()) {
            SocialAccount::create([
                'user_id' => Auth::id(),
                'provider' => $provider,
                'provider_id' => $providerId,
                'provider_token' => $token,
                'provider_refresh_token' => $refreshToken,
                'avatar_url' => $avatar,
            ]);

            return redirect()->route('settings')->with('success', ucfirst($provider) . ' account linked successfully.');
        }

        // 3. Check if email exists in users table (link + login)
        if ($email) {
            $existingUser = User::where('email', $email)->first();

            if ($existingUser) {
                SocialAccount::create([
                    'user_id' => $existingUser->id,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                    'provider_token' => $token,
                    'provider_refresh_token' => $refreshToken,
                    'avatar_url' => $avatar,
                ]);

                Auth::login($existingUser, true);
                return redirect()->intended('/');
            }
        }

        // 4. Create new user + channel
        $username = $this->generateUniqueUsername($name, $email);

        $user = User::create([
            'username' => $username,
            'email' => $email ?? $provider . '_' . $providerId . '@social.local',
            'password' => Hash::make(Str::random(32)),
            'avatar' => $avatar,
            'email_verified_at' => $email ? now() : null,
        ]);

        ChannelService::createForUser($user);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $providerId,
            'provider_token' => $token,
            'provider_refresh_token' => $refreshToken,
            'avatar_url' => $avatar,
        ]);

        Auth::login($user, true);

        return redirect()->route('home')->with('success', 'Welcome to HubTube!');
    }

    protected function isProviderEnabled(string $provider): bool
    {
        if (!in_array($provider, $this->supportedProviders)) {
            return false;
        }

        return (bool) Setting::get("social_login_{$provider}_enabled", false);
    }

    /**
     * Resolve the Socialite driver name.
     * Twitter/X uses 'twitter-oauth-2' in Socialite v5+.
     */
    protected function resolveDriverName(string $provider): string
    {
        if ($provider === 'twitter') {
            return 'twitter-oauth-2';
        }

        return $provider;
    }

    protected function generateUniqueUsername(string $name, ?string $email): string
    {
        // Try name-based username first
        $base = Str::slug($name, '_');

        if (empty($base) && $email) {
            $base = Str::before($email, '@');
            $base = Str::slug($base, '_');
        }

        if (empty($base)) {
            $base = 'user';
        }

        // Truncate to 28 chars to leave room for suffix
        $base = Str::limit($base, 28, '');

        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }

        return $username;
    }
}
