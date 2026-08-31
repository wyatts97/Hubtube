<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Page;
use App\Models\Playlist;
use App\Services\SocialLinkService;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
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

    /**
     * Clear the static holder at the start of each request.
     *
     * Without this the payload survives between requests in any long-running
     * worker (Octane, queue-driven rendering) and across tests in one process,
     * so a page that never built its own SEO data would silently inherit the
     * previous page's title, canonical and JSON-LD.
     */
    public static function reset(): void
    {
        static::$currentSeo = null;
    }

    /**
     * Build the rel="alternate" hreflang map for the current page.
     *
     * Two shapes of page exist:
     *
     *  - Videos carry per-locale slugs, so a builder stores the resolved URLs
     *    in the payload's `alternateUrls` and those are used verbatim.
     *  - Everything else shares one path across locales (/es/category/foo),
     *    which TranslationService::hreflangMapForPath() derives.
     *
     * Returns [hreflangCode => absoluteUrl], including x-default. Empty when
     * only one locale is enabled.
     */
    public static function hreflangTags(): array
    {
        try {
            if (count(TranslationService::getEnabledLocales()) <= 1) {
                return [];
            }

            $alternates = static::$currentSeo['alternateUrls'] ?? [];

            return empty($alternates)
                ? TranslationService::hreflangMapForPath(static::localeFreePath())
                : static::hreflangMapForAlternates($alternates);
        } catch (Exception $e) {
            // Settings/translations tables may be unavailable (install, tests).
            return [];
        }
    }

    /**
     * Map pre-resolved per-locale URLs onto BCP 47 hreflang codes.
     *
     * Locales whose URL duplicates one already emitted are skipped, so an
     * untranslated video doesn't advertise the same page several times — but
     * the default locale always keeps its own tag alongside x-default.
     */
    protected static function hreflangMapForAlternates(array $alternates): array
    {
        $defaultLocale = TranslationService::getDefaultLocale();
        $defaultUrl = $alternates[$defaultLocale] ?? reset($alternates);

        $map = ['x-default' => $defaultUrl];
        $seen = [$defaultUrl => true];

        foreach ($alternates as $locale => $href) {
            $tag = TranslationService::toHreflang($locale);

            if (! isset($seen[$href])) {
                $map[$tag] = $href;
                $seen[$href] = true;
            } elseif ($locale === $defaultLocale) {
                $map[$tag] = $href;
            }
        }

        return $map;
    }

    /**
     * The current request path with any locale prefix removed.
     *
     * Matching the first segment against the enabled locales matters: a blind
     * [a-z]{2,3} pattern also eats real first segments like /tag/... and /pro,
     * which would point those pages' hreflang at the wrong URL.
     */
    protected static function localeFreePath(): string
    {
        $segments = array_values(array_filter(explode('/', request()->path()), 'strlen'));

        if (isset($segments[0]) && TranslationService::isValidLocale($segments[0])) {
            array_shift($segments);
        }

        return '/'.implode('/', $segments);
    }

    protected function s(string $key, mixed $default = null): mixed
    {
        if (empty($this->settings)) {
            try {
                $this->settings = Setting::getAll();
            } catch (Exception $e) {
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
            $template = str_replace('{'.$key.'}', (string) $value, $template);
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

        // Cut back to the last whole word so descriptions don't end mid-word.
        // A single word longer than the limit has no space to fall back to, so
        // it's cut hard rather than returned empty.
        $cut = mb_substr($text, 0, $max - 3);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!?").'...';
    }

    /**
     * Build canonical URL for the current page.
     */
    public function canonical(?string $path = null): ?string
    {
        if (! $this->s('seo_canonical_enabled', true)) {
            return null;
        }
        $url = $path ? url($path) : url()->current();

        // Strip query parameters for canonical.
        // Note: no trailing slash is ever appended. public/.htaccess 301-redirects
        // trailing slashes away, so a canonical carrying one would point at a URL
        // that immediately redirects. The old seo_force_trailing_slash setting that
        // did this has been removed.
        return strtok($url, '?');
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

        // Thumbnail alt text. Read through the model accessor so this page
        // and every video card elsewhere on the site announce the same string:
        // the accessor returns the persisted thumbnail_alt_text column and only
        // falls back to generating when the backfill has not reached this row.
        $seo['thumbnailAlt'] = $video->thumbnail_alt;

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

            $schema['isFamilyFriendly'] = ! $video->age_restricted;

            // Add language annotation
            $schema['inLanguage'] = App::getLocale();

            $seo['schema'][] = $schema;
        }

        // Breadcrumbs: Home > Category > Video
        $crumbs = [];
        if ($video->category) {
            $crumbs[] = ['name' => $video->category->name, 'path' => "/category/{$video->category->slug}"];
        }
        $crumbs[] = ['name' => $video->title, 'path' => $canonicalPath];
        $seo['schema'][] = $this->breadcrumbSchema($crumbs);

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
        // The banner is a far better share card than a 256px square avatar.
        if ($banner = $user->channel?->banner_image) {
            $seo['og']['image'] = $banner;
        } elseif ($user->avatar) {
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
        // Prefer the channel description; users.bio is the legacy field and is
        // not what the channel page renders.
        $description = $user->channel?->description ?: $user->bio;
        if ($description) {
            $schema['description'] = $this->truncate($description, 300);
        }

        // sameAs is the standard way to tell search engines which off-site
        // profiles belong to this person. Only validated links go in.
        $sameAs = array_column(
            app(SocialLinkService::class)->forDisplay($user),
            'url'
        );
        if ($sameAs) {
            $schema['sameAs'] = $sameAs;
        }

        $seo['schema'][] = $schema;
        $seo['schema'][] = $this->breadcrumbSchema([
            ['name' => $vars['channel_name'], 'path' => "/channel/{$user->username}"],
        ]);

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
    public function forCategory(Category $category, mixed $paginator = null): array
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

        $seo['schema'][] = $this->breadcrumbSchema([
            ['name' => 'Categories', 'path' => '/categories'],
            ['name' => $category->name, 'path' => "/category/{$category->slug}"],
        ]);
        $seo['pagination'] = $this->paginationLinks($paginator);

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the Shorts page.
     */
    public function forShorts(array $filters = []): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_shorts_title', 'Shorts | {site_name}'), $vars);
        $description = $this->truncate($this->template($this->s('seo_shorts_description', 'Watch quick vertical short-form videos on {site_name}.'), $vars));

        $seo = $this->baseMeta($title, $description, '/shorts');
        $seo['og']['type'] = 'website';

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
     * Generate SEO data for search results.
     */
    public function forSearch(?string $query): array
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
    public function forTag(string $tag, mixed $paginator = null): array
    {
        $title = "#{$tag} Videos {$this->separator()} {$this->siteName()}";
        $description = $this->truncate("Watch videos tagged with #{$tag} on {$this->siteName()}.");

        $seo = $this->baseMeta($title, $description, "/tag/{$tag}");

        $seo['schema'][] = $this->breadcrumbSchema([
            ['name' => 'Tags', 'path' => '/tags'],
            ['name' => "#{$tag}", 'path' => "/tag/{$tag}"],
        ]);
        $seo['pagination'] = $this->paginationLinks($paginator);

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the videos browse index page (/videos).
     */
    public function forVideosIndex(?string $category = null, ?string $sort = null, mixed $paginator = null): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_videos_index_title', 'All Videos | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_videos_index_description', 'Browse all videos on {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/videos');
        $seo['og']['type'] = 'website';

        // Filtered/sorted views shouldn't dilute the canonical /videos page
        if ($category || ($sort && $sort !== 'newest')) {
            $seo['robots'] = 'noindex, follow';
        }

        if ($this->s('seo_schema_enabled', true)) {
            $seo['schema'][] = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'description' => $description,
                'url' => url('/videos'),
            ];
        }

        $seo['schema'][] = $this->breadcrumbSchema([['name' => 'Videos', 'path' => '/videos']]);
        $seo['pagination'] = $this->paginationLinks($paginator);

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the categories index page (/categories).
     */
    public function forCategoriesIndex(): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_categories_index_title', 'All Categories | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_categories_index_description', 'Browse video categories on {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/categories');
        $seo['og']['type'] = 'website';

        if ($this->s('seo_schema_enabled', true)) {
            $seo['schema'][] = [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => $title,
                'description' => $description,
                'url' => url('/categories'),
            ];
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the tags index page (/tags).
     */
    public function forTagsIndex(): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_tags_index_title', 'All Tags | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_tags_index_description', 'Browse all video tags on {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/tags');
        $seo['og']['type'] = 'website';

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the images browse index (/images).
     */
    public function forImagesIndex(?string $category = null, ?string $sort = null): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_images_index_title', 'Photos | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_images_index_description', 'Browse photos uploaded to {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/images');
        $seo['og']['type'] = 'website';

        if ($category || ($sort && $sort !== 'newest')) {
            $seo['robots'] = 'noindex, follow';
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for an image detail page (/image/{slug}).
     */
    public function forImage(Image $image): array
    {
        $title = $image->title ?: ('Photo '.$image->uuid);
        $vars = [
            'title' => $title,
            'site_name' => $this->siteName(),
            'uploader' => $image->user?->username ?? 'Unknown',
        ];

        $titleStr = $this->template('{title} | {site_name}', $vars);
        $descRaw = $image->description ?: $this->template('View photo "{title}" on {site_name}.', $vars);
        $description = $this->truncate($descRaw);

        $canonicalPath = "/image/{$image->slug}";
        $seo = $this->baseMeta($titleStr, $description, $canonicalPath);

        $imageUrl = null;
        if ($image->file_path) {
            $imageUrl = StorageManager::permanentUrl($image->file_path, $image->storage_disk ?? 'public');
        }

        if ($imageUrl) {
            $seo['og']['type'] = 'article';
            $seo['og']['image'] = $imageUrl;
            $seo['twitter']['image'] = $imageUrl;
            $seo['twitter']['card'] = 'summary_large_image';
        }

        // noindex non-public/unapproved images
        if (
            (isset($image->privacy) && $image->privacy !== 'public')
            || (isset($image->is_approved) && ! $image->is_approved)
        ) {
            $seo['robots'] = 'noindex, nofollow';
        }

        if ($this->s('seo_schema_enabled', true) && $imageUrl) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'ImageObject',
                'name' => $title,
                'description' => $description,
                'contentUrl' => $imageUrl,
                'url' => url($canonicalPath),
                'uploadDate' => ($image->created_at ?? now())->toIso8601String(),
            ];
            if ($image->user) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $image->user->username,
                    'url' => url("/channel/{$image->user->username}"),
                ];
            }
            if (! empty($image->width) && ! empty($image->height)) {
                $schema['width'] = (int) $image->width;
                $schema['height'] = (int) $image->height;
            }
            $seo['schema'][] = $schema;
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the galleries browse index (/galleries).
     */
    public function forGalleriesIndex(?string $sort = null): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_galleries_index_title', 'Photo Galleries | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_galleries_index_description', 'Browse photo galleries on {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/galleries');
        $seo['og']['type'] = 'website';

        if ($sort && $sort !== 'newest') {
            $seo['robots'] = 'noindex, follow';
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for a gallery detail page (/gallery/{slug}).
     */
    public function forGallery(Gallery $gallery): array
    {
        $vars = [
            'title' => $gallery->title,
            'site_name' => $this->siteName(),
            'creator' => $gallery->user?->username ?? 'Unknown',
            'image_count' => number_format($gallery->images_count ?? 0),
        ];

        $title = $this->template('{title} - Gallery | {site_name}', $vars);
        $descRaw = $gallery->description
            ?: $this->template('Browse {image_count} photos in the "{title}" gallery by {creator} on {site_name}.', $vars);
        $description = $this->truncate($descRaw);

        $canonicalPath = "/gallery/{$gallery->slug}";
        $seo = $this->baseMeta($title, $description, $canonicalPath);
        $seo['og']['type'] = 'website';

        $coverImage = $gallery->relationLoaded('coverImage')
            ? $gallery->coverImage
            : $gallery->coverImage()->first();

        if ($coverImage && $coverImage->file_path) {
            $coverUrl = StorageManager::permanentUrl(
                $coverImage->thumbnail_path ?: $coverImage->file_path,
                $coverImage->storage_disk ?? 'public'
            );
            if ($coverUrl) {
                $seo['og']['image'] = $coverUrl;
                $seo['twitter']['image'] = $coverUrl;
                $seo['twitter']['card'] = 'summary_large_image';
            }
        }

        if (isset($gallery->privacy) && $gallery->privacy !== 'public') {
            $seo['robots'] = 'noindex, nofollow';
        }

        if ($this->s('seo_schema_enabled', true)) {
            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'ImageGallery',
                'name' => $gallery->title,
                'description' => $description,
                'url' => url($canonicalPath),
                'numberOfItems' => (int) ($gallery->images_count ?? 0),
            ];
            if ($gallery->user) {
                $schema['author'] = [
                    '@type' => 'Person',
                    'name' => $gallery->user->username,
                    'url' => url("/channel/{$gallery->user->username}"),
                ];
            }
            $seo['schema'][] = $schema;
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for the public playlists index (/public-playlists).
     */
    public function forPublicPlaylists(?string $sort = null): array
    {
        $vars = ['site_name' => $this->siteName()];
        $title = $this->template($this->s('seo_public_playlists_title', 'Public Playlists | {site_name}'), $vars);
        $description = $this->truncate($this->template(
            $this->s('seo_public_playlists_description', 'Discover community-created public playlists on {site_name}.'),
            $vars
        ));

        $seo = $this->baseMeta($title, $description, '/public-playlists');
        $seo['og']['type'] = 'website';

        if ($sort && $sort !== 'newest') {
            $seo['robots'] = 'noindex, follow';
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for a static/legal page (/pages/{slug}).
     */
    public function forPage(Page $page, ?string $translatedTitle = null, ?string $translatedContent = null): array
    {
        $title = $translatedTitle ?: $page->title;
        $vars = [
            'title' => $title,
            'site_name' => $this->siteName(),
        ];

        $titleStr = $this->template('{title} | {site_name}', $vars);

        $body = $translatedContent ?: ($page->content ?? '');
        $description = $this->truncate(strip_tags($body) ?: $title);

        $canonicalPath = "/pages/{$page->slug}";
        $seo = $this->baseMeta($titleStr, $description, $canonicalPath);
        $seo['og']['type'] = 'article';

        // Legal/static pages rarely change — let crawlers index them
        if ($this->s('seo_schema_enabled', true)) {
            $seo['schema'][] = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $title,
                'description' => $description,
                'url' => url($canonicalPath),
                'dateModified' => $page->updated_at?->toIso8601String(),
            ];
        }

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Generate SEO data for a page that must never be indexed — authenticated
     * areas (settings, dashboard, wallet), auth screens, and error responses.
     *
     * These pages carry no Open Graph value, but they still need a payload so
     * that app.blade.php and SeoHead.vue render from the same source. Without
     * one, the Inertia head manager would strip the server-rendered tags on
     * hydration instead of replacing them.
     *
     * For account pages the noindex directive is gated on seo_noindex_user_pages
     * (default on) — turning it off doesn't make them reachable, they're still
     * behind auth, it just stops emitting the directive. Error responses pass
     * $alwaysNoindex so they never depend on that setting.
     */
    public function forPrivatePage(?string $title = null, bool $alwaysNoindex = false): array
    {
        $fullTitle = $title
            ? "{$title} {$this->separator()} {$this->siteName()}"
            : $this->siteName();

        $seo = $this->baseMeta($fullTitle, '', null);

        if ($alwaysNoindex || $this->s('seo_noindex_user_pages', true)) {
            $seo['robots'] = 'noindex, nofollow';
        }
        // Private URLs shouldn't advertise a canonical.
        $seo['canonical'] = null;
        $seo['og']['url'] = null;

        static::$currentSeo = $seo;

        return $seo;
    }

    /**
     * Site-wide default meta, used when a page never called a for*() builder.
     *
     * Sets the static holder so the Blade head and SeoHead.vue stay in sync.
     */
    public function defaultMeta(): array
    {
        $title = $this->s('seo_site_title') ?: $this->siteName();
        $description = $this->truncate($this->s('seo_meta_description', ''));

        $seo = $this->baseMeta($title, $description);

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
            'pagination' => [],
            // Exposed so SeoHead.vue can compose override titles with the same
            // separator the server uses. Not rendered as a tag.
            'separator' => $this->separator(),
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
     * Build BreadcrumbList JSON-LD from an ordered list of crumbs.
     *
     * Home is prepended automatically. Crumbs are ['name' => …, 'path' => …];
     * the final crumb is the current page and still carries its own URL, which
     * Google accepts and prefers over an item-less last entry.
     */
    protected function breadcrumbSchema(array $crumbs): array
    {
        $items = [];
        $position = 1;

        foreach (array_merge([['name' => 'Home', 'path' => '/']], $crumbs) as $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $crumb['name'],
                'item' => url($crumb['path']),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Previous/next URLs for a paginated listing.
     *
     * Google retired rel=prev/next as an indexing signal, but Bing and other
     * crawlers still consume it, and it remains valid markup for describing a
     * paginated series.
     */
    protected function paginationLinks(mixed $paginator): array
    {
        if (! $paginator instanceof LengthAwarePaginator) {
            return [];
        }

        return array_filter([
            'prev' => $paginator->currentPage() > 1 ? $paginator->previousPageUrl() : null,
            'next' => $paginator->hasMorePages() ? $paginator->nextPageUrl() : null,
        ]);
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
            if (! empty($links)) {
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
                    'urlTemplate' => url('/search').'?q={search_term_string}',
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
        if ($hours > 0) {
            $duration .= "{$hours}H";
        }
        if ($minutes > 0) {
            $duration .= "{$minutes}M";
        }
        if ($secs > 0 || $duration === 'PT') {
            $duration .= "{$secs}S";
        }

        return $duration;
    }
}
