<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Public Routes — Guest Access
|--------------------------------------------------------------------------
| Verify all public-facing pages return 200 and render correctly.
| These are the routes visitors see without logging in.
*/

test('homepage loads successfully', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});

test('trending page loads successfully', function () {
    $response = $this->get('/trending');
    $response->assertStatus(200);
});

test('shorts page loads successfully', function () {
    $response = $this->get('/shorts');
    $response->assertStatus(200);
});

test('search page loads successfully', function () {
    $response = $this->get('/search');
    $response->assertStatus(200);
});

test('search page accepts query parameter', function () {
    $response = $this->get('/search?q=test');
    $response->assertStatus(200);
});

test('categories page loads successfully', function () {
    $response = $this->get('/categories');
    $response->assertStatus(200);
});

test('category page loads with valid category', function () {
    $category = Category::factory()->create();
    $response = $this->get("/category/{$category->slug}");
    $response->assertStatus(200);
});

test('video page loads with valid video', function () {
    $video = Video::factory()->create();
    $response = $this->get("/{$video->slug}");
    $response->assertStatus(200);
});

test('video page returns 404 for non-existent slug', function () {
    $response = $this->get('/this-video-does-not-exist-xyz');
    $response->assertStatus(404);
});

test('channel page loads with valid user', function () {
    $user = User::factory()->create();
    $response = $this->get("/channel/{$user->username}");
    $response->assertStatus(200);
});

test('channel videos tab loads', function () {
    $user = User::factory()->create();
    $response = $this->get("/channel/{$user->username}/videos");
    $response->assertStatus(200);
});

test('channel about tab loads', function () {
    $user = User::factory()->create();
    $response = $this->get("/channel/{$user->username}/about");
    $response->assertStatus(200);
});

test('contact page loads successfully', function () {
    $response = $this->get('/contact');
    $response->assertStatus(200);
});

test('live streams page no longer exists', function () {
    // Live streaming was removed in 2025_02_24_000001_drop_live_streaming_tables.
    // SitemapController still advertises /live — see SEOANDLANGUAGEREFACTOR.MD Phase 3.
    $this->get('/live')->assertStatus(404);
});

test('videos index page loads successfully', function () {
    $response = $this->get('/videos');
    $response->assertStatus(200);
});

test('offline page loads for PWA', function () {
    $response = $this->get('/offline');
    $response->assertStatus(200);
});

test('health check endpoint returns 200', function () {
    $response = $this->get('/up');
    $response->assertStatus(200);
});
