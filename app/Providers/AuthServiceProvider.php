<?php

namespace App\Providers;

use App\Models\Video;
use App\Policies\VideoPolicy;
use App\Models\Comment;
use App\Policies\CommentPolicy;
use App\Models\Playlist;
use App\Policies\PlaylistPolicy;
use App\Models\Setting;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Video::class => VideoPolicy::class,
        Comment::class => CommentPolicy::class,
        Playlist::class => PlaylistPolicy::class,
    ];

    public function boot(): void
    {
        Gate::define('admin', function ($user) {
            return $user->is_admin;
        });

        Gate::define('upload-video', function ($user) {
            return $user->canUpload();
        });

        Gate::define('withdraw', function ($user) {
            return $user->wallet_balance >= (int) Setting::get('min_withdrawal', 50);
        });

    }
}
