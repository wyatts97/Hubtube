<?php

namespace App\Services\Media;

/**
 * What the Media Library is allowed to touch.
 *
 * Extracted from App\Filament\Pages\MediaLibrary so the indexer, the jobs and
 * the console commands all decide this the same way, and so the rules can be
 * tested without booting a Livewire component. The semantics are deliberately
 * unchanged from the page's originals.
 */
class MediaPathGuard
{
    /** Top-level directories the library may browse. */
    public function allowedRoots(): array
    {
        return array_values(array_filter(array_map(
            fn ($path) => trim((string) $path, '/'),
            (array) config('hubtube.media_library.allowed_paths', ['media'])
        )));
    }

    /**
     * Paths never shown or indexed, even inside an allowed root.
     *
     * The generated thumbnail cache is the reason this exists: it lives on the
     * same disk, shards into 256 subdirectories, and is not media.
     */
    public function excludedPaths(): array
    {
        return array_values(array_filter(array_map(
            fn ($path) => trim((string) $path, '/'),
            (array) config('hubtube.media_library.excluded_paths', [])
        )));
    }

    /**
     * Normalise a path: forward slashes, no leading or trailing slash, no
     * empty or "." segments.
     *
     * Note that ".." is deliberately *not* stripped here — isAllowed() rejects
     * it. Silently removing it would turn "a/../../etc" into a path that looks
     * legitimate.
     */
    public function sanitize(string $path): string
    {
        $path = str_replace('\\', '/', trim($path, '/'));

        // A null byte truncates paths in some C-level filesystem calls.
        $path = str_replace("\0", '', $path);

        $segments = array_filter(
            explode('/', $path),
            fn ($segment) => $segment !== '' && $segment !== '.'
        );

        return implode('/', $segments);
    }

    /** Whether $path is inside one of the allowed roots and not excluded. */
    public function isAllowed(string $path): bool
    {
        $path = trim($path, '/');

        if ($path === '' || str_contains($path, '..') || str_contains($path, "\0")) {
            return false;
        }

        if ($this->isExcluded($path)) {
            return false;
        }

        foreach ($this->allowedRoots() as $root) {
            // The trailing slash matters: without it 'medialibrary' would pass
            // as being inside 'media'.
            if ($path === $root || str_starts_with($path, $root.'/')) {
                return true;
            }
        }

        return false;
    }

    public function isExcluded(string $path): bool
    {
        $path = trim($path, '/');

        foreach ($this->excludedPaths() as $excluded) {
            if ($path === $excluded || str_starts_with($path, $excluded.'/')) {
                return true;
            }
        }

        return false;
    }

    /** A videos/{slug} directory, which belongs to one video record. */
    public function isVideoSlugDirectory(string $path): bool
    {
        return (bool) preg_match('#^videos/[^/]+$#', trim($path, '/'));
    }

    public function isUnderVideoSlugDirectory(string $path): bool
    {
        return (bool) preg_match('#^videos/[^/]+/.+$#', trim($path, '/'));
    }

    /**
     * Whether renaming or moving this path would break a record that points at
     * it by convention rather than by stored path.
     *
     * videos/{slug} is the video's own directory; images/{uuid} holds an
     * image's original plus the variants ImageService wrote beside it, none of
     * which are recorded individually.
     */
    public function isProtected(string $path): bool
    {
        $path = trim($path, '/');

        return $this->isVideoSlugDirectory($path)
            || $this->isUnderVideoSlugDirectory($path)
            || (bool) preg_match('#^images/[^/]+(/.+)?$#', $path);
    }

    /** The allowed root a path sits under, or '' if it sits under none. */
    public function rootOf(string $path): string
    {
        $segments = explode('/', trim($path, '/'));

        return $segments[0] ?? '';
    }
}
