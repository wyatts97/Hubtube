<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates one creator-supplied outbound link.
 *
 * This is the security boundary for channel social links. It runs server-side
 * and must stay that way — the client-side list in SocialLinks.vue is
 * presentation only, and resources/js/Composables/useSanitize.js is a regex
 * HTML stripper for ad markup, not a URL validator.
 *
 * Deliberately does NOT fetch the URL to normalise or verify it: requesting a
 * user-supplied host from the application server is SSRF.
 */
class SocialLinkUrl implements ValidationRule
{
    public function __construct(
        private readonly ?string $platform = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            $fail('Enter a link.');

            return;
        }

        $parts = parse_url(trim($value));

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            $fail('Enter a full link including https://');

            return;
        }

        // Blocks javascript:, data:, vbscript:, file: — the payloads that turn
        // an href into script execution.
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            $fail('Only http and https links are allowed.');

            return;
        }

        // Blocks https://onlyfans.com@evil.example, where everything before the
        // @ is userinfo and the real host is the attacker's.
        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('Links may not contain a username or password.');

            return;
        }

        $host = strtolower(rtrim($parts['host'], '.'));

        // Bare IPs bypass the host allowlist entirely and can target internal
        // addresses.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $fail('Links must use a domain name, not an IP address.');

            return;
        }

        // Punycode can render as a visually identical domain (аmazon vs amazon).
        if (str_contains($host, 'xn--')) {
            $fail('That domain is not allowed.');

            return;
        }

        $allowed = $this->platform
            ? config("social_links.{$this->platform}.hosts")
            : null;

        // A null allowlist means "any host" and is reserved for the free-form
        // website entry. An unknown platform has no config at all, so guard on
        // the platform being configured rather than on hosts being null.
        if ($this->platform && ! config()->has("social_links.{$this->platform}")) {
            $fail('Unsupported link type.');

            return;
        }

        if (is_array($allowed) && ! in_array($host, $allowed, true)) {
            $label = config("social_links.{$this->platform}.label", $this->platform);
            $fail("That does not look like a {$label} link.");
        }
    }
}
