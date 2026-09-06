<?php

use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Resource table empty states
|--------------------------------------------------------------------------
|
| Filament's default empty state is a bare "No records found". Every resource
| list table sets its own icon, heading and description instead, and the ones
| an admin can actually create a record for also offer a create button.
|
| The icon is always the resource's own $navigationIcon, so the empty state
| reads as the same object the sidebar entry points at.
|
*/

test('every resource list table defines an empty state', function () {
    asAdmin();

    $missing = [];

    foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
        if (! str_contains($resource, 'App')) {
            continue;
        }

        $page = $resource::getPages()['index'] ?? null;
        if (! $page) {
            continue;
        }

        $table = Livewire::test($page->getPage())->instance()->getTable();

        $name = class_basename($resource);

        if (blank($table->getEmptyStateHeading())) {
            $missing[] = "{$name}: heading";
        }

        if (blank($table->getEmptyStateIcon())) {
            $missing[] = "{$name}: icon";
        }

        if (blank($table->getEmptyStateDescription())) {
            $missing[] = "{$name}: description";
        }
    }

    expect($missing)->toBe([]);
});

test('the empty state icon matches the resource navigation icon', function () {
    asAdmin();

    $mismatched = [];

    foreach (Filament::getCurrentOrDefaultPanel()->getResources() as $resource) {
        if (! str_contains($resource, 'App')) {
            continue;
        }

        $page = $resource::getPages()['index'] ?? null;
        if (! $page) {
            continue;
        }

        $table = Livewire::test($page->getPage())->instance()->getTable();
        $navIcon = $resource::getNavigationIcon();

        if (is_string($navIcon) && $table->getEmptyStateIcon() !== $navIcon) {
            $mismatched[] = class_basename($resource)
                .': '.$table->getEmptyStateIcon().' !== '.$navIcon;
        }
    }

    expect($mismatched)->toBe([]);
});
