<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Setting;
use App\Models\Video;
use App\Models\User;
use App\Models\Category;
use App\Services\StorageManager;
use Illuminate\Support\Facades\App;

class SeoService
{
    protected array $settings = [];

    /**
     * Last generated SEO data — stored statically so app.blade.php can
     * render OG/Twitter/Schema tags server-side (Inertia has no SSR,
     * so SeoHead.vue only renders client-side, invisible to crawlers).
     */
    protected static ?array $currentSeo = null;

    public static function getCurrent(): ?array
    {
        return static::$currentSeo;
    }

    protected function s(string $key, mixed $default = null): mixed
    {
        if (empty($this->settings)) {
            try {
                $this->settings = Setting::getAll();
            } catch (\Exception $e) {
                $this->settings = [];
            }
        }
        return $this->settings[$key] ?? $default;
    }

    protected function siteName(): string
    {
        return $this->s('site_name', config('app.name', 'HubTube'));
    }

    protected function separator(): string
    {
        return $this->s('seo_title_separator', '|');
    }

    /**
     * Replace template variables with actual values.
     */
    protected function template(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', (string) $value, $template);
        }
        return $template;
    }

    /**
     * Truncate text to a max length at word boundary.
     */
    protected function truncate(string $text, int $max = 160): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', trim($text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        return mb_substr($text, 0, $max - 3) . '...';
    }

    /**
     * Build canonical URL for the current page.
     */
    public function canonical(?string $path = null): ?string
    {
        if (!$this->s('seo_canonical_enabled', true)) {
            return null;
        }
        $url = $path ? url($path) : url()->current();
        // Strip query parameters for canonical
        $url = strtok($url, '?');
        if ($this->s('seo_force_trailing_slash', false) && !str_ends_with($url, '/')) {
            $url .= '/';
        }
        return $url;
    }

    /**
     * Generate SEO data for the homepage.
     */
    public function forHome(): array
    {
        $title = $this->s('seo_home_title') ?: $this->s('seo_site_title') ?: $this->siteName();
        $description = $this->s('seo_home_description') ?: $this->s('seo_meta_description', '');

        $seo = $this->baseMeta($title, $description, '/');
        $seo['og']['type'] = 'website';

        // Organization schema on homepage
        if ($this->s('seo_schema_enabled', true)) {
            $seo['schema'][] = $this->organizationSchema();
            $seo['schema'][] = $this->websiteSchema();
        }

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for a video page.
     */
    public function forVideo(Video $video): array
    {
        $vars = [
            'title' => $video->title,
            'description' => $video->description ?? '',
            'site_name' => $this->siteName(),
            'uploader' => $video->user?->username ?? 'Unknown',
            'category' => $video->category?->name ?? '',
            'duration' => $video->formatted_duration ?? '',
            'views' => number_format($video->views_count),
            'tags' => is_array($video->tags) ? implode(', ', $video->tags) : '',
        ];

        // Title
        $titleTemplate = $this->s('seo_video_title_template', '{title} | {site_name}');
        $title = $this->template($titleTemplate, $vars);

        // Description
        $description = $video->description;
        if (empty($description) && $this->s('seo_video_auto_description', true)) {
            $fallbackTemplate = $this->s('seo_video_description_fallback', 'Watch {title} on {site_name}.');
            $description = $this->template($fallbackTemplate, $vars);
        }
        $descriptionTemplate = $this->s('seo_video_description_template', '{description}');
        $metaDescription = $this->truncate($this->template($descriptionTemplate, array_merge($vars, ['description' => $description])));

        $thumbnailUrl = $video->thumbnail_url;

        // For og:image and twitter:image we need a permanent (non-expiring) URL.
        // Prefer local thumbnail over external_thumbnail_url because migrated videos
        // may still have stale Bunny CDN URLs in external_thumbnail_url even though
        // the thumbnail was downloaded locally during migration.
        if ($video->thumbnail) {
            $ogThumbnailUrl = StorageManager::permanentUrl($video->thumbnail, $video->storage_disk ?? 'public');
        } elseif ($video->external_thumbnail_url) {
            $ogThumbnailUrl = $video->external_thumbnail_url;
        } else {
            $ogThumbnailUrl = $thumbnailUrl;
        }

        $videoUrl = $video->video_url;
        $canonicalPath = "/{$video->slug}";

        $seo = $this->baseMeta($title, $metaDescription, $canonicalPath);

        // Enhanced OG tags for video
        $seo['og']['type'] = 'video.other';
        $seo['og']['image'] = $ogThumbnailUrl;
        $seo['og']['image:width'] = '1280';
        $seo['og']['image:height'] = '720';
        $seo['og']['video:duration'] = (string) ($video->duration ?? 0);
        $seo['og']['video:release_date'] = $video->published_at?->toIso8601String() ?? $video->created_at->toIso8601String();
        if (is_array($video->tags)) {
            $seo['og']['video:tag'] = $video->tags;
        }

        // Twitter player card
        $seo['twitter']['card'] = 'summary_large_image';
        $seo['twitter']['image'] = $ogThumbnailUrl;

        // Thumbnail alt text
        $altTemplate = $this->s('seo_video_thumbnail_alt_template', '{title} - Video Thumbnail');
        $seo['thumbnailAlt'] = $this->template($altTemplate, $vars);

        // Robots
        if ($video->privacy !== 'public' && $this->s('seo_noindex_private_videos', true)) {
            $seo['robots'] = 'noindex, nofollow';
        }

        // JSON-LD VideoObject schema
        if ($this->s('seo_video_schema_enabled', true)) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'VideoObject',
                'name' => $video->title,
                'description' => $this->truncate($description ?: $video->title, 300),
                'thumbnailUrl' => [$thumbnailUrl],
                'uploadDate' => $video->published_at?->toIso8601String() ?? $video->created_at->toIso8601String(),
                'duration' => $this->isoDuration($video->duration ?? 0),
                'contentUrl' => $videoUrl,
                'interactionStatistic' => [
                    '@type' => 'InteractionCounter',
                    'interactionType' => ['@type' => 'WatchAction'],
                    'userInteractionCount' => $video->views_count,
                ],
            ];

            if ($this->s('seo_video_embed_enabled', true)) {
                $schema['embedUrl'] = url("/{$video->slug}");
            }

            if ($video->user) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $video->user->username,
                    'url' => url("/channel/{$video->user->username}"),
                ];
            }

            if ($video->category) {
                $schema['genre'] = $video->category->name;
            }

            if (is_array($video->tags) && count($video->tags) > 0) {
                $schema['keywords'] = implode(', ', $video->tags);
            }

            if ($video->likes_count > 0 || $video->dislikes_count > 0) {
                $total = $video->likes_count + $video->dislikes_count;
                $rating = $total > 0 ? round(($video->likes_count / $total) * 5, 1) : 0;
                $schema['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => (string) $rating,
                    'bestRating' => '5',
                    'worstRating' => '1',
                    'ratingCount' => (string) $total,
                ];
            }

            if ($video->comments_count > 0) {
                $schema['commentCount'] = $video->comments_count;
            }

            $schema['isFamilyFriendly'] = !$video->age_restricted;

            // Add language annotation
            $schema['inLanguage'] = App::getLocale();

            $seo['schema'][] = $schema;
        }

        // Multi-language SEO: og:locale:alternate + alternateUrls for hreflang
        $enabledLocales = TranslationService::getEnabledLocales();
        if (count($enabledLocales) > 1) {
            $defaultLocale = TranslationService::getDefaultLocale();
            $currentLocale = App::getLocale();

            // Set og:locale to current locale (e.g. es_ES)
            $seo['og']['locale'] = $this->toOgLocale($currentLocale);

            // Add og:locale:alternate for all other enabled languages
            $seo['og']['locale:alternate'] = [];
            foreach ($enabledLocales as $locale) {
                if ($locale !== $currentLocale) {
                    $seo['og']['locale:alternate'][] = $this->toOgLocale($locale);
                }
            }

            // Build alternate URLs using translated slugs
            $translationService = app(TranslationService::class);
            $seo['alternateUrls'] = $translationService->getAlternateUrls(Video::class, $video->id, $video->slug);
        }

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for a channel page.
     */
    public function forChannel(User $user): array
    {
        $vars = [
            'channel_name' => $user->channel?->name ?? $user->username,
            'site_name' => $this->siteName(),
            'subscriber_count' => number_format($user->subscriber_count),
            'video_count' => number_format($user->videos()->public()->approved()->count()),
        ];

        $titleTemplate = $this->s('seo_channel_title_template', '{channel_name} | {site_name}');
        $title = $this->template($titleTemplate, $vars);

        $descTemplate = $this->s('seo_channel_description_template', 'Watch videos from {channel_name} on {site_name}. {subscriber_count} subscribers.');
        $description = $this->truncate($this->template($descTemplate, $vars));

        $seo = $this->baseMeta($title, $description, "/channel/{$user->username}");
        $seo['og']['type'] = 'profile';
        if ($user->avatar) {
            $seo['og']['image'] = $user->avatar;
        }

        // Person schema
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Person',
            'name' => $user->channel?->name ?? $user->username,
            'url' => url("/channel/{$user->username}"),
            'interactionStatistic' => [
                '@type' => 'InteractionCounter',
                'interactionType' => ['@type' => 'SubscribeAction'],
                'userInteractionCount' => $user->subscriber_count,
            ],
        ];
        if ($user->avatar) {
            $schema['image'] = $user->avatar;
        }
        if ($user->bio) {
            $schema['description'] = $this->truncate($user->bio, 300);
        }
        $seo['schema'][] = $schema;

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for a playlist page.
     */
    public function forPlaylist(Playlist $playlist): array
    {
        $videoCount = $playlist->videos_count ?? $playlist->videos()->count();
        $vars = [
            'playlist_title' => $playlist->title,
            'site_name' => $this->siteName(),
            'video_count' => number_format($videoCount),
            'creator' => $playlist->user?->username ?? 'Unknown',
        ];

        $title = $this->template('{playlist_title} - Playlist | {site_name}', $vars);

        $description = $playlist->description;
        if (empty($description)) {
            $description = $this->template('Watch {video_count} videos in the "{playlist_title}" playlist by {creator} on {site_name}.', $vars);
        }
        $metaDescription = $this->truncate($description);

        $canonicalPath = "/playlist/{$playlist->slug}";
        $seo = $this->baseMeta($title, $metaDescription, $canonicalPath);

        $seo['og']['type'] = 'website';

        // Use first video's thumbnail as OG image if available
        $firstVideo = $playlist->relationLoaded('videos') && $playlist->videos->isNotEmpty()
            ? $playlist->videos->first()
            : $playlist->videos()->first();
        if ($firstVideo) {
            $seo['og']['image'] = $firstVideo->thumbnail_url;
            $seo['twitter']['image'] = $firstVideo->thumbnail_url;
        }

        $seo['twitter']['card'] = 'summary_large_image';

        // All playlists are public now

        // JSON-LD ItemList schema
        if ($this->s('seo_schema_enabled', true)) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => $playlist->title,
                'description' => $this->truncate($description, 300),
                'url' => url($canonicalPath),
                'numberOfItems' => $videoCount,
            ];

            if ($playlist->user) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $playlist->user->username,
                    'url' => url("/channel/{$playlist->user->username}"),
                ];
            }

            // Add individual video items to the list
            $videos = $playlist->relationLoaded('videos') ? $playlist->videos : $playlist->videos()->limit(50)->get();
            if ($videos->isNotEmpty()) {
                $schema['itemListElement'] = $videos->map(function ($video, $index) {
                    return [
                        '@type' => 'ListItem',
                        'position' => $index + 1,
                        'url' => url("/{$video->slug}"),
                        'name' => $video->title,
                    ];
                })->toArray();
            }

            $seo['schema'][] = $schema;
        }

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for a category page.
     */
    public function forCategory(Category $category): array
    {
        $vars = [
            'category_name' => $category->name,
            'site_name' => $this->siteName(),
        ];

        $titleTemplate = $this->s('seo_category_title_template', '{category_name} Videos | {site_name}');
        $title = $this->template($titleTemplate, $vars);

        $descTemplate = $this->s('seo_category_description_template', 'Browse {category_name} videos on {site_name}.');
        $description = $this->truncate($this->template($descTemplate, $vars));

        $seo = $this->baseMeta($title, $description, "/category/{$category->slug}");

        // CollectionPage schema
        $seo['schema'][] = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $category->name,
            'description' => $description,
            'url' => url("/category/{$category->slug}"),
        ];

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for trending page.
     */
    public function forTrending(): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_trending_title', 'Trending Videos | {site_name}'), $vars);
        $description = $this->truncate($this->template($this->s('seo_trending_description', 'Watch the most popular trending videos on {site_name} right now.'), $vars));

        $seo = $this->baseMeta($title, $description, '/trending');
        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for live streams page.
     */
    public function forLive(): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_live_title', 'Live Streams | {site_name}'), $vars);
        $description = $this->truncate($this->template($this->s('seo_live_description', 'Watch live streams on {site_name}.'), $vars));

        $seo = $this->baseMeta($title, $description, '/live');
        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for search results.
     */
    public function forSearch(string $query): array
    {
        $vars = ['query' => $query, 'site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_search_title', 'Search Results for "{query}" | {site_name}'), $vars);

        $seo = $this->baseMeta($title, '', '/search');
        $seo['robots'] = 'noindex, follow';

        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Generate SEO data for a tag page.
     */
    public function forTag(string $tag): array
    {
        $title = "#{$tag} Videos {$this->separator()} {$this->siteName()}";
        $description = $this->truncate("Watch videos tagged with #{$tag} on {$this->siteName()}.");

        $seo = $this->baseMeta($title, $description, "/tag/{$tag}");
        static::$currentSeo = $seo;
        return $seo;
    }

    /**
     * Build base meta array shared by all pages.
     */
    protected function baseMeta(string $title, string $description, ?string $canonicalPath = null): array
    {
        $canonical = $this->canonical($canonicalPath);
        $currentLocale = App::getLocale();

        $meta = [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => null,
            'og' => [
                'title' => $title,
                'description' => $description,
                'url' => $canonical ?? url()->current(),
                'site_name' => $this->siteName(),
                'locale' => $this->toOgLocale($currentLocale),
                'type' => $this->s('seo_og_type', 'website'),
                'image' => $this->s('seo_og_image', ''),
            ],
            'twitter' => [
                'card' => $this->s('seo_twitter_card', 'summary_large_image'),
                'site' => $this->s('seo_twitter_site', ''),
                'title' => $title,
                'description' => $description,
            ],
            'keywords' => $this->s('seo_meta_keywords', ''),
            'schema' => [],
            'thumbnailAlt' => '',
            'alternateUrls' => [],
        ];

        // Add og:locale:alternate for all other enabled languages
        $enabledLocales = TranslationService::getEnabledLocales();
        if (count($enabledLocales) > 1) {
            $meta['og']['locale:alternate'] = [];
            foreach ($enabledLocales as $locale) {
                if ($locale !== $currentLocale) {
                    $meta['og']['locale:alternate'][] = $this->toOgLocale($locale);
                }
            }
        }

        return $meta;
    }

    /**
     * Generate Organization JSON-LD schema.
     */
    protected function organizationSchema(): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $this->s('seo_schema_org_name') ?: $this->siteName(),
            'url' => $this->s('seo_schema_org_url') ?: url('/'),
        ];

        $logo = $this->s('seo_schema_org_logo');
        if ($logo) {
            $schema['logo'] = $logo;
        }

        $sameAs = $this->s('seo_schema_same_as', '');
        if ($sameAs) {
            $links = array_filter(array_map('trim', explode("\n", $sameAs)));
            if (!empty($links)) {
                $schema['sameAs'] = $links;
            }
        }

        return $schema;
    }

    /**
     * Generate WebSite JSON-LD schema with SearchAction.
     */
    protected function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $this->siteName(),
            'url' => url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => url('/search') . '?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Convert a locale code (e.g. 'en') to OG locale format (e.g. 'en_US').
     */
    protected function toOgLocale(string $locale): string
    {
        $map = [
            'en' => 'en_US', 'es' => 'es_ES', 'fr' => 'fr_FR', 'de' => 'de_DE',
            'pt' => 'pt_BR', 'it' => 'it_IT', 'nl' => 'nl_NL', 'ru' => 'ru_RU',
            'ja' => 'ja_JP', 'ko' => 'ko_KR', 'zh' => 'zh_CN', 'ar' => 'ar_SA',
            'hi' => 'hi_IN', 'tr' => 'tr_TR', 'pl' => 'pl_PL', 'sv' => 'sv_SE',
            'da' => 'da_DK', 'no' => 'nb_NO', 'fi' => 'fi_FI', 'cs' => 'cs_CZ',
            'th' => 'th_TH', 'vi' => 'vi_VN', 'id' => 'id_ID', 'ms' => 'ms_MY',
            'ro' => 'ro_RO', 'uk' => 'uk_UA', 'el' => 'el_GR', 'hu' => 'hu_HU',
            'he' => 'he_IL', 'bg' => 'bg_BG', 'hr' => 'hr_HR', 'sk' => 'sk_SK',
            'sr' => 'sr_RS', 'lt' => 'lt_LT', 'lv' => 'lv_LV', 'et' => 'et_EE',
            'fil' => 'fil_PH',
        ];
        return $map[$locale] ?? $locale;
    }

    /**
     * Convert seconds to ISO 8601 duration (PT#H#M#S).
     */
    protected function isoDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'PT0S';
        }
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        $duration = 'PT';
        if ($hours > 0) $duration .= "{$hours}H";
        if ($minutes > 0) $duration .= "{$minutes}M";
        if ($secs > 0 || $duration === 'PT') $duration .= "{$secs}S";

        return $duration;
    }
}
