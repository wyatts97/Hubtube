<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\CashierConfigServiceProvider;
use App\Providers\DynamicConfigServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\HorizonServiceProvider;
use App\Providers\SocialLoginServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    CashierConfigServiceProvider::class,
    DynamicConfigServiceProvider::class,
    EventServiceProvider::class,
    AdminPanelProvider::class,
    HorizonServiceProvider::class,
    SocialLoginServiceProvider::class,
];
