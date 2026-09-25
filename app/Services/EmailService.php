<?php

namespace App\Services;

use App\Models\Setting;
use FinityLabs\FinMail\Enums\EmailStatus;
use FinityLabs\FinMail\Mail\TemplateMail as FinMailTemplateMail;
use FinityLabs\FinMail\Models\EmailTemplate;
use FinityLabs\FinMail\Models\EmailTheme;
use FinityLabs\FinMail\Models\SentEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailService
{
    /**
     * Account emails. They can't be switched off in Notification Settings:
     * without them nobody can verify or recover an account.
     */
    public const ACCOUNT_TEMPLATES = ['verify-email', 'reset-password'];

    /**
     * Templates the app sends. Each must exist and be active, or its send
     * fails (see the EmailCheck health check).
     */
    public const CORE_TEMPLATES = [
        'verify-email', 'reset-password', 'welcome', 'video-published', 'new-subscriber',
        'contact-form-admin', 'video-approved', 'video-rejected', 'withdrawal-approved',
        'withdrawal-rejected', 'admin-new-user', 'admin-new-video', 'admin-new-report',
    ];

    /**
     * Send a FinMail template email to a user.
     */
    public static function sendToUser(string $templateKey, string $toEmail, array $data = []): bool
    {
        if (! static::isMailConfigured()) {
            return false;
        }

        if (! in_array($templateKey, self::ACCOUNT_TEMPLATES, true)
            && ! filter_var(Setting::get("email_notify_{$templateKey}", 'true'), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        try {
            static::deliver($templateKey, $toEmail, $data);

            return true;
        } catch (Throwable $e) {
            Log::error("EmailService: failed to send '{$templateKey}' to {$toEmail}: {$e->getMessage()}");
            report($e);

            return false;
        }
    }

    /**
     * Send a FinMail template email to the admin notification address.
     */
    public static function sendToAdmin(string $templateKey, array $data = [], ?string $replyTo = null, ?string $replyToName = null): bool
    {
        if (! static::isMailConfigured()) {
            return false;
        }

        $settingKey = "admin_notify_{$templateKey}";
        $enabled = Setting::get($settingKey, true);
        if (! filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $adminEmail = Setting::get('admin_notification_email', '');
        if (empty($adminEmail)) {
            $adminEmail = Setting::get('mail_from_address', '');
        }

        if (empty($adminEmail)) {
            Log::warning("EmailService: no admin email configured for notification '{$templateKey}'.");

            return false;
        }

        try {
            static::deliver($templateKey, $adminEmail, $data, $replyTo, $replyToName);

            return true;
        } catch (Throwable $e) {
            Log::error("EmailService: failed to send admin notification '{$templateKey}': {$e->getMessage()}");
            report($e);

            return false;
        }
    }

    /**
     * Render and send one template now, logged in FinMail's Sent Emails.
     * Throws on failure; the callers above decide how to report it.
     */
    public static function deliver(string $templateKey, string $toEmail, array $data = [], ?string $replyTo = null, ?string $replyToName = null): void
    {
        $mail = FinMailTemplateMail::make($templateKey)
            ->models(self::prepareData($data));

        // FinMail keeps its own copy of the sender address, which drifted from
        // the SMTP settings. Unless a template sets its own, send from the
        // address the SMTP account is allowed to send for.
        if (empty($mail->getTemplate()->from['address'] ?? null) && config('mail.from.address')) {
            $mail->overrideFrom(config('mail.from.address'), config('mail.from.name'));
        }

        if ($replyTo) {
            $mail->overrideReplyTo($replyTo, $replyToName);
        }

        $envelope = $mail->envelope();
        $sentEmail = SentEmail::create([
            'email_template_id' => $mail->getTemplate()->id,
            'sender' => $envelope->from?->address ?? config('mail.from.address'),
            'to' => [$toEmail],
            'subject' => $envelope->subject,
            'status' => EmailStatus::Queued,
            'sent_by' => auth()->id(),
        ]);

        $mail = $mail->extraData(['theme' => static::resolveEmailThemeColors($mail->getTemplate())]);

        Mail::to($toEmail)->sendNow($mail->withLogging($sentEmail));
    }

    /**
     * Run a send once the web response is out, so the visitor doesn't wait on
     * SMTP. Not queued: workers load mail settings once at boot and would keep
     * using old SMTP details after an admin changes them.
     */
    public static function afterResponse(callable $send): void
    {
        if (app()->runningInConsole()) {
            $send();
        } else {
            app()->terminating($send);
        }
    }

    /**
     * Check if mail has been configured beyond the default 'log' driver.
     */
    public static function isMailConfigured(): bool
    {
        $mailer = Setting::get('mail_mailer', config('mail.default', 'log'));

        return ! empty($mailer) && $mailer !== 'log';
    }

    public static function resolveEmailThemeColors(EmailTemplate $template): array
    {
        $colors = $template->resolvedThemeColors();
        $defaults = EmailTheme::defaultColors();

        if ($colors !== $defaults) {
            return $colors;
        }

        $fallback = EmailTheme::getDefault()
            ?? EmailTheme::query()->orderBy('id')->first();

        return $fallback?->resolvedColors() ?? $defaults;
    }

    /**
     * Ensure legacy scalar tokens (e.g. {{ username }}) continue to resolve
     * by passing the data array as top-level models and adding the site name.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function prepareData(array $data): array
    {
        if (! array_key_exists('site_name', $data)) {
            $data['site_name'] = config('app.name', 'HubTube');
        }

        return $data;
    }
}
