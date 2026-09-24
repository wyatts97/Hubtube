<?php

use App\Models\Category;
use App\Models\Playlist;
use App\Models\User;
use App\Models\Video;
use App\Services\VideoService;
use Illuminate\Http\UploadedFile;

test('custom thumbnail extension comes from its content, not its name', function () {
    $video = Video::factory()->create();
    $jpeg = UploadedFile::fake()->image('real.jpg');
    $disguised = new UploadedFile($jpeg->getRealPath(), 'shell.php', 'image/jpeg', null, true);

    app(VideoService::class)->update($video, ['thumbnail' => $disguised]);

    expect($video->fresh()->thumbnail)->toEndWith('.jpg')->not->toContain('.php');
});

test('public playlist hides videos made private or rejected', function () {
    $playlist = Playlist::factory()->create(['privacy' => 'public']);
    $visible = Video::factory()->create(['title' => 'Visible Clip']);
    $private = Video::factory()->private()->create(['title' => 'Private Clip']);
    $rejected = Video::factory()->create(['title' => 'Rejected Clip', 'is_approved' => false]);
    $playlist->videos()->attach([$visible->id => ['position' => 1], $private->id => ['position' => 2], $rejected->id => ['position' => 3]]);

    $this->get(route('playlists.show', $playlist->slug))
        ->assertOk()
        ->assertInertia(function ($page) {
            $titles = collect($page->toArray()['props']['playlist']['videos'])->pluck('title');
            expect($titles->all())->toBe(['Visible Clip']);
        });
});

test('cannot like, comment on or download a video pending moderation', function () {
    $video = Video::factory()->create(['is_approved' => false]);
    $viewer = User::factory()->create();
    $viewer->forceFill(['is_pro' => true])->save();
    asUser($viewer);

    $this->postJson(route('videos.like', $video))->assertNotFound();
    $this->postJson(route('comments.store', $video), ['content' => 'hi'])->assertNotFound();
    $this->get(route('videos.download', $video))->assertForbidden();
});

test('changing email requires the current password and resets verification', function () {
    $user = User::factory()->create(['email' => 'old@example.test', 'password' => 'secret-pass-1']);
    asUser($user);

    $this->put('/settings/profile', ['username' => $user->username, 'email' => 'new@example.test'])
        ->assertSessionHasErrors('current_password');
    expect($user->fresh()->email)->toBe('old@example.test');

    $this->put('/settings/profile', [
        'username' => $user->username,
        'email' => 'new@example.test',
        'current_password' => 'secret-pass-1',
    ])->assertSessionHasNoErrors();

    expect($user->fresh())
        ->email->toBe('new@example.test')
        ->email_verified_at->toBeNull();
});

test('non-pro users cannot bulk edit video categories', function () {
    $user = asUser();
    $video = Video::factory()->create(['user_id' => $user->id]);
    $original = $video->category_id;

    $this->post(route('studio.videos.bulk'), [
        'action' => 'category',
        'video_ids' => [$video->id],
        'category_id' => Category::factory()->create()->id,
    ])->assertForbidden();

    expect($video->fresh()->category_id)->toBe($original);
});
