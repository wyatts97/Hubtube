<?php

namespace App\Providers;

use App\Http\Middleware\SetAdminTimezone;
use App\Models\Category;
use App\Observers\CategoryObserver;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;

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

        Category::observe(CategoryObserver::class);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Event::listen(JobProcessing::class, fn () => SetAdminTimezone::setTimezone());

        Health::checks([
            DatabaseCheck::new(),
            RedisCheck::new(),
            CacheCheck::new(),
            DebugModeCheck::new()->unless(config('app.debug') === false),
            EnvironmentCheck::new()->expectEnvironment(config('app.env')),
            HorizonCheck::new(),
            ScheduleCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(95),
        ]);
    }
}
