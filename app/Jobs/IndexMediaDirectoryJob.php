<?php

namespace App\Jobs;

use App\Services\Media\MediaIndexService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Bring one directory's Media Library index rows in line with the disk.
 *
 * Dispatched by the things that write media without going through the Media
 * Library page — the encoder when a video finishes, ImageService after it
 * writes its variants, the ad creative processor. Without these hooks those
 * files stay invisible until the ten-minute scheduled pass, or until someone
 * presses Rescan.
 *
 * Queued rather than inline because these run inside jobs that are already
 * doing expensive work, and unique per directory so a burst of writes into the
 * same folder collapses into one scan.
 */
class IndexMediaDirectoryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(
        public string $directory,
        public bool $recursive = true,
    ) {
        // Shares the thumbnail queue: both are Media Library housekeeping that
        // must never delay encoding, and that queue is already niced to yield.
        $this->onQueue('media-thumbnails');
    }

    public function uniqueId(): string
    {
        return $this->directory.($this->recursive ? ':r' : '');
    }

    public function handle(MediaIndexService $index): void
    {
        $index->indexDirectory($this->directory, $this->recursive);
    }
}
