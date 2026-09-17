<?php

namespace App\Services\Media;

/**
 * The file-type taxonomy the Media Library sorts and filters on.
 *
 * One place, because the same lists were written out three times — in the
 * page's fileType()/isImage()/isVideo() and again in
 * FileManagerThumbnailService — and had already drifted: the page counted svg,
 * bmp and ico as images while the thumbnailer choked on them.
 */
class MediaType
{
    public const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'bmp', 'ico', 'tif', 'tiff', 'heic',
    ];

    public const VIDEO_EXTENSIONS = [
        'mp4', 'mov', 'webm', 'mkv', 'avi', 'flv', 'wmv', 'm4v', 'mpg', 'mpeg',
    ];

    public const AUDIO_EXTENSIONS = [
        'mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'opus',
    ];

    public const DOCUMENT_EXTENSIONS = [
        'pdf', 'txt', 'vtt', 'srt', 'json', 'csv', 'xml', 'm3u8',
    ];

    /**
     * Raster formats GD can decode, and so the only ones worth sending to the
     * thumbnailer. avif depends on the build.
     */
    public static function rasterisableExtensions(): array
    {
        $supported = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (function_exists('imagecreatefromavif')) {
            $supported[] = 'avif';
        }

        return $supported;
    }

    public static function forExtension(string $extension): string
    {
        $extension = strtolower(ltrim($extension, '.'));

        return match (true) {
            in_array($extension, self::IMAGE_EXTENSIONS, true) => 'image',
            in_array($extension, self::VIDEO_EXTENSIONS, true) => 'video',
            in_array($extension, self::AUDIO_EXTENSIONS, true) => 'audio',
            in_array($extension, self::DOCUMENT_EXTENSIONS, true) => 'document',
            default => 'other',
        };
    }

    public static function forPath(string $path): string
    {
        return self::forExtension(pathinfo($path, PATHINFO_EXTENSION));
    }

    public static function isImage(string $extension): bool
    {
        return self::forExtension($extension) === 'image';
    }

    public static function isVideo(string $extension): bool
    {
        return self::forExtension($extension) === 'video';
    }

    public static function isRasterisable(string $extension): bool
    {
        return in_array(strtolower(ltrim($extension, '.')), self::rasterisableExtensions(), true);
    }
}
