<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The checks that decide whether an account may be created at all:
 * the registration switch, blocked email domains and blocked IPs.
 *
 * Used by the register form, the registration middleware and social sign-in
 * (which creates accounts of its own), so all three agree.
 */
class RegistrationGuard
{
    /** Bundled list of known disposable-email providers. */
    public const DISPOSABLE_LIST = 'data/disposable-email-domains.txt';

    public function registrationOpen(): bool
    {
        return filter_var(Setting::get('registration_enabled', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function emailAllowed(?string $email): bool
    {
        $domain = $this->domainOf($email);

        if ($domain === null) {
            return true;
        }

        if (in_array($domain, $this->blockedDomains(), true)) {
            return false;
        }

        return ! $this->isDisposable($domain);
    }

    public function ipAllowed(?string $ip): bool
    {
        if ($ip === null || $ip === '') {
            return true;
        }

        $blocked = $this->listSetting('blocked_ips');

        return empty($blocked) || ! IpUtils::checkIp($ip, $blocked);
    }

    public function requestAllowed(Request $request): bool
    {
        return $this->ipAllowed($request->ip());
    }

    /** Domains an admin has blocked outright, lower-cased. */
    public function blockedDomains(): array
    {
        return array_map('strtolower', $this->listSetting('blocked_email_domains'));
    }

    public function isDisposable(string $domain): bool
    {
        if (! filter_var(Setting::get('block_disposable_emails', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        return isset($this->disposableDomains()[$domain]);
    }

    /**
     * The bundled disposable list, as a lookup map.
     *
     * Cached because it is read on every registration attempt, and kept as a
     * plain text file so it can be updated without a migration.
     */
    public function disposableDomains(): array
    {
        return Cache::remember('registration:disposable-domains', now()->addDay(), function (): array {
            $path = resource_path(self::DISPOSABLE_LIST);

            if (! is_file($path)) {
                return [];
            }

            $domains = [];

            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = strtolower(trim($line));

                if ($line !== '' && ! str_starts_with($line, '#')) {
                    $domains[$line] = true;
                }
            }

            return $domains;
        });
    }

    public function domainOf(?string $email): ?string
    {
        if (! is_string($email) || ! str_contains($email, '@')) {
            return null;
        }

        return strtolower(trim(substr($email, strrpos($email, '@') + 1)));
    }

    /** A setting stored as an array, a JSON array, or newline/comma separated text. */
    protected function listSetting(string $key): array
    {
        $value = Setting::get($key, []);

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : preg_split('/[\s,;]+/', $value);
        }

        return array_values(array_filter(array_map(
            fn ($entry) => trim((string) $entry),
            is_array($value) ? $value : []
        )));
    }
}
