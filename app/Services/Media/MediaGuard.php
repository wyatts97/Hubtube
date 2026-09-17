<?php

namespace App\Services\Media;

/**
 * The last word on whether a file may be removed or renamed.
 *
 * Deliberately separate from the index. `media_files.is_referenced` is a
 * denormalised flag that exists to render a badge and disable a button without
 * a query per row — it is not permission to delete. Every destructive action
 * asks this class, which re-runs the reference lookup live, so a stale or
 * tampered-with index can never authorise data loss.
 */
class MediaGuard
{
    public function __construct(
        protected MediaPathGuard $paths,
        protected MediaReferenceResolver $references,
    ) {}

    /**
     * Why this file may not be deleted, or null when it may.
     */
    public function blockedReason(string $path): ?string
    {
        $path = $this->paths->sanitize($path);

        if (! $this->paths->isAllowed($path)) {
            return 'That path is outside the media library.';
        }

        if ($this->references->forPath($path) !== []) {
            return 'This file is used by a Video or Image record. Remove the reference first.';
        }

        return null;
    }

    public function isDeletable(string $path): bool
    {
        return $this->blockedReason($path) === null;
    }

    /**
     * Why this file may not be renamed, or null when it may.
     *
     * Renaming is stricter than deleting: a referenced file can be renamed
     * (the path columns are updated with it), but a file inside a directory a
     * record locates by convention cannot, because nothing records the
     * individual names.
     */
    public function renameBlockedReason(string $path): ?string
    {
        $path = $this->paths->sanitize($path);

        if (! $this->paths->isAllowed($path)) {
            return 'That path is outside the media library.';
        }

        if ($this->paths->isVideoSlugDirectory($path) || $this->paths->isUnderVideoSlugDirectory($path)) {
            return 'Rename videos from the video editor so their URLs stay in sync.';
        }

        if (preg_match('#^images/[^/]+/.+$#', $path)) {
            return 'Image files are named by the image record. Rename it from the image editor.';
        }

        return null;
    }

    /**
     * Whether a whole directory may be deleted, and why not if it may not.
     *
     * Checked against the real database rather than the index: the recursive
     * candidate list comes from the caller, and one batched lookup answers for
     * all of it.
     *
     * @param  list<string>  $containedPaths
     */
    public function folderBlockedReason(string $path, array $containedPaths): ?string
    {
        $path = $this->paths->sanitize($path);

        if (! $this->paths->isAllowed($path)) {
            return 'That path is outside the media library.';
        }

        // A bare root is the library's own structure, not content.
        if (in_array($path, $this->paths->allowedRoots(), true)) {
            return 'Top-level media folders cannot be deleted.';
        }

        if ($this->paths->isVideoSlugDirectory($path)) {
            return 'This folder belongs to a video. Delete the video instead.';
        }

        $referenced = $this->references->forPaths($containedPaths);

        if ($referenced !== []) {
            return 'This folder contains '.count($referenced).' file(s) still used by a Video or Image record.';
        }

        return null;
    }
}
