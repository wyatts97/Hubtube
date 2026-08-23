<?php

use App\Jobs\ProcessAdCreativeJob;
use App\Models\VideoAd;
use Illuminate\Support\Facades\Queue;

it('dispatches ProcessAdCreativeJob when a new mp4 ad gets an uploaded file_path', function () {
    Queue::fake();

    $ad = VideoAd::factory()->create([
        'type' => 'mp4',
        'file_path' => 'media/ads/example.mp4',
    ]);

    Queue::assertPushedOn('ad-processing', ProcessAdCreativeJob::class);
    Queue::assertPushed(ProcessAdCreativeJob::class, fn ($job) => $job->videoAd->is($ad));

    expect($ad->fresh()->hls_status)->toBe('pending');
});

it('does not dispatch when file_path is unchanged on a later save', function () {
    $ad = VideoAd::factory()->create([
        'type' => 'mp4',
        'file_path' => 'media/ads/example.mp4',
    ]);

    Queue::fake();

    $ad->update(['name' => 'Renamed Ad']);

    Queue::assertNotPushed(ProcessAdCreativeJob::class);
});

it('does not dispatch for non-mp4 ad types', function () {
    Queue::fake();

    VideoAd::factory()->create([
        'type' => 'vast',
        'content' => 'https://example.com/vast.xml',
        'file_path' => null,
    ]);

    Queue::assertNotPushed(ProcessAdCreativeJob::class);
});

it('does not dispatch when file_path is null', function () {
    Queue::fake();

    VideoAd::factory()->create([
        'type' => 'mp4',
        'file_path' => null,
    ]);

    Queue::assertNotPushed(ProcessAdCreativeJob::class);
});
