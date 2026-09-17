<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Video;
use App\Services\PlayerAdListBuilder;
use App\Services\SeoService;
use App\Support\VisitorCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The iframe player other sites embed, and its oEmbed description.
 *
 * A stripped-down page: player, title, and a link back to the watch page.
 * Ad breaks are kept, since an embed is a real view — PlayerAdListBuilder
 * decides the schedule exactly as it does on the watch page.
 */
class EmbedController extends Controller
{
    /** Default iframe size, also reported through oEmbed. */
    public const WIDTH = 640;

    public const HEIGHT = 360;

    public function show(Request $request, Video $video): Response
    {
        $this->assertEmbeddable($request, $video);

        $video->load(['user.channel', 'category']);
        $video->append(['quality_urls', 'hls_playlist_url']);

        return Inertia::render('Embed', [
            'video' => $video,
            'watchUrl' => url("/{$video->slug}"),
            'channelUrl' => $video->user ? url("/channel/{$video->user->username}") : null,
            'videoAdsEnabled' => ! $this->shouldSuppressAds(),
            'playerAdList' => app(PlayerAdListBuilder::class)->build($video, $video->category_id),
            'seo' => app(SeoService::class)->forPrivatePage($video->title, alwaysNoindex: true),
        ]);
    }

    /**
     * oEmbed description of a watch URL (https://oembed.com).
     *
     * Lets editors, chat apps and CMSes turn a pasted link into the player
     * without anyone hand-writing an iframe.
     */
    public function oembed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => 'required|string|max:2048',
            'format' => 'nullable|in:json',
            'maxwidth' => 'nullable|integer|min:120|max:4096',
            'maxheight' => 'nullable|integer|min:80|max:4096',
        ]);

        $video = $this->videoFromUrl($data['url']);

        if (! $video) {
            // oEmbed says 404 for a URL this provider cannot describe.
            abort(404, 'No video found for that URL.');
        }

        $this->assertEmbeddable($request, $video);

        [$width, $height] = $this->scaledSize(
            isset($data['maxwidth']) ? (int) $data['maxwidth'] : null,
            isset($data['maxheight']) ? (int) $data['maxheight'] : null,
            (bool) $video->is_portrait,
        );

        return response()->json([
            'type' => 'video',
            'version' => '1.0',
            'provider_name' => (string) Setting::get('site_name', config('app.name')),
            'provider_url' => url('/'),
            'title' => $video->title,
            'author_name' => $video->user?->username,
            'author_url' => $video->user ? url("/channel/{$video->user->username}") : null,
            'thumbnail_url' => $video->thumbnail_url,
            'width' => $width,
            'height' => $height,
            'duration' => (int) $video->duration,
            'html' => $this->iframe($video, $width, $height),
        ]);
    }

    /** The iframe markup handed out by oEmbed and shown in the share dialog. */
    public function iframe(Video $video, int $width = self::WIDTH, int $height = self::HEIGHT): string
    {
        return sprintf(
            '<iframe src="%s" width="%d" height="%d" frameborder="0" scrolling="no" allow="autoplay; fullscreen; encrypted-media" allowfullscreen title="%s"></iframe>',
            e(route('videos.embed', $video->slug)),
            $width,
            $height,
            e($video->title)
        );
    }

    /**
     * Embeddable means: embedding is switched on, the video is public or
     * unlisted (never private or a draft), published, processed, and not
     * blocked in the viewer's country.
     */
    protected function assertEmbeddable(Request $request, Video $video): void
    {
        if (! filter_var(Setting::get('embed_enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            abort(404);
        }

        if ($video->is_draft || $video->privacy === 'private') {
            abort(404);
        }

        if (! $video->is_approved || $video->status !== 'processed' || $video->is_embedded) {
            abort(404);
        }

        if ($video->isGeoBlockedFor(VisitorCountry::fromRequest($request))) {
            abort(451, 'This video is not available in your country.');
        }
    }

    /** Resolve a watch, locale-prefixed watch, or embed URL to its video. */
    protected function videoFromUrl(string $url): ?Video
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $host = parse_url($url, PHP_URL_HOST);

        // Only describe our own URLs.
        if ($host && $host !== parse_url(url('/'), PHP_URL_HOST)) {
            return null;
        }

        $segments = array_values(array_filter(explode('/', $path)));

        if (empty($segments)) {
            return null;
        }

        // /embed/{slug}
        if ($segments[0] === 'embed' && isset($segments[1])) {
            return Video::where('slug', $segments[1])->first();
        }

        // /{slug} or /{locale}/{slug}
        $slug = end($segments);

        return Video::where('slug', $slug)->first();
    }

    /**
     * Fit the player inside the consumer's limits, keeping its aspect ratio.
     *
     * @return array{0: int, 1: int}
     */
    protected function scaledSize(?int $maxWidth, ?int $maxHeight, bool $portrait): array
    {
        $ratio = $portrait ? 16 / 9 : 9 / 16;
        $width = $portrait ? self::HEIGHT : self::WIDTH;
        $height = (int) round($width * $ratio);

        if ($maxWidth && $width > $maxWidth) {
            $width = $maxWidth;
            $height = (int) round($width * $ratio);
        }

        if ($maxHeight && $height > $maxHeight) {
            $height = $maxHeight;
            $width = (int) round($height / $ratio);
        }

        return [max(120, $width), max(80, $height)];
    }
}
