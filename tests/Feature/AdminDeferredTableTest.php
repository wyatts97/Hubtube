<?php

use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\ImageResource\Pages\ListImages;
use App\Filament\Resources\PointsTransactionResource\Pages\ListPointsTransactions;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Resources\WalletTransactionResource\Pages\ListWalletTransactions;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Deferred table loading
|--------------------------------------------------------------------------
|
| deferLoading() moves the initial query off first paint, but it costs an extra
| Livewire roundtrip, so it only pays where the list page is otherwise bare.
|
| It is deliberately NOT on the video and image lists: their getTabs() issues a
| COUNT per tab and they render header widgets, none of which defer, so
| deferring only the table would render tab badges above a spinner while still
| blocking on the chrome. VideoResource also polls every 15s, which stays
| active during the deferred window and would add a second roundtrip.
|
*/

test('bare high-volume list tables defer their initial query', function (string $page) {
    asAdmin();

    expect(Livewire::test($page)->instance()->getTable()->isLoadingDeferred())->toBeTrue();
})->with([
    'comments' => [ListComments::class],
    'reports' => [ListReports::class],
    'wallet transactions' => [ListWalletTransactions::class],
    'points transactions' => [ListPointsTransactions::class],
]);

test('tabbed list tables with header chrome do not defer', function (string $page) {
    asAdmin();

    expect(Livewire::test($page)->instance()->getTable()->isLoadingDeferred())->toBeFalse();
})->with([
    'videos' => [ListVideos::class],
    'images' => [ListImages::class],
]);

test('the wallet ledger is ordered newest first', function () {
    asAdmin();

    $table = Livewire::test(ListWalletTransactions::class)->instance()->getTable();

    expect($table->getDefaultSortColumn())->toBe('created_at');
    expect($table->getDefaultSortDirection())->toBe('desc');
});
