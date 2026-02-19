<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he']) ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'HubTube') }}</title>

    {{-- Default SEO meta (overridden per-page by Inertia Head / SeoHead component) --}}
    @php
        $seoDesc = \App\Models\Setting::get('seo_meta_description', '');
        $seoKeywords = \App\Models\Setting::get('seo_meta_keywords', '');
        $googleVerify = \App\Models\Setting::get('seo_google_verification', '');
        $bingVerify = \App\Models\Setting::get('seo_bing_verification', '');
        $yandexVerify = \App\Models\Setting::get('seo_yandex_verification', '');
        $pinterestVerify = \App\Models\Setting::get('seo_pinterest_verification', '');
    @endphp
    @if($seoDesc)
    <meta name="description" content="{{ $seoDesc }}">
    @endif
    @if($seoKeywords)
    <meta name="keywords" content="{{ $seoKeywords }}">
    @endif
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

    {{-- Favicon --}}
    @php $siteFavicon = \App\Models\Setting::get('site_favicon', ''); @endphp
    @if($siteFavicon)
    <link rel="icon" href="{{ str_starts_with($siteFavicon, 'http') || str_starts_with($siteFavicon, '/') ? $siteFavicon : '/storage/' . $siteFavicon }}">
    @endif

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#ef4444">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HubTube">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    
    @php
        $siteTitleFont = \App\Models\Setting::get('site_title_font', '');
    @endphp
    @if($siteTitleFont)
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family={{ str_replace(' ', '+', $siteTitleFont) }}&display=swap" rel="stylesheet">
    @endif

    <style>
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

    {{-- hreflang tags for multi-language SEO (with translated video slugs) --}}
    @php
        $enabledLangs = \App\Services\TranslationService::getEnabledLocales();
        $defaultLang = \App\Services\TranslationService::getDefaultLocale();
        $currentPath = request()->path();
        // Strip locale prefix from current path if present
        $cleanPath = preg_replace('#^[a-z]{2,3}(/|$)#', '', $currentPath);
        $cleanPath = $cleanPath ?: '/';

        // For video pages, look up translated slugs so each hreflang points to the correct translated URL
        $videoAlternates = null;
        if (count($enabledLangs) > 1) {
            $route = request()->route();
            $routeName = $route?->getName();
            // Detect video show pages (both default and locale-prefixed)
            if (in_array($routeName, ['videos.show', 'locale.videos.show'])) {
                $video = $route->parameter('video') ?? null;
                $slug = $route->parameter('slug') ?? null;
                $videoModel = null;
                if ($video instanceof \App\Models\Video) {
                    $videoModel = $video;
                } elseif ($slug) {
                    $videoModel = \App\Models\Video::where('slug', $slug)->first()
                        ?? \App\Models\Video::whereHas('translations', fn($q) => $q->where('translated_slug', $slug))->first();
                }
                if ($videoModel) {
                    $translationService = app(\App\Services\TranslationService::class);
                    $videoAlternates = $translationService->getAlternateUrls(\App\Models\Video::class, $videoModel->id, $videoModel->slug);
                }
            }
        }
    @endphp
    @if(count($enabledLangs) > 1)
        @if($videoAlternates)
            {{-- Video page: use translated slugs for each language --}}
            <link rel="alternate" hreflang="x-default" href="{{ $videoAlternates[$defaultLang] ?? url($cleanPath) }}" />
            @foreach($enabledLangs as $lang)
                <link rel="alternate" hreflang="{{ $lang }}" href="{{ $videoAlternates[$lang] ?? url($lang . '/' . $cleanPath) }}" />
            @endforeach
        @else
            {{-- Non-video pages: same path structure across locales --}}
            <link rel="alternate" hreflang="x-default" href="{{ url($cleanPath) }}" />
            @foreach($enabledLangs as $lang)
                @if($lang === $defaultLang)
                    <link rel="alternate" hreflang="{{ $lang }}" href="{{ url($cleanPath) }}" />
                @else
                    <link rel="alternate" hreflang="{{ $lang }}" href="{{ url($lang . '/' . $cleanPath) }}" />
                @endif
            @endforeach
        @endif
    @endif

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

    @routes(nonce: \Illuminate\Support\Facades\Vite::cspNonce())
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased" style="background-color: var(--color-bg-primary); color: var(--color-text-primary);">
    @inertia

    {{-- Custom Ad Scripts (ExoClick, etc.) --}}
    @php
        $popunderEnabled = \App\Models\Setting::get('custom_popunder_enabled', false);
        $popunderCode = \App\Models\Setting::get('custom_popunder_code', '');
        $popunderMobileCode = \App\Models\Setting::get('custom_popunder_mobile_code', '');
        $interstitialEnabled = \App\Models\Setting::get('custom_interstitial_enabled', false);
        $interstitialCode = \App\Models\Setting::get('custom_interstitial_code', '');
        $interstitialMobileCode = \App\Models\Setting::get('custom_interstitial_mobile_code', '');
        $stickyEnabled = \App\Models\Setting::get('custom_sticky_banner_enabled', false);
        $stickyCode = \App\Models\Setting::get('custom_sticky_banner_code', '');
        $stickyMobileCode = \App\Models\Setting::get('custom_sticky_banner_mobile_code', '');
    @endphp
    @if($popunderEnabled && ($popunderCode || $popunderMobileCode))
        <div class="ht-popunder-desktop" style="display:none">{!! $popunderCode !!}</div>
        <div class="ht-popunder-mobile" style="display:none">{!! $popunderMobileCode ?: $popunderCode !!}</div>
        <script>
            (function(){
                var w = window.innerWidth;
                if (w >= 768) {
                    var el = document.querySelector('.ht-popunder-desktop');
                    if (el) el.style.display = '';
                } else {
                    var el = document.querySelector('.ht-popunder-mobile');
                    if (el) el.style.display = '';
                }
            })();
        </script>
    @endif
    @if($interstitialEnabled && ($interstitialCode || $interstitialMobileCode))
        <div class="ht-interstitial-desktop" style="display:none">{!! $interstitialCode !!}</div>
        <div class="ht-interstitial-mobile" style="display:none">{!! $interstitialMobileCode ?: $interstitialCode !!}</div>
        <script>
            (function(){
                var w = window.innerWidth;
                if (w >= 768) {
                    var el = document.querySelector('.ht-interstitial-desktop');
                    if (el) el.style.display = '';
                } else {
                    var el = document.querySelector('.ht-interstitial-mobile');
                    if (el) el.style.display = '';
                }
            })();
        </script>
    @endif
    @if($stickyEnabled && ($stickyCode || $stickyMobileCode))
        <div class="ht-sticky-ad-desktop" style="display:none">{!! $stickyCode !!}</div>
        <div class="ht-sticky-ad-mobile" style="display:none">{!! $stickyMobileCode ?: $stickyCode !!}</div>
        <script>
            (function(){
                var w = window.innerWidth;
                if (w >= 768) {
                    var el = document.querySelector('.ht-sticky-ad-desktop');
                    if (el) el.style.display = '';
                } else {
                    var el = document.querySelector('.ht-sticky-ad-mobile');
                    if (el) el.style.display = '';
                }
            })();
        </script>
    @endif

    {{-- Custom Footer Scripts (from Admin > Site Settings > Analytics) --}}
    @php $customFooterScripts = \App\Models\Setting::get('custom_footer_scripts', ''); @endphp
    @if($customFooterScripts)
    {!! $customFooterScripts !!}
    @endif
</body>
</html>
