<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\User;
use App\Models\EmbeddedVideo;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $videos = Video::query()
            ->public()
            ->approved()
            ->processed()
            ->select(['slug', 'updated_at'])
            ->latest('updated_at')
            ->limit(5000)
            ->get();

        $channels = User::query()
            ->whereHas('videos', fn($q) => $q->public()->approved()->processed())
            ->select(['username', 'updated_at'])
            ->limit(5000)
            ->get();

        $embeddedVideos = EmbeddedVideo::published()
            ->select(['id', 'updated_at'])
            ->latest('updated_at')
            ->limit(5000)
            ->get();

        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Static pages
        $staticPages = ['/', '/trending', '/shorts', '/live', '/embedded'];
        foreach ($staticPages as $page) {
            $content .= $this->urlEntry(url($page), now()->toW3cString(), 'daily', '0.8');
        }

        // Video pages
        foreach ($videos as $video) {
            $content .= $this->urlEntry(
                url("/{$video->slug}"),
                $video->updated_at->toW3cString(),
                'weekly',
                '0.9'
            );
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

        // Embedded video pages
        foreach ($embeddedVideos as $embedded) {
            $content .= $this->urlEntry(
                url("/embedded/{$embedded->id}"),
                $embedded->updated_at->toW3cString(),
                'weekly',
                '0.6'
            );
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
}
