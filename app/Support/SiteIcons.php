<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Single source of truth for the admin-uploaded site icon.
 *
 * The favicon used to be resolved inline in three places (the app layout, the
 * Filament panel, and the static public/manifest.json, which ignored the
 * setting entirely). That let an installed PWA and the browser tab disagree,
 * and it meant a stale or missing upload silently fell through to the shipped
 * default icon with nothing to indicate why.
 */
class SiteIcons
{
    /**
     * Public URL for the admin-uploaded favicon, or null when none is usable.
     *
     * A stored path that no longer exists on the public disk returns null so
     * callers fall back to the shipped icons instead of emitting a link that
     * 404s — with public/favicon.ico now on disk, a 404 here is invisible:
     * the browser quietly falls back to the default icon.
     */
    public static function faviconUrl(): ?string
    {
        $path = trim((string) Setting::get('site_favicon', ''));

        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, '/')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        // Cache-bust on the stored file's mtime: browsers cache favicons hard
        // and per-origin, so replacing the upload without this can leave the
        // previous icon in the tab for days.
        $version = Storage::disk('public')->lastModified($path);

        return '/storage/'.ltrim($path, '/').'?v='.$version;
    }

    /**
     * MIME type matching the uploaded favicon, for the <link type> attribute.
     */
    public static function faviconMimeType(): ?string
    {
        $url = static::faviconUrl();

        if ($url === null) {
            return null;
        }

        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return match ($extension) {
            'ico'          => 'image/x-icon',
            'png'          => 'image/png',
            'svg'          => 'image/svg+xml',
            'jpg', 'jpeg'  => 'image/jpeg',
            'webp'         => 'image/webp',
            default        => null,
        };
    }

    /**
     * Icon list for the web app manifest.
     *
     * An uploaded favicon is advertised first so installed PWAs and Android
     * home screens use the admin's branding, but the shipped PNG set is always
     * kept behind it: the upload is typically a single small icon and Chrome
     * requires a 192px and a 512px entry before it will offer installation.
     */
    public static function manifestIcons(): array
    {
        $icons = [];
        $custom = static::faviconUrl();

        if ($custom !== null) {
            $icons[] = array_filter([
                'src'     => $custom,
                'sizes'   => static::faviconMimeType() === 'image/svg+xml' ? 'any' : '64x64',
                'type'    => static::faviconMimeType(),
                'purpose' => 'any',
            ]);
        }

        foreach ([72, 96, 128, 144, 152, 192, 384, 512] as $size) {
            $icons[] = [
                'src'     => "/icons/icon-{$size}x{$size}.png",
                'sizes'   => "{$size}x{$size}",
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        return $icons;
    }
}
