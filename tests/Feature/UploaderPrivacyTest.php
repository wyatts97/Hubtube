<?php

use App\Models\User;
use App\Models\Video;

/*
| Videos are serialized with their uploader onto public pages. User::$hidden
| is what keeps the uploader's private columns out of those payloads.
*/

function privateUploaderVideo(): Video
{
    $user = User::factory()->create([
        'email' => 'uploader-private@example.test',
        'first_name' => 'Secretfirst',
        'wallet_balance' => 4321.55,
    ]);

    return Video::factory()->create(['user_id' => $user->id, 'title' => 'Privacy Probe Video']);
}

test('watch page does not leak uploader private fields', function () {
    $video = privateUploaderVideo();

    $this->get(route('videos.show', $video->slug))
        ->assertOk()
        ->assertInertia(function ($page) {
            $user = $page->toArray()['props']['video']['user'];

            expect($user)->toHaveKey('username')
                ->not->toHaveKey('email')
                ->not->toHaveKey('first_name')
                ->not->toHaveKey('wallet_balance')
                ->not->toHaveKey('is_admin')
                ->not->toHaveKey('settings');
        });
});

test('public pages never contain the uploader email', function () {
    $video = privateUploaderVideo();

    foreach ([route('home'), route('videos.embed', $video->slug), route('search', ['q' => 'Privacy Probe'])] as $url) {
        $this->get($url)->assertOk()->assertDontSee('uploader-private@example.test', false);
    }

    $this->getJson(route('videos.loadMore'))->assertOk()->assertDontSee('uploader-private@example.test', false);
});

test('admin user form still receives private fields', function () {
    $user = User::factory()->create(['email' => 'admin-form@example.test', 'wallet_balance' => 12.5]);

    expect($user->attributesForAdminForm())
        ->toHaveKey('email', 'admin-form@example.test')
        ->toHaveKey('wallet_balance')
        ->not->toHaveKey('password')
        ->not->toHaveKey('two_factor_secret');
});
