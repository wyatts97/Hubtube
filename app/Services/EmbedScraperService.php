<?php

namespace App\Services;

use App\Models\EmbeddedVideo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmbedScraperService
{
    protected string $scraperUrl;

    public function __construct()
    {
        $this->scraperUrl = config('services.scraper.url', 'http://localhost:3001');
    }

    public function getAvailableSites(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->scraperUrl}/api/sites");
            
            if ($response->successful()) {
                return $response->json('sites', []);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch available sites: ' . $e->getMessage());
        }

        return [];
    }

    public function search(string $site, string $query, int $page = 1): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->scraperUrl}/api/search/{$site}", [
                'q' => $query,
                'page' => $page,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Mark videos that are already imported
                if (!empty($data['videos'])) {
                    $sourceIds = array_column($data['videos'], 'sourceId');
                    $importedIds = EmbeddedVideo::getImportedIds($site, $sourceIds);
                    
                    foreach ($data['videos'] as &$video) {
                        $video['isImported'] = in_array($video['sourceId'], $importedIds);
                    }
                }
                
                return $data;
            }

            return [
                'error' => 'Failed to fetch videos',
                'message' => $response->body(),
                'videos' => [],
            ];
        } catch (\Exception $e) {
            Log::error("Scraper search error for {$site}: " . $e->getMessage());
            
            return [
                'error' => 'Scraper service unavailable',
                'message' => $e->getMessage(),
                'videos' => [],
            ];
        }
    }

    public function getVideoDetails(string $site, string $videoId): ?array
    {
        try {
            $response = Http::timeout(30)->get("{$this->scraperUrl}/api/search/{$site}/video/{$videoId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch video details for {$site}/{$videoId}: " . $e->getMessage());
        }

        return null;
    }

    public function importVideo(array $videoData): ?EmbeddedVideo
    {
        // Check if already imported
        if (EmbeddedVideo::isAlreadyImported($videoData['sourceSite'], $videoData['sourceId'])) {
            return null;
        }

        return EmbeddedVideo::create([
            'source_site' => $videoData['sourceSite'],
            'source_video_id' => $videoData['sourceId'],
            'title' => $videoData['title'],
            'description' => $videoData['description'] ?? null,
            'duration' => $videoData['duration'] ?? 0,
            'duration_formatted' => $videoData['durationFormatted'] ?? null,
            'thumbnail_url' => $videoData['thumbnail'] ?? null,
            'thumbnail_preview_url' => $videoData['thumbnailPreview'] ?? null,
            'source_url' => $videoData['url'] ?? '',
            'embed_url' => $videoData['embedUrl'] ?? '',
            'embed_code' => $videoData['embedCode'] ?? '',
            'views_count' => $videoData['views'] ?? 0,
            'rating' => $videoData['rating'] ?? 0,
            'tags' => $videoData['tags'] ?? [],
            'actors' => $videoData['actors'] ?? [],
            'is_published' => false,
            'imported_at' => now(),
        ]);
    }

    public function bulkImport(array $videos): array
    {
        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($videos as $videoData) {
            try {
                $video = $this->importVideo($videoData);
                
                if ($video) {
                    $imported[] = $video->id;
                } else {
                    $skipped[] = $videoData['sourceId'];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'sourceId' => $videoData['sourceId'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => count($imported),
            'skipped' => count($skipped),
            'errors' => $errors,
        ];
    }
}
