<?php

use App\Models\Setting;
use App\Models\User;
use App\Services\RegistrationGuard;

/*
|--------------------------------------------------------------------------
| Registration controls: the switch, blocked domains, blocked IPs
|--------------------------------------------------------------------------
*/

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'username' => 'newcomer',
        'email' => 'newcomer@example.com',
        'password' => 'Sup3rSecret',
        'password_confirmation' => 'Sup3rSecret',
    ], $overrides);
}

beforeEach(function () {
    Setting::set('registration_enabled', true, 'general', 'boolean');
    Setting::set('block_disposable_emails', true, 'general', 'boolean');
    Setting::set('blocked_email_domains', [], 'general', 'array');
    Setting::set('blocked_ips', [], 'general', 'array');
});

test('the registration switch closes the form', function () {
    Setting::set('registration_enabled', false, 'general', 'boolean');

    $this->get('/register')->assertRedirect(route('login'));
    $this->post('/register', registerPayload())->assertRedirect(route('login'));

    expect(User::where('email', 'newcomer@example.com')->exists())->toBeFalse();
});

test('registration works while the switch is on', function () {
    $this->post('/register', registerPayload())->assertRedirect();

    expect(User::where('email', 'newcomer@example.com')->exists())->toBeTrue();
});

test('the switch is shared with the front end so the link can be hidden', function () {
    Setting::set('registration_enabled', false, 'general', 'boolean');

    $page = inertiaPagePayload($this->get('/login'));

    expect($page['props']['app']['registration_enabled'])->toBeFalse();
});

test('disposable email providers are refused', function () {
    $this->post('/register', registerPayload(['email' => 'burner@mailinator.com']))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

test('disposable addresses are allowed when the setting is off', function () {
    Setting::set('block_disposable_emails', false, 'general', 'boolean');

    $this->post('/register', registerPayload(['email' => 'burner@mailinator.com']))->assertRedirect();

    expect(User::where('email', 'burner@mailinator.com')->exists())->toBeTrue();
});

test('an admin can block extra domains', function () {
    Setting::set('blocked_email_domains', ['Rival.example'], 'general', 'array');

    // Matching is case-insensitive.
    $this->post('/register', registerPayload(['email' => 'someone@rival.example']))
        ->assertSessionHasErrors('email');

    $this->post('/register', registerPayload(['email' => 'someone@allowed.example']))->assertRedirect();
});

test('registration is refused from a blocked IP or range', function () {
    Setting::set('blocked_ips', ['127.0.0.1'], 'general', 'array');

    $this->get('/register')->assertRedirect(route('login'));
    $this->post('/register', registerPayload())->assertRedirect(route('login'));

    expect(User::count())->toBe(0);
});

test('CIDR ranges are honoured', function () {
    $guard = app(RegistrationGuard::class);

    Setting::set('blocked_ips', ['203.0.113.0/24'], 'general', 'array');

    expect($guard->ipAllowed('203.0.113.7'))->toBeFalse()
        ->and($guard->ipAllowed('203.0.114.7'))->toBeTrue()
        ->and($guard->ipAllowed(null))->toBeTrue();
});

test('the bundled disposable list is loaded', function () {
    expect(app(RegistrationGuard::class)->disposableDomains())
        ->toHaveKey('mailinator.com')
        ->toHaveKey('yopmail.com')
        ->not->toHaveKey('gmail.com');
});
