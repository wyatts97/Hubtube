<?php

namespace App\Services;

use App\Models\Video;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
     * Get video play data which includes pre-authorized download URLs.
     * This bypasses CDN security settings (Direct URL Access, Token Auth, etc.)
     */
    public function getVideoPlayData(string $videoId): ?array
    {
        try {
            $response = Http::withHeaders([
                'AccessKey' => $this->apiKey,
            ])->get("{$this->baseUrl}/library/{$this->libraryId}/videos/{$videoId}/play");

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Bunny Stream play data failed', [
                'videoId' => $videoId,
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Bunny Stream play data error', ['videoId' => $videoId, 'error' => $e->getMessage()]);
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
     * Download a video via HLS using ffmpeg's native HLS input.
     * ffmpeg reads the playlist URL directly, downloads segments, and outputs
     * a properly indexed, seekable MP4 with moov atom at the start.
     * Returns the stored path on success, null on failure.
     */
    public function downloadViaHls(string $videoId, string $storagePath, string $disk = 'public'): ?string
    {
        $playlistUrl = "https://{$this->cdnHost}/{$videoId}/playlist.m3u8";

        // 1. Verify playlist is accessible
        try {
            $checkResponse = Http::timeout(15)->get($playlistUrl);
            if (!$checkResponse->successful()) {
                Log::warning('BunnyStreamService HLS: playlist not accessible', [
                    'url' => $playlistUrl,
                    'status' => $checkResponse->status(),
                ]);
                return null;
            }
        } catch (\Throwable $e) {
            Log::error('BunnyStreamService HLS: playlist check error', ['error' => $e->getMessage()]);
            return null;
        }

        Log::info('BunnyStreamService HLS: starting ffmpeg download', [
            'playlist' => $playlistUrl,
            'target' => $storagePath,
        ]);

        // 2. Use ffmpeg to read HLS directly and output a seekable MP4
        //    -i <playlist_url>   : ffmpeg natively handles HLS input
        //    -c copy             : no re-encoding, just remux (fast)
        //    -bsf:a aac_adtstoasc : fix AAC stream from MPEG-TS to MP4 container
        //    -movflags +faststart : move moov atom to start for instant seeking
        $tempDir = storage_path('app/temp/hls_' . $videoId . '_' . time());
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }
        $outputFile = "{$tempDir}/output.mp4";
        $ffmpegBinary = config('hubtube.ffmpeg.binary', '/usr/bin/ffmpeg');

        try {
            $cmd = "{$ffmpegBinary} -i " . escapeshellarg($playlistUrl)
                . " -c copy -bsf:a aac_adtstoasc -movflags +faststart"
                . " " . escapeshellarg($outputFile) . " -y 2>&1";

            Log::info('BunnyStreamService HLS: running ffmpeg', ['cmd' => $cmd]);

            $result = Process::timeout(900)->run($cmd);

            if (!$result->successful() || !file_exists($outputFile) || filesize($outputFile) === 0) {
                Log::error('BunnyStreamService HLS: ffmpeg failed', [
                    'exit_code' => $result->exitCode(),
                    'output' => substr($result->output(), -1000),
                ]);
                $this->cleanupTempDir($tempDir);
                return null;
            }

            Log::info('BunnyStreamService HLS: ffmpeg success', [
                'output_size' => filesize($outputFile),
            ]);
        } catch (\Throwable $e) {
            Log::error('BunnyStreamService HLS: ffmpeg error', ['error' => $e->getMessage()]);
            $this->cleanupTempDir($tempDir);
            return null;
        }

        // 3. Store the output MP4 to the target disk
        $diskInstance = Storage::disk($disk);
        $directory = dirname($storagePath);
        if ($directory && $directory !== '.') {
            $diskInstance->makeDirectory($directory);
        }

        $diskInstance->put($storagePath, file_get_contents($outputFile));

        // 4. Cleanup temp files
        $this->cleanupTempDir($tempDir);

        // 5. Verify
        if ($diskInstance->exists($storagePath) && $diskInstance->size($storagePath) > 0) {
            Log::info('BunnyStreamService HLS: download complete', [
                'path' => $storagePath,
                'size' => $diskInstance->size($storagePath),
            ]);
            return $storagePath;
        }

        Log::warning('BunnyStreamService HLS: file empty after write', ['path' => $storagePath]);
        return null;
    }

    /**
     * Remove a temporary directory and all its contents.
     */
    private function cleanupTempDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob("{$dir}/*");
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($dir);
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
        $fileSlug = Str::slug($video->title, '_');
        if (empty($fileSlug)) {
            $fileSlug = $video->uuid;
        }

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
            'availableResolutions' => $videoInfo['availableResolutions'] ?? 'N/A',
            'status' => $videoInfo['status'] ?? 'N/A',
        ]);

        // 2. Try downloading the video file
        $videoPath = null;

        // Strategy A: Try direct CDN download first (works if Direct URL Access is allowed)
        $originalUrl = $this->getOriginalUrl($bunnyVideoId);
        Log::info('BunnyStreamService: trying direct CDN original', ['url' => $originalUrl]);
        $videoPath = $this->downloadFile($originalUrl, "{$storageBase}/{$fileSlug}.mp4", $targetDisk);

        // Strategy B: Download via HLS — the CDN always allows HLS streaming
        // Fetch the playlist, download all .ts segments, remux to MP4 with ffmpeg
        if (!$videoPath) {
            Log::info('BunnyStreamService: direct CDN blocked, trying HLS download');
            $videoPath = $this->downloadViaHls($bunnyVideoId, "{$storageBase}/{$fileSlug}.mp4", $targetDisk);
        }

        // Strategy C: Try signed MP4 fallback URLs (if MP4 fallback is enabled)
        if (!$videoPath) {
            $resolutions = ['1080p', '720p', '480p', '360p', '240p'];
            foreach ($resolutions as $res) {
                $mp4Url = $this->getMp4Url($bunnyVideoId, $res);
                $videoPath = $this->downloadFile($mp4Url, "{$storageBase}/{$fileSlug}_{$res}.mp4", $targetDisk);
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
            return ['success' => false, 'error' => "Could not download video file. Tried direct CDN, HLS download, and MP4 fallbacks."];
        }

        // 3. Download thumbnail
        $thumbnailFileName = $videoInfo['thumbnailFileName'] ?? null;
        $thumbnailUrl = $this->getThumbnailUrl($bunnyVideoId, $thumbnailFileName);
        $thumbnailPath = $this->downloadFile(
            $thumbnailUrl,
            "thumbnails/{$video->user_id}/{$fileSlug}_thumb.jpg",
            $targetDisk
        );

        // 4. Download animated preview WebP
        $previewWebpUrl = $this->getPreviewUrl($bunnyVideoId);
        $previewPath = $this->downloadFile(
            $previewWebpUrl,
            "{$storageBase}/{$fileSlug}_preview.webp",
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
