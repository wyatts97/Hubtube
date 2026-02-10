<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RedtubeApiAdapter
{
    public const NAME = 'redtube';
    public const LABEL = 'RedTube (API)';
    public const API_URL = 'https://api.redtube.com/';

    /**
     * Search videos via RedTube's public JSON API.
     * Docs: https://api.redtube.com/docs/
     */
    public function search(string $query, int $page = 1, int $perPage = 20): array
    {
        try {
            $response = Http::timeout(15)->get(self::API_URL, [
                'data' => 'redtube.Videos.searchVideos',
                'output' => 'json',
                'search' => $query,
                'page' => $page,
                'thumbsize' => 'big',
            ]);

            if (!$response->successful()) {
                return $this->errorResponse("API returned status {$response->status()}");
            }

            $data = $response->json();

            if (isset($data['code'])) {
                return $this->errorResponse($data['message'] ?? 'RedTube API error');
            }

            $videos = [];
            foreach ($data['videos'] ?? [] as $item) {
                $v = $item['video'] ?? $item;
                $duration = $this->parseDuration($v['duration'] ?? '0:00');

                $videoId = $v['video_id'] ?? '';
                $videos[] = [
                    'sourceId' => (string) $videoId,
                    'sourceSite' => self::NAME,
                    'title' => $v['title'] ?? 'Untitled',
                    'duration' => $duration,
                    'durationFormatted' => $v['duration'] ?? '0:00',
                    'thumbnail' => $v['default_thumb'] ?? $v['thumb'] ?? '',
                    'thumbnailPreview' => null,
                    'url' => $v['url'] ?? "https://www.redtube.com/{$videoId}",
                    'embedUrl' => "https://embed.redtube.com/?id={$videoId}",
                    'embedCode' => "<iframe src=\"https://embed.redtube.com/?id={$videoId}\" frameborder=\"0\" width=\"640\" height=\"360\" allowfullscreen></iframe>",
                    'views' => (int) str_replace(',', '', $v['views'] ?? '0'),
                    'rating' => (float) ($v['rating'] ?? 0),
                    'tags' => $this->extractTags($v['tags'] ?? []),
                    'actors' => $this->extractStars($v['stars'] ?? []),
                    'uploadDate' => $v['publish_date'] ?? null,
                ];
            }

            // RedTube API returns 20 per page, doesn't give total count
            $count = $data['count'] ?? count($videos);
            $hasNextPage = count($videos) >= 20;

            return [
                'site' => self::NAME,
                'query' => $query,
                'page' => $page,
                'hasNextPage' => $hasNextPage,
                'hasPrevPage' => $page > 1,
                'totalResults' => (int) $count,
                'videos' => $videos,
            ];
        } catch (\Exception $e) {
            Log::error("RedTube API error: {$e->getMessage()}");
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get video details by ID via the RedTube API.
     */
    public function getVideoDetails(string $videoId): ?array
    {
        try {
            $response = Http::timeout(15)->get(self::API_URL, [
                'data' => 'redtube.Videos.getVideoById',
                'output' => 'json',
                'video_id' => $videoId,
                'thumbsize' => 'big',
            ]);

            if (!$response->successful()) return null;

            $data = $response->json();
            $v = $data['video'] ?? $data['videos'][0]['video'] ?? null;
            if (!$v) return null;

            $duration = $this->parseDuration($v['duration'] ?? '0:00');

            // Get embed code
            $embedResponse = Http::timeout(10)->get(self::API_URL, [
                'data' => 'redtube.Videos.getVideoEmbedCode',
                'output' => 'json',
                'video_id' => $videoId,
            ]);
            $embedCode = '';
            if ($embedResponse->successful()) {
                $embedData = $embedResponse->json();
                $embedCode = base64_decode($embedData['embed']['code'] ?? '');
            }

            return [
                'sourceId' => (string) $videoId,
                'sourceSite' => self::NAME,
                'title' => $v['title'] ?? 'Untitled',
                'duration' => $duration,
                'durationFormatted' => $v['duration'] ?? '0:00',
                'thumbnail' => $v['default_thumb'] ?? $v['thumb'] ?? '',
                'url' => $v['url'] ?? "https://www.redtube.com/{$videoId}",
                'embedUrl' => "https://embed.redtube.com/?id={$videoId}",
                'embedCode' => $embedCode ?: "<iframe src=\"https://embed.redtube.com/?id={$videoId}\" frameborder=\"0\" width=\"640\" height=\"360\" allowfullscreen></iframe>",
                'views' => (int) str_replace(',', '', $v['views'] ?? '0'),
                'rating' => (float) ($v['rating'] ?? 0),
                'tags' => $this->extractTags($v['tags'] ?? []),
                'actors' => $this->extractStars($v['stars'] ?? []),
            ];
        } catch (\Exception $e) {
            Log::error("RedTube API video details error: {$e->getMessage()}");
            return null;
        }
    }

    protected function parseDuration(string $duration): int
    {
        $parts = explode(':', $duration);
        if (count($parts) === 3) {
            return (int) $parts[0] * 3600 + (int) $parts[1] * 60 + (int) $parts[2];
        } elseif (count($parts) === 2) {
            return (int) $parts[0] * 60 + (int) $parts[1];
        }
        return (int) $duration;
    }

    protected function extractTags(array $tags): array
    {
        return array_map(fn($t) => $t['tag_name'] ?? $t, $tags);
    }

    protected function extractStars(array $stars): array
    {
        return array_map(fn($s) => $s['star_name'] ?? $s, $stars);
    }

    protected function errorResponse(string $message): array
    {
        return [
            'error' => 'RedTube API error',
            'message' => $message,
            'videos' => [],
        ];
    }
}
