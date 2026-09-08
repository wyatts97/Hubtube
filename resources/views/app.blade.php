<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Services\TranslationService::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}" class="@if(\App\Support\ThemeTokens::defaultMode() === 'light')theme-light @else dark @endif">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Server-side SEO meta tags — critical for crawlers (Twitterbot, Facebookbot, Googlebot)
         that don't execute JavaScript. Without Inertia SSR, the SeoHead.vue component only
         renders client-side, so these tags must be in the raw HTML response.

         Every tag below carrying an `inertia="key"` attribute has a matching
         `head-key` in SeoHead.vue. Inertia's head manager replaces matched tags
         on hydration — and REMOVES any inertia-attributed tag the client doesn't
         re-emit. So never add `inertia` to a tag SeoHead.vue doesn't also render
         (hreflang, verification, favicon, PWA and font tags stay unkeyed). --}}
    @php
        $seo = \App\Services\SeoService::getCurrent();
        $seoDesc = \App\Models\Setting::get('seo_meta_description', '');
        $seoKeywords = \App\Models\Setting::get('seo_meta_keywords', '');
        $googleVerify = \App\Models\Setting::get('seo_google_verification', '');
        $bingVerify = \App\Models\Setting::get('seo_bing_verification', '');
        $yandexVerify = \App\Models\Setting::get('seo_yandex_verification', '');
        $pinterestVerify = \App\Models\Setting::get('seo_pinterest_verification', '');

        // Use page-specific SEO if available, fall back to site defaults
        $metaDesc = $seo['description'] ?? $seoDesc;
        $metaTitle = $seo['title'] ?? null;
        $ogImage = $seo['og']['image'] ?? '';
        $ogTitle = $seo['og']['title'] ?? $metaTitle;
        $ogDesc = $seo['og']['description'] ?? $metaDesc;
        $ogType = $seo['og']['type'] ?? 'website';
        $ogUrl = $seo['og']['url'] ?? null;
        $ogSiteName = $seo['og']['site_name'] ?? \App\Models\Setting::get('site_name', config('app.name', 'HubTube'));
        $twCard = $seo['twitter']['card'] ?? \App\Models\Setting::get('seo_twitter_card', 'summary_large_image');
        $twSite = $seo['twitter']['site'] ?? \App\Models\Setting::get('seo_twitter_site', '');
        $twImage = $seo['twitter']['image'] ?? $ogImage;
        $twTitle = $seo['twitter']['title'] ?? $ogTitle;
        $twDesc = $seo['twitter']['description'] ?? $ogDesc;
        $canonical = $seo['canonical'] ?? null;
        $robots = $seo['robots'] ?? null;
        $keywords = $seo['keywords'] ?? $seoKeywords;
        $schemas = $seo['schema'] ?? [];
    @endphp

    <title inertia>{{ $metaTitle ?? config('app.name', 'HubTube') }}</title>

    {{-- Page description --}}
    @if($metaDesc)
    <meta name="description" content="{{ $metaDesc }}" inertia="description">
    @endif
    @if($keywords)
    <meta name="keywords" content="{{ $keywords }}" inertia="keywords">
    @endif
    @if($robots)
    <meta name="robots" content="{{ $robots }}" inertia="robots">
    @endif
    @if($canonical)
    <link rel="canonical" href="{{ $canonical }}" inertia="canonical">
    @endif

    {{-- Open Graph --}}
    @if($ogTitle)
    <meta property="og:title" content="{{ $ogTitle }}" inertia="og:title">
    @endif
    @if($ogDesc)
    <meta property="og:description" content="{{ $ogDesc }}" inertia="og:description">
    @endif
    <meta property="og:type" content="{{ $ogType }}" inertia="og:type">
    @if($ogUrl)
    <meta property="og:url" content="{{ $ogUrl }}" inertia="og:url">
    @endif
    <meta property="og:site_name" content="{{ $ogSiteName }}" inertia="og:site_name">
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}" inertia="og:image">
    @if(!empty($seo['og']['image:width']))
    <meta property="og:image:width" content="{{ $seo['og']['image:width'] }}" inertia="og:image:width">
    <meta property="og:image:height" content="{{ $seo['og']['image:height'] ?? '720' }}" inertia="og:image:height">
    @endif
    @endif
    @if(!empty($seo['og']['locale']))
    <meta property="og:locale" content="{{ $seo['og']['locale'] }}" inertia="og:locale">
    @endif
    @if(!empty($seo['og']['locale:alternate']))
    @foreach($seo['og']['locale:alternate'] as $i => $altLocale)
    <meta property="og:locale:alternate" content="{{ $altLocale }}" inertia="og:locale:alternate:{{ $i }}">
    @endforeach
    @endif
    @if(!empty($seo['og']['video:duration']))
    <meta property="og:video:duration" content="{{ $seo['og']['video:duration'] }}" inertia="og:video:duration">
    @endif
    @if(!empty($seo['og']['video:release_date']))
    <meta property="og:video:release_date" content="{{ $seo['og']['video:release_date'] }}" inertia="og:video:release_date">
    @endif
    @if(!empty($seo['og']['video:tag']) && is_array($seo['og']['video:tag']))
    @foreach($seo['og']['video:tag'] as $i => $tag)
    <meta property="og:video:tag" content="{{ $tag }}" inertia="og:video:tag:{{ $i }}">
    @endforeach
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $twCard }}" inertia="twitter:card">
    @if($twSite)
    <meta name="twitter:site" content="{{ $twSite }}" inertia="twitter:site">
    @endif
    @if($twTitle)
    <meta name="twitter:title" content="{{ $twTitle }}" inertia="twitter:title">
    @endif
    @if($twDesc)
    <meta name="twitter:description" content="{{ $twDesc }}" inertia="twitter:description">
    @endif
    @if($twImage)
    <meta name="twitter:image" content="{{ $twImage }}" inertia="twitter:image">
    @endif

    {{-- JSON-LD Structured Data --}}
    @if(!empty($schemas))
    <script type="application/ld+json" inertia="schema">{!! json_encode(count($schemas) === 1 ? $schemas[0] : $schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif

    {{-- Verification tags --}}
    @if($googleVerify)
    <meta name="google-site-verification" content="{{ $googleVerify }}">
    @endif
    @if($bingVerify)
    <meta name="msvalidate.01" content="{{ $bingVerify }}">
    @endif
    @if($yandexVerify)
    <meta name="yandex-verification" content="{{ $yandexVerify }}">
    @endif
    @if($pinterestVerify)
    <meta name="p:domain_verify" content="{{ $pinterestVerify }}">
    @endif

    {{-- Favicon.
         An admin-uploaded icon wins. Otherwise fall back to the shipped PWA icons
         rather than emitting nothing: with no <link rel="icon"> the browser
         requests /favicon.ico implicitly, which used to 404 on every page view. --}}
    @php
        $siteFavicon = \App\Support\SiteIcons::faviconUrl();
        $siteFaviconType = \App\Support\SiteIcons::faviconMimeType();
    @endphp
    @if($siteFavicon)
    <link rel="icon" href="{{ $siteFavicon }}"@if($siteFaviconType) type="{{ $siteFaviconType }}"@endif>
    @else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    @endif

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    {{-- Matches the page ground, not the accent: this paints the browser chrome
         on Android and standalone PWA, and an accent-coloured bar above a dark
         page reads as a rendering bug. The inline theme script below keeps it in
         sync when a visitor is in light mode. --}}
    <meta name="theme-color" content="{{ \App\Support\ThemeTokens::palette(\App\Support\ThemeTokens::defaultMode())['bgPrimary'] }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HubTube">
    {{-- iOS ignores rel="icon" entirely, so the admin icon has to be repeated
         here or a home-screen bookmark keeps the shipped default. --}}
    <link rel="apple-touch-icon" href="{{ $siteFavicon ?: '/icons/icon-192x192.png' }}">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    {{-- Fonts are resolved by App\Support\Typography from the admin's choices —
         one stylesheet covering every slot, requesting only the weights the site
         actually renders. Served from Bunny Fonts rather than Google so visitor
         IPs are never sent to Google (a live GDPR issue in the EU). --}}
    @php $fontStylesheet = \App\Support\Typography::stylesheetUrl(); @endphp
    @if($fontStylesheet)
    <link rel="preload" as="style" href="{{ $fontStylesheet }}">
    <link rel="stylesheet" href="{{ $fontStylesheet }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ $fontStylesheet }}"></noscript>
    @endif

    <style>
        /* Theme palettes. BOTH are emitted from App\Support\ThemeTokens — the same
           source that feeds the Inertia `theme` prop and the Filament colour
           pickers — so switching themes is a class toggle on <html> with no
           JavaScript writing custom properties one at a time, and the two can
           never drift apart. Dark is the default; .theme-light overrides it. */
        :root {
            {!! \App\Support\ThemeTokens::cssVariables('dark') !!}
            {!! \App\Support\Typography::cssVariables() !!}
        }
        :root.theme-light {
            {!! \App\Support\ThemeTokens::cssVariables('light') !!}
        }
    </style>

    {{-- Resolve the theme before first paint. Without this a visitor who chose
         light mode gets a dark flash on every navigation, which on this kind of
         site is worse than the usual cosmetic annoyance. Deliberately does NOT
         consult prefers-color-scheme: an unset visitor gets the admin default
         (dark), because landing an unsuspecting person on a bright adult page
         because their laptop is in light mode is not a good default. --}}
    <script>
        (function () {
            try {
                var root = document.documentElement;
                /* Server-resolved theme. Authoritative when the admin pinned a
                   theme, or when a signed-in user has a saved choice — in both
                   cases the class on <html> is already correct and localStorage
                   (which may be stale from another account) must not override it. */
                var authoritative = {!! json_encode(\App\Support\ThemeTokens::authoritativeMode()) !!};
                if (authoritative) return;

                var saved = localStorage.getItem('ht-theme');
                var resolved = (saved === 'light' || saved === 'dark') ? saved : 'dark';

                root.classList.toggle('theme-light', resolved === 'light');
                root.classList.toggle('dark', resolved !== 'light');

                var meta = document.querySelector('meta[name="theme-color"]');
                if (meta) {
                    var ground = getComputedStyle(root).getPropertyValue('--color-bg-primary').trim();
                    if (ground) meta.setAttribute('content', ground);
                }
            } catch (e) {
                /* Private mode or blocked storage — the server default stands. */
            }
        })();
    </script>

    {{-- Paginated series links. Google retired rel=prev/next as an indexing
         signal, but Bing and others still consume it. Unkeyed, like hreflang. --}}
    @if(!empty($seo['pagination']['prev']))
    <link rel="prev" href="{{ $seo['pagination']['prev'] }}">
    @endif
    @if(!empty($seo['pagination']['next']))
    <link rel="next" href="{{ $seo['pagination']['next'] }}">
    @endif

    {{-- hreflang tags for multi-language SEO. Built by SeoService from the SEO
         payload the controller already produced — no re-querying here.
         Deliberately NOT marked with `inertia`: SeoHead.vue doesn't emit these,
         and the head manager removes any keyed tag the client doesn't re-render. --}}
    @foreach(\App\Services\SeoService::hreflangTags() as $hl => $href)
        <link rel="alternate" hreflang="{{ $hl }}" href="{{ $href }}" />
    @endforeach

    {{-- Google Analytics --}}
    @php $gaId = \App\Models\Setting::get('google_analytics_id', ''); @endphp
    @if($gaId)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $gaId }}');
    </script>
    @endif

    {{-- Custom Head Scripts (from Admin > Site Settings > Analytics) --}}
    @php $customHeadScripts = \App\Models\Setting::get('custom_head_scripts', ''); @endphp
    @if($customHeadScripts)
    {!! $customHeadScripts !!}
    @endif

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased" style="background-color: var(--color-bg-primary); color: var(--color-text-primary);">
    @inertia

    {{-- Custom Ad Scripts (ExoClick / pemsrv popunder, interstitial, sticky) --}}
    {{-- These scripts MUST be output as raw HTML (not dynamically injected) because:
         1. Ad scripts like ExoClick use document.currentScript which only works in
            parser-inserted <script> tags, not dynamically created ones.
         2. The popunder's popMagic.init() + loadHosted() must run in the original
            script context for anti-adblock protections to work.
         Desktop/mobile selection is done server-side via User-Agent. --}}
    @php
        $popunderEnabled = filter_var(\App\Models\Setting::get('custom_popunder_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $popunderCode = \App\Models\Setting::get('custom_popunder_code', '') ?: '';
        $popunderMobileCode = \App\Models\Setting::get('custom_popunder_mobile_code', '') ?: '';
        $interstitialEnabled = filter_var(\App\Models\Setting::get('custom_interstitial_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $interstitialMode = \App\Models\Setting::get('custom_interstitial_mode', 'manual') ?: 'manual';
        $interstitialCode = \App\Models\Setting::get('custom_interstitial_code', '') ?: '';
        $interstitialMobileCode = \App\Models\Setting::get('custom_interstitial_mobile_code', '') ?: '';
        $stickyEnabled = filter_var(\App\Models\Setting::get('custom_sticky_banner_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $stickyCode = \App\Models\Setting::get('custom_sticky_banner_code', '') ?: '';
        $stickyMobileCode = \App\Models\Setting::get('custom_sticky_banner_mobile_code', '') ?: '';

        // Zone popunder config (our click-triggered zone URL handler)
        $zonePopunderEnabled = filter_var(\App\Models\Setting::get('zone_popunder_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $zonePopunderUrl = \App\Models\Setting::get('zone_popunder_url', '') ?: '';
        $zonePopunderMobileUrl = \App\Models\Setting::get('zone_popunder_mobile_url', '') ?: '';

        // Server-side mobile detection for ad variant selection. The regex used
        // to live here; it now sits in App\Support\DeviceType so ad statistics
        // classify a visitor exactly the same way this does.
        $isMobileUA = \App\Support\DeviceType::prefersMobileCreative(request()->header('User-Agent', ''));

        // Pro / ad-free users skip all Blade-injected ads. Shares App\Services\AdService
        // with the controllers and the Inertia middleware so one rule governs
        // every ad surface.
        $shouldSuppressAds = app(\App\Services\AdService::class)->shouldSuppress(auth()->user());
    @endphp
    {{-- Consent (CMP). Emitted before every ad tag below and before the Vue
         bundle's ad slots, because a TCF string that arrives after the ad code
         has already called home is worth nothing. --}}
    @php
        $cmpEnabled = filter_var(\App\Models\Setting::get('cmp_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $cmpScript = \App\Models\Setting::get('cmp_script', '') ?: '';
        $cmpWait = filter_var(\App\Models\Setting::get('cmp_wait_for_consent', true), FILTER_VALIDATE_BOOLEAN);
        $cmpTimeout = (int) \App\Models\Setting::get('cmp_timeout_ms', 3000);
    @endphp
    <script>
        // Read by AdSlot.vue before it injects any creative. Always defined so
        // the client never has to feature-detect the setting.
        window.__adConsent = {
            enabled: @json($cmpEnabled && !$shouldSuppressAds),
            wait: @json($cmpEnabled && $cmpWait && !$shouldSuppressAds),
            timeoutMs: @json($cmpTimeout),
        };
    </script>
    @if($cmpEnabled && !$shouldSuppressAds && $cmpScript)
        {!! $cmpScript !!}
    @endif
    @if($popunderEnabled && !$shouldSuppressAds && ($popunderCode || $popunderMobileCode))
        {!! $isMobileUA ? ($popunderMobileCode ?: $popunderCode) : $popunderCode !!}
    @endif
    @if($zonePopunderEnabled && !$shouldSuppressAds && ($zonePopunderUrl || $zonePopunderMobileUrl))
        <script>
            window.__zonePopunder = {
                enabled: true,
                url: @json($zonePopunderUrl),
                mobileUrl: @json($zonePopunderMobileUrl),
                triggerType: @json(\App\Models\Setting::get('zone_popunder_trigger_type', 'clicks')),
                clickFrequency: @json((int) \App\Models\Setting::get('zone_popunder_click_frequency', 3)),
                cooldownMinutes: @json((int) \App\Models\Setting::get('zone_popunder_cooldown_minutes', 5)),
                maxPerSession: @json((int) \App\Models\Setting::get('zone_popunder_max_per_session', 3)),
            };
        </script>
    @endif
    @if($interstitialEnabled && !$shouldSuppressAds && $interstitialMode === 'automatic' && ($interstitialCode || $interstitialMobileCode))
        {!! $isMobileUA ? ($interstitialMobileCode ?: $interstitialCode) : $interstitialCode !!}
    @endif
    @if($stickyEnabled && !$shouldSuppressAds && ($stickyCode || $stickyMobileCode))
        <div class="ht-sticky-banner fixed bottom-0 left-0 right-0 z-50 flex justify-center w-full" style="max-height: 120px; overflow: hidden;">
            {!! $isMobileUA ? ($stickyMobileCode ?: $stickyCode) : $stickyCode !!}
        </div>
    @endif

    {{-- Custom Footer Scripts (from Admin > Site Settings > Analytics) --}}
    @php $customFooterScripts = \App\Models\Setting::get('custom_footer_scripts', ''); @endphp
    @if($customFooterScripts)
    {!! $customFooterScripts !!}
    @endif
</body>
</html>
