<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\Video;
use App\Services\IndexNowService;
use App\Services\TranslationService;
use Illuminate\Console\Command;

class IndexNowSubmitVideo extends Command
{
    protected $signature = 'indexnow:submit-video {video : Video ID or slug}';

    protected $description = 'Submit a specific video URL (and translated alternates) to IndexNow';

    public function handle(IndexNowService $service): int
    {
        if (!$service->isEnabled()) {
            $this->warn('IndexNow is disabled or missing key.');
            return self::FAILURE;
        }

        $arg = (string) $this->argument('video');
        $video = is_numeric($arg)
            ? Video::find((int) $arg)
            : Video::where('slug', $arg)->first();

        if (!$video) {
            $this->error("Video not found: {$arg}");
            return self::FAILURE;
        }

        $urls = [url("/{$video->slug}")];
        try {
            $alternates = app(TranslationService::class)
                ->getAlternateUrls(Video::class, $video->id, $video->slug);
            foreach ($alternates as $altUrl) {
                if (is_string($altUrl) && $altUrl !== '') {
                    $urls[] = $altUrl;
                }
            }
        } catch (Throwable) {
            // Ignore — submit primary only
        }

        $ok = $service->submitUrls(array_values(array_unique($urls)), Video::class, $video->id);

        if ($ok) {
            $this->info("Submitted video {$video->id} (" . count($urls) . " URL(s))");
            return self::SUCCESS;
        }

        $this->error("Submission failed for video {$video->id}");
        return self::FAILURE;
    }
}
