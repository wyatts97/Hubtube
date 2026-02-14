<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Pages\Page;
use Illuminate\Http\RedirectResponse;

class ActivityLog extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.activity-log';

    public function mount(): RedirectResponse
    {
        return redirect(ActivityLogResource::getUrl('index'));
    }
}
