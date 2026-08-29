<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ \App\Services\TranslationService::isRtl(app()->getLocale()) ? 'rtl' : 'ltr' }}" class="dark">
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
    @php $siteFavicon = \App\Models\Setting::get('site_favicon', ''); @endphp
    @if($siteFavicon)
    <link rel="icon" href="{{ str_starts_with($siteFavicon, 'http') || str_starts_with($siteFavicon, '/') ? $siteFavicon : '/storage/' . $siteFavicon }}">
    @else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96x96.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192x192.png">
    @endif

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ef4444">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HubTube">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    {{-- Preload critical font weights to eliminate render-blocking chain (PageSpeed: 900ms savings) --}}
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-500-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-600-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2" as="font" type="font/woff2" crossorigin>
    {{-- Load font CSS asynchronously — not render-blocking --}}
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap"></noscript>
    
    @php
        $siteTitleFont = \App\Models\Setting::get('site_title_font', '');
    @endphp
    @if($siteTitleFont)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $siteTitleFont) }}&display=swap" rel="stylesheet">
    @endif

    <style>
        /* Inline @font-face so text renders immediately with preloaded fonts (no FOIT) */
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 400;
            font-display: swap;
            src: url('https://fonts.bunny.net/inter/files/inter-latin-400-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 500;
            font-display: swap;
            src: url('https://fonts.bunny.net/inter/files/inter-latin-500-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 600;
            font-display: swap;
            src: url('https://fonts.bunny.net/inter/files/inter-latin-600-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        @font-face {
            font-family: 'Inter';
            font-style: normal;
            font-weight: 700;
            font-display: swap;
            src: url('https://fonts.bunny.net/inter/files/inter-latin-700-normal.woff2') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
        }
        :root {
            --color-bg-primary: #0a0a0a;
            --color-bg-secondary: #171717;
            --color-bg-card: #1f1f1f;
            --color-accent: #ef4444;
            --color-text-primary: #ffffff;
            --color-text-secondary: #a3a3a3;
            --color-border: #262626;
        }
    </style>

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

        // Server-side mobile detection via User-Agent for ad variant selection
        $ua = request()->header('User-Agent', '');
        $isMobileUA = (bool) preg_match('/Android|iPhone|iPad|iPod|Opera Mini|IEMobile|Mobile|webOS/i', $ua);

        // Pro / ad-free users skip all Blade-injected ads
        $proAdFree = (bool) \App\Models\Setting::get('pro_ad_free', true);
        $currentUser = auth()->user();
        $shouldSuppressAds = $currentUser && $currentUser->is_pro && $proAdFree;
    @endphp
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
