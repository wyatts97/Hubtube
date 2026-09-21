<?php

use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Table column manager
|--------------------------------------------------------------------------
|
| Filament lists every column in the column-manager dropdown, and renders any
| column lacking ->toggleable() as checked AND disabled:
|
|     x-bind:checked="(getColumn(...) || {}).isToggled || false"
|     x-bind:disabled="(getColumn(...) || {}).isToggleable === false"
|
| 112 of 141 columns were in that state, which is why so many entries could
| not be unticked. Every column is now toggleable except one pinned identity
| column per table, so a row can never render unidentifiable.
|
*/

/** The one column per resource that is deliberately NOT toggleable. */
const PINNED = [
    'UserResource' => 'username',
    'VideoResource' => 'title',
    'ImageResource' => 'title',
    'GalleryResource' => 'title',
    'PageResource' => 'title',
    'SponsoredCardResource' => 'title',
    'CategoryResource' => 'name',
    'ChannelResource' => 'name',
    'TagResource' => 'name',
    'VideoAdResource' => 'name',
    'MenuItemResource' => 'label',
    'CommentResource' => 'content',
    'ContactMessageResource' => 'subject',
    'DmcaRequestResource' => 'complainant_name',
    'CCBillSubscriptionResource' => 'ccbill_subscription_id',
    'ReportResource' => 'reported_content',
    'WalletTransactionResource' => 'user.username',
    'PointsTransactionResource' => 'user.username',
    'WithdrawalRequestResource' => 'id',
];

test('every column is toggleable except the pinned identity column', function () {
    asAdmin();

    $offenders = [];
    $pinnedFound = [];

    foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
        if (! str_contains($resource, 'App')) {
            continue;
        }

        $page = $resource::getPages()['index'] ?? null;
        if (! $page) {
            continue;
        }

        $name = class_basename($resource);
        $pinned = PINNED[$name] ?? null;
        $table = Livewire::test($page->getPage())->instance()->getTable();

        foreach ($table->getColumns() as $column) {
            $key = $column->getName();

            if ($key === $pinned) {
                $pinnedFound[$name] = true;

                // The identity column must stay pinned, or a row can be
                // reduced to anonymous numbers with no way back.
                if ($column->isToggleable()) {
                    $offenders[] = "{$name}.{$key} should be pinned but is toggleable";
                }

                continue;
            }

            if (! $column->isToggleable()) {
                $offenders[] = "{$name}.{$key} renders permanently checked and disabled";
            }
        }
    }

    expect($offenders)->toBe([]);

    // Guards against a rename silently turning the pin into a no-op, which
    // would leave that table with no pinned column at all.
    expect(array_keys($pinnedFound))->toHaveCount(count(PINNED));
});

test('the images table keeps its filters in the toolbar rather than a second bar', function () {
    asAdmin();

    $table = Livewire::test(App\Filament\Resources\ImageResource\Pages\ListImages::class)
        ->instance()
        ->getTable();

    // Every other resource already uses the v5 default. ImageResource was the
    // one overriding it to AboveContentCollapsible, which pushed filters onto
    // their own row above the table instead of into the top bar.
    expect($table->getFiltersLayout())->toBe(\Filament\Tables\Enums\FiltersLayout::Dropdown);
});

test('numeric columns no longer carry decorative icons', function () {
    asAdmin();

    $checks = [
        [App\Filament\Resources\UserResource\Pages\ListUsers::class, ['videos_count', 'points_balance']],
        [App\Filament\Resources\VideoResource\Pages\ListVideos::class, ['views_count', 'likes_count']],
        [App\Filament\Resources\ImageResource\Pages\ListImages::class, ['views_count']],
    ];

    foreach ($checks as [$page, $columns]) {
        $table = Livewire::test($page)->instance()->getTable();

        foreach ($table->getColumns() as $column) {
            if (in_array($column->getName(), $columns, true)) {
                expect($column->getIcon(null))->toBeNull();
            }
        }
    }
});
