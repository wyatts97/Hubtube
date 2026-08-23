<?php

use App\Filament\Resources\ImageResource\Pages\CreateImage;
use App\Models\Category;
use App\Models\Image;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Admin Image Create — Filament create page regression
|--------------------------------------------------------------------------
|
| Covers the admin /admin/images/create flow that was failing with a
| BadMethodCallException because CreateImage called Image::withTrashed()
| on a model without SoftDeletes, and because required metadata
| (mime_type, width, height) was never populated before insert.
|
*/

test('admin can create an image and required metadata is persisted', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension is required to generate test images.');
    }

    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $uploader = User::factory()->create();
    $category = Category::factory()->create();

    Livewire::actingAs($admin)
        ->test(CreateImage::class)
        ->fillForm([
            'title' => 'Sunset Beach',
            'description' => 'A beautiful sunset',
            'user_id' => $uploader->id,
            'category_id' => $category->id,
            'privacy' => 'public',
            'is_approved' => true,
            'image_file' => UploadedFile::fake()->image('sunset.jpg', 800, 600),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $image = Image::where('title', 'Sunset Beach')->first();

    expect($image)->not->toBeNull()
        ->and($image->slug)->toBe('sunset-beach')
        ->and($image->file_path)->toContain('images/sunset-beach/')
        ->and($image->mime_type)->toBe('image/jpeg')
        ->and($image->width)->toBe(800)
        ->and($image->height)->toBe(600)
        ->and($image->file_size)->toBeGreaterThan(0)
        ->and($image->storage_disk)->toBe('public')
        ->and($image->is_animated)->toBeFalse()
        ->and($image->published_at)->not->toBeNull();

    Storage::disk('public')->assertExists($image->file_path);
});

test('duplicate titles get a numeric slug suffix without soft deletes', function () {
    if (! function_exists('imagecreatetruecolor')) {
        $this->markTestSkipped('GD extension is required to generate test images.');
    }

    Storage::fake('public');

    $admin = User::factory()->admin()->create();
    $uploader = User::factory()->create();
    $category = Category::factory()->create();

    foreach (['sunset-beach', 'sunset-beach-2'] as $expectedSlug) {
        Livewire::actingAs($admin)
            ->test(CreateImage::class)
            ->fillForm([
                'title' => 'Sunset Beach',
                'user_id' => $uploader->id,
                'category_id' => $category->id,
                'privacy' => 'public',
                'is_approved' => true,
                'image_file' => UploadedFile::fake()->image('sunset.jpg', 800, 600),
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(Image::where('title', 'Sunset Beach')->pluck('slug')->all())
        ->toBe(['sunset-beach', 'sunset-beach-2']);
});

test('slug uniqueness check does not call withTrashed on Image model', function () {
    // Regression: CreateImage::generateUniqueSlug previously called
    // Image::withTrashed(), which throws BadMethodCallException because
    // the Image model does not use SoftDeletes. Verify the query works.
    Image::factory()->create(['slug' => 'existing-slug']);

    $page = new ReflectionClass(CreateImage::class);
    $method = $page->getMethod('generateUniqueSlug');
    $method->setAccessible(true);

    // Should not throw BadMethodCallException
    $slug = $method->invoke(new CreateImage, 'existing-slug');
    expect($slug)->toBe('existing-slug-2');
});

test('non-admin user cannot access image create page', function () {
    asUser();

    $this->get('/admin/images/create')->assertStatus(403);
});
