<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \App\Models\Video::class => \App\Policies\VideoPolicy::class,
        \App\Models\Comment::class => \App\Policies\CommentPolicy::class,
        \App\Models\Playlist::class => \App\Policies\PlaylistPolicy::class,
        \App\Models\LiveStream::class => \App\Policies\LiveStreamPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin', function ($user) {
            return $user->is_admin;
        });

        Gate::define('upload-video', function ($user) {
            return $user->canUpload();
        });

        Gate::define('go-live', function ($user) {
            return $user->canGoLive() && $user->isAgeVerified();
        });

        Gate::define('withdraw', function ($user) {
            return $user->wallet_balance >= (int) Setting::get('min_withdrawal', 50);
        });
    }
}
