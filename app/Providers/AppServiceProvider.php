<?php

namespace App\Providers;

use App\Http\Middleware\SetAdminTimezone;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\User;
use App\Models\Video;
use App\Observers\CategoryObserver;
use App\Observers\ChannelObserver;
use App\Observers\GalleryObserver;
use App\Observers\ImageObserver;
use App\Observers\UserObserver;
use App\Observers\VideoObserver;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\ServiceProvider;
use App\Services\Translation\Contracts\TranslationProvider;
use App\Services\Translation\TranslationProviderManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use STS\FilamentImpersonate\Events\EnterImpersonation;
use STS\FilamentImpersonate\Events\LeaveImpersonation;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use App\Health\Checks\TranslationQueueCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
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

        $this->app->singleton(TranslationProviderManager::class);

        // scoped(), not singleton(): queue workers and Octane reuse the
        // container across jobs, and a provider swapped in the admin panel must
        // take effect on the next job rather than the next deploy.
        $this->app->scoped(
            TranslationProvider::class,
            fn ($app) => $app->make(TranslationProviderManager::class)->default(),
        );
    }

    public function boot(): void
    {
        Model::shouldBeStrict(!$this->app->isProduction());

        Category::observe(CategoryObserver::class);
        User::observe(UserObserver::class);

        // Alt text generation. These observers fill the *_alt_text columns on
        // save whenever they are blank, so newly uploaded media never needs a
        // manual backfill run. See App\Services\AltTextService.
        Video::observe(VideoObserver::class);
        Image::observe(ImageObserver::class);
        Gallery::observe(GalleryObserver::class);
        Channel::observe(ChannelObserver::class);

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        Event::listen(JobProcessing::class, fn () => SetAdminTimezone::setTimezone());

        // Log admin impersonation start/stop to the activity log for auditability.
        //
        // AdminLogger::log() attributes the entry to Auth::user(), but by the
        // time these events fire the session's auth identity has already been
        // swapped to the impersonated user (see ImpersonateManager::enter()),
        // so Auth::user() would misattribute the action to the target. Call
        // activity() directly instead, with causedBy() forced to the real
        // admin from the event payload.
        Event::listen(EnterImpersonation::class, function (EnterImpersonation $event) {
            activity('admin')
                ->causedBy($event->impersonator)
                ->performedOn($event->impersonated)
                ->withProperties([
                    'impersonator_id' => $event->impersonator->getAuthIdentifier(),
                    'impersonator_username' => $event->impersonator->username ?? null,
                    'impersonated_id' => $event->impersonated->getAuthIdentifier(),
                    'impersonated_username' => $event->impersonated->username ?? null,
                ])
                ->log("Impersonated user \"{$event->impersonated->username}\" (#{$event->impersonated->getAuthIdentifier()})");
        });

        Event::listen(LeaveImpersonation::class, function (LeaveImpersonation $event) {
            $logger = activity('admin')
                ->causedBy($event->impersonator)
                ->withProperties([
                    'impersonator_id' => $event->impersonator->getAuthIdentifier(),
                    'impersonator_username' => $event->impersonator->username ?? null,
                    'impersonated_id' => $event->impersonated?->getAuthIdentifier(),
                    'impersonated_username' => $event->impersonated->username ?? null,
                ]);

            if ($event->impersonated) {
                $logger->performedOn($event->impersonated);
            }

            $logger->log('Stopped impersonating user' . ($event->impersonated ? " \"{$event->impersonated->username}\" (#{$event->impersonated->getAuthIdentifier()})" : ''));
        });

        Health::checks([
            DatabaseCheck::new(),
            RedisCheck::new(),
            CacheCheck::new(),
            DebugModeCheck::new()->unless(config('app.debug') === false),
            EnvironmentCheck::new()->expectEnvironment(config('app.env')),
            HorizonCheck::new(),
            // Proves jobs are actually draining, not just that Horizon is up.
            // Needs `health:queue-check-heartbeat` on the scheduler.
            QueueCheck::new(),
            ScheduleCheck::new(),
            // Content translation is queued, so a stalled worker fails silently
            // — pages keep rendering the source language.
            TranslationQueueCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(80)
                ->failWhenUsedSpaceIsAbovePercentage(95),
        ]);
    }
}
