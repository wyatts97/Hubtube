<?php

use App\Providers\Filament\AdminPanelProvider;

/*
|--------------------------------------------------------------------------
| Admin sidebar resizer
|--------------------------------------------------------------------------
|
| The drag-to-resize sidebar used to be martin6363/filament-sidebar-resize,
| whose published blade had already been rewritten in this repo. It is now
| owned here as two render hooks: a pre-paint width restore in the head and
| the pointer-driven handle at the end of the body.
|
| These pin the parts that are easy to break silently — a hook that stops
| rendering shows up as "the handle just isn't there any more", with nothing
| in the logs.
|
*/

it('renders both sidebar resize hooks into the panel', function () {
    asAdmin();

    $html = $this->get('/admin')->assertStatus(200)->getContent();

    expect($html)
        ->toContain(AdminPanelProvider::SIDEBAR_WIDTH_STORAGE_KEY)
        ->toContain('fi-sidebar-resize-handle')
        // The pre-paint restore has to reach the head, before the sidebar is
        // laid out, or the width visibly snaps on every load.
        ->toContain('--sidebar-width');

    $head = substr($html, 0, strpos($html, '</head>'));

    expect($head)->toContain(AdminPanelProvider::SIDEBAR_WIDTH_STORAGE_KEY);
});

it('clamps the pre-paint width to the same bounds the drag handler uses', function () {
    asAdmin();

    $html = $this->get('/admin')->getContent();

    // Both partials read the bounds from the provider constants; if one is
    // ever hardcoded, a saved width could restore outside the draggable range
    // and be unrecoverable without clearing storage.
    expect(substr_count($html, (string) AdminPanelProvider::SIDEBAR_MIN_WIDTH))->toBeGreaterThanOrEqual(2)
        ->and(substr_count($html, (string) AdminPanelProvider::SIDEBAR_MAX_WIDTH))->toBeGreaterThanOrEqual(2)
        ->and(AdminPanelProvider::SIDEBAR_DEFAULT_WIDTH)
        ->toBeGreaterThanOrEqual(AdminPanelProvider::SIDEBAR_MIN_WIDTH)
        ->toBeLessThanOrEqual(AdminPanelProvider::SIDEBAR_MAX_WIDTH);
});

it('no longer depends on the third-party sidebar resize package', function () {
    $composer = json_decode(file_get_contents(base_path('composer.json')), true);

    expect($composer['require'])->not->toHaveKey('martin6363/filament-sidebar-resize')
        ->and(is_dir(base_path('resources/views/vendor/sidebar-resize')))->toBeFalse();
});

it('keeps every navigation group iconed so the collapsed flyout still renders', function () {
    // Filament only renders the collapsed-sidebar dropdown listing a group's
    // items when the group has BOTH a label and an icon (see the sidebar.group
    // blade's $hasDropdown). A group added without an icon would silently
    // become unreachable once the sidebar is collapsed.
    asAdmin();

    $groups = filament()->getPanel('admin')->getNavigationGroups();

    expect($groups)->not->toBeEmpty();

    foreach ($groups as $group) {
        expect($group->getIcon())->not->toBeNull(
            'Navigation group [' . $group->getLabel() . '] has no icon.'
        );
    }
});
