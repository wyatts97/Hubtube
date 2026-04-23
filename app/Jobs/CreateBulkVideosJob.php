<?php

namespace App\Jobs;

use App\Services\BulkVideoCreator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Asynchronously creates videos from bulk-upload entries and caches the
 * resulting IDs under a short-lived token so the BulkVideoUploader page
 * can pick them up via polling.
 */
class CreateBulkVideosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;
    public string $queue = 'default';

    public function __construct(
        public array $entries,
        public bool $addToQueue,
        public int $actorId,
        public string $token,
    ) {}

    public function handle(BulkVideoCreator $creator): void
    {
        $cacheKey = self::cacheKey($this->actorId, $this->token);

        Cache::put($cacheKey, [
            'status' => 'running',
            'created_ids' => [],
            'total' => count($this->entries),
        ], now()->addHours(6));

        try {
            $createdIds = $creator->createMany($this->entries, $this->addToQueue, $this->actorId);

            Cache::put($cacheKey, [
                'status' => 'done',
                'created_ids' => $createdIds,
                'total' => count($this->entries),
            ], now()->addHours(6));
        } catch (\Throwable $e) {
            Log::error('CreateBulkVideosJob failed', [
                'actor_id' => $this->actorId,
                'token' => $this->token,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'created_ids' => [],
                'total' => count($this->entries),
                'error' => $e->getMessage(),
            ], now()->addHours(6));

            throw $e;
        }
    }

    public static function cacheKey(int $actorId, string $token): string
    {
        return "bulk_upload:{$actorId}:{$token}";
    }
}
