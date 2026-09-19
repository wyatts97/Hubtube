<?php

use App\Filament\Pages\MediaLibrary;
use App\Services\Media\MediaCompressService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    asAdmin();
});

test('compress smoke: service splits and page queues', function () {
    Storage::disk('public')->put('media/clip.mp4', str_repeat('x', 100));
    Storage::disk('public')->put('media/photo.jpg', 'x');
    app(App\Services\Media\MediaIndexService::class)->indexAll();

    $s = app(MediaCompressService::class);
    [$ok, $refused] = $s->splitTargets(['media/clip.mp4', 'media/photo.jpg']);
    expect($ok)->toBe(['media/clip.mp4']);
    expect($refused)->toHaveCount(1);
    expect($s->targetPathFor('media/clip.mp4', 'h265'))->toBe('media/clip.h265.mp4');
    expect($s->buildCommand('/tmp/in.mp4', '/tmp/out.mp4', 'h265', 'balanced'))->toContain('libx265');

    // Explorer shortcuts
    $c = Livewire::test(MediaLibrary::class)->call('largestFirst');
    expect($c->get('searchScope'))->toBe('subtree')
        ->and($c->get('typeFilter'))->toBe('video')
        ->and($c->get('sortBy'))->toBe('size');

    $c->call('folderView');
    expect($c->get('searchScope'))->toBe('folder');

    // Compress modal opens for videos, refuses images alone
    Livewire::test(MediaLibrary::class)
        ->call('startCompress', ['media/clip.mp4'])
        ->assertSet('showCompressModal', true)
        ->assertSet('compressTargets', ['media/clip.mp4']);

    Livewire::test(MediaLibrary::class)
        ->call('startCompress', ['media/photo.jpg'])
        ->assertSet('showCompressModal', false);

    // Page still renders (blade with new toolbar/modal)
    Livewire::test(MediaLibrary::class)->assertOk()->assertSee('Largest');

    // Every video row carries its own Compress button — no Alpine needed.
    $flat = Livewire::test(MediaLibrary::class)->call('setViewMode', 'flat');
    expect($flat->html())->toContain('Compress');

    // Selecting a file in flat mode shows the server-side action strip.
    $strip = Livewire::test(MediaLibrary::class)
        ->call('setViewMode', 'flat')
        ->call('selectFile', 'media/clip.mp4');
    expect($strip->html())->toContain('clip.mp4')->toContain('Compress');
});
