<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\VideoUploaded::class => [
            \App\Listeners\ProcessVideoUpload::class,
        ],
        \App\Events\VideoProcessed::class => [
            \App\Listeners\NotifyVideoProcessed::class,
            \App\Listeners\TweetNewVideoListener::class,
        ],
        \App\Events\GiftSent::class => [
            \App\Listeners\ProcessGiftTransaction::class,
        ],
        \App\Events\LiveStreamStarted::class => [
            \App\Listeners\NotifySubscribersOfLiveStream::class,
        ],
        \App\Events\NewSubscriber::class => [
            \App\Listeners\NotifyChannelOfNewSubscriber::class,
        ],
        \SocialiteProviders\Manager\SocialiteWasCalled::class => [
            \SocialiteProviders\Reddit\RedditExtendSocialite::class . '@handle',
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
