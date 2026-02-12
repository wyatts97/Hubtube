<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    }
}
