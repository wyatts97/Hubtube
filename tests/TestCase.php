<?php

namespace Tests;

use App\Jobs\TranslateModelJob;
use App\Jobs\TranslateTextJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mark app as installed so routes work
        if (!file_exists(storage_path('installed'))) {
            file_put_contents(storage_path('installed'), now()->toDateTimeString());
            $this->beforeApplicationDestroyed(function () {
                // Don't delete — other tests may need it
            });
        }

        // QUEUE_CONNECTION=sync runs queued jobs inline, so the translation
        // jobs would call the real translation provider — a throttled third
        // party — during the test run. Faking just these two keeps the suite
        // offline and fast while every other job still executes as before.
        // Tests that assert on dispatch can still call Queue::fake() freely.
        Queue::fake([TranslateModelJob::class, TranslateTextJob::class]);
    }
}
