<?php

namespace App\Listeners;

use App\Events\VideoProcessed;
use App\Jobs\TranslateModelJob;
use App\Models\Video;
use App\Services\TranslationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Throwable;

/**
 * Queues title/description translations for a newly processed video, in every
 * enabled locale.
 *
 * This is what makes the request path's cache-only reads acceptable: by the
 * time a visitor reaches a listing, the translations are usually already
 * stored, so nobody sees the source language while a job catches up.
 */
class PreTranslateVideoListener implements ShouldQueue
{
    public string $queue = 'default';

    public function handle(VideoProcessed $event): void
    {
        try {
            if (! TranslationService::autoTranslateEnabled()) {
                return;
            }

            $video = $event->video;
            $video->refresh();

            // Nothing to show publicly means nothing worth translating yet.
            if ($video->privacy !== 'public' || ! $video->is_approved) {
                return;
            }

            $default = TranslationService::getDefaultLocale();

            foreach (TranslationService::getEnabledLocales() as $locale) {
                if ($locale === $default) {
                    continue;
                }

                TranslateModelJob::dispatch(Video::class, $video->id, ['title', 'description'], $locale);
            }
        } catch (Throwable) {
            // Pre-translation is an optimisation — never fail video processing.
        }
    }
}
