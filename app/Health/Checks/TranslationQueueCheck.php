<?php

namespace App\Health\Checks;

use App\Jobs\TranslateModelJob;
use App\Services\TranslationService;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

/**
 * Watches the background translation pipeline.
 *
 * Content translation moved off the request path, so an HTTP response no longer
 * waits on the provider — but that also means a stalled or failing queue is
 * silent: pages simply keep rendering the source language forever instead of
 * being slow. This check makes that visible.
 *
 * Skipped entirely when only one locale is enabled or auto-translation is off,
 * so single-language installs don't see a permanently amber check.
 */
class TranslationQueueCheck extends Check
{
    protected int $failureThreshold = 1;

    protected int $backlogWarningThreshold = 500;

    public function failWhenFailedJobsExceeds(int $count): self
    {
        $this->failureThreshold = $count;

        return $this;
    }

    public function warnWhenBacklogExceeds(int $count): self
    {
        $this->backlogWarningThreshold = $count;

        return $this;
    }

    public function run(): Result
    {
        $result = Result::make();

        try {
            if (! TranslationService::autoTranslateEnabled()
                || count(TranslationService::getEnabledLocales()) <= 1) {
                return $result->ok('Content translation is not in use.');
            }

            $failed = $this->countMatchingJobs('failed_jobs');
            $pending = $this->countMatchingJobs('jobs');

            $result->meta(['failed' => $failed, 'pending' => $pending]);
            $result->shortSummary("{$pending} pending, {$failed} failed");

            if ($failed >= $this->failureThreshold) {
                return $result->failed(
                    "{$failed} translation job(s) have failed — translated titles will not appear. Check the queue worker and `php artisan queue:failed`."
                );
            }

            if ($pending > $this->backlogWarningThreshold) {
                return $result->warning(
                    "{$pending} translation job(s) are queued — the worker may be stopped or falling behind."
                );
            }

            return $result->ok();
        } catch (Throwable $e) {
            return $result->failed("Could not inspect the translation queue: {$e->getMessage()}");
        }
    }

    /**
     * Count queued/failed rows belonging to TranslateModelJob.
     *
     * The job class name is embedded in the serialised payload, so a LIKE is
     * the only portable way to isolate it across queue drivers.
     */
    protected function countMatchingJobs(string $table): int
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            return 0;
        }

        return DB::table($table)
            ->where('payload', 'like', '%'.str_replace('\\', '\\\\', TranslateModelJob::class).'%')
            ->count();
    }
}
