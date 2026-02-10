<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Setting;
use App\Models\Video;
use App\Models\User;
use App\Services\StorageManager;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $videoSitemapEnabled = Setting::get('seo_sitemap_video_enabled', true);
        $maxVideos = (int) Setting::get('seo_sitemap_max_videos', 10000);
        $maxChannels = (int) Setting::get('seo_sitemap_max_channels', 5000);

        $videoColumns = $videoSitemapEnabled
            ? ['id', 'slug', 'title', 'description', 'thumbnail', 'external_thumbnail_url', 'duration', 'views_count', 'tags', 'published_at', 'updated_at', 'user_id', 'storage_disk', 'is_embedded', 'age_restricted', 'category_id']
            : ['slug', 'updated_at'];

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
            ->select(['username', 'updated_at'])
            ->limit($maxChannels)
            ->get();

        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";

        if ($videoSitemapEnabled) {
            $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        } else {
            $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        }

        // Static pages
        $staticPages = ['/', '/trending', '/shorts', '/live', '/categories'];
        foreach ($staticPages as $page) {
            $content .= $this->urlEntry(url($page), now()->toW3cString(), 'daily', '0.8');
        }

        // Video pages with optional video:video extensions
        foreach ($videos as $video) {
            if ($videoSitemapEnabled) {
                $content .= $this->videoUrlEntry($video);
            } else {
                $content .= $this->urlEntry(
                    url("/{$video->slug}"),
                    $video->updated_at->toW3cString(),
                    'weekly',
                    '0.9'
                );
            }
        }

        // Channel pages
        foreach ($channels as $channel) {
            $content .= $this->urlEntry(
                url("/channel/{$channel->username}"),
                $channel->updated_at->toW3cString(),
                'weekly',
                '0.7'
            );
        }

        // Legal / static pages
        try {
            $pages = Page::published()->select(['slug', 'updated_at'])->get();
            foreach ($pages as $page) {
                $content .= $this->urlEntry(
                    url("/pages/{$page->slug}"),
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

    private function urlEntry(string $loc, string $lastmod, string $changefreq, string $priority): string
    {
        return "  <url>\n" .
            "    <loc>{$loc}</loc>\n" .
            "    <lastmod>{$lastmod}</lastmod>\n" .
            "    <changefreq>{$changefreq}</changefreq>\n" .
            "    <priority>{$priority}</priority>\n" .
            "  </url>\n";
    }

    /**
     * Generate a <url> entry with video:video extensions for Google Video Search.
     * @see https://developers.google.com/search/docs/crawling-indexing/sitemaps/video-sitemaps
     */
    private function videoUrlEntry(Video $video): string
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
