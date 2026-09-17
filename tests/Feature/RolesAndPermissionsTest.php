<?php

use App\Filament\Pages\MediaLibrary;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\CommentResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\VideoResource;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use App\Support\Permissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Roles: a moderator who is not a super-admin
|--------------------------------------------------------------------------
|
| Roles narrow what a plain admin can reach in the panel. Two bypasses keep
| the introduction safe: super-admins pass everything, and an admin with no
| roles keeps the access they had before roles existed.
|
*/

function withRole(string $role, array $attributes = []): User
{
    $user = User::factory()->plainAdmin()->create($attributes);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $user = $user->fresh();
    test()->actingAs($user);

    return $user;
}

beforeEach(function () {
    test()->artisan('hubtube:sync-roles')->assertSuccessful();
});

test('the command creates the seeded roles and permissions', function () {
    expect(Role::pluck('name')->all())
        ->toContain('moderator', 'editor', 'uploader', 'trusted_uploader');

    expect(Permission::pluck('name')->all())
        ->toContain('view_any_video', 'delete_comment', 'bypass_video_approval');

    // Re-running changes nothing and keeps admin edits.
    $moderator = Role::findByName('moderator');
    $moderator->revokePermissionTo('delete_comment');
    $this->artisan('hubtube:sync-roles')->assertSuccessful();

    expect(Role::findByName('moderator')->hasPermissionTo('delete_comment'))->toBeFalse();

    $this->artisan('hubtube:sync-roles', ['--reset' => true])->assertSuccessful();
    expect(Role::findByName('moderator')->fresh()->hasPermissionTo('delete_comment'))->toBeTrue();
});

test('a moderator reaches moderation but not site configuration', function () {
    withRole('moderator');

    expect(VideoResource::canViewAny())->toBeTrue()
        ->and(CommentResource::canViewAny())->toBeTrue()
        ->and(CommentResource::canDeleteAny())->toBeTrue()
        // Taxonomy, users and the media library are not moderation.
        ->and(CategoryResource::canViewAny())->toBeFalse()
        ->and(UserResource::canAccess())->toBeFalse()
        ->and(MediaLibrary::canAccess())->toBeFalse();
});

test('a moderator cannot create or delete videos outright', function () {
    withRole('moderator');

    expect(VideoResource::canCreate())->toBeFalse()
        ->and(VideoResource::canDeleteAny())->toBeFalse();
});

test('an editor also manages taxonomy and pages', function () {
    withRole('editor');

    expect(CategoryResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeTrue()
        ->and(VideoResource::canCreate())->toBeTrue()
        ->and(MediaLibrary::canAccess())->toBeTrue()
        ->and(UserResource::canAccess())->toBeFalse();
});

test('an admin with no roles keeps the access they had before', function () {
    asAdmin(User::factory()->plainAdmin()->create());

    expect(VideoResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeTrue()
        ->and(MediaLibrary::canAccess())->toBeTrue()
        // Super-admin areas were never theirs.
        ->and(UserResource::canAccess())->toBeFalse();
});

test('a super admin passes every permission check', function () {
    asAdmin();

    expect(VideoResource::canViewAny())->toBeTrue()
        ->and(CategoryResource::canCreate())->toBeTrue()
        ->and(UserResource::canAccess())->toBeTrue();
});

test('the permission bypass does not satisfy unrelated gates', function () {
    Setting::set('min_withdrawal', 50, 'general', 'integer');
    $admin = asAdmin(User::factory()->plainAdmin()->create(['wallet_balance' => 0]));

    // 'withdraw' is a balance check, not a permission.
    expect($admin->can('withdraw'))->toBeFalse();
});

test('a non-admin with a moderator role gets nothing in the panel', function () {
    $user = User::factory()->create();
    $user->assignRole('moderator');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    asUser($user->fresh());

    expect(VideoResource::canViewAny())->toBeFalse();
    $this->get('/admin')->assertForbidden();
});

test('a trusted uploader skips the moderation queue and the daily cap', function () {
    Setting::set('video_auto_approve', false, 'general', 'boolean');
    Setting::set('max_daily_uploads_free', 1, 'general', 'integer');

    $user = User::factory()->create();
    $user->assignRole('trusted_uploader');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->fresh();

    Video::factory()->count(3)->create(['user_id' => $user->id]);
    expect($user->canUpload())->toBeTrue();

    $video = Video::factory()->create(['user_id' => $user->id, 'is_approved' => false, 'status' => 'processing']);
    app(VideoService::class)->markAsProcessed($video, ['original']);

    expect($video->fresh()->is_approved)->toBeTrue();
});

test('an ordinary uploader is still held to the cap and the queue', function () {
    Setting::set('video_auto_approve', false, 'general', 'boolean');
    Setting::set('max_daily_uploads_free', 1, 'general', 'integer');

    $user = User::factory()->create();
    $user->assignRole('uploader');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user = $user->fresh();

    Video::factory()->create(['user_id' => $user->id]);
    expect($user->canUpload())->toBeFalse();

    $video = Video::factory()->create(['user_id' => $user->id, 'is_approved' => false, 'status' => 'processing']);
    app(VideoService::class)->markAsProcessed($video, ['original']);

    expect($video->fresh()->is_approved)->toBeFalse();
});

test('permission names follow Shield\'s convention', function () {
    expect(Permissions::forResource('dmca_request', 'view_any'))->toBe('view_any_dmca_request')
        ->and(Permissions::resourceKey(Video::class))->toBe('video')
        ->and(Permissions::isManaged('view_any_video'))->toBeTrue()
        ->and(Permissions::isManaged('withdraw'))->toBeFalse();
});
