<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\Video;
use App\Models\User;
use App\Services\StorageManager;
use App\Services\TranslationService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    /**
     * Enabled locales and default locale — loaded once per request.
     */
    private array $locales = [];
    private string $defaultLocale = 'en';
    private bool $multiLang = false;

    public function index(): Response
    {
        $this->defaultLocale = TranslationService::getDefaultLocale();
        $this->locales = TranslationService::getEnabledLocales();
        $this->multiLang = count($this->locales) > 1;

        $videoSitemapEnabled = Setting::get('seo_sitemap_video_enabled', true);
        $maxVideos = (int) Setting::get('seo_sitemap_max_videos', 10000);
        $maxChannels = (int) Setting::get('seo_sitemap_max_channels', 5000);

        $videoColumns = $videoSitemapEnabled
            ? ['id', 'slug', 'title', 'description', 'thumbnail', 'external_thumbnail_url', 'duration', 'views_count', 'tags', 'published_at', 'updated_at', 'user_id', 'storage_disk', 'is_embedded', 'age_restricted', 'category_id']
            : ['id', 'slug', 'updated_at'];

        $videos = Video::query()
            ->public()
            ->approved()
            ->processed()
            ->select($videoColumns)
            ->when($videoSitemapEnabled, fn($q) => $q->with('user:id,username', 'category:id,name'))
            ->latest('updated_at')
            ->limit($maxVideos)
            ->get();

        $channels = User::query()
            ->whereHas('videos', fn($q) => $q->public()->approved()->processed())
            ->select(['id', 'username', 'updated_at'])
            ->limit($maxChannels)
            ->get();

        // Pre-load all translated slugs for videos in one query (for hreflang alternates)
        $translatedSlugs = [];
        if ($this->multiLang && $videos->isNotEmpty()) {
            $translatedSlugs = Translation::where('translatable_type', Video::class)
                ->whereIn('translatable_id', $videos->pluck('id'))
                ->where('field', 'title')
                ->whereNotNull('translated_slug')
                ->get()
                ->groupBy('translatable_id')
                ->map(fn($group) => $group->pluck('translated_slug', 'locale')->toArray())
                ->toArray();
        }

        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        $namespaces = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($videoSitemapEnabled) {
            $namespaces .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
        }
        if ($this->multiLang) {
            $namespaces .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }
        $content .= "<urlset {$namespaces}>\n";

        // Static pages with hreflang alternates
        $staticPages = ['/', '/trending', '/shorts', '/live', '/categories'];
        foreach ($staticPages as $page) {
            $content .= $this->staticUrlEntry($page, now()->toW3cString(), 'daily', '0.8');
        }

        // Video pages with hreflang alternates and optional video:video extensions
        foreach ($videos as $video) {
            $slugsByLocale = $translatedSlugs[$video->id] ?? [];
            if ($videoSitemapEnabled) {
                $content .= $this->videoUrlEntry($video, $slugsByLocale);
            } else {
                $content .= $this->videoSimpleEntry($video, $slugsByLocale);
            }
        }

        // Channel pages with hreflang alternates
        foreach ($channels as $channel) {
            $content .= $this->channelUrlEntry($channel);
        }

        // Legal / static pages
        try {
            $pages = Page::published()->select(['slug', 'updated_at'])->get();
            foreach ($pages as $page) {
                $content .= $this->staticUrlEntry(
                    "/pages/{$page->slug}",
                    $page->updated_at->toW3cString(),
                    'monthly',
                    '0.5'
                );
            }
        } catch (\Exception $e) {
            // Pages table may not exist yet
        }

        $content .= '</urlset>';

        return response($content, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }

    /**
     * Generate hreflang <xhtml:link> elements for a static page path.
     * Static pages use the same path across locales: /{locale}/path
     */
    private function hreflangLinks(string $path): string
    {
        if (!$this->multiLang) {
            return '';
        }

        $links = '';
        $cleanPath = ltrim($path, '/');

        // x-default points to the default locale version
        $links .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->xmlEscape(url($path)) . '" />' . "\n";

        foreach ($this->locales as $locale) {
            if ($locale === $this->defaultLocale) {
                $href = url($path);
            } else {
                $href = url("/{$locale}" . ($cleanPath ? "/{$cleanPath}" : ''));
            }
            $links .= '    <xhtml:link rel="alternate" hreflang="' . $locale . '" href="' . $this->xmlEscape($href) . '" />' . "\n";
        }

        return $links;
    }

    /**
     * Generate hreflang <xhtml:link> elements for a video page using translated slugs.
     */
    private function videoHreflangLinks(Video $video, array $slugsByLocale): string
    {
        if (!$this->multiLang) {
            return '';
        }

        $links = '';

        // x-default points to the original slug
        $links .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->xmlEscape(url("/{$video->slug}")) . '" />' . "\n";

        foreach ($this->locales as $locale) {
            if ($locale === $this->defaultLocale) {
                $href = url("/{$video->slug}");
            } else {
                // Use translated slug if available, otherwise fall back to original slug
                $slug = $slugsByLocale[$locale] ?? $video->slug;
                $href = url("/{$locale}/{$slug}");
            }
            $links .= '    <xhtml:link rel="alternate" hreflang="' . $locale . '" href="' . $this->xmlEscape($href) . '" />' . "\n";
        }

        return $links;
    }

    /**
     * Static page URL entry with hreflang alternates.
     */
    private function staticUrlEntry(string $path, string $lastmod, string $changefreq, string $priority): string
    {
        $entry = "  <url>\n";
        $entry .= '    <loc>' . $this->xmlEscape(url($path)) . "</loc>\n";
        $entry .= "    <lastmod>{$lastmod}</lastmod>\n";
        $entry .= "    <changefreq>{$changefreq}</changefreq>\n";
        $entry .= "    <priority>{$priority}</priority>\n";
        $entry .= $this->hreflangLinks($path);
        $entry .= "  </url>\n";

        return $entry;
    }

    /**
     * Simple video URL entry (no video:video extensions) with hreflang alternates.
     */
    private function videoSimpleEntry(Video $video, array $slugsByLocale): string
    {
        $entry = "  <url>\n";
        $entry .= '    <loc>' . $this->xmlEscape(url("/{$video->slug}")) . "</loc>\n";
        $entry .= '    <lastmod>' . $video->updated_at->toW3cString() . "</lastmod>\n";
        $entry .= "    <changefreq>weekly</changefreq>\n";
        $entry .= "    <priority>0.9</priority>\n";
        $entry .= $this->videoHreflangLinks($video, $slugsByLocale);
        $entry .= "  </url>\n";

        return $entry;
    }

    /**
     * Channel URL entry with hreflang alternates.
     */
    private function channelUrlEntry(User $channel): string
    {
        $path = "/channel/{$channel->username}";

        $entry = "  <url>\n";
        $entry .= '    <loc>' . $this->xmlEscape(url($path)) . "</loc>\n";
        $entry .= '    <lastmod>' . $channel->updated_at->toW3cString() . "</lastmod>\n";
        $entry .= "    <changefreq>weekly</changefreq>\n";
        $entry .= "    <priority>0.7</priority>\n";
        $entry .= $this->hreflangLinks($path);
        $entry .= "  </url>\n";

        return $entry;
    }

    /**
     * Generate a <url> entry with video:video extensions and hreflang alternates.
     * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps
     */
    private function videoUrlEntry(Video $video, array $slugsByLocale = []): string
    {
        $loc = $this->xmlEscape(url("/{$video->slug}"));
        $title = $this->xmlEscape($video->title);
        $description = $this->xmlEscape(
            mb_substr(strip_tags($video->description ?? $video->title), 0, 2048)
        );

        // Thumbnail URL
        $thumbnailUrl = '';
        if ($video->external_thumbnail_url) {
            $thumbnailUrl = $video->external_thumbnail_url;
        } elseif ($video->thumbnail) {
            $thumbnailUrl = StorageManager::url($video->thumbnail, $video->storage_disk ?? 'public');
        }
        $thumbnailUrl = $this->xmlEscape($thumbnailUrl);

        // Content URL (the actual video file)
        $contentUrl = '';
        if (!$video->is_embedded && $video->video_url) {
            $contentUrl = $this->xmlEscape($video->video_url);
        }

        // Player URL (the page where the video plays)
        $playerUrl = $this->xmlEscape(url("/{$video->slug}"));

        // Duration in seconds
        $duration = $video->duration ?? 0;

        // View count
        $viewCount = $video->views_count ?? 0;

        // Publication date
        $pubDate = ($video->published_at ?? $video->created_at)?->toW3cString() ?? now()->toW3cString();

        // Family friendly
        $familyFriendly = $video->age_restricted ? 'no' : 'yes';

        // Uploader
        $uploader = $this->xmlEscape($video->user?->username ?? 'Unknown');
        $uploaderUrl = $video->user ? $this->xmlEscape(url("/channel/{$video->user->username}")) : '';

        // Tags (max 32 per Google spec)
        $tags = is_array($video->tags) ? array_slice($video->tags, 0, 32) : [];

        // Category
        $category = $this->xmlEscape($video->category?->name ?? '');

        $entry = "  <url>\n";
        $entry .= "    <loc>{$loc}</loc>\n";
        $entry .= "    <lastmod>{$pubDate}</lastmod>\n";
        $entry .= "    <changefreq>weekly</changefreq>\n";
        $entry .= "    <priority>0.9</priority>\n";

        // hreflang alternates for multi-language SEO
        $entry .= $this->videoHreflangLinks($video, $slugsByLocale);

        $entry .= "    <video:video>\n";
        $entry .= "      <video:thumbnail_loc>{$thumbnailUrl}</video:thumbnail_loc>\n";
        $entry .= "      <video:title>{$title}</video:title>\n";
        $entry .= "      <video:description>{$description}</video:description>\n";

        if ($contentUrl) {
            $entry .= "      <video:content_loc>{$contentUrl}</video:content_loc>\n";
        }

        $entry .= "      <video:player_loc>{$playerUrl}</video:player_loc>\n";

        if ($duration > 0) {
            $entry .= "      <video:duration>{$duration}</video:duration>\n";
        }

        $entry .= "      <video:view_count>{$viewCount}</video:view_count>\n";
        $entry .= "      <video:publication_date>{$pubDate}</video:publication_date>\n";
        $entry .= "      <video:family_friendly>{$familyFriendly}</video:family_friendly>\n";

        if ($uploader) {
            if ($uploaderUrl) {
                $entry .= "      <video:uploader info=\"{$uploaderUrl}\">{$uploader}</video:uploader>\n";
            } else {
                $entry .= "      <video:uploader>{$uploader}</video:uploader>\n";
            }
        }

        if ($category) {
            $entry .= "      <video:category>{$category}</video:category>\n";
        }

        foreach ($tags as $tag) {
            $entry .= "      <video:tag>" . $this->xmlEscape($tag) . "</video:tag>\n";
        }

        $entry .= "      <video:live>no</video:live>\n";
        $entry .= "    </video:video>\n";
        $entry .= "  </url>\n";

        return $entry;
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
