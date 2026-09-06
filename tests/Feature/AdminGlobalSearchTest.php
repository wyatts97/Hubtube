<?php

use App\Filament\Resources\ChannelResource;
use App\Filament\Resources\CommentResource;
use App\Filament\Resources\DmcaRequestResource;
use App\Filament\Resources\GalleryResource;
use App\Filament\Resources\ImageResource;
use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\PointsTransactionResource;
use App\Filament\Resources\ReportResource;
use App\Filament\Resources\WalletTransactionResource;
use App\Filament\Resources\WithdrawalRequestResource;
use App\Models\Channel;
use App\Models\Comment;
use App\Models\DmcaRequest;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\MenuItem;
use App\Models\Report;
use App\Models\User;
use App\Models\Video;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Global search
|--------------------------------------------------------------------------
|
| getGlobalSearchEloquentQuery() defaults to getEloquentQuery(), so a table's
| ->modifyQueryUsing() eager loads do NOT reach global search. Any resource
| that dereferences a relationship in getGlobalSearchResultDetails() therefore
| needs its own eager loads: without them each result lazy-loads, which throws
| under Model::shouldBeStrict() outside production and costs one query per
| result in it. Search re-runs every 500ms at up to 50 results per resource.
|
| ChannelResource was the worst case: its details called User::totalVideoViews(),
| a cached SUM aggregate that eager loading cannot fix, so that column was
| dropped in favour of the channel's own subscriber_count.
|
*/

/** Render every result's details and return how many queries that cost. */
function queriesToRenderDetails(string $resource): int
{
    $records = $resource::getGlobalSearchEloquentQuery()->limit(10)->get();

    expect($records)->not->toBeEmpty("no {$resource} records were seeded");

    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    foreach ($records as $record) {
        $resource::getGlobalSearchResultDetails($record);
    }

    return $count;
}

test('channel search results render without lazy loads or per-result aggregates', function () {
    User::factory()->count(3)->create()->each(function (User $user, int $i) {
        Channel::create([
            'user_id' => $user->id,
            'name' => "Channel {$i}",
            'slug' => "channel-{$i}",
            'subscriber_count' => 10,
        ]);
    });

    expect(queriesToRenderDetails(ChannelResource::class))->toBe(0);
});

test('comment search results render without lazy loads', function () {
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create();
    Comment::factory()->count(3)->for($user)->for($video)->create();

    expect(queriesToRenderDetails(CommentResource::class))->toBe(0);
});

test('comment search resolves the video title even when the video is soft deleted', function () {
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create(['title' => 'Deleted Clip']);
    Comment::factory()->for($user)->for($video)->create();
    $video->delete();

    $record = CommentResource::getGlobalSearchEloquentQuery()->firstOrFail();

    expect(CommentResource::getGlobalSearchResultDetails($record)['Video'])->toBe('Deleted Clip');
});

test('gallery search results render without lazy loads', function () {
    User::factory()->count(3)->create()->each(function (User $user, int $i) {
        Gallery::create([
            'user_id' => $user->id,
            'title' => "Gallery {$i}",
            'slug' => "gallery-{$i}",
        ]);
    });

    expect(queriesToRenderDetails(GalleryResource::class))->toBe(0);
});

test('image search results render without lazy loads', function () {
    $user = User::factory()->create();
    Image::factory()->count(3)->for($user)->create();

    expect(queriesToRenderDetails(ImageResource::class))->toBe(0);
});

test('menu item search results render without lazy loads', function () {
    $parent = MenuItem::create(['label' => 'Browse', 'type' => 'dropdown']);

    foreach (range(1, 3) as $i) {
        MenuItem::create([
            'label' => "Child {$i}",
            'type' => 'link',
            'url' => "/child-{$i}",
            'parent_id' => $parent->id,
        ]);
    }

    expect(queriesToRenderDetails(MenuItemResource::class))->toBe(0);
});

test('dmca request search results render without lazy loads', function () {
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create();

    foreach (range(1, 3) as $i) {
        DmcaRequest::create([
            'complainant_name' => "Rights Holder {$i}",
            'complainant_email' => "legal{$i}@example.com",
            'infringing_urls' => "https://example.test/v/{$i}",
            'copyrighted_work_description' => "Original work {$i}",
            'signature' => "Rights Holder {$i}",
            'video_id' => $video->id,
            'status' => 'pending',
        ]);
    }

    expect(queriesToRenderDetails(DmcaRequestResource::class))->toBe(0);
});

test('report search results render without lazy loads across the reportable morph', function () {
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create(['title' => 'Reported Clip']);

    foreach (range(1, 3) as $i) {
        Report::create([
            'user_id' => $user->id,
            'reportable_type' => $video->getMorphClass(),
            'reportable_id' => $video->id,
            'reason' => Report::REASON_SPAM,
            'description' => "Looks like spam {$i}",
        ]);
    }

    expect(queriesToRenderDetails(ReportResource::class))->toBe(0);
});

test('report search results are titled by the reported content, not the model label', function () {
    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create(['title' => 'Reported Clip']);

    Report::create([
        'user_id' => $user->id,
        'reportable_type' => $video->getMorphClass(),
        'reportable_id' => $video->id,
        'reason' => Report::REASON_SPAM,
        'description' => 'Looks like spam',
    ]);

    $record = ReportResource::getGlobalSearchEloquentQuery()->firstOrFail();

    expect(ReportResource::getGlobalSearchResultTitle($record))->toBe('Reported Clip');
});

test('withdrawal request search results render without lazy loads and are titled by id', function () {
    User::factory()->count(3)->create()->each(function (User $user) {
        WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => 25.00,
            'payment_method' => 'paypal',
            'payment_details' => ['email' => 'payee@example.com'],
            'status' => 'pending',
            'transaction_id' => 'TXN-'.$user->id,
        ]);
    });

    expect(queriesToRenderDetails(WithdrawalRequestResource::class))->toBe(0);

    $record = WithdrawalRequestResource::getGlobalSearchEloquentQuery()->firstOrFail();
    expect(WithdrawalRequestResource::getGlobalSearchResultTitle($record))
        ->toBe('Withdrawal #'.$record->getKey());
});

test('withdrawal search never exposes payment details', function () {
    expect(WithdrawalRequestResource::getGloballySearchableAttributes())
        ->not->toContain('payment_details');
});

test('ledger resources are deliberately excluded from global search', function (string $resource) {
    asAdmin();

    expect($resource::canGloballySearch())->toBeFalse();
})->with([
    'wallet transactions' => [WalletTransactionResource::class],
    'points transactions' => [PointsTransactionResource::class],
]);

test('every globally searchable resource can build a result url', function () {
    asAdmin();

    $user = User::factory()->create();
    $video = Video::factory()->for($user)->create();

    // A resource with no edit/view page falls back to ?tableAction=view, which
    // is only a live link when the table actually registers a view action.
    expect(ReportResource::getGlobalSearchResultUrl(Report::create([
        'user_id' => $user->id,
        'reportable_type' => $video->getMorphClass(),
        'reportable_id' => $video->id,
        'reason' => Report::REASON_SPAM,
    ])))->not->toBeNull();

    expect(MenuItemResource::getGlobalSearchResultUrl(
        MenuItem::create(['label' => 'Home', 'type' => 'link', 'url' => '/'])
    ))->not->toBeNull();
});
