<?php

namespace App\Http\Middleware;

use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /**
     * Skip Inertia entirely for admin/Filament routes so Livewire
     * navigation works without Inertia interference.
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        if ($request->is('admin/*') || $request->is('admin') || $request->is('livewire/*')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? (function () use ($request) {
            $user = $request->user();
            $user->loadMissing('channel');
            return [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'bio' => $user->bio,
                        'avatar' => $user->avatar_url,
                        'is_verified' => $user->is_verified,
                        'is_pro' => $user->is_pro,
                        'is_admin' => $user->is_admin,
                        'wallet_balance' => $user->wallet_balance,
                        'age_verified' => $user->isAgeVerified(),
                        'can_edit_video' => $user->canEditVideo(),
                        'settings' => $user->settings ?? [],
                        'channel' => $user->channel ? [
                            'id' => $user->channel->id,
                            'name' => $user->channel->name,
                            'banner_image' => $user->channel->banner_image,
                        ] : null,
                    ];
        })() : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'warning' => fn() => $request->session()->get('warning'),
                'info' => fn() => $request->session()->get('info'),
            ],
            'csrf_token' => csrf_token(),
            'app' => fn() => $this->getAppSettings(),
            'socialLogin' => fn() => $this->getSocialLoginProviders(),
            'theme' => fn() => $this->getThemeSettings(),
            'menuItems' => fn() => $this->getMenuItems(),
            'locale' => fn() => $this->getLocaleData(),
        ];
    }

    /**
     * Load all settings in a single query and return a value by key with a fallback default.
     */
    protected function allSettings(): array
    {
        if (!isset($this->cachedSettings)) {
            try {
                $this->cachedSettings = Setting::getAll();
            }
            catch (\Exception $e) {
                $this->cachedSettings = [];
            }
        }
        return $this->cachedSettings;
    }

    protected array $cachedSettings;

    protected function s(string $key, mixed $default = null): mixed
    {
        return $this->allSettings()[$key] ?? $default;
    }

    protected function getAppSettings(): array
    {
        return [
            'name' => config('app.name'),
            'age_verification_required' => (bool)$this->s('age_verification_required', true),
            'infinite_scroll_enabled' => $this->s('infinite_scroll_enabled', false),
            'videos_per_page' => $this->s('videos_per_page', 24),
            'monetization_enabled' => (bool)$this->s('monetization_enabled', true),
        ];
    }

    protected function getThemeSettings(): array
    {
        return [
            'siteTitle' => $this->s('site_title', 'HubTube'),
            'siteTitleFont' => $this->s('site_title_font', ''),
            'siteTitleSize' => $this->s('site_title_size', 20),
            'siteTitleColor' => $this->s('site_title_color', ''),

            'mode' => $this->s('theme_mode', 'dark'),
            'allowToggle' => $this->s('allow_user_theme_toggle', true),
            'dark' => [
                'bgPrimary' => $this->s('dark_bg_primary', '#0a0a0a'),
                'bgSecondary' => $this->s('dark_bg_secondary', '#171717'),
                'bgCard' => $this->s('dark_bg_card', '#1f1f1f'),
                'accent' => $this->s('dark_accent_color', '#ef4444'),
                'textPrimary' => $this->s('dark_text_primary', '#ffffff'),
                'textSecondary' => $this->s('dark_text_secondary', '#a3a3a3'),
                'border' => $this->s('dark_border_color', '#262626'),
            ],
            'light' => [
                'bgPrimary' => $this->s('light_bg_primary', '#ffffff'),
                'bgSecondary' => $this->s('light_bg_secondary', '#f5f5f5'),
                'bgCard' => $this->s('light_bg_card', '#ffffff'),
                'accent' => $this->s('light_accent_color', '#dc2626'),
                'textPrimary' => $this->s('light_text_primary', '#171717'),
                'textSecondary' => $this->s('light_text_secondary', '#525252'),
                'border' => $this->s('light_border_color', '#e5e5e5'),
            ],
            'icons' => [
                'colorMode' => $this->s('icon_color_mode', 'inherit'),
                'globalColor' => $this->s('icon_global_color', ''),
                'globalColorDark' => $this->s('icon_global_color_dark', ''),
                'home' => ['icon' => $this->s('nav_home_icon', 'home'), 'color' => $this->s('nav_home_color', '')],
                'trending' => ['icon' => $this->s('nav_trending_icon', 'trending-up'), 'color' => $this->s('nav_trending_color', '')],
                'shorts' => ['icon' => $this->s('nav_shorts_icon', 'zap'), 'color' => $this->s('nav_shorts_color', '')],
                'live' => ['icon' => $this->s('nav_live_icon', 'radio'), 'color' => $this->s('nav_live_color', '')],
                'playlists' => ['icon' => $this->s('nav_playlists_icon', 'list-video'), 'color' => $this->s('nav_playlists_color', '')],
                'history' => ['icon' => $this->s('nav_history_icon', 'history'), 'color' => $this->s('nav_history_color', '')],
            ],
            'ageVerification' => [
                'overlayColor' => $this->s('age_overlay_color', 'rgba(0, 0, 0, 0.85)'),
                'overlayBlur' => (int)$this->s('age_overlay_blur', 8),
                'showLogo' => (bool)$this->s('age_show_logo', false),
                'logoUrl' => $this->storageUrl($this->s('site_logo', '')),
                'headerText' => $this->s('age_header_text', 'Age Verification Required'),
                'headerSize' => (int)$this->s('age_header_size', 28),
                'headerColor' => $this->s('age_header_color', ''),
                'descriptionText' => $this->s('age_description_text', 'This website contains age-restricted content. You must be at least 18 years old to enter.'),
                'disclaimerText' => $this->s('age_disclaimer_text', 'By clicking "{confirm}", you confirm that you are at least 18 years of age and consent to viewing adult content.'),
                'confirmText' => $this->s('age_confirm_text', 'I am 18 or older'),
                'declineText' => $this->s('age_decline_text', 'Exit'),
                'termsText' => $this->s('age_terms_text', 'By entering this site, you agree to our'),
                'buttonColor' => $this->s('age_button_color', ''),
                'textColor' => $this->s('age_text_color', ''),
                'fontFamily' => $this->s('age_font_family', ''),
            ],
            'categoryTypography' => [
                'font' => $this->s('category_title_font', ''),
                'size' => $this->s('category_title_size', 18),
                'color' => $this->s('category_title_color', '#ffffff'),
                'opacity' => $this->s('category_title_opacity', 90),
            ],
            'site_title' => $this->s('site_title', 'HubTube'),
            'site_title_color' => $this->s('site_title_color', ''),
            'site_title_font' => $this->s('site_title_font', ''),
            'site_logo' => $this->storageUrl($this->s('site_logo', '')),
            'site_favicon' => $this->storageUrl($this->s('site_favicon', '')),
            'footer_logo_url' => $this->storageUrl($this->s('footer_logo_url', '')),
            'progressBarColor' => $this->s('progress_bar_color', ''),
            'footer_ad_enabled' => (bool)$this->s('footer_ad_enabled', false),
            'footer_ad_code' => $this->s('footer_ad_code', ''),
            'footer_ad_mobile_code' => $this->s('footer_ad_mobile_code', ''),
            'videoCard' => [
                'showAvatar' => (bool)$this->s('video_card_show_avatar', true),
                'showUploader' => (bool)$this->s('video_card_show_uploader', true),
                'showViews' => (bool)$this->s('video_card_show_views', true),
                'showDuration' => (bool)$this->s('video_card_show_duration', true),
                'showTimestamp' => (bool)$this->s('video_card_show_timestamp', true),
                'titleFont' => $this->s('video_card_title_font', ''),
                'titleSize' => (int)$this->s('video_card_title_size', 14),
                'titleColor' => $this->s('video_card_title_color', ''),
                'titleLines' => (int)$this->s('video_card_title_lines', 2),
                'metaFont' => $this->s('video_card_meta_font', ''),
                'metaSize' => (int)$this->s('video_card_meta_size', 13),
                'metaColor' => $this->s('video_card_meta_color', ''),
                'borderRadius' => (int)$this->s('video_card_border_radius', 12),
            ],
            'mobileVideoGrid' => $this->s('mobile_video_grid', '1'),
        ];
    }

    protected function getSocialLoginProviders(): array
    {
        $providers = [];
        foreach (['google', 'twitter', 'reddit'] as $provider) {
            if ((bool)$this->s("social_login_{$provider}_enabled", false)) {
                $providers[] = $provider;
            }
        }
        return $providers;
    }

    protected function getLocaleData(): array
    {
        try {
            $currentLocale = App::getLocale();
            $defaultLocale = TranslationService::getDefaultLocale();
            $enabledLanguages = TranslationService::getEnabledLanguages();
            $isTranslated = $currentLocale !== $defaultLocale;

            // Load UI translations from JSON file for current locale (always, including default)
            $translations = [];
            $file = resource_path("js/i18n/{$currentLocale}.json");
            if (file_exists($file)) {
                $translations = json_decode(file_get_contents($file), true) ?: [];
            }

            return [
                'current' => $currentLocale,
                'default' => $defaultLocale,
                'languages' => $enabledLanguages,
                'enabled' => count($enabledLanguages) > 1,
                'prefix' => $isTranslated ? "/{$currentLocale}" : '',
                'translations' => $translations,
            ];
        }
        catch (\Exception $e) {
            return [
                'current' => 'en',
                'default' => 'en',
                'languages' => ['en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇺🇸']],
                'enabled' => false,
                'prefix' => '',
                'translations' => [],
            ];
        }
    }

    /**
     * Resolve a storage-relative path to a public URL.
     * Passes through absolute URLs and empty strings unchanged.
     */
    protected function storageUrl(?string $path): string
    {
        if (!$path) {
            return '';
        }

        // Already a full URL or absolute path starting with /
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        return '/storage/' . $path;
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
        }
        catch (\Exception $e) {
            // Table may not exist yet (pre-migration)
            return ['header' => [], 'mobile' => []];
        }
    }
}
