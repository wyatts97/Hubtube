<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\IndexNowService;
use Illuminate\Console\Command;

class IndexNowSubmitRecent extends Command
{
    protected $signature = 'indexnow:submit-recent
        {--limit=100 : Max number of videos to submit}
        {--days=7 : Only consider videos published within the last N days}';

    protected $description = 'Submit recently published public videos to IndexNow in a single batch';

    public function handle(IndexNowService $service): int
    {
        if (!$service->isEnabled()) {
            $this->warn('IndexNow is disabled or missing key.');
            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));

        $videos = Video::query()
            ->where('status', 'processed')
            ->where('is_approved', true)
            ->where('privacy', 'public')
            ->whereNotNull('published_at')
            ->where('published_at', '>=', now()->subDays($days))
            ->whereNull('queue_order')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'slug']);

        if ($videos->isEmpty()) {
            $this->info('No recent eligible videos found.');
            return self::SUCCESS;
        }

        $urls = $videos->map(fn ($v) => url("/{$v->slug}"))->all();
        $ok = $service->submitUrls($urls);

        if ($ok) {
            $this->info('Submitted ' . count($urls) . ' video URL(s).');
            return self::SUCCESS;
        }

        $this->error('Submission failed (see logs).');
        return self::FAILURE;
    }
}
