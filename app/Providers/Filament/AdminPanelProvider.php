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
    protected static function buildNavigationGroups(): array
    {
        $defaults = [
            ['key' => 'Content',          'collapsed' => false, 'sort' => 1],
            ['key' => 'Users & Messages', 'collapsed' => false, 'sort' => 2],
            ['key' => 'Monetization',     'collapsed' => false, 'sort' => 3],
            ['key' => 'Appearance',       'collapsed' => false, 'sort' => 4],
            ['key' => 'Integrations',     'collapsed' => false, 'sort' => 5],
            ['key' => 'System',           'collapsed' => true,  'sort' => 6],
            ['key' => 'Tools',            'collapsed' => true,  'sort' => 7],
        ];

        try {
            $raw = \App\Models\Setting::get('admin_nav_config', null);
            if ($raw) {
                $config = json_decode($raw, true);
                if (is_array($config)) {
                    $byKey = collect($config)->keyBy('key');
                    foreach ($defaults as &$d) {
                        if ($byKey->has($d['key'])) {
                            $saved = $byKey[$d['key']];
                            $d['collapsed'] = (bool) ($saved['collapsed'] ?? $d['collapsed']);
                            $d['sort']      = (int)  ($saved['sort']      ?? $d['sort']);
                        }
                    }
                    unset($d);
                    usort($defaults, fn ($a, $b) => $a['sort'] <=> $b['sort']);
                }
            }
        } catch (\Throwable) {}

        return array_map(function (array $g) {
            $group = NavigationGroup::make($g['key']);
            if ($g['collapsed']) {
                $group->collapsed();
            }
            return $group;
        }, $defaults);
    }

    public function panel(Panel $panel): Panel
    {
        // Resolve site logo for admin panel branding
        $brandLogo = null;
        $brandName = 'HubTube';
        try {
            $siteLogo = Setting::get('site_logo', '');
            if ($siteLogo) {
                // Use same URL resolution as frontend: /storage/ prefix for relative paths
                if (str_starts_with($siteLogo, 'http://') || str_starts_with($siteLogo, 'https://') || str_starts_with($siteLogo, '/')) {
                    $brandLogo = $siteLogo;
                } else {
                    $brandLogo = '/storage/' . $siteLogo;
                }
            }
            $brandName = Setting::get('site_title', 'HubTube') ?: 'HubTube';
        } catch (\Throwable $e) {
            // Database may not be available during boot
        }

        // Resolve favicon for admin panel
        $faviconUrl = null;
        try {
            $siteFavicon = Setting::get('site_favicon', '');
            if ($siteFavicon) {
                if (str_starts_with($siteFavicon, 'http://') || str_starts_with($siteFavicon, 'https://') || str_starts_with($siteFavicon, '/')) {
                    $faviconUrl = $siteFavicon;
                } else {
                    $faviconUrl = '/storage/' . $siteFavicon;
                }
            }
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

        if ($faviconUrl) {
            $builder = $builder->favicon($faviconUrl);
        }

        if ($brandLogo) {
            $builder = $builder
                ->brandLogo($brandLogo)
                ->brandLogoHeight('2rem');
        }

        return $builder
            ->login()
            ->profile()
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
            ->navigationGroups(static::buildNavigationGroups())
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
