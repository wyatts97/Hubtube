<?php

namespace Tests;

use App\Jobs\TranslateModelJob;
use App\Models\Setting;
use App\Services\Translation\TranslationProviderManager;
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

        // QUEUE_CONNECTION=sync runs queued jobs inline, so this job would call
        // the real translation provider — a throttled third party — during the
        // test run. Faking just this one keeps the suite offline and fast while
        // every other job still executes as before. Tests that assert on
        // dispatch can still call Queue::fake() freely.
        Queue::fake([TranslateModelJob::class]);

        // The settings cache is process-wide and survives RefreshDatabase, so a
        // test that switches translation provider (or any other setting) leaks
        // into the next one. Left alone, a stale `libretranslate` provider makes
        // later tests attempt real HTTP to a bogus endpoint and wait out DNS.
        Setting::clearCache();
        app(TranslationProviderManager::class)->forget();
    }
}
