<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Services\SeoService;
use App\Services\TranslationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;
use STS\FilamentImpersonate\Facades\Impersonation;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next)
    {
        // SeoService stashes each page's payload in a static so app.blade.php
        // can render it server-side. Clear it per request so one response can
        // never inherit the previous one's SEO data.
        SeoService::reset();

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
                        'points_balance' => $user->points_balance,
                        'age_verified' => $user->isAgeVerified(),
                        'email_verified' => $user->email_verified_at !== null,
                        'can_edit_video' => $user->canEditVideo(),
                        'settings' => $user->settings ?? [],
                        'channel' => $user->channel ? [
                            'id' => $user->channel->id,
                            'name' => $user->channel->name,
                            'banner_image' => $user->channel->banner_image,
                            // The owner's raw saved links, so the Settings form
                            // can edit them. Unlike the public channel payload
                            // these are not filtered for display — the owner
                            // must still see a link the kill switch is hiding.
                            'description' => $user->channel->description,
                            'social_links' => is_array($user->channel->social_links)
                                ? $user->channel->social_links
                                : [],
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
            'impersonating' => fn () => $this->getImpersonationData(),
            'app' => fn() => $this->getAppSettings(),
            'socialLogin' => fn() => $this->getSocialLoginProviders(),
            'theme' => fn() => $this->getThemeSettings(),
            'menuItems' => fn() => $this->getMenuItems(),
            'locale' => fn() => $this->getLocaleData(),
            'seo' => fn() => $this->getSeoData($request),
        ];
    }

    /**
     * Whether the current session is an active admin impersonation, and who
     * the real admin is — powers the "You are impersonating X" banner on the
     * public frontend (stechstudio/filament-impersonate's own banner only
     * renders inside the Filament admin panel, not here).
     */
    protected function getImpersonationData(): ?array
    {
        try {
            if (!Impersonation::isImpersonating()) {
                return null;
            }

            $impersonator = Impersonation::getImpersonator();

            return [
                'impersonator' => $impersonator?->username ?? $impersonator?->email,
                'leave_url' => route('filament-impersonate.leave'),
            ];
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Resolve the SEO payload for the current response.
     *
     * Controllers that call a SeoService::for*() builder win — the builder
     * stores its result statically and also passes it as a page prop, so this
     * closure simply hands back the same array. Pages that never called one
     * still get a payload, because app.blade.php and SeoHead.vue must render
     * from an identical source: any server-rendered head tag the client-side
     * <Head> doesn't re-emit is removed from the DOM on hydration.
     */
    protected function getSeoData(Request $request): array
    {
        $current = SeoService::getCurrent();
        if ($current !== null) {
            return $current;
        }

        $service = app(SeoService::class);

        return $this->isIndexableRoute($request)
            ? $service->defaultMeta()
            : $service->forPrivatePage();
    }

    /**
     * A route behind auth/guest/verification gating is never publicly
     * crawlable, so it defaults to noindex. An unmatched route (an error
     * response) is treated the same way.
     */
    protected function isIndexableRoute(Request $request): bool
    {
        $route = $request->route();
        if ($route === null) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }
            $name = explode(':', $middleware)[0];
            if (in_array($name, ['auth', 'guest', 'verified', 'password.confirm'], true)) {
                return false;
            }
        }

        return true;
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
            catch (Exception $e) {
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
            'currency' => $this->s('currency', 'USD'),
            'pro_enabled' => (bool) $this->s('pro_enabled', true),
            'pro_ad_free' => (bool) $this->s('pro_ad_free', true),
            'pro_badge_text' => (string) $this->s('pro_badge_text', 'PRO'),
            'points_enabled' => (bool) $this->s('points_enabled', true),
            'upload' => [
                'allowed_extensions' => array_values((array) config('hubtube.video.allowed_extensions', [
                    'mp4', 'mov', 'avi', 'mkv', 'webm', 'flv', 'wmv',
                ])),
                'max_size_free' => (int) $this->s('max_upload_size_free', 500),
                'max_size_pro' => (int) $this->s('max_upload_size_pro', 5000),
                'max_daily_uploads_free' => (int) $this->s('max_daily_uploads_free', 5),
                'max_daily_uploads_pro' => (int) $this->s('max_daily_uploads_pro', 50),
            ],
            'interstitial' => [
                'enabled'    => (bool) $this->s('custom_interstitial_enabled', false),
                'mode'       => (string) $this->s('custom_interstitial_mode', 'manual'),
                'code'       => (string) $this->s('custom_interstitial_code', ''),
                'mobileCode' => (string) $this->s('custom_interstitial_mobile_code', ''),
                'frequency'  => (int) $this->s('interstitial_frequency', 5),
                'skipDelay'  => (int) $this->s('interstitial_skip_delay', 5),
            ],
        ];
    }

    protected function getThemeSettings(): array
    {
        return [
            'siteTitle' => $this->s('site_title', 'HubTube'),
            'siteTitleFont' => $this->s('site_title_font', ''),
            'siteTitleSize' => $this->s('site_title_size', 20),
            'siteTitleColor' => $this->s('site_title_color', ''),

            'dark' => [
                'bgPrimary' => $this->s('dark_bg_primary', '#0a0a0a'),
                'bgSecondary' => $this->s('dark_bg_secondary', '#171717'),
                'bgCard' => $this->s('dark_bg_card', '#1f1f1f'),
                'accent' => $this->s('dark_accent_color', '#ef4444'),
                'textPrimary' => $this->s('dark_text_primary', '#ffffff'),
                'textSecondary' => $this->s('dark_text_secondary', '#a3a3a3'),
                'border' => $this->s('dark_border_color', '#262626'),
            ],
            'icons' => [
                'colorMode' => $this->s('icon_color_mode', 'inherit'),
                'globalColor' => $this->s('icon_global_color', ''),
                'globalColorDark' => $this->s('icon_global_color_dark', ''),
                'home' => ['icon' => $this->s('nav_home_icon', 'home'), 'color' => $this->s('nav_home_color', '')],
                'trending' => ['icon' => $this->s('nav_trending_icon', 'trending-up'), 'color' => $this->s('nav_trending_color', '')],
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
            'mobileVideoGrid' => $this->s('mobile_video_grid', '2'),
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

            return [
                'current' => $currentLocale,
                'default' => $defaultLocale,
                'languages' => $enabledLanguages,
                'enabled' => count($enabledLanguages) > 1,
                'prefix' => $isTranslated ? "/{$currentLocale}" : '',
                'dir' => TranslationService::isRtl($currentLocale) ? 'rtl' : 'ltr',
                'translations' => $this->uiTranslations($currentLocale),
                // Default-locale catalogue, so useI18n()'s t() can fall back to
                // English for any key the active locale is missing. Only sent
                // when the locale differs — otherwise it would duplicate the
                // payload on every response.
                'fallback' => $isTranslated ? $this->uiTranslations($defaultLocale) : [],
            ];
        }
        catch (Exception $e) {
            return [
                'current' => 'en',
                'default' => 'en',
                'languages' => ['en' => ['name' => 'English', 'native' => 'English', 'flag' => '🇺🇸']],
                'enabled' => false,
                'prefix' => '',
                'dir' => 'ltr',
                'translations' => [],
                'fallback' => [],
            ];
        }
    }

    /**
     * Read a UI translation catalogue from resources/js/i18n.
     *
     * Cached, keyed on the catalogue's modification time. Without it every
     * request — including partial Inertia reloads — re-read and re-decoded a
     * ~16KB JSON file from disk.
     *
     * The mtime is part of the cache key on purpose. This used to be a plain
     * rememberForever() cleared only by translations:generate, so any deploy
     * that edited a catalogue by hand served the stale copy and every new key
     * rendered to users as its raw dot-path ("common.verified") until someone
     * remembered to run translations:clear-cache. Keying on mtime makes a
     * changed file invalidate itself; a stat() per request is far cheaper than
     * re-decoding the JSON, and translations:clear-cache still works.
     */
    /**
     * Cache key for a locale's catalogue, versioned by the file's mtime.
     *
     * Shared with translations:clear-cache and translations:generate so they
     * forget the key this actually writes.
     */
    public static function uiTranslationCacheKey(string $locale): string
    {
        $file = resource_path("js/i18n/{$locale}.json");
        $version = (file_exists($file) ? @filemtime($file) : 0) ?: 0;

        return "i18n:ui:{$locale}:{$version}";
    }

    protected function uiTranslations(string $locale): array
    {
        $file = resource_path("js/i18n/{$locale}.json");

        if (!file_exists($file)) {
            return [];
        }

        return Cache::rememberForever(static::uiTranslationCacheKey($locale), function () use ($file) {
            return json_decode(file_get_contents($file), true) ?: [];
        });
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
            return Cache::remember(MenuItem::CACHE_KEY, MenuItem::CACHE_TTL, function () {
                $items      = MenuItem::getMenuTree('both');
                $headerOnly = MenuItem::getMenuTree('header');
                $mobileOnly = MenuItem::getMenuTree('mobile');

                if ($items->isEmpty() && $headerOnly->isEmpty() && $mobileOnly->isEmpty()) {
                    $categories = Category::active()->parentCategories()->orderBy('sort_order')->get();

                    $default = $categories->map(fn (Category $category) => [
                        'id'         => 'category_' . $category->id,
                        'label'      => $category->name,
                        'type'       => 'category',
                        'url'        => route('categories.show', $category->slug),
                        'target'     => '_self',
                        'icon'       => null,
                        'is_active'  => true,
                        'is_mega'    => false,
                        'mega_columns' => null,
                        'children'   => [],
                        'sort_order' => $category->sort_order ?? 0,
                    ])->values()->toArray();

                    return [
                        'header' => $default,
                        'mobile' => $default,
                    ];
                }

                return [
                    'header' => $items->merge($headerOnly)->sortBy('sort_order')->values()->toArray(),
                    'mobile' => $items->merge($mobileOnly)->sortBy('sort_order')->values()->toArray(),
                ];
            });
        }
        catch (Exception $e) {
            // Table may not exist yet (pre-migration)
            return ['header' => [], 'mobile' => []];
        }
    }
}
