<?php

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

/*
| Password reset and social login had no tests at all.
*/

test('a valid reset token sets the new password', function () {
    $user = User::factory()->create(['email' => 'reset@example.test']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => 'reset@example.test',
        'password' => 'N3w-long-password!',
        'password_confirmation' => 'N3w-long-password!',
    ])->assertRedirect(route('login'));

    expect(Hash::check('N3w-long-password!', $user->fresh()->password))->toBeTrue();
});

test('a wrong reset token changes nothing', function () {
    $user = User::factory()->create(['email' => 'reset2@example.test']);
    $before = $user->password;

    $this->post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => 'reset2@example.test',
        'password' => 'N3w-long-password!',
        'password_confirmation' => 'N3w-long-password!',
    ])->assertSessionHasErrors('email');

    expect($user->fresh()->password)->toBe($before);
});

function fakeSocialUser(string $id, ?string $email): void
{
    $socialUser = (new SocialiteUser)->map([
        'id' => $id,
        'name' => 'Social Person',
        'email' => $email,
        'avatar' => null,
    ]);

    Socialite::shouldReceive('driver->user')->andReturn($socialUser);
}

test('a disabled provider is refused', function () {
    $this->get('/auth/google/callback')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('a first social login creates an account and signs in', function () {
    Setting::set('social_login_google_enabled', '1', 'social', 'boolean');
    fakeSocialUser('g-123', 'new-social@example.test');

    $this->get('/auth/google/callback')->assertRedirect(route('home'));

    $user = User::where('email', 'new-social@example.test')->first();
    expect($user)->not->toBeNull()
        ->and(SocialAccount::where('provider_id', 'g-123')->value('user_id'))->toBe($user->id);
    $this->assertAuthenticatedAs($user);
});

test('a returning social login signs into the linked account', function () {
    Setting::set('social_login_google_enabled', '1', 'social', 'boolean');
    $user = User::factory()->create();
    SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'g-456']);
    fakeSocialUser('g-456', 'someone-else@example.test');

    $this->get('/auth/google/callback')->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});
