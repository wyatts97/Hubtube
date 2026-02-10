<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EpornerApiAdapter
{
    public const NAME = 'eporner';
    public const LABEL = 'Eporner (API)';
    public const BASE_URL = 'https://www.eporner.com';
    public const API_URL = 'https://www.eporner.com/api/v2/video/search/';

    /**
     * Search videos via eporner's free public JSON API.
     * No scraping needed — returns structured JSON with pagination.
     */
    public function search(string $query, int $page = 1, int $perPage = 30, string $order = 'top-weekly'): array
    {
        try {
            $response = Http::timeout(15)->get(self::API_URL, [
                'query' => $query,
                'per_page' => min($perPage, 1000),
                'page' => $page,
                'thumbsize' => 'big',
                'order' => $order,
                'gay' => 0,
                'lq' => 0,
                'format' => 'json',
            ]);

            if (!$response->successful()) {
                return $this->errorResponse("API returned status {$response->status()}");
            }

            $data = $response->json();

            $videos = [];
            foreach ($data['videos'] ?? [] as $v) {
                $videos[] = [
                    'sourceId' => $v['id'] ?? '',
                    'sourceSite' => self::NAME,
                    'title' => $v['title'] ?? 'Untitled',
                    'duration' => $v['length_sec'] ?? 0,
                    'durationFormatted' => $v['length_min'] ?? '0:00',
                    'thumbnail' => $v['default_thumb']['src'] ?? '',
                    'thumbnailPreview' => null,
                    'url' => $v['url'] ?? '',
                    'embedUrl' => $v['embed'] ?? "https://www.eporner.com/embed/{$v['id']}/",
                    'embedCode' => '<iframe src="' . ($v['embed'] ?? "https://www.eporner.com/embed/{$v['id']}/") . '" frameborder="0" width="640" height="360" allowfullscreen></iframe>',
                    'views' => $v['views'] ?? 0,
                    'rating' => (float) ($v['rate'] ?? 0),
                    'tags' => is_string($v['keywords'] ?? null)
                        ? array_map('trim', explode(',', $v['keywords']))
                        : ($v['keywords'] ?? []),
                    'actors' => [],
                    'uploadDate' => $v['added'] ?? null,
                ];
            }

            $totalPages = $data['total_pages'] ?? 1;

            return [
                'site' => self::NAME,
                'query' => $query,
                'page' => $page,
                'hasNextPage' => $page < $totalPages,
                'hasPrevPage' => $page > 1,
                'totalResults' => $data['total_count'] ?? count($videos),
                'totalPages' => $totalPages,
                'videos' => $videos,
            ];
        } catch (\Exception $e) {
            Log::error("Eporner API error: {$e->getMessage()}");
            return $this->errorResponse($e->getMessage());
        }
    }

    /**
     * Get video details by ID via the eporner API.
     */
    public function getVideoDetails(string $videoId): ?array
    {
        try {
            $response = Http::timeout(15)->get(self::API_URL, [
                'id' => $videoId,
                'per_page' => 1,
                'thumbsize' => 'big',
                'format' => 'json',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $data = $response->json();
            $v = $data['videos'][0] ?? null;
            if (!$v) return null;

            return [
                'sourceId' => $v['id'] ?? $videoId,
                'sourceSite' => self::NAME,
                'title' => $v['title'] ?? 'Untitled',
                'duration' => $v['length_sec'] ?? 0,
                'durationFormatted' => $v['length_min'] ?? '0:00',
                'thumbnail' => $v['default_thumb']['src'] ?? '',
                'url' => $v['url'] ?? '',
                'embedUrl' => $v['embed'] ?? "https://www.eporner.com/embed/{$videoId}/",
                'embedCode' => '<iframe src="' . ($v['embed'] ?? "https://www.eporner.com/embed/{$videoId}/") . '" frameborder="0" width="640" height="360" allowfullscreen></iframe>',
                'views' => $v['views'] ?? 0,
                'rating' => (float) ($v['rate'] ?? 0),
                'tags' => is_string($v['keywords'] ?? null)
                    ? array_map('trim', explode(',', $v['keywords']))
                    : ($v['keywords'] ?? []),
                'actors' => [],
            ];
        } catch (\Exception $e) {
            Log::error("Eporner API video details error: {$e->getMessage()}");
            return null;
        }
    }

    protected function errorResponse(string $message): array
    {
        return [
            'error' => 'Eporner API error',
            'message' => $message,
            'videos' => [],
        ];
    }
}
