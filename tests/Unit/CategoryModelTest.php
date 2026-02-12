<?php

use App\Models\Category;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| Category Model — Scopes, Relationships
|--------------------------------------------------------------------------
*/

test('category active scope filters correctly', function () {
    Category::factory()->create(['is_active' => true]);
    Category::factory()->create(['is_active' => false]);

    expect(Category::active()->count())->toBe(1);
});

test('category parentCategories scope filters correctly', function () {
    $parent = Category::factory()->create(['parent_id' => null]);
    Category::factory()->create(['parent_id' => $parent->id]);

    expect(Category::parentCategories()->count())->toBe(1);
});

test('category has many videos', function () {
    $category = Category::factory()->create();
    Video::factory()->count(3)->create(['category_id' => $category->id]);

    expect($category->videos()->count())->toBe(3);
});

test('category has children relationship', function () {
    $parent = Category::factory()->create();
    Category::factory()->count(2)->create(['parent_id' => $parent->id]);

    expect($parent->children()->count())->toBe(2);
});

test('category has parent relationship', function () {
    $parent = Category::factory()->create();
    $child = Category::factory()->create(['parent_id' => $parent->id]);

    expect($child->parent->id)->toBe($parent->id);
});
