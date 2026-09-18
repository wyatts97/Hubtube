<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Storage\StorageReclaimService;
use App\Support\Bytes;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Queue re-compressions of the biggest original uploads.
 *
 * Deliberately a limited command rather than a "shrink everything" button:
 * this re-encodes files a live site is serving, so the intended way to use it
 * is `--limit=1` on one known video, check the result in the review page
 * against `du -sh`, and only then widen.
 *
 * Nothing here deletes anything. Each queued encode writes a new file beside
 * the original and waits for a human to accept it.
 */
class ReclaimStorage extends Command
{
    protected $signature = 'storage:reclaim
                            {--video= : Re-compress one video by id, ignoring the ranking}
                            {--limit=10 : How many videos to queue}
                            {--min-bytes= : Skip uploads smaller than this}
                            {--dry-run : List the candidates and stop}';

    protected $description = 'Queue re-compressions of the largest original uploads';

    public function handle(StorageReclaimService $reclaims): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $minBytes = $this->option('min-bytes') !== null ? (int) $this->option('min-bytes') : 0;

        $candidates = $this->option('video')
            ? Video::where('id', (int) $this->option('video'))->get()
            // Over-fetched, because eligibility refuses some of these and the
            // limit counts what was actually queued.
            : $this->rank($limit * 3, $minBytes);

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

            if ($reason = $reclaims->eligibility($video)) {
                $rows[] = [$video->id, $this->truncate($video->title), Bytes::format((int) $video->size), 'skipped', $reason];

                continue;
            }

            if ($this->option('dry-run')) {
                $rows[] = [$video->id, $this->truncate($video->title), Bytes::format((int) $video->size), 'would queue', '—'];
                $queued++;

                continue;
            }

            $result = $reclaims->requestAndStart($video);

            $rows[] = [
                $video->id,
                $this->truncate($video->title),
                Bytes::format((int) $video->size),
                $result['reclaim'] ? 'queued' : 'refused',
                $result['reason'] ?? '—',
            ];

            if ($result['reclaim']) {
                $queued++;
            }
        }

        $this->table(['Video', 'Title', 'Upload', 'Result', 'Reason'], $rows);

        $this->info($this->option('dry-run')
            ? "{$queued} re-compression(s) would be queued."
            : "Queued {$queued} re-compression(s) on the storage-reclaim queue. Review them in Admin → Content → Storage Reclaim; nothing is deleted until you accept.");

        return self::SUCCESS;
    }

    /**
     * Processed videos with the largest uploads first.
     *
     * `videos.size` is the upload's own size, which is what this shrinks, so
     * it is both the ranking and the thing being measured.
     *
     * @return Collection<int, Video>
     */
    protected function rank(int $take, int $minBytes): Collection
    {
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
