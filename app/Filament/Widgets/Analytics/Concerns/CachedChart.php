<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Analytics charts summarize days of data, so they are built at most every
 * ten minutes and don't poll. Filament's default was to re-run every chart's
 * GROUP BY queries every 5 seconds for as long as the page stayed open.
 *
 * The widget builds its options in buildOptions().
 */
trait CachedChart
{
    protected function getPollingInterval(): ?string
    {
        return null;
    }

    protected function getOptions(): array
    {
        return Cache::remember('admin-chart:'.static::class, 600, fn () => $this->buildOptions());
    }

    abstract protected function buildOptions(): array;
}
