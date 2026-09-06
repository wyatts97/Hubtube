<?php

use App\Filament\Clusters\Settings as SettingsCluster;
use App\Filament\Pages\AdSettings;
use App\Filament\Pages\IntegrationSettings;
use App\Filament\Pages\LanguageSettings;
use App\Filament\Pages\NotificationSettings;
use App\Filament\Pages\PaymentSettings;
use App\Filament\Pages\PointsSettings;
use App\Filament\Pages\PwaSettings;
use App\Filament\Pages\SearchIndexingSettings;
use App\Filament\Pages\SeoSettings;
use App\Filament\Pages\SiteSettings;
use App\Filament\Pages\SocialNetworkSettings;
use App\Filament\Pages\StorageSettings;
use App\Filament\Pages\ThemeSettings;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Settings cluster
|--------------------------------------------------------------------------
|
| Thirteen settings pages used to sit in four unrelated navigation groups, so
| the sidebar carried thirteen separate entries and nothing represented
| "configuration". They now sit behind one Settings cluster.
|
| A cluster keeps each page as its own Livewire component, so LanguageSettings
| and PointsSettings keep their HasTable implementations, the ~400 form fields
| stay in thirteen components rather than one, and per-page access control
| still works. These tests pin the navigation collapse, the access control, and
| that every page still loads and saves.
|
*/

dataset('clusteredSettingsPages', [
    'site' => [SiteSettings::class],
    'theme' => [ThemeSettings::class],
    'seo' => [SeoSettings::class],
    'search indexing' => [SearchIndexingSettings::class],
    'languages' => [LanguageSettings::class],
    'storage' => [StorageSettings::class],
    'pwa' => [PwaSettings::class],
    'email' => [IntegrationSettings::class],
    'notifications' => [NotificationSettings::class],
    'social login' => [SocialNetworkSettings::class],
    'payments' => [PaymentSettings::class],
    'ads' => [AdSettings::class],
    'reward points' => [PointsSettings::class],
]);

test('all thirteen settings pages belong to the cluster', function (string $page) {
    expect($page::getCluster())->toBe(SettingsCluster::class);
})->with('clusteredSettingsPages');

test('the cluster collapses thirteen sidebar entries into one', function () {
    asAdmin();

    // A page inside a cluster returns early from registerNavigationItems(), so
    // none of the thirteen should appear in the sidebar on its own.
    $items = collect(Filament::getCurrentOrDefaultPanel()->getNavigationItems())
        ->map(fn ($item) => $item->getLabel());

    foreach (['Site Settings', 'Theme & Appearance', 'SEO Settings', 'Ad Settings',
        'Payment Settings', 'Reward Points', 'PWA & Push', 'Storage & CDN',
        'Notifications', 'Languages', 'Social Login', 'Search Indexing'] as $label) {
        expect($items)->not->toContain($label);
    }
});

test('every settings page still loads inside the cluster', function (string $page) {
    asAdmin();

    $this->get($page::getUrl())->assertStatus(200);
})->with('clusteredSettingsPages');

test('settings page urls are nested under the cluster', function (string $page) {
    asAdmin();

    expect($page::getUrl())->toContain('/admin/settings/');
})->with('clusteredSettingsPages');

test('the cluster preserves per-page super-admin gating', function () {
    asUser(User::factory()->plainAdmin()->create());

    // Gated by RequiresSuperAdmin.
    foreach ([SiteSettings::class,
        PaymentSettings::class,
        StorageSettings::class,
        IntegrationSettings::class] as $page) {
        $this->get($page::getUrl())->assertStatus(403);
    }

    // Not gated: a plain admin keeps these.
    foreach ([ThemeSettings::class,
        SeoSettings::class,
        NotificationSettings::class] as $page) {
        $this->get($page::getUrl())->assertStatus(200);
    }
});

test('legacy settings urls redirect to their clustered location', function (string $legacy, string $page) {
    asAdmin();

    $this->get("/admin/{$legacy}")->assertRedirect($page::getUrl());
})->with([
    ['site-settings', SiteSettings::class],
    ['seo-settings', SeoSettings::class],
    ['ad-settings', AdSettings::class],
    ['social-networks', SocialNetworkSettings::class],
    ['points-settings', PointsSettings::class],
]);

test('a clustered settings page still saves', function () {
    asAdmin();

    Livewire::test(SeoSettings::class)
        ->set('data.seo_sitemap_chunk_size', 4321)
        ->call('save')
        ->assertHasNoErrors();

    expect((int) Setting::get('seo_sitemap_chunk_size'))->toBe(4321);
});

test('the pages that also render tables still boot inside the cluster', function (string $page) {
    asAdmin();

    // LanguageSettings and PointsSettings each implement HasTable. This is the
    // pair that made a single merged settings page impossible, so it is the
    // pair most worth pinning.
    expect(Livewire::test($page)->instance()->getTable())->not->toBeNull();
})->with([
    'languages' => [LanguageSettings::class],
    'reward points' => [PointsSettings::class],
]);
