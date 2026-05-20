<?php

namespace App\Http\Controllers;

use Throwable;
use Carbon\Carbon;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Page;
use App\Models\Playlist;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\User;
use App\Models\Video;
use App\Services\StorageManager;
use App\Services\TranslationService;
use Illuminate\Http\Response;

/**
 * Sitemap controller. Emits a true <sitemapindex> at /sitemap.xml
 * and serves individual child sitemaps for each content type:
 *
 *   /sitemap-pages.xml       static + legal pages
 *   /sitemap-categories.xml  category index + category pages
 *   /sitemap-tags.xml        tag index + per-tag pages
 *   /sitemap-channels.xml    channel pages
 *   /sitemap-videos.xml      first chunk of videos (with video:video extensions)
 *   /sitemap-videos-{n}.xml  paginated additional video chunks
 *   /sitemap-images.xml      public approved images (with image:image extension)
 *   /sitemap-galleries.xml   public galleries
 *   /sitemap-playlists.xml   public playlists
 */
class SitemapController extends Controller
{
    private array $locales = [];
    private string $defaultLocale = 'en';
    private bool $multiLang = false;

    public function __construct()
    {
        $this->defaultLocale = TranslationService::getDefaultLocale();
        $this->locales = TranslationService::getEnabledLocales();
        $this->multiLang = count($this->locales) > 1;
    }

    /**
     * Sitemap index — lists all child sitemaps.
     */
    public function index(): Response
    {
        $chunkSize = $this->videoChunkSize();
        $videoCount = Video::query()->public()->approved()->processed()->count();
        $videoChunks = max(1, (int) ceil($videoCount / $chunkSize));

        $entries = [];

        $entries[] = ['loc' => url('/sitemap-pages.xml'), 'lastmod' => now()->toW3cString()];
        $entries[] = ['loc' => url('/sitemap-categories.xml'), 'lastmod' => $this->maxUpdatedAt(Category::query())];
        $entries[] = ['loc' => url('/sitemap-tags.xml'), 'lastmod' => now()->toW3cString()];
        $entries[] = ['loc' => url('/sitemap-channels.xml'), 'lastmod' => $this->maxUpdatedAt(User::query())];

        for ($i = 1; $i <= $videoChunks; $i++) {
            $loc = $i === 1 ? url('/sitemap-videos.xml') : url("/sitemap-videos-{$i}.xml");
            $entries[] = ['loc' => $loc, 'lastmod' => $this->maxUpdatedAt(Video::query()->public()->approved()->processed())];
        }

        if (Setting::get('seo_sitemap_images_enabled', true)) {
            $entries[] = ['loc' => url('/sitemap-images.xml'), 'lastmod' => $this->maxUpdatedAt(Image::query()->public()->approved())];
        }
        if (Setting::get('seo_sitemap_galleries_enabled', true)) {
            $entries[] = ['loc' => url('/sitemap-galleries.xml'), 'lastmod' => $this->maxUpdatedAt(Gallery::query()->public())];
        }
        if (Setting::get('seo_sitemap_playlists_enabled', true)) {
            $entries[] = ['loc' => url('/sitemap-playlists.xml'), 'lastmod' => $this->maxUpdatedAt(Playlist::query()->public())];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            $xml .= "  <sitemap>\n";
            $xml .= '    <loc>' . $this->xmlEscape($entry['loc']) . "</loc>\n";
            if (!empty($entry['lastmod'])) {
                $xml .= '    <lastmod>' . $entry['lastmod'] . "</lastmod>\n";
            }
            $xml .= "  </sitemap>\n";
        }
        $xml .= '</sitemapindex>';

        return $this->xmlResponse($xml);
    }

    /**
     * Static + legal pages sitemap.
     */
    public function pages(): Response
    {
        $urls = [];

        $staticPages = [
            ['/', 'daily', '1.0'],
            ['/trending', 'daily', '0.8'],
            ['/live', 'daily', '0.7'],
            ['/categories', 'weekly', '0.7'],
            ['/tags', 'weekly', '0.5'],
            ['/videos', 'daily', '0.8'],
            ['/images', 'daily', '0.6'],
            ['/galleries', 'daily', '0.6'],
            ['/public-playlists', 'weekly', '0.6'],
            ['/contact', 'monthly', '0.3'],
        ];

        foreach ($staticPages as [$path, $changefreq, $priority]) {
            $urls[] = $this->staticUrlEntry($path, now()->toW3cString(), $changefreq, $priority);
        }

        try {
            $pages = Page::published()->select(['slug', 'updated_at'])->get();
            foreach ($pages as $page) {
                $urls[] = $this->staticUrlEntry(
                    "/pages/{$page->slug}",
                    $page->updated_at->toW3cString(),
                    'monthly',
                    '0.5'
                );
            }
        } catch (Throwable) {
            // Pages table may not exist yet
        }

        return $this->urlsetResponse($urls, multiLang: $this->multiLang);
    }

