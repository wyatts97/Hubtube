<?php

use App\Models\Category;
use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Image Slugs — SEO-friendly URLs
|--------------------------------------------------------------------------
*/

test('image show page resolves by slug', function () {
    $image = Image::factory()->create(['title' => 'Sunset Beach', 'slug' => 'sunset-beach']);

    $this->get('/image/sunset-beach')->assertStatus(200);
});

test('old uuid URLs no longer resolve', function () {
    $image = Image::factory()->create();

    $this->get("/image/{$image->uuid}")->assertNotFound();
});

test('upload generates a slug from the title', function () {
    Storage::fake('public');
    $user = asUser();
    $category = Category::factory()->create();

    $this->actingAs($user)->post('/image-upload', [
        'image_file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        'title' => 'Sunset Beach',
        'privacy' => 'public',
        'category_id' => $category->id,
    ])->assertRedirect('/image/sunset-beach');

    $this->assertDatabaseHas('images', [
        'title' => 'Sunset Beach',
        'slug' => 'sunset-beach',
    ]);
});

test('duplicate titles get a numeric suffix', function () {
    Storage::fake('public');
    $user = asUser();
    $category = Category::factory()->create();

    foreach (['sunset-beach', 'sunset-beach-2'] as $expectedSlug) {
        $this->actingAs($user)->post('/image-upload', [
            'image_file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
            'title' => 'Sunset Beach',
            'privacy' => 'public',
            'category_id' => $category->id,
        ])->assertRedirect("/image/{$expectedSlug}");
    }

    expect(Image::where('title', 'Sunset Beach')->pluck('slug')->all())
        ->toBe(['sunset-beach', 'sunset-beach-2']);
});

test('non-latin titles fall back to a random slug', function () {
    Storage::fake('public');
    $user = asUser();
    $category = Category::factory()->create();

    $this->actingAs($user)->post('/image-upload', [
        'image_file' => UploadedFile::fake()->image('photo.jpg', 800, 600),
        'title' => '日本語のタイトル',
        'privacy' => 'public',
        'category_id' => $category->id,
    ])->assertRedirect();

    $image = Image::latest('id')->first();
    expect($image->slug)->toMatch('/^image-[a-z0-9]{6}$/');
});
