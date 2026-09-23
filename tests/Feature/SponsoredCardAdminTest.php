<?php

use App\Filament\Resources\SponsoredCardResource\Pages\CreateSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages\EditSponsoredCard;
use App\Filament\Resources\SponsoredCardResource\Pages\ListSponsoredCards;
use App\Models\Setting;
use App\Models\SponsoredCard;
use Livewire\Livewire;

test('an html card needs only a name and ad code', function () {
    asAdmin();

    Livewire::test(CreateSponsoredCard::class)
        ->fillForm([
            'type' => 'html',
            'title' => 'Network zone',
            'html_code' => '<iframe src="https://ads.example.com"></iframe>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $card = SponsoredCard::sole();
    expect($card->type)->toBe('html')
        ->and($card->click_url)->toBeNull()
        ->and($card->target_pages)->toBeNull();
});

test('an image card still requires a link and an image', function () {
    asAdmin();

    Livewire::test(CreateSponsoredCard::class)
        ->fillForm(['type' => 'image', 'title' => 'Offer'])
        ->call('create')
        ->assertHasFormErrors(['click_url' => 'required', 'thumbnail_url' => 'required']);
});

test('a video card accepts a url instead of an upload', function () {
    asAdmin();

    Livewire::test(CreateSponsoredCard::class)
        ->fillForm([
            'type' => 'video',
            'title' => 'Clip',
            'click_url' => 'https://example.com',
            'video_url' => 'https://example.com/ad.mp4',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SponsoredCard::sole()->video_url)->toBe('https://example.com/ad.mp4');
});

test('the edit page loads an existing card', function () {
    asAdmin();
    $card = SponsoredCard::factory()->html()->create();

    Livewire::test(EditSponsoredCard::class, ['record' => $card->getRouteKey()])
        ->assertOk()
        ->assertFormSet(['type' => 'html', 'html_code' => $card->html_code]);
});

test('the frequency action saves the shared setting', function () {
    asAdmin();

    Livewire::test(ListSponsoredCards::class)
        ->callAction('frequency', data: ['frequency' => 12])
        ->assertHasNoActionErrors();

    expect((int) Setting::get('sponsored_card_frequency'))->toBe(12);
});

test('duplicating a card saves an inactive copy with fresh stats', function () {
    asAdmin();
    $card = SponsoredCard::factory()->create(['impressions_count' => 50]);

    Livewire::test(ListSponsoredCards::class)
        ->callTableAction('replicate', $card);

    $copy = SponsoredCard::whereKeyNot($card->id)->sole();
    expect($copy->is_active)->toBeFalse()
        ->and((int) $copy->impressions_count)->toBe(0)
        ->and($copy->title)->toEndWith('(copy)');
});
