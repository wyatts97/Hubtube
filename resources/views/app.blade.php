<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['ar', 'he']) ? 'rtl' : 'ltr' }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'HubTube') }}</title>

    {{-- Server-side SEO meta tags — critical for crawlers (Twitterbot, Facebookbot, Googlebot)
         that don't execute JavaScript. Without Inertia SSR, the SeoHead.vue component only
         renders client-side, so these tags must be in the raw HTML response. --}}
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

    {{-- Page description --}}
    @if($metaDesc)
    <meta name="description" content="{{ $metaDesc }}">
    @endif
    @if($keywords)
    <meta name="keywords" content="{{ $keywords }}">
    @endif
    @if($robots)
    <meta name="robots" content="{{ $robots }}">
    @endif
    @if($canonical)
    <link rel="canonical" href="{{ $canonical }}">
    @endif

    {{-- Open Graph --}}
    @if($ogTitle)
    <meta property="og:title" content="{{ $ogTitle }}">
    @endif
    @if($ogDesc)
    <meta property="og:description" content="{{ $ogDesc }}">
    @endif
    <meta property="og:type" content="{{ $ogType }}">
    @if($ogUrl)
    <meta property="og:url" content="{{ $ogUrl }}">
    @endif
    <meta property="og:site_name" content="{{ $ogSiteName }}">
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @if(!empty($seo['og']['image:width']))
    <meta property="og:image:width" content="{{ $seo['og']['image:width'] }}">
    <meta property="og:image:height" content="{{ $seo['og']['image:height'] ?? '720' }}">
    @endif
    @endif
    @if(!empty($seo['og']['locale']))
    <meta property="og:locale" content="{{ $seo['og']['locale'] }}">
    @endif
    @if(!empty($seo['og']['locale:alternate']))
    @foreach($seo['og']['locale:alternate'] as $altLocale)
    <meta property="og:locale:alternate" content="{{ $altLocale }}">
    @endforeach
    @endif
    @if(!empty($seo['og']['video:duration']))
    <meta property="og:video:duration" content="{{ $seo['og']['video:duration'] }}">
    @endif
    @if(!empty($seo['og']['video:release_date']))
    <meta property="og:video:release_date" content="{{ $seo['og']['video:release_date'] }}">
    @endif
    @if(!empty($seo['og']['video:tag']) && is_array($seo['og']['video:tag']))
    @foreach($seo['og']['video:tag'] as $tag)
    <meta property="og:video:tag" content="{{ $tag }}">
    @endforeach
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $twCard }}">
    @if($twSite)
    <meta name="twitter:site" content="{{ $twSite }}">
    @endif
    @if($twTitle)
    <meta name="twitter:title" content="{{ $twTitle }}">
    @endif
    @if($twDesc)
    <meta name="twitter:description" content="{{ $twDesc }}">
    @endif
    @if($twImage)
    <meta name="twitter:image" content="{{ $twImage }}">
    @endif

    {{-- JSON-LD Structured Data --}}
    @if(!empty($schemas))
    <script type="application/ld+json">{!! json_encode(count($schemas) === 1 ? $schemas[0] : $schemas, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
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

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="font-sans antialiased" style="background-color: var(--color-bg-primary); color: var(--color-text-primary);">
    @inertia

    {{-- Custom Ad Scripts (ExoClick, etc.) --}}
    {{-- Ad network scripts (popunder, interstitial, sticky) are injected via JS to ensure
         proper execution. External scripts load sequentially so inline scripts that depend
         on them (e.g. AdProvider.push) run after the provider JS is ready. --}}
    @php
        $popunderEnabled = filter_var(\App\Models\Setting::get('custom_popunder_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $popunderCode = \App\Models\Setting::get('custom_popunder_code', '') ?: '';
        $popunderMobileCode = \App\Models\Setting::get('custom_popunder_mobile_code', '') ?: '';
        $interstitialEnabled = filter_var(\App\Models\Setting::get('custom_interstitial_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $interstitialCode = \App\Models\Setting::get('custom_interstitial_code', '') ?: '';
        $interstitialMobileCode = \App\Models\Setting::get('custom_interstitial_mobile_code', '') ?: '';
        $stickyEnabled = filter_var(\App\Models\Setting::get('custom_sticky_banner_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $stickyCode = \App\Models\Setting::get('custom_sticky_banner_code', '') ?: '';
        $stickyMobileCode = \App\Models\Setting::get('custom_sticky_banner_mobile_code', '') ?: '';
    @endphp
    @if($popunderEnabled || $interstitialEnabled || $stickyEnabled)
    <script>
        /**
         * Inject ad HTML into the DOM and execute scripts sequentially.
         * External <script src="..."> tags are loaded one at a time (waiting for onload)
         * so that inline scripts that reference the provider object run after it exists.
         * Non-script nodes (ins, div, etc.) are appended immediately and remain visible.
         */
        function htInjectAdCode(html, className) {
            if (!html) return;
            var container = document.createElement('div');
            container.className = className;
            document.body.appendChild(container);

            var temp = document.createElement('div');
            temp.innerHTML = html;

            var scripts = [];
            var nodes = Array.from(temp.childNodes);
            for (var i = 0; i < nodes.length; i++) {
                var node = nodes[i];
                if (node.nodeName === 'SCRIPT') {
                    var src = node.getAttribute('src') || '';
                    var attrs = {};
                    Array.from(node.attributes).forEach(function(a) { attrs[a.name] = a.value; });
                    scripts.push({ src: src, attrs: attrs, content: node.textContent || '' });
                } else {
                    container.appendChild(node.cloneNode(true));
                }
            }

            function loadNext(idx) {
                if (idx >= scripts.length) return;
                var def = scripts[idx];
                var el = document.createElement('script');

                if (def.src) {
                    // External script — copy attributes, wait for load before continuing
                    Object.keys(def.attrs).forEach(function(name) {
                        if (name === 'async' || name === 'defer') return;
                        el.setAttribute(name, def.attrs[name]);
                    });
                    el.onload = function() { loadNext(idx + 1); };
                    el.onerror = function() { loadNext(idx + 1); };
                    container.appendChild(el);
                } else {
                    // Inline script — execute immediately then continue
                    el.textContent = def.content;
                    container.appendChild(el);
                    loadNext(idx + 1);
                }
            }

            if (scripts.length > 0) {
                loadNext(0);
            }
        }
    </script>
    @endif
    @if($popunderEnabled && ($popunderCode || $popunderMobileCode))
        <script>
            (function(){
                var code = (window.innerWidth < 768)
                    ? @json($popunderMobileCode ?: $popunderCode)
                    : @json($popunderCode);
                htInjectAdCode(code, 'ht-popunder-ad');
            })();
        </script>
    @endif
    @if($interstitialEnabled && ($interstitialCode || $interstitialMobileCode))
        <script>
            (function(){
                var code = (window.innerWidth < 768)
                    ? @json($interstitialMobileCode ?: $interstitialCode)
                    : @json($interstitialCode);
                htInjectAdCode(code, 'ht-interstitial-ad');
            })();
        </script>
    @endif
    @if($stickyEnabled && ($stickyCode || $stickyMobileCode))
        <script>
            (function(){
                var code = (window.innerWidth < 768)
                    ? @json($stickyMobileCode ?: $stickyCode)
                    : @json($stickyCode);
                htInjectAdCode(code, 'ht-sticky-ad');
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