    /**
     * Categories sitemap (active parent categories with at least one indexable video).
     */
    public function categories(): Response
    {
        $categories = Category::query()
            ->where(function ($q) {
                $q->where('is_active', true)->orWhereNull('is_active');
            })
            ->whereNull('parent_id')
            ->whereHas('videos', fn($q) => $q->public()->approved()->processed())
            ->select(['id', 'slug', 'updated_at'])
            ->get();

        $urls = [];
        foreach ($categories as $category) {
            $urls[] = $this->staticUrlEntry(
                "/category/{$category->slug}",
                $category->updated_at?->toW3cString() ?? now()->toW3cString(),
                'weekly',
                '0.7'
            );
        }

        return $this->urlsetResponse($urls, multiLang: $this->multiLang);
    }

    /**
     * Tags sitemap. Builds a unique list of tags from indexable videos,
     * limited to keep the sitemap reasonable.
     */
    public function tags(): Response
    {
        $maxTags = (int) Setting::get('seo_sitemap_max_tags', 5000);

        $rows = Video::query()
            ->public()
            ->approved()
            ->processed()
            ->whereNotNull('tags')
            ->select(['tags', 'updated_at'])
            ->latest('published_at')
            ->limit(2000)
            ->get();

        $tagMap = [];
        foreach ($rows as $row) {
            if (!is_array($row->tags)) continue;
            foreach ($row->tags as $tag) {
                $tag = trim((string) $tag);
                if ($tag === '') continue;
                if (!isset($tagMap[$tag]) || $tagMap[$tag] < $row->updated_at) {
                    $tagMap[$tag] = $row->updated_at;
                }
                if (count($tagMap) >= $maxTags) break 2;
            }
        }

        $urls = [];
        foreach ($tagMap as $tag => $lastmod) {
            $urls[] = $this->staticUrlEntry(
                '/tag/' . rawurlencode($tag),
                ($lastmod?->toW3cString()) ?? now()->toW3cString(),
                'weekly',
                '0.4'
            );
        }

        return $this->urlsetResponse($urls, multiLang: $this->multiLang);
    }

    /**
     * Channels sitemap.
     */
    public function channels(): Response
    {
        $maxChannels = (int) Setting::get('seo_sitemap_max_channels', 5000);

        $channels = User::query()
            ->whereHas('videos', fn($q) => $q->public()->approved()->processed())
            ->select(['id', 'username', 'updated_at'])
            ->limit($maxChannels)
            ->get();

        $urls = [];
        foreach ($channels as $channel) {
            $urls[] = $this->channelUrlEntry($channel);
        }

        return $this->urlsetResponse($urls, multiLang: $this->multiLang);
    }

    /**
     * Videos sitemap. Page 1 → /sitemap-videos.xml, page N → /sitemap-videos-N.xml.
     */
    public function videos(int $page = 1): Response
    {
        $page = max(1, $page);
        $chunkSize = $this->videoChunkSize();
        $videoSitemapEnabled = (bool) Setting::get('seo_sitemap_video_enabled', true);

        $columns = $videoSitemapEnabled
            ? ['id', 'slug', 'title', 'description', 'thumbnail', 'external_thumbnail_url', 'duration', 'views_count', 'tags', 'published_at', 'updated_at', 'user_id', 'storage_disk', 'is_embedded', 'video_path', 'age_restricted', 'category_id']
            : ['id', 'slug', 'updated_at'];

        $videos = Video::query()
            ->public()
            ->approved()
            ->processed()
            ->select($columns)
            ->when($videoSitemapEnabled, fn($q) => $q->with('user:id,username', 'category:id,name'))
            ->latest('updated_at')
            ->offset(($page - 1) * $chunkSize)
            ->limit($chunkSize)
            ->get();

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

        $urls = [];
        foreach ($videos as $video) {
            $slugsByLocale = $translatedSlugs[$video->id] ?? [];
            $urls[] = $videoSitemapEnabled
                ? $this->videoUrlEntry($video, $slugsByLocale)
                : $this->videoSimpleEntry($video, $slugsByLocale);
        }

        return $this->urlsetResponse(
            $urls,
            multiLang: $this->multiLang,
            videoExt: $videoSitemapEnabled,
        );
    }

