<?php

namespace App\Services;

use App\Mail\TemplateMail;
use App\Models\EmailTemplate;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send a template-based email to a user.
     * Checks if the template is active and if the email notification type is enabled.
     */
    public static function sendToUser(string $templateSlug, string $toEmail, array $data = []): bool
    {
        if (!static::isMailConfigured()) {
            return false;
        }

        $template = EmailTemplate::findBySlug($templateSlug);
        if (!$template) {
            Log::warning("EmailService: template '{$templateSlug}' not found or inactive.");
            return false;
        }

        // Check if this user-facing notification type is enabled
        $settingKey = "email_notify_{$templateSlug}";
        $enabled = Setting::get($settingKey, 'true');
        if ($enabled === 'false' || $enabled === '0') {
            return false;
        }

        try {
            Mail::to($toEmail)->send(new TemplateMail($templateSlug, $data));
            return true;
        } catch (\Throwable $e) {
            Log::error("EmailService: failed to send '{$templateSlug}' to {$toEmail}: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Send a template-based email to the admin notification address.
     * Uses the admin_notification_email setting, falls back to mail_from_address.
     */
    public static function sendToAdmin(string $templateSlug, array $data = [], ?string $replyTo = null, ?string $replyToName = null): bool
    {
        if (!static::isMailConfigured()) {
            return false;
        }

        // Check if admin wants this notification type
        $settingKey = "admin_notify_{$templateSlug}";
        $enabled = Setting::get($settingKey, 'true');
        if ($enabled === 'false' || $enabled === '0') {
            return false;
        }

        $adminEmail = Setting::get('admin_notification_email', '');
        if (empty($adminEmail)) {
            $adminEmail = Setting::get('mail_from_address', '');
        }

        if (empty($adminEmail)) {
            Log::warning("EmailService: no admin email configured for notification '{$templateSlug}'.");
            return false;
        }

        try {
            Mail::to($adminEmail)->send(new TemplateMail($templateSlug, $data, $replyTo, $replyToName));
            return true;
        } catch (\Throwable $e) {
            Log::error("EmailService: failed to send admin notification '{$templateSlug}': {$e->getMessage()}");
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
}
