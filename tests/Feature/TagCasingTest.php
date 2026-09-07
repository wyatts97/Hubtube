<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Tag casing
|--------------------------------------------------------------------------
|
| Tags are displayed uppercase everywhere but stored with their original
| casing, because the URL is /tag/{name}. That combination is only safe if the
| lookup is case-insensitive — otherwise two chips that render identically as
| "AMATEUR" would link to separate, incomplete result sets.
|
*/

function makeTaggedVideo(array $tags, string $title = 'Tagged'): Video
{
    return Video::factory()->create([
        'user_id' => User::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
        'tags' => $tags,
        'privacy' => 'public',
        'status' => 'processed',
        'is_approved' => true,
        'published_at' => now()->subDay(),
        'title' => $title,
    ]);
}

it('finds a tag regardless of the casing used in the url', function (string $requested) {
    makeTaggedVideo(['MixedCase']);

    $this->get("/tag/{$requested}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.total', 1));
})->with(['MixedCase', 'mixedcase', 'MIXEDCASE', 'mIxEdCaSe']);

it('does not treat one tag as a substring of another', function () {
    makeTaggedVideo(['hd'], 'Plain HD');
    makeTaggedVideo(['hd-remaster'], 'Remaster');

    // The stored JSON is ["hd-remaster"], so a naive LIKE '%hd%' would match it
    // for the "hd" tag. The quotes around the needle prevent that.
    $this->get('/tag/hd')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.total', 1));

    $this->get('/tag/hd-remaster')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.total', 1));
});

it('collects videos that stored the same tag with different casing', function () {
    // This is the fragmentation the case-insensitive lookup exists to fix.
    makeTaggedVideo(['Amateur'], 'Capitalised');
    makeTaggedVideo(['amateur'], 'Lowercase');
    makeTaggedVideo(['AMATEUR'], 'Shouty');

    $this->get('/tag/amateur')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.total', 3));
});

it('does not match a tag that merely shares a prefix', function () {
    makeTaggedVideo(['solo'], 'Solo');
    makeTaggedVideo(['solofemale'], 'Solo Female');

    $this->get('/tag/solo')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('videos.total', 1));
});
