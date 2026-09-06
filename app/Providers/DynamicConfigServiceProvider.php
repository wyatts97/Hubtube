<?php

namespace App\Providers;

use Throwable;
use App\Models\Setting;
use Illuminate\Support\ServiceProvider;

/**
 * Applies DB-driven configuration at boot time.
 * All optional settings (mail, integrations, etc.) are stored in the
 * Setting model and applied here so the app is fully self-sufficient
 * without .env for optional features.
 */
class DynamicConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Only apply if the settings table exists (skip during migrations)
        try {
            $this->applyMailConfig();
        } catch (Throwable $e) {
            // Table doesn't exist yet (fresh install, pre-migration) — skip silently
        }
    }

    protected function applyMailConfig(): void
    {
        $mailer = Setting::get('mail_mailer', '');

        // Only override if admin has configured mail in the panel
        if (empty($mailer) || $mailer === 'log') {
            return;
        }

        $encryption = Setting::get('mail_encryption', config('mail.mailers.smtp.encryption'));

        config([
            'mail.default' => $mailer,
            'mail.mailers.smtp.host' => Setting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) Setting::get('mail_port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.username' => Setting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => Setting::getDecrypted('mail_password', config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.encryption' => $encryption ?: null,
        ]);

        $fromAddress = Setting::get('mail_from_address', '');
        $fromName = Setting::get('mail_from_name', '');

        if (!empty($fromAddress)) {
            config(['mail.from.address' => $fromAddress]);
        }
        if (!empty($fromName)) {
            config(['mail.from.name' => $fromName]);
        }

        // SSL peer verification — disable for self-hosted mail servers with self-signed
        // or hostname-mismatched certs. Laravel 11 passes this config straight through to
        // Symfony's EsmtpTransportFactory as a DSN option, so it MUST be the top-level
        // `verify_peer` key (the nested `stream.ssl` key is never read by Laravel).
        // Symfony treats an empty string as "verify on", so pass a real boolean.
        $verifyPeer = filter_var(Setting::get('mail_verify_peer', 'true'), FILTER_VALIDATE_BOOLEAN);
        config(['mail.mailers.smtp.verify_peer' => $verifyPeer]);
    }
}
