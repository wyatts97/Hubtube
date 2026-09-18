<?php

namespace App\Console\Commands;

use App\Models\MediaFolder;
use App\Models\StorageReclaim;
use App\Models\Video;
use App\Services\Storage\StorageReclaimService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Queue storage reclaims for the biggest candidates.
 *
 * Deliberately a limited command rather than a "reclaim everything" button:
 * this re-encodes or drops files a live site is serving, so the intended way
 * to use it is `--limit=1` on one known video, check the result in the review
 * page against `du -sh`, and only then widen.
 */
class ReclaimStorage extends Command
{
    protected $signature = 'storage:reclaim
                            {--target=hls : hls, rendition or original}
                            {--quality= : Which rendition, for --target=rendition}
                            {--video= : Reclaim one video by id, ignoring the ranking}
                            {--limit=10 : How many videos to queue}
                            {--min-bytes= : Skip candidates holding less than this}
                            {--dry-run : List the candidates and stop}';

    protected $description = 'Queue storage reclaims, biggest candidates first';

    public function handle(StorageReclaimService $reclaims): int
    {
        $target = (string) $this->option('target');

        if (! in_array($target, StorageReclaim::TARGETS, true)) {
            $this->error('--target must be one of: '.implode(', ', StorageReclaim::TARGETS));

            return self::FAILURE;
        }

        $quality = $this->option('quality') ? (string) $this->option('quality') : null;
        $limit = max(1, (int) $this->option('limit'));
        $minBytes = $this->option('min-bytes') !== null ? (int) $this->option('min-bytes') : 0;

        $candidates = $this->option('video')
            ? Video::where('id', (int) $this->option('video'))->get()
            : $this->rank($target, $limit * 3, $minBytes);

        if ($candidates->isEmpty()) {
            $this->info('No candidates found.');

            return self::SUCCESS;
        }

        $queued = 0;
        $rows = [];

        foreach ($candidates as $video) {
            if ($queued >= $limit) {
                break;
            }

            if ($reason = $reclaims->eligibility($video, $target, $quality)) {
                $rows[] = [$video->id, $this->truncate($video->title), 'skipped', $reason];

                continue;
            }

            if ($this->option('dry-run')) {
                $rows[] = [$video->id, $this->truncate($video->title), 'would queue', '—'];
                $queued++;

                continue;
            }

            $result = $reclaims->requestAndStart($video, $target, $quality);

            $rows[] = [
                $video->id,
                $this->truncate($video->title),
                $result['reclaim'] ? 'queued' : 'refused',
                $result['reason'] ?? '—',
            ];

            if ($result['reclaim']) {
                $queued++;
            }
        }

        $this->table(['Video', 'Title', 'Result', 'Reason'], $rows);

        $this->info($this->option('dry-run')
            ? "{$queued} reclaim(s) would be queued."
            : "Queued {$queued} reclaim(s) on the storage-reclaim queue. Review them in Admin → Content → Storage Reclaim.");

        return self::SUCCESS;
    }

    /**
     * Videos ranked by how much the chosen target is holding.
     *
     * For HLS this reads the folder rollups the media index already maintains
     * — `videos/{slug}/processed/hls` at depth 3 — so the biggest duplicate
     * trees come first without a filesystem walk. For the encode targets the
     * file size is the ranking, biggest first.
     *
     * @return Collection<int, Video>
     */
    protected function rank(string $target, int $take, int $minBytes)
    {
        if ($target === StorageReclaim::TARGET_HLS) {
            $directories = MediaFolder::query()
                ->where('root', 'videos')
                ->where('depth', 3)
                ->where('name_lower', 'hls')
                ->when($minBytes > 0, fn ($query) => $query->where('total_size', '>=', $minBytes))
                ->orderByDesc('total_size')
                ->limit($take)
                ->pluck('path');

            // videos/{slug}/processed/hls → videos/{slug}
            $prefixes = $directories
                ->map(fn (string $path) => dirname(dirname($path)).'/')
                ->all();

            if ($prefixes === []) {
                return collect();
            }

            $videos = Video::query()
                ->where(function ($query) use ($prefixes) {
                    foreach ($prefixes as $prefix) {
                        $query->orWhere('video_path', 'like', $prefix.'%');
                    }
                })
                ->get()
                ->keyBy(fn (Video $video) => dirname($video->video_path).'/');

            // Preserve the biggest-first order the rollups gave us.
            return collect($prefixes)
                ->map(fn (string $prefix) => $videos->get($prefix))
                ->filter()
                ->values();
        }

        return Video::query()
            ->where('status', 'processed')
            ->where('is_embedded', false)
            ->whereNotNull('video_path')
            ->when($minBytes > 0, fn ($query) => $query->where('size', '>=', $minBytes))
            ->orderByDesc('size')
            ->limit($take)
            ->get();
    }

    protected function truncate(?string $title): string
    {
        $title = (string) $title;

        return mb_strlen($title) > 40 ? mb_substr($title, 0, 39).'…' : $title;
    }
}
