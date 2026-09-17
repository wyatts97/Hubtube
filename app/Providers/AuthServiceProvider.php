<?php

namespace App\Providers;

use App\Models\Video;
use App\Policies\VideoPolicy;
use App\Models\Comment;
use App\Policies\CommentPolicy;
use App\Models\Playlist;
use App\Policies\PlaylistPolicy;
use App\Models\Setting;
use App\Support\Permissions;
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
        /*
         * Two bypasses for the role permissions introduced with Shield.
         *
         * A super-admin passes everything. So does an admin who holds no roles
         * at all: roles are opt-in, and without this every existing admin
         * would lose the panel the moment the feature shipped.
         *
         * Limited to permission names HubTube manages, so this can never
         * satisfy an unrelated gate such as 'withdraw'.
         */
        Gate::before(function ($user, string $ability) {
            if (! Permissions::isManaged($ability)) {
                return null;
            }

            if ($user->isSuperAdmin()) {
                return true;
            }

            return $user->is_admin && $user->roles->isEmpty() ? true : null;
        });

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
