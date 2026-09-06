<?php

use App\Filament\Resources\CommentResource\Pages\ListComments;
use App\Filament\Resources\VideoResource\Pages\ListVideos;
use App\Filament\Widgets\RecentSignupsTable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin table state persistence
|--------------------------------------------------------------------------
|
| AdminPanelProvider::boot() turns on filter/sort/search session persistence
| for every resource list table via Table::configureUsing, so admins don't lose
| their filters when they open a record and come back.
|
| ComponentManager fires that callback for EVERY table in the process, so the
| callback is guarded to this panel's own ListRecords pages. Without the guard,
| the dashboard table widgets — which are ->paginated(false) with a searchable
| column — would each get a sticky session search and silently read empty on
| every later dashboard load. These tests pin both halves of that behaviour.
|
*/

test('resource list tables persist filter, sort and search state', function (string $page) {
    asAdmin();

    $table = Livewire::test($page)->instance()->getTable();

    expect($table->persistsFiltersInSession())->toBeTrue();
    expect($table->persistsSortInSession())->toBeTrue();
    expect($table->persistsSearchInSession())->toBeTrue();
})->with([
    'videos' => [ListVideos::class],
    'comments' => [ListComments::class],
]);

test('dashboard table widgets do not persist table state', function () {
    asAdmin();

    $table = Livewire::test(RecentSignupsTable::class)->instance()->getTable();

    expect($table->persistsFiltersInSession())->toBeFalse();
    expect($table->persistsSortInSession())->toBeFalse();
    expect($table->persistsSearchInSession())->toBeFalse();
});
