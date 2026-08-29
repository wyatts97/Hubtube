<?php

use App\Models\Setting;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Admin Authorization — super-admin tier and 2FA enforcement
|--------------------------------------------------------------------------
|
| Previously `is_admin` was the only gate on the panel, so any admin could
| reach the settings pages that build ffmpeg commands, read and write payment
| credentials, and grant `is_admin` to any account.
|
*/

dataset('superAdminOnlyPages', [
    'site settings' => [App\Filament\Pages\SiteSettings::class],
    'payment settings' => [App\Filament\Pages\PaymentSettings::class],
    'storage settings' => [App\Filament\Pages\StorageSettings::class],
    'integration settings' => [App\Filament\Pages\IntegrationSettings::class],
    'archive importer' => [App\Filament\Pages\ArchiveImporter::class],
]);

test('a plain admin cannot reach super-admin-only pages', function (string $page) {
    asUser(User::factory()->plainAdmin()->create());

    $this->get($page::getUrl())->assertStatus(403);
})->with('superAdminOnlyPages');

test('a super admin can reach super-admin-only pages', function (string $page) {
    asAdmin();

    $this->get($page::getUrl())->assertStatus(200);
})->with('superAdminOnlyPages');

test('a plain admin cannot reach user management', function () {
    asUser(User::factory()->plainAdmin()->create());

    $this->get(App\Filament\Resources\UserResource::getUrl('index'))->assertStatus(403);
});

test('a plain admin can still reach the dashboard and moderate content', function () {
    asUser(User::factory()->plainAdmin()->create());

    $this->get('/admin')->assertStatus(200);
    $this->get(App\Filament\Resources\VideoResource::getUrl('index'))->assertStatus(200);
});

test('isSuperAdmin requires both flags', function () {
    expect(User::factory()->make(['is_admin' => false, 'is_super_admin' => true])->isSuperAdmin())->toBeFalse();
    expect(User::factory()->make(['is_admin' => true, 'is_super_admin' => false])->isSuperAdmin())->toBeFalse();
    expect(User::factory()->make(['is_admin' => true, 'is_super_admin' => true])->isSuperAdmin())->toBeTrue();
});

test('admins without 2FA are redirected to setup when the setting is enabled', function () {
    Setting::set('admin_require_2fa', '1', 'general', 'boolean');

    asAdmin();

    $this->get('/admin')->assertRedirect(route('settings.two-factor.status'));
});

test('the 2FA requirement is off unless explicitly enabled', function () {
    asAdmin();

    $this->get('/admin')->assertStatus(200);
});

test('non-admins are unaffected by the admin 2FA requirement', function () {
    Setting::set('admin_require_2fa', '1', 'general', 'boolean');

    asUser();

    // Still just a normal 403 from the panel, not a 2FA redirect.
    $this->get('/admin')->assertStatus(403);
});
