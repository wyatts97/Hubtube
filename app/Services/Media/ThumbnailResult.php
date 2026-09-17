<?php

namespace App\Services\Media;

use App\Models\MediaFile;

/**
 * The outcome of one thumbnail attempt.
 *
 * A value object rather than a bare bool, because "it didn't work" has four
 * meaningful answers and each implies a different retry policy: an unsupported
 * format must never be tried again, a missing ffmpeg should be tried again once
 * one is installed, a corrupt file waits for an explicit retry, and a file that
 * vanished is nobody's problem.
 */
class ThumbnailResult
{
    private function __construct(
        public readonly string $state,
        public readonly ?string $path = null,
        public readonly ?string $error = null,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?int $durationSeconds = null,
    ) {}

    public static function ready(string $path, ?int $width = null, ?int $height = null, ?int $duration = null): self
    {
        return new self(MediaFile::THUMB_READY, $path, null, $width, $height, $duration);
    }

    /** No thumbnail is needed — the file is its own preview, or is not visual. */
    public static function notNeeded(?int $width = null, ?int $height = null): self
    {
        return new self(MediaFile::THUMB_READY, null, null, $width, $height);
    }

    /** The format can never be rendered here. Never retried. */
    public static function unsupported(): self
    {
        return new self(MediaFile::THUMB_UNSUPPORTED);
    }

    /** The tool is missing, not the capability. Retried when it appears. */
    public static function unavailable(string $error): self
    {
        return new self(MediaFile::THUMB_UNAVAILABLE, null, $error);
    }

    public static function failed(string $error): self
    {
        return new self(MediaFile::THUMB_FAILED, null, $error);
    }

    public function isReady(): bool
    {
        return $this->state === MediaFile::THUMB_READY;
    }
}
