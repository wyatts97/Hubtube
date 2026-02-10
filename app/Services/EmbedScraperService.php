<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmbedScraperService
{
    protected string $scraperUrl;

    public function __construct()
    {
        $this->scraperUrl = Setting::get('scraper_url', 'http://localhost:3001');
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
                
                // Mark videos that are already imported (check the unified videos table)
                if (!empty($data['videos'])) {
                    $sourceIds = array_column($data['videos'], 'sourceId');
                    $importedIds = $this->getImportedIds($site, $sourceIds);
                    
                    foreach ($data['videos'] as &$video) {
                        $video['isImported'] = in_array($video['sourceId'], $importedIds);
                    }
                }
                
                return $data;
            }

            // Check for blocked/geo-restricted response
            if ($response->status() === 403) {
                $data = $response->json();
                return [
                    'error' => 'Site access blocked',
                    'message' => $data['message'] ?? 'This site appears to be geo-restricted in your region (common in Texas for adult sites).',
                    'blocked' => true,
                    'suggestion' => $data['suggestion'] ?? 'Try using a VPN or proxy service, or select a different source site.',
                    'videos' => [],
                ];
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

    /**
     * Check which source video IDs are already imported in the unified videos table.
     */
    protected function getImportedIds(string $sourceSite, array $sourceVideoIds): array
    {
        // Normalize site name to match how it's stored
        $siteName = strtolower(str_replace(' ', '', $sourceSite));

        return Video::where('source_site', $siteName)
            ->whereIn('source_video_id', $sourceVideoIds)
            ->pluck('source_video_id')
            ->toArray();
    }

    /**
     * Check if a specific video is already imported.
     */
    protected function isAlreadyImported(string $sourceSite, string $sourceVideoId): bool
    {
        $siteName = strtolower(str_replace(' ', '', $sourceSite));

        return Video::where('source_site', $siteName)
            ->where('source_video_id', $sourceVideoId)
            ->exists();
    }

    public function importVideo(array $videoData): ?Video
    {
        // Check if already imported in the unified videos table
        if ($this->isAlreadyImported($videoData['sourceSite'], $videoData['sourceId'])) {
            Log::info("Video already imported: {$videoData['sourceSite']}/{$videoData['sourceId']}");
            return null;
        }

        Log::info("Importing video: {$videoData['sourceSite']}/{$videoData['sourceId']} - {$videoData['title']}");

        $title = $videoData['title'] ?? 'Untitled';
        $slug = Str::slug($title);
        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (Video::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $defaultUserId = Setting::get('import_user_id', 1);

        $video = Video::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $defaultUserId,
            'title' => $title,
            'slug' => $slug,
            'description' => $videoData['description'] ?? null,
            'duration' => $videoData['duration'] ?? 0,
            'is_embedded' => true,
            'embed_url' => $videoData['embedUrl'] ?? '',
            'embed_code' => $videoData['embedCode'] ?? '',
            'external_thumbnail_url' => $videoData['thumbnail'] ?? null,
            'external_preview_url' => $videoData['thumbnailPreview'] ?? null,
            'source_site' => strtolower(str_replace(' ', '', $videoData['sourceSite'])),
            'source_video_id' => $videoData['sourceId'],
            'source_url' => $videoData['url'] ?? '',
            'views_count' => $videoData['views'] ?? 0,
            'status' => 'processed',
            'is_approved' => true,
            'privacy' => 'public',
            'published_at' => now(),
            'tags' => $videoData['tags'] ?? [],
        ]);

        Log::info("Video imported to videos table with ID: {$video->id}");

        return $video;
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
