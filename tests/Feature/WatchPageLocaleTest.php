<?php

use App\Models\Translation;
use App\Models\Video;

test('a cached translated title is sent with the watch page', function () {
    enableLocales(['en', 'es']);
    $video = Video::factory()->create(['title' => 'Original Title', 'description' => 'Original body']);
    foreach (['title' => 'Título Traducido', 'description' => 'Cuerpo traducido'] as $field => $value) {
        Translation::create([
            'translatable_type' => Video::class,
            'translatable_id' => $video->id,
            'field' => $field,
            'locale' => 'es',
            'value' => $value,
        ]);
    }

    $this->get('/es/'.$video->slug)
        ->assertOk()
        ->assertInertia(function ($page) {
            $props = $page->toArray()['props'];
            expect($props['translatedTitle'])->toBe('Título Traducido')
                ->and($props['translatedDescription'])->toBe('Cuerpo traducido')
                ->and($props['seo']['title'])->toContain('Título Traducido');
        });
});

test('an untranslated watch page leaves translation to the browser', function () {
    enableLocales(['en', 'es']);
    $video = Video::factory()->create(['title' => 'Only English']);

    $this->get('/es/'.$video->slug)
        ->assertOk()
        ->assertInertia(fn ($page) => expect($page->toArray()['props']['translatedTitle'])->toBeNull());
});

test('video schema is never family friendly and carries no self-rating', function () {
    $video = Video::factory()->create(['age_restricted' => false, 'likes_count' => 10, 'dislikes_count' => 2]);

    $this->get('/'.$video->slug)
        ->assertOk()
        ->assertInertia(function ($page) {
            $schema = collect($page->toArray()['props']['seo']['schema'])->firstWhere('@type', 'VideoObject');
            expect($schema['isFamilyFriendly'])->toBeFalse()
                ->and($schema)->not->toHaveKey('aggregateRating');
        });
});
