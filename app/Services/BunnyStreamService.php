<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BunnyStreamService
{
    private string $apiKey;
    private string $libraryId;
    private string $cdnHost;
    private string $baseUrl = 'https://video.bunnycdn.com';

    public function __construct()
    {
        $this->apiKey = config('services.bunny_stream.api_key', '');
        $this->libraryId = config('services.bunny_stream.library_id', '');
        $this->cdnHost = config('services.bunny_stream.cdn_host', '');
    }

    /**
     * Check if the API key and library ID are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->libraryId);
    }

    /**
     * Test the API connection by fetching the first page of videos.
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'API key or Library ID not configured'];
        }

        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->get("{$this->baseUrl}/library/{$this->libraryId}/videos", [
                'page' => 1,
                'itemsPerPage' => 1,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'total_videos' => $data['totalItems'] ?? 0,
                    'library_id' => $this->libraryId,
                ];
            }

            return ['success' => false, 'error' => 'API returned status ' . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * List videos from the Bunny Stream library with pagination.
     */
    public function listVideos(int $page = 1, int $perPage = 100): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->get("{$this->baseUrl}/library/{$this->libraryId}/videos", [
                'page' => $page,
                'itemsPerPage' => $perPage,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Bunny Stream list videos failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Bunny Stream list videos error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get detailed info for a single video.
     */
    public function getVideo(string $videoId): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->get("{$this->baseUrl}/library/{$this->libraryId}/videos/{$videoId}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (\Throwable $e) {
            Log::error('Bunny Stream get video error', ['videoId' => $videoId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Build the direct download URL for the original uploaded file.
     */
    public function getOriginalUrl(string $videoId): string
    {
        return "https://{$this->cdnHost}/{$videoId}/original";
    }

    /**
     * Build the MP4 fallback URL for a specific resolution.
     */
    public function getMp4Url(string $videoId, string $resolution = '720p'): string
    {
        return "https://{$this->cdnHost}/{$videoId}/play_{$resolution}.mp4";
    }

    /**
     * Build the thumbnail URL.
     */
    public function getThumbnailUrl(string $videoId, ?string $thumbnailFileName = null): string
    {
        $file = $thumbnailFileName ?: 'thumbnail.jpg';
        return "https://{$this->cdnHost}/{$videoId}/{$file}";
    }

    /**
     * Build the animated preview WebP URL.
     */
    public function getPreviewUrl(string $videoId): string
    {
        return "https://{$this->cdnHost}/{$videoId}/preview.webp";
    }

    /**
     * Download a remote file to a local/S3 storage path.
     * Returns the stored path on success, null on failure.
     * Uses streaming to handle large video files without exhausting memory.
     */
    public function downloadFile(string $url, string $storagePath, string $disk = 'public'): ?string
    {
        try {
            $response = Http::withOptions([
                'stream' => true,
                'timeout' => 600,
                'connect_timeout' => 30,
            ])->get($url);

            if (!$response->successful()) {
                Log::warning('Bunny download failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return null;
            }

            // Ensure directory exists for local disks
            $diskInstance = Storage::disk($disk);
            $directory = dirname($storagePath);
            if ($directory && $directory !== '.') {
                $diskInstance->makeDirectory($directory);
            }

            // Stream the response body to disk
            $diskInstance->put($storagePath, $response->body());

            // Verify file was written
            if ($diskInstance->exists($storagePath) && $diskInstance->size($storagePath) > 0) {
                return $storagePath;
            }

            Log::warning('Bunny download: file empty after write', ['path' => $storagePath]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Bunny download error', [
                'url' => $url,
                'path' => $storagePath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get the best available MP4 resolution for a video.
     * Checks availableResolutions from the API and picks the highest.
     */
    public function getBestMp4Resolution(array $videoInfo): string
    {
        $available = $videoInfo['availableResolutions'] ?? '';
        if (empty($available)) {
            return '720p';
        }

        // availableResolutions is a comma-separated string like "240p,360p,480p,720p,1080p"
        $resolutions = array_map('trim', explode(',', $available));
        $priority = ['1080p', '720p', '480p', '360p', '240p'];

        foreach ($priority as $res) {
            if (in_array($res, $resolutions)) {
                return $res;
            }
        }

        return '720p';
    }

    public function getLibraryId(): string
    {
        return $this->libraryId;
    }

    public function getCdnHost(): string
    {
        return $this->cdnHost;
    }
}
