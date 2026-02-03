<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'username' => $request->user()->username,
                    'email' => $request->user()->email,
                    'avatar' => $request->user()->avatar,
                    'is_verified' => $request->user()->is_verified,
                    'is_pro' => $request->user()->is_pro,
                    'is_admin' => $request->user()->is_admin,
                    'wallet_balance' => $request->user()->wallet_balance,
                    'age_verified' => $request->user()->isAgeVerified(),
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'app' => [
                'name' => config('app.name'),
                'age_verification_required' => config('hubtube.age_verification_required'),
                'infinite_scroll_enabled' => Setting::get('infinite_scroll_enabled', false),
                'videos_per_page' => Setting::get('videos_per_page', 24),
            ],
            'theme' => fn () => $this->getThemeSettings(),
        ];
    }

    protected function getThemeSettings(): array
    {
        return [
            'mode' => Setting::get('theme_mode', 'dark'),
            'allowToggle' => Setting::get('allow_user_theme_toggle', true),
            'dark' => [
                'bgPrimary' => Setting::get('dark_bg_primary', '#0a0a0a'),
                'bgSecondary' => Setting::get('dark_bg_secondary', '#171717'),
                'bgCard' => Setting::get('dark_bg_card', '#1f1f1f'),
                'accent' => Setting::get('dark_accent_color', '#ef4444'),
                'textPrimary' => Setting::get('dark_text_primary', '#ffffff'),
                'textSecondary' => Setting::get('dark_text_secondary', '#a3a3a3'),
                'border' => Setting::get('dark_border_color', '#262626'),
            ],
            'light' => [
                'bgPrimary' => Setting::get('light_bg_primary', '#ffffff'),
                'bgSecondary' => Setting::get('light_bg_secondary', '#f5f5f5'),
                'bgCard' => Setting::get('light_bg_card', '#ffffff'),
                'accent' => Setting::get('light_accent_color', '#dc2626'),
                'textPrimary' => Setting::get('light_text_primary', '#171717'),
                'textSecondary' => Setting::get('light_text_secondary', '#525252'),
                'border' => Setting::get('light_border_color', '#e5e5e5'),
            ],
            'icons' => [
                'colorMode' => Setting::get('icon_color_mode', 'inherit'),
                'globalColor' => Setting::get('icon_global_color', ''),
                'home' => ['icon' => Setting::get('nav_home_icon', 'home'), 'color' => Setting::get('nav_home_color', '')],
                'trending' => ['icon' => Setting::get('nav_trending_icon', 'trending-up'), 'color' => Setting::get('nav_trending_color', '')],
                'shorts' => ['icon' => Setting::get('nav_shorts_icon', 'zap'), 'color' => Setting::get('nav_shorts_color', '')],
                'live' => ['icon' => Setting::get('nav_live_icon', 'radio'), 'color' => Setting::get('nav_live_color', '')],
                'playlists' => ['icon' => Setting::get('nav_playlists_icon', 'list-video'), 'color' => Setting::get('nav_playlists_color', '')],
                'history' => ['icon' => Setting::get('nav_history_icon', 'history'), 'color' => Setting::get('nav_history_color', '')],
            ],
        ];
    }
}