    /**
     * Images sitemap (public approved). Includes image:image extension.
     */
    public function images(): Response
    {
        if (!Setting::get('seo_sitemap_images_enabled', true)) {
            return $this->urlsetResponse([], multiLang: $this->multiLang);
        }

        $max = (int) Setting::get('seo_sitemap_max_images', 5000);

        $images = Image::query()
            ->public()
            ->approved()
            ->select(['id', 'uuid', 'title', 'file_path', 'thumbnail_path', 'storage_disk', 'updated_at'])
            ->latest('updated_at')
            ->limit($max)
            ->get();

        $urls = [];
        foreach ($images as $image) {
            $imageUrl = $image->file_path
                ? StorageManager::permanentUrl($image->file_path, $image->storage_disk ?? 'public')
                : null;

            $entry = "  <url>\n";
            $entry .= '    <loc>' . $this->xmlEscape(url("/image/{$image->uuid}")) . "</loc>\n";
            $entry .= '    <lastmod>' . $image->updated_at->toW3cString() . "</lastmod>\n";
            $entry .= "    <changefreq>monthly</changefreq>\n";
            $entry .= "    <priority>0.5</priority>\n";

            if ($imageUrl) {
                $entry .= "    <image:image>\n";
                $entry .= '      <image:loc>' . $this->xmlEscape($imageUrl) . "</image:loc>\n";
                if (!empty($image->title)) {
                    $entry .= '      <image:title>' . $this->xmlEscape($image->title) . "</image:title>\n";
                }
                $entry .= "    </image:image>\n";
            }
            $entry .= "  </url>\n";
            $urls[] = $entry;
        }

        return $this->urlsetResponse($urls, multiLang: false, imageExt: true);
    }

    /**
     * Galleries sitemap (public). Includes image:image with cover thumbnail.
     */
    public function galleries(): Response
    {
        if (!Setting::get('seo_sitemap_galleries_enabled', true)) {
            return $this->urlsetResponse([], multiLang: $this->multiLang);
        }

        $max = (int) Setting::get('seo_sitemap_max_galleries', 5000);

        $galleries = Gallery::query()
            ->public()
            ->with('coverImage:id,thumbnail_path,file_path,storage_disk')
            ->select(['id', 'slug', 'title', 'cover_image_id', 'updated_at'])
            ->latest('updated_at')
            ->limit($max)
            ->get();

        $urls = [];
        foreach ($galleries as $gallery) {
            $entry = "  <url>\n";
            $entry .= '    <loc>' . $this->xmlEscape(url("/gallery/{$gallery->slug}")) . "</loc>\n";
            $entry .= '    <lastmod>' . $gallery->updated_at->toW3cString() . "</lastmod>\n";
            $entry .= "    <changefreq>weekly</changefreq>\n";
            $entry .= "    <priority>0.6</priority>\n";

            if ($gallery->coverImage && $gallery->coverImage->file_path) {
                $coverUrl = StorageManager::permanentUrl(
                    $gallery->coverImage->thumbnail_path ?: $gallery->coverImage->file_path,
                    $gallery->coverImage->storage_disk ?? 'public'
                );
                if ($coverUrl) {
                    $entry .= "    <image:image>\n";
                    $entry .= '      <image:loc>' . $this->xmlEscape($coverUrl) . "</image:loc>\n";
                    if (!empty($gallery->title)) {
                        $entry .= '      <image:title>' . $this->xmlEscape($gallery->title) . "</image:title>\n";
                    }
                    $entry .= "    </image:image>\n";
                }
            }
            $entry .= "  </url>\n";
            $urls[] = $entry;
        }

        return $this->urlsetResponse($urls, multiLang: false, imageExt: true);
    }

