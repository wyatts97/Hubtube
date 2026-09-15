<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Keeps private videos' files off the open /storage/ path.
 *
 * Video files live on the public disk because FFmpeg processing, the admin
 * preview tools and cloud offloading all read them from there. Moving private
 * videos elsewhere would touch every one of those paths, so instead each
 * private video's directory carries an empty marker file. Nginx checks for it
 * on every /storage/videos/{slug}/ request and hands marked directories to
 * Laravel (see deployment/nginx/hubtube.conf), where ProtectedMediaController
 * authorises the viewer and returns the file — via X-Accel-Redirect when that
 * is enabled, otherwise streamed by PHP.
 *
 * Public and unlisted videos have no marker and keep being served directly by
 * nginx, so the extra work is limited to the rare private video.
 */
class ProtectedMediaService
{
    public const MARKER = '.private';

    public function markerPath(Video $video): ?string
    {
        if (! $this->hasSafeSlug($video->slug)) {
            return null;
        }

        return "videos/{$video->slug}/".self::MARKER;
    }

    /**
     * Create or remove the marker so it matches the video's current privacy.
     */
    public function sync(Video $video): void
    {
        $marker = $this->markerPath($video);

        if ($marker === null || $video->is_embedded) {
            return;
        }

        try {
            $disk = Storage::disk('public');

            if ($video->privacy === 'private') {
                if (! $disk->exists($marker)) {
                    $disk->put($marker, '');
                }
            } elseif ($disk->exists($marker)) {
                $disk->delete($marker);
            }
        } catch (Throwable $e) {
            // A missing marker leaves a private video reachable by direct URL,
            // so this is worth an admin-visible error rather than a debug line.
            Log::error('ProtectedMediaService: failed to sync privacy marker', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the response for an authorised request to a protected file.
     *
     * $relativePath is relative to the public disk root and must already have
     * been validated by resolveFile().
     */
    public function respond(string $relativePath, string $absolutePath): Response
    {
        $headers = [
            'Content-Type' => $this->mimeType($absolutePath),
            // Never let Cloudflare or a browser share a cached copy with
            // someone else — the whole point is per-viewer authorisation.
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        if ((bool) Setting::get('media_x_accel_redirect', false)) {
            $encoded = implode('/', array_map('rawurlencode', explode('/', $relativePath)));

            return response('', 200, $headers + [
                'X-Accel-Redirect' => rtrim((string) config('hubtube.media.x_accel_prefix'), '/').'/'.$encoded,
            ]);
        }

        // BinaryFileResponse answers Range requests itself, which the player
        // needs for seeking in MP4s. It also marks itself public on
        // construction, overriding the header passed in, so set it afterwards.
        $response = response()->file($absolutePath, $headers);
        $response->headers->set('Cache-Control', $headers['Cache-Control']);

        return $response;
    }

    /**
     * Resolve a request path to a real file inside the video's directory.
     *
     * Returns [relativePath, absolutePath], or null when the path is unsafe or
     * the file does not exist.
     *
     * @return array{0: string, 1: string}|null
     */
    public function resolveFile(Video $video, string $path): ?array
    {
        if (! $this->hasSafeSlug($video->slug)) {
            return null;
        }

        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_contains($path, "\0")) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            // Rejects '..' and '.', and hides dotfiles such as the marker itself.
            if ($segment === '' || str_starts_with($segment, '.')) {
                return null;
            }
        }

        $relative = "videos/{$video->slug}/{$path}";
        $disk = Storage::disk('public');
        $absolute = $disk->path($relative);

        $realBase = realpath($disk->path("videos/{$video->slug}"));
        $realFile = realpath($absolute);

        if (! $realBase || ! $realFile || ! is_file($realFile)
            || ! str_starts_with($realFile, $realBase.DIRECTORY_SEPARATOR)) {
            return null;
        }

        return [$relative, $realFile];
    }

    /**
     * Slugs are usually Str::slug() output, but imported videos can carry
     * anything. All that matters here is that the slug is a single, non-hidden
     * path segment.
     */
    protected function hasSafeSlug(?string $slug): bool
    {
        return is_string($slug)
            && $slug !== ''
            && ! str_starts_with($slug, '.')
            && strpbrk($slug, "/\\\0") === false;
    }

    protected function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4', 'm4v' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'mkv' => 'video/x-matroska',
            'avi' => 'video/x-msvideo',
            'flv' => 'video/x-flv',
            'wmv' => 'video/x-ms-wmv',
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            'm4s' => 'video/iso.segment',
            'vtt' => 'text/vtt',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }
}
