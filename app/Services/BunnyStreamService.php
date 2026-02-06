<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BunnyStreamService
{
    private string $apiKey;
    private string $libraryId;
    private string $cdnHost;
    private string $cdnTokenKey;
    private string $baseUrl = 'https://video.bunnycdn.com';

    public function __construct()
    {
        $this->apiKey = config('services.bunny_stream.api_key', '');
        $this->libraryId = config('services.bunny_stream.library_id', '');
        $this->cdnHost = config('services.bunny_stream.cdn_host', '');
        $this->cdnTokenKey = config('services.bunny_stream.cdn_token_key', '');
    }

    /**
     * Sign a CDN URL with Bunny's Basic Token Authentication.
     * Format: Base64(MD5(security_key + path + expiration)) with URL-safe base64.
     * Returns the original URL if no CDN token key is configured.
     */
    public function signUrl(string $url, int $expiresInSeconds = 3600): string
    {
        if (empty($this->cdnTokenKey)) {
            return $url;
        }

        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $expires = time() + $expiresInSeconds;

        $hashableBase = $this->cdnTokenKey . $path . $expires;
        $token = md5($hashableBase, true);
        $token = base64_encode($token);
        $token = strtr($token, '+/', '-_');
        $token = str_replace('=', '', $token);

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'token=' . $token . '&expires=' . $expires;
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
        $url = "https://{$this->cdnHost}/{$videoId}/original";
        return $this->signUrl($url);
    }

    /**
     * Build the MP4 fallback URL for a specific resolution.
     */
    public function getMp4Url(string $videoId, string $resolution = '720p'): string
    {
        $url = "https://{$this->cdnHost}/{$videoId}/play_{$resolution}.mp4";
        return $this->signUrl($url);
    }

    /**
     * Build the thumbnail URL.
     */
    public function getThumbnailUrl(string $videoId, ?string $thumbnailFileName = null): string
    {
        $file = $thumbnailFileName ?: 'thumbnail.jpg';
        $url = "https://{$this->cdnHost}/{$videoId}/{$file}";
        return $this->signUrl($url);
    }

    /**
     * Build the animated preview WebP URL.
     */
    public function getPreviewUrl(string $videoId): string
    {
        $url = "https://{$this->cdnHost}/{$videoId}/preview.webp";
        return $this->signUrl($url);
    }

    /**
     * Download a remote file to a local/S3 storage path.
     * Returns the stored path on success, null on failure.
     * Uses streaming to handle large video files without exhausting memory.
     */
    public function downloadFile(string $url, string $storagePath, string $disk = 'public'): ?string
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
                'Referer' => config('app.url', 'https://hubtube.com'),
            ])->withOptions([
                'stream' => true,
                'timeout' => 600,
                'connect_timeout' => 30,
            ])->get($url);

            if (!$response->successful()) {
                Log::warning('Bunny download failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return null;
            }

            $body = $response->body();
            $bodySize = strlen($body);

            if ($bodySize < 1000) {
                Log::warning('Bunny download: response too small, likely an error page', [
                    'url' => $url,
                    'size' => $bodySize,
                    'body' => substr($body, 0, 500),
                ]);
                return null;
            }

            // Ensure directory exists for local disks
            $diskInstance = Storage::disk($disk);
            $directory = dirname($storagePath);
            if ($directory && $directory !== '.') {
                $diskInstance->makeDirectory($directory);
            }

            // Write to disk
            $diskInstance->put($storagePath, $body);

            // Verify file was written
            if ($diskInstance->exists($storagePath) && $diskInstance->size($storagePath) > 0) {
                Log::info('Bunny download success', [
                    'url' => $url,
                    'path' => $storagePath,
                    'size' => $diskInstance->size($storagePath),
                ]);
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

    /**
     * Download a single embedded video's files and convert it to a native video.
     * Returns an array with status info: ['success' => bool, 'error' => string|null, 'video_path' => string|null]
     */
    public function downloadVideo(Video $video, string $targetDisk = 'public'): array
    {
        if (!$video->is_embedded || !$video->source_video_id) {
            return ['success' => false, 'error' => 'Video is not embedded or has no source_video_id'];
        }

        $bunnyVideoId = $video->source_video_id;
        $storageBase = "videos/{$video->user_id}/{$video->uuid}";

        Log::info('BunnyStreamService: downloading', [
            'video_id' => $video->id,
            'bunny_id' => $bunnyVideoId,
            'title' => $video->title,
        ]);

        // 1. Get video info from Bunny API
        $videoInfo = $this->getVideo($bunnyVideoId);

        Log::info('BunnyStreamService: API response', [
            'bunny_id' => $bunnyVideoId,
            'api_returned' => $videoInfo !== null,
            'hasOriginal' => $videoInfo['hasOriginal'] ?? 'N/A',
            'hasMP4Fallback' => $videoInfo['hasMP4Fallback'] ?? 'N/A',
            'availableResolutions' => $videoInfo['availableResolutions'] ?? 'N/A',
            'status' => $videoInfo['status'] ?? 'N/A',
        ]);

        // 2. Try downloading the video file — attempt all methods regardless of API flags
        $videoPath = null;

        // Try original file first
        $originalUrl = $this->getOriginalUrl($bunnyVideoId);
        Log::info('BunnyStreamService: trying original', ['url' => $originalUrl]);
        $videoPath = $this->downloadFile($originalUrl, "{$storageBase}/original.mp4", $targetDisk);

        // If original failed, try MP4 fallbacks at each resolution (highest first)
        if (!$videoPath) {
            $resolutions = ['1080p', '720p', '480p', '360p', '240p'];
            foreach ($resolutions as $res) {
                $mp4Url = $this->getMp4Url($bunnyVideoId, $res);
                Log::info('BunnyStreamService: trying MP4 fallback', ['url' => $mp4Url, 'resolution' => $res]);
                $videoPath = $this->downloadFile($mp4Url, "{$storageBase}/play_{$res}.mp4", $targetDisk);
                if ($videoPath) {
                    break;
                }
            }
        }

        if (!$videoPath) {
            Log::error('BunnyStreamService: all download attempts failed', [
                'video_id' => $video->id,
                'bunny_id' => $bunnyVideoId,
            ]);
            $video->update(['status' => 'failed']);
            return ['success' => false, 'error' => "Could not download video file. Tried original + 5 MP4 resolutions. Check Bunny Stream security settings (Direct URL Access, Token Auth, Allowed Domains)."];
        }

        // 3. Download thumbnail
        $thumbnailFileName = $videoInfo['thumbnailFileName'] ?? null;
        $thumbnailUrl = $this->getThumbnailUrl($bunnyVideoId, $thumbnailFileName);
        $thumbnailPath = $this->downloadFile(
            $thumbnailUrl,
            "thumbnails/{$video->user_id}/{$video->uuid}_thumb.jpg",
            $targetDisk
        );

        // 4. Download animated preview WebP
        $previewWebpUrl = $this->getPreviewUrl($bunnyVideoId);
        $previewPath = $this->downloadFile(
            $previewWebpUrl,
            "{$storageBase}/preview.webp",
            $targetDisk
        );

        // 5. Update the video record to become a native video
        $updateData = [
            'video_path' => $videoPath,
            'is_embedded' => false,
            'embed_url' => null,
            'embed_code' => null,
            'external_thumbnail_url' => null,
            'external_preview_url' => null,
            'status' => 'processed',
            'qualities_available' => ['original'],
        ];

        if ($thumbnailPath) {
            $updateData['thumbnail'] = $thumbnailPath;
        }

        if ($previewPath) {
            $updateData['preview_path'] = $previewPath;
        }

        $video->update($updateData);

        Log::info('BunnyStreamService: download completed', [
            'video_id' => $video->id,
            'video_path' => $videoPath,
        ]);

        return [
            'success' => true,
            'error' => null,
            'video_path' => $videoPath,
            'thumbnail' => $thumbnailPath,
            'preview' => $previewPath,
        ];
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
