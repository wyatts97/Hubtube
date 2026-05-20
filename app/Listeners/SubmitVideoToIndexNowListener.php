<?php

namespace App\Listeners;

use Throwable;
use App\Events\VideoProcessed;
use App\Models\SearchIndexSubmission;
use App\Models\Setting;
use App\Models\Video;
use App\Services\IndexNowService;
use App\Services\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SubmitVideoToIndexNowListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(VideoProcessed $event): void
    {
        if (!Setting::get('indexnow_enabled', false)) {
            return;
        }
        if (!Setting::get('indexnow_auto_submit_videos', true)) {
            return;
        }

        $video = $event->video;
        $video->refresh();

        if (!$this->isIndexable($video)) {
            return;
        }

        // Skip if we've already submitted this video URL recently
        $primaryUrl = url("/{$video->slug}");
        $recent = SearchIndexSubmission::where('engine', 'indexnow')
            ->where('url_hash', SearchIndexSubmission::hashUrl($primaryUrl))
            ->where('status', 'success')
            ->where('submitted_at', '>=', now()->subHours(24))
            ->exists();
        if ($recent) {
            return;
        }

        $urls = [$primaryUrl];

        if (Setting::get('indexnow_submit_translated_urls', true)) {
            try {
                $translationService = app(TranslationService::class);
                $alternates = $translationService->getAlternateUrls(
                    Video::class,
                    $video->id,
                    $video->slug
                );
                if (is_array($alternates)) {
                    foreach ($alternates as $altUrl) {
                        if (is_string($altUrl) && $altUrl !== '') {
                            $urls[] = $altUrl;
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::debug('IndexNow: failed to resolve alternate URLs', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            app(IndexNowService::class)->submitUrls(
                array_values(array_unique($urls)),
                Video::class,
                $video->id,
            );
        } catch (Throwable $e) {
            Log::warning('IndexNow: video submission failed', [
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function isIndexable(Video $video): bool
    {
        if ($video->status !== 'processed') {
            return false;
        }
        if (!$video->is_approved) {
            return false;
        }
        if ($video->privacy !== 'public') {
            return false;
        }
        if (!$video->published_at || $video->published_at->isFuture()) {
            return false;
        }
        if ($video->scheduled_at || $video->queue_order !== null || $video->requires_schedule) {
            return false;
        }
        return true;
    }
}
