<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

class SocialLoginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            $this->configureSocialProviders();
        } catch (\Throwable $e) {
            // Database may not be available during boot (e.g. fresh install)
        }
    }

    protected function configureSocialProviders(): void
    {
        $providers = ['google', 'twitter', 'reddit'];

        foreach ($providers as $provider) {
            $clientId = Setting::getDecrypted("social_login_{$provider}_client_id", '');
            $clientSecret = Setting::getDecrypted("social_login_{$provider}_client_secret", '');

            if (!empty($clientId) && !empty($clientSecret)) {
                $configKey = $provider === 'twitter' ? 'twitter-oauth-2' : $provider;

                config([
                    "services.{$configKey}.client_id" => $clientId,
                    "services.{$configKey}.client_secret" => $clientSecret,
                ]);
            }
        }
    }
}
