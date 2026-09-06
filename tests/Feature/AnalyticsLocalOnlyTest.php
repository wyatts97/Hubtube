<?php

use App\Models\Setting;

/*
|--------------------------------------------------------------------------
| Analytics page is local-only
|--------------------------------------------------------------------------
|
| The Google Analytics *reporting* integration (Data API widgets, the service
| account form) was removed. The Google Analytics *tracking tag* is a separate
| feature and must survive: it is a different setting key, rendered on the
| public site, and unrelated to the admin widgets.
|
*/

test('the analytics page loads without google analytics', function () {
    asAdmin();

    $html = $this->get(App\Filament\Pages\Analytics::getUrl())
        ->assertStatus(200)
        ->getContent();

    expect($html)->not->toContain('Google Analytics');
    expect($html)->not->toContain('GA4 Property ID');
});

test('the removed google analytics packages are gone', function () {
    expect(class_exists(\BezhanSalleh\GoogleAnalytics\GoogleAnalyticsPlugin::class))->toBeFalse();
    expect(class_exists(\Spatie\Analytics\Analytics::class))->toBeFalse();
    expect(file_exists(config_path('analytics.php')))->toBeFalse();
    expect(file_exists(config_path('google-analytics.php')))->toBeFalse();
});

test('the public tracking tag still renders when a measurement id is set', function () {
    Setting::set('google_analytics_id', 'G-TESTID123');

    $html = $this->get('/')->assertStatus(200)->getContent();

    expect($html)->toContain('googletagmanager.com/gtag/js?id=G-TESTID123');
});

test('the public tracking tag is absent when no measurement id is set', function () {
    Setting::set('google_analytics_id', '');

    $html = $this->get('/')->assertStatus(200)->getContent();

    expect($html)->not->toContain('googletagmanager.com/gtag/js');
});
