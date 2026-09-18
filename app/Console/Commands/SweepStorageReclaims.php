<?php

namespace App\Console\Commands;

use App\Models\StorageReclaim;
use App\Services\Storage\StorageReclaimService;
use App\Support\Bytes;
use Illuminate\Console\Command;

/**
 * Accept every storage reclaim whose review window has passed.
 *
 * A reclaim holds the old file until someone says yes, which means the space
 * is not actually free while it waits. If nobody ever reviews, nothing is ever
 * saved and the ledger just accumulates kept files — so a passed keep_until is
 * read as acceptance. Scheduled daily; see routes/console.php.
 */
class SweepStorageReclaims extends Command
{
    protected $signature = 'storage:reclaim-sweep
                            {--limit= : Accept at most this many}
                            {--dry-run : List what would be accepted and stop}';

    protected $description = 'Accept storage reclaims whose review window has expired';

    public function handle(StorageReclaimService $reclaims): int
    {
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $due = StorageReclaim::query()
            ->awaitingReview()
            ->whereNotNull('keep_until')
            ->where('keep_until', '<=', now())
            ->orderBy('keep_until')
            ->when($limit, fn ($query) => $query->limit($limit))
            ->get();

        if ($due->isEmpty()) {
            $this->info('No storage reclaims are due for review.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Video', 'Target', 'Saving', 'Waiting since'],
            $due->map(fn (StorageReclaim $reclaim) => [
                $reclaim->id,
                $reclaim->video_id,
                $reclaim->targetLabel(),
                $reclaim->savingLabel(),
                $reclaim->keep_until?->diffForHumans(),
            ])->all(),
        );

        if ($this->option('dry-run')) {
            $this->comment($due->count().' reclaim(s) would be accepted.');

            return self::SUCCESS;
        }

        $freed = 0;
        $accepted = 0;

        foreach ($due as $reclaim) {
            if ($reclaims->accept($reclaim, null, StorageReclaim::EXPIRED)) {
                $accepted++;
                $freed += $reclaim->savedBytes();

                continue;
            }

            $this->warn("Reclaim #{$reclaim->id} could not be accepted: ".($reclaim->fresh()?->error ?? 'unknown reason'));
        }

        $this->info("Accepted {$accepted} reclaim(s), freeing ".Bytes::format($freed).'.');

        return self::SUCCESS;
    }
}
