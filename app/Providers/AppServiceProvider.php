<?php

namespace App\Providers;

use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class,
        );

    }

    public function boot(): void
    {
        Model::shouldBeStrict(!$this->app->isProduction());
        
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }
}
