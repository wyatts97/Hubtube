<?php

namespace App\Providers;

use App\Events\VideoUploaded;
use App\Listeners\ProcessVideoUpload;
use App\Events\VideoProcessed;
use App\Listeners\NotifyVideoProcessed;
use App\Listeners\TweetNewVideoListener;
use App\Listeners\SubmitVideoToIndexNowListener;
use App\Events\NewSubscriber;
use App\Listeners\NotifyChannelOfNewSubscriber;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Reddit\RedditExtendSocialite;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Cashier\Events\WebhookReceived;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        VideoUploaded::class => [
            ProcessVideoUpload::class,
        ],
        VideoProcessed::class => [
            NotifyVideoProcessed::class,
            TweetNewVideoListener::class,
            SubmitVideoToIndexNowListener::class,
        ],
        NewSubscriber::class => [
            NotifyChannelOfNewSubscriber::class,
        ],
        SocialiteWasCalled::class => [
            RedditExtendSocialite::class . '@handle',
        ],
        WebhookReceived::class => [
            \App\Listeners\HandleStripeSubscriptionChanges::class,
        ],
    ];

    public function boot(): void
    {
        //
    }

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