    /**
     * Public playlists sitemap.
     */
    public function playlists(): Response
    {
        if (!Setting::get('seo_sitemap_playlists_enabled', true)) {
            return $this->urlsetResponse([], multiLang: $this->multiLang);
        }

        $max = (int) Setting::get('seo_sitemap_max_playlists', 5000);

        $playlists = Playlist::query()
            ->public()
            ->where('video_count', '>', 0)
            ->select(['id', 'slug', 'updated_at'])
            ->latest('updated_at')
            ->limit($max)
            ->get();

        $urls = [];
        foreach ($playlists as $playlist) {
            $urls[] = $this->staticUrlEntry(
                "/playlist/{$playlist->slug}",
                $playlist->updated_at->toW3cString(),
                'weekly',
                '0.6'
            );
        }

        return $this->urlsetResponse($urls, multiLang: false);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function videoChunkSize(): int
    {
        $size = (int) Setting::get('seo_sitemap_chunk_size', 10000);
        return max(100, min($size, 50000));
    }

    private function maxUpdatedAt($query): string
    {
        try {
            $val = (clone $query)->max('updated_at');
            if ($val) {
                return Carbon::parse($val)->toW3cString();
            }
        } catch (Throwable) {
            // Table may be missing — fall through
        }
        return now()->toW3cString();
    }

    private function urlsetResponse(array $urls, bool $multiLang = false, bool $videoExt = false, bool $imageExt = false): Response
    {
        $namespaces = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
        if ($videoExt) {
            $namespaces .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
        }
        if ($imageExt) {
            $namespaces .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        }
        if ($multiLang) {
            $namespaces .= ' xmlns:xhtml="http://www.w3.org/1999/xhtml"';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= "<urlset {$namespaces}>\n";
        foreach ($urls as $entry) {
            $xml .= $entry;
        }
        $xml .= '</urlset>';

        return $this->xmlResponse($xml);
    }

    private function xmlResponse(string $body): Response
    {
        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=utf-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * hreflang links for static-style paths (same path on each locale prefix).
     */
    private function hreflangLinks(string $path): string
    {
        if (!$this->multiLang) {
            return '';
        }

        $map = TranslationService::hreflangMapForPath($path);
        $links = '';
        foreach ($map as $hl => $href) {
            $links .= '    <xhtml:link rel="alternate" hreflang="' . $hl . '" href="' . $this->xmlEscape($href) . '" />' . "\n";
        }

        return $links;
    }

    /**
     * hreflang links for a video using translated slugs.
     * Skips locales without confirmed translations to avoid duplicate alternate entries.
     */
    private function videoHreflangLinks(Video $video, array $slugsByLocale): string
    {
        if (!$this->multiLang) {
            return '';
        }

        $defaultUrl = url("/{$video->slug}");
        $links = '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $this->xmlEscape($defaultUrl) . '" />' . "\n";

        $seen = [$defaultUrl => true];

        foreach ($this->locales as $locale) {
            if ($locale === $this->defaultLocale) {
                $href = $defaultUrl;
                $tag = TranslationService::toHreflang($locale);
                $links .= '    <xhtml:link rel="alternate" hreflang="' . $tag . '" href="' . $this->xmlEscape($href) . '" />' . "\n";
                continue;
            }

            // Only emit hreflang for locales that have a confirmed translated slug
            if (!isset($slugsByLocale[$locale])) {
                continue;
            }
            $href = url("/{$locale}/{$slugsByLocale[$locale]}");
            if (isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $tag = TranslationService::toHreflang($locale);
            $links .= '    <xhtml:link rel="alternate" hreflang="' . $tag . '" href="' . $this->xmlEscape($href) . '" />' . "\n";
        }

        return $links;
    }

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
     * Video URL entry with full video:video extensions and hreflang alternates.
     * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps
     */
    private function videoUrlEntry(Video $video, array $slugsByLocale = []): string
    {
        $loc = $this->xmlEscape(url("/{$video->slug}"));
        $title = $this->xmlEscape($video->title);
        $description = $this->xmlEscape(
            mb_substr(strip_tags($video->description ?? $video->title), 0, 2048)
        );

        $thumbnailUrl = '';
        if ($video->thumbnail) {
            $thumbnailUrl = StorageManager::permanentUrl($video->thumbnail, $video->storage_disk ?? 'public');
        } elseif ($video->external_thumbnail_url) {
            $thumbnailUrl = $video->external_thumbnail_url;
        }
        $thumbnailUrl = $this->xmlEscape($thumbnailUrl);

        $contentUrl = '';
        if (!$video->is_embedded && $video->video_path) {
            $contentUrl = $this->xmlEscape(StorageManager::permanentUrl($video->video_path, $video->storage_disk ?? 'public'));
        }

        $duration = $video->duration ?? 0;
        $viewCount = $video->views_count ?? 0;
        $pubDate = ($video->published_at ?? $video->created_at)?->toW3cString() ?? now()->toW3cString();
        $familyFriendly = $video->age_restricted ? 'no' : 'yes';
        $uploader = $this->xmlEscape($video->user?->username ?? 'Unknown');
        $uploaderUrl = $video->user ? $this->xmlEscape(url("/channel/{$video->user->username}")) : '';
        $tags = is_array($video->tags) ? array_slice($video->tags, 0, 32) : [];
        $category = $this->xmlEscape($video->category?->name ?? '');

        $entry = "  <url>\n";
        $entry .= "    <loc>{$loc}</loc>\n";
        $entry .= "    <lastmod>{$pubDate}</lastmod>\n";
        $entry .= "    <changefreq>weekly</changefreq>\n";
        $entry .= "    <priority>0.9</priority>\n";
        $entry .= $this->videoHreflangLinks($video, $slugsByLocale);

        $entry .= "    <video:video>\n";
        if ($thumbnailUrl) {
            $entry .= "      <video:thumbnail_loc>{$thumbnailUrl}</video:thumbnail_loc>\n";
        }
        $entry .= "      <video:title>{$title}</video:title>\n";
        $entry .= "      <video:description>{$description}</video:description>\n";

        if ($contentUrl) {
            $entry .= "      <video:content_loc>{$contentUrl}</video:content_loc>\n";
        }
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
