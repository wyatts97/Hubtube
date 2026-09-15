<?php

use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| "Require Email Verification" — enforced on uploads and comments
|--------------------------------------------------------------------------
*/

function requireVerification(bool $on): void
{
    Setting::set('email_verification_required', $on, 'general', 'boolean');
}

test('unverified users are sent to verify before uploading when required', function () {
    requireVerification(true);
    asUser(User::factory()->unverified()->create());

    $this->get('/upload')->assertRedirect(route('verification.notice'));
});

test('chunk uploads from unverified users get a JSON 403 when required', function () {
    requireVerification(true);
    asUser(User::factory()->unverified()->create());

    $this->postJson('/upload/chunk', [])
        ->assertForbidden()
        ->assertJson(['verification_required' => true]);
});

test('unverified users cannot comment when required', function () {
    requireVerification(true);
    $video = Video::factory()->create();
    asUser(User::factory()->unverified()->create());

    $this->post("/videos/{$video->id}/comments", ['content' => 'Hello there'])
        ->assertRedirect(route('verification.notice'));

    expect($video->comments()->count())->toBe(0);
});

test('verified users are unaffected when required', function () {
    requireVerification(true);
    asUser();

    $this->get('/upload')->assertOk();
});

test('admins are exempt so an unverified installer account is not locked out', function () {
    requireVerification(true);
    asAdmin(User::factory()->admin()->unverified()->create());

    $this->get('/upload')->assertOk();
});

test('nothing is enforced while the setting is off', function () {
    requireVerification(false);
    asUser(User::factory()->unverified()->create());

    $this->get('/upload')->assertOk();
});

test('nothing is enforced on an install that never saved the setting', function () {
    Setting::where('key', 'email_verification_required')->delete();
    Setting::clearCache();
    asUser(User::factory()->unverified()->create());

    $this->get('/upload')->assertOk();
});
