<?php

namespace App\Services;

use Throwable;
use App\Models\Setting;
use FinityLabs\FinMail\Mail\TemplateMail as FinMailTemplateMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send a FinMail template email to a user.
     */
    public static function sendToUser(string $templateKey, string $toEmail, array $data = []): bool
    {
        if (!static::isMailConfigured()) {
            return false;
        }

        $settingKey = "email_notify_{$templateKey}";
        $enabled = Setting::get($settingKey, 'true');
        if ($enabled === 'false' || $enabled === '0') {
            return false;
        }

        try {
            Mail::to($toEmail)->sendNow(
                FinMailTemplateMail::make($templateKey)->models(self::prepareData($data))
            );
            return true;
        } catch (Throwable $e) {
            Log::error("EmailService: failed to send '{$templateKey}' to {$toEmail}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send a FinMail template email to the admin notification address.
     */
    public static function sendToAdmin(string $templateKey, array $data = [], ?string $replyTo = null, ?string $replyToName = null): bool
    {
        if (!static::isMailConfigured()) {
            return false;
        }

        $settingKey = "admin_notify_{$templateKey}";
        $enabled = Setting::get($settingKey, true);
        if (!filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
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
            $mail = FinMailTemplateMail::make($templateKey)->models(self::prepareData($data));

            if ($replyTo) {
                $mail->overrideReplyTo($replyTo, $replyToName);
            }

            Mail::to($adminEmail)->sendNow($mail);
            return true;
        } catch (Throwable $e) {
            Log::error("EmailService: failed to send admin notification '{$templateKey}': {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Check if mail has been configured beyond the default 'log' driver.
     */
    public static function isMailConfigured(): bool
    {
        $mailer = Setting::get('mail_mailer', config('mail.default', 'log'));
        return !empty($mailer) && $mailer !== 'log';
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
        if (!array_key_exists('site_name', $data)) {
            $data['site_name'] = config('app.name', 'HubTube');
        }

        return $data;
    }
}
