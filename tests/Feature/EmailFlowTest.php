<?php

use App\Health\Checks\EmailCheck;
use App\Models\Setting;
use App\Models\User;
use App\Services\EmailService;
use FinityLabs\FinMail\Enums\EmailStatus;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Models\SentEmail;
use Illuminate\Support\Facades\Mail;
use Spatie\Health\Enums\Status;

/*
| A user never got their password reset email, and every way that can happen
| was silent. These cover the fixes.
*/

function configureMail(): void
{
    Setting::set('mail_mailer', 'smtp', 'integrations');
    config(['mail.from.address' => 'site@wedgietube.test', 'mail.from.name' => 'WedgieTube']);
    Mail::fake();
}

function restoreTemplatesMigration(): void
{
    (require database_path('migrations/2026_09_25_000001_restore_core_email_templates.php'))->up();
}

test('core templates exist after migrating', function () {
    expect(EmailTemplate::active()->where('key', 'reset-password')->exists())->toBeTrue()
        ->and(EmailTemplate::count())->toBeGreaterThanOrEqual(13);
});

test('restoring templates adds missing ones and leaves edited ones alone', function () {
    EmailTemplate::where('key', 'welcome')->update(['subject' => json_encode(['en' => 'My own subject'])]);
    EmailTemplate::where('key', 'verify-email')->forceDelete();
    EmailTemplate::where('key', 'reset-password')->update(['is_active' => false]);

    restoreTemplatesMigration();

    expect(EmailTemplate::where('key', 'welcome')->first()->getTranslation('subject', 'en'))->toBe('My own subject')
        ->and(EmailTemplate::active()->where('key', 'verify-email')->exists())->toBeTrue()
        ->and(EmailTemplate::active()->where('key', 'reset-password')->exists())->toBeTrue();
});

test('a reset request sends the reset email from the SMTP sender address', function () {
    configureMail();
    User::factory()->create(['email' => 'member@example.test']);

    $this->post('/forgot-password', ['email' => 'member@example.test'])->assertSessionHas('success');

    $sent = SentEmail::latest('id')->first();
    expect($sent)->not->toBeNull()
        ->and($sent->sender)->toBe('site@wedgietube.test')
        ->and($sent->template->key)->toBe('reset-password');
});

test('the old toggle can no longer switch reset emails off', function () {
    configureMail();
    Setting::set('email_notify_reset-password', false, 'notifications', 'boolean');
    User::factory()->create(['email' => 'member2@example.test']);

    $this->post('/forgot-password', ['email' => 'member2@example.test']);

    expect(SentEmail::count())->toBe(1);
});

test('an unknown email gets the same answer as a real one', function () {
    configureMail();

    $this->post('/forgot-password', ['email' => 'nobody@example.test'])
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    expect(SentEmail::count())->toBe(0);
});

test('the email health check reports what stops mail', function () {
    $status = fn () => EmailCheck::new()->run()->status;

    configureMail();
    expect($status())->toBe(Status::ok());

    EmailTemplate::where('key', 'reset-password')->update(['is_active' => false]);
    expect($status())->toBe(Status::failed());
    EmailTemplate::where('key', 'reset-password')->update(['is_active' => true]);

    SentEmail::create(['sender' => 'site@wedgietube.test', 'to' => ['x@example.test'], 'subject' => 'x', 'status' => EmailStatus::Failed]);
    expect($status())->toBe(Status::failed());
    SentEmail::query()->delete();

    config(['mail.from.address' => null]);
    expect($status())->toBe(Status::failed());
});

test('a restored reset template renders with the link filled in', function () {
    Setting::set('mail_mailer', 'array', 'integrations');
    config(['mail.default' => 'array', 'mail.from.address' => 'site@wedgietube.test']);

    EmailService::deliver('reset-password', 'member@example.test', [
        'username' => 'member',
        'reset_url' => 'https://wedgietube.test/reset-password/abc',
        'expiry_minutes' => 60,
    ]);

    $message = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
    expect($message->getHtmlBody())
        ->toContain('https://wedgietube.test/reset-password/abc')
        ->toContain('member')
        ->not->toContain('{{')
        ->and($message->getFrom()[0]->getAddress())->toBe('site@wedgietube.test');
});
