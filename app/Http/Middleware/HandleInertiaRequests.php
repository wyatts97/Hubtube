<?php

namespace App\Http\Middleware;

use App\Models\MenuItem;
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
                    'settings' => $request->user()->settings ?? [],
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
            'csrf_token' => csrf_token(),
            'app' => [
                'name' => config('app.name'),
                'age_verification_required' => (bool) Setting::get('age_verification_required', true),
                'infinite_scroll_enabled' => Setting::get('infinite_scroll_enabled', false),
                'videos_per_page' => Setting::get('videos_per_page', 24),
            ],
            'theme' => fn () => $this->getThemeSettings(),
            'menuItems' => fn () => $this->getMenuItems(),
        ];
    }

    protected function getThemeSettings(): array
    {
        return [
            // Site Title Settings
            'siteTitle' => Setting::get('site_title', 'HubTube'),
            'siteTitleFont' => Setting::get('site_title_font', ''),
            'siteTitleSize' => Setting::get('site_title_size', 20),
            'siteTitleColor' => Setting::get('site_title_color', ''),
            
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
            'ageVerification' => [
                'overlayColor' => Setting::get('age_overlay_color', 'rgba(0, 0, 0, 0.85)'),
                'overlayBlur' => (int) Setting::get('age_overlay_blur', 8),
                'showLogo' => (bool) Setting::get('age_show_logo', false),
                'logoUrl' => Setting::get('age_logo_url', ''),
                'headerText' => Setting::get('age_header_text', 'Age Verification Required'),
                'headerSize' => (int) Setting::get('age_header_size', 28),
                'headerColor' => Setting::get('age_header_color', ''),
                'descriptionText' => Setting::get('age_description_text', 'This website contains age-restricted content. You must be at least 18 years old to enter.'),
                'disclaimerText' => Setting::get('age_disclaimer_text', 'By clicking "{confirm}", you confirm that you are at least 18 years of age and consent to viewing adult content.'),
                'confirmText' => Setting::get('age_confirm_text', 'I am 18 or older'),
                'declineText' => Setting::get('age_decline_text', 'Exit'),
                'termsText' => Setting::get('age_terms_text', 'By entering this site, you agree to our'),
                'buttonColor' => Setting::get('age_button_color', ''),
                'textColor' => Setting::get('age_text_color', ''),
                'fontFamily' => Setting::get('age_font_family', ''),
            ],
            'categoryTypography' => [
                'font' => Setting::get('category_title_font', ''),
                'size' => Setting::get('category_title_size', 18),
                'color' => Setting::get('category_title_color', '#ffffff'),
                'opacity' => Setting::get('category_title_opacity', 90),
            ],
        ];
    }

    protected function getMenuItems(): array
    {
        try {
            $items = MenuItem::getMenuTree('both');
            $headerOnly = MenuItem::getMenuTree('header');
            $mobileOnly = MenuItem::getMenuTree('mobile');

            return [
                'header' => $items->merge($headerOnly)->sortBy('sort_order')->values()->toArray(),
                'mobile' => $items->merge($mobileOnly)->sortBy('sort_order')->values()->toArray(),
            ];
        } catch (\Exception $e) {
            // Table may not exist yet (pre-migration)
            return ['header' => [], 'mobile' => []];
        }
    }
}
