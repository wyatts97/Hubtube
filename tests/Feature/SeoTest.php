<?php

use App\Models\Category;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| SEO — Sitemap, Robots.txt, Meta Tags
|--------------------------------------------------------------------------
*/

test('sitemap.xml returns valid XML', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/xml; charset=UTF-8');
});

test('robots.txt returns plain text', function () {
    $response = $this->get('/robots.txt');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->getContent())->toContain('User-agent');
});

test('homepage returns Inertia response with seo prop', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('video page returns Inertia response with seo prop', function () {
    $video = Video::factory()->create();
    $response = $this->get("/{$video->slug}");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('category page returns Inertia response with seo prop', function () {
    $category = Category::factory()->create();
    $response = $this->get("/category/{$category->slug}");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('channel page returns Inertia response with seo prop', function () {
    $user = User::factory()->create();
    $response = $this->get("/channel/{$user->username}");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('locale-prefixed homepage loads', function () {
    $response = $this->get('/es/');
    $response->assertStatus(200);
});

test('locale-prefixed trending loads', function () {
    $response = $this->get('/fr/trending');
    $response->assertStatus(200);
});
