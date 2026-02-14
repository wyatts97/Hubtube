<?php

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateFilament;
use App\Models\Setting;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Resolve site logo for admin panel branding
        $brandLogo = null;
        $brandName = 'HubTube';
        try {
            $siteLogo = Setting::get('site_logo', '');
            if ($siteLogo) {
                $brandLogo = Storage::disk('public')->url($siteLogo);
            }
            $brandName = Setting::get('site_title', 'HubTube') ?: 'HubTube';
        } catch (\Throwable $e) {
            // Database may not be available during boot
        }

        $builder = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->darkMode(true, true)
            ->brandName($brandName)
            ->colors([
                'primary' => Color::Rose,
            ]);

        if ($brandLogo) {
            $builder = $builder
                ->brandLogo($brandLogo)
                ->brandLogoHeight('2rem');
        }

        return $builder
            ->userMenuItems([
                MenuItem::make()
                    ->label('View Site')
                    ->url('/')
                    ->icon('heroicon-o-globe-alt'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                function (): string {
                    try {
                        return view('filament.widgets.system-status-bar', [
                            'metrics' => app(\App\Services\SystemStatusBar::class)->getMetrics(),
                        ])->render();
                    } catch (\Throwable $e) {
                        return '';
                    }
                },
            )
            ->navigationGroups([
                NavigationGroup::make('Content'),
                NavigationGroup::make('Users & Messages'),
                NavigationGroup::make('Monetization'),
                NavigationGroup::make('Appearance'),
                NavigationGroup::make('System')
                    ->collapsed(),
                NavigationGroup::make('Tools')
                    ->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                AuthenticateFilament::class,
            ])
            ->authGuard('web');
    }
}
