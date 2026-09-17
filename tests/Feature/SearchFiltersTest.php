<?php

use App\Models\Hashtag;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Search filters and hashtag results
|--------------------------------------------------------------------------
|
| Two bugs on the search page: paging dropped every filter, and hashtag results
| were plain cards with nowhere to click. Both fixes are in Search.vue, so what
| is asserted here is the server side of the contract plus the one structural
| invariant that kept paging honest — every navigation on that page goes
| through a single helper that always includes the filter state.
|
*/

test('the filter state survives a filtered, paged search', function () {
    Video::factory()->count(30)->create(['duration' => 120, 'title' => 'Short Clip']);

    $response = $this->get('/search?q=Clip&duration=short&sort=popular&page=2');
    $page = inertiaPagePayload($response);

    expect($page['props']['filters']['duration'])->toBe('short')
        ->and($page['props']['filters']['sort'])->toBe('popular')
        ->and($page['props']['results']['current_page'])->toBe(2)
        // withQueryString() keeps the filters on the paginator's own links.
        ->and($page['props']['results']['next_page_url'] ?? $page['props']['results']['prev_page_url'])
        ->toContain('duration=short');
});

test('a filter that excludes everything returns nothing rather than ignoring the filter', function () {
    Video::factory()->create(['title' => 'Long Documentary', 'duration' => 4000]);

    $page = inertiaPagePayload($this->get('/search?q=Documentary&duration=short'));

    expect($page['props']['results']['data'])->toBeEmpty();
});

test('hashtag search returns the names the page links to', function () {
    Hashtag::factory()->create(['name' => 'timelapse', 'slug' => 'timelapse', 'usage_count' => 4]);

    $page = inertiaPagePayload($this->get('/search?q=timelapse&type=hashtags'));

    expect($page['props']['type'])->toBe('hashtags')
        ->and($page['props']['results']['data'][0]['name'])->toBe('timelapse');
});

test('the tag page a hashtag result points at exists', function () {
    Hashtag::factory()->create(['name' => 'timelapse', 'slug' => 'timelapse']);
    Video::factory()->create(['tags' => ['timelapse']]);

    $this->get('/tag/timelapse')->assertOk();
});

/*
 * A structural guard rather than a behavioural one: goToPage, submitSearch and
 * switchTab each used to build their own parameter list, and the paging one
 * left the filters out. They now share visitSearch(), which is the only place
 * in the file that navigates — if a fourth navigation is added inline, this
 * fails and points at the reason.
 */
test('every navigation on the search page goes through one helper', function () {
    $source = file_get_contents(resource_path('js/Pages/Search.vue'));

    expect(substr_count($source, 'router.get('))->toBe(1)
        ->and($source)->toContain('const visitSearch =')
        // And the helper carries the filter state.
        ->and($source)->toContain('activeFilters.value');
});

test('hashtag results are links to the tag page', function () {
    $source = file_get_contents(resource_path('js/Pages/Search.vue'));

    expect($source)->toContain('/tag/${encodeURIComponent(hashtag.name)}');
});
