<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Normalises channel social links on write and filters them on read.
 *
 * Links are re-checked on the way out as well as on the way in. Rows can
 * predate a platform being removed from config/social_links.php, and an
 * admin can revoke a host allowlist entry after the fact — validating only
 * at write time would leave those live forever.
 */
class SocialLinkService
{
    /**
     * Hard cap on links per channel. Keeps the sidebar readable and limits
     * the value of a compromised account as a link farm.
     */
    public const MAX_LINKS = 8;

    /**
     * Clean a validated payload into the shape stored in channels.social_links.
     *
     * @param  array<int, array{platform?: string, url?: string, label?: string|null}>  $links
     * @return array<int, array{platform: string, url: string, label: string|null}>
     */
    public function normalize(array $links): array
    {
        $seen = [];
        $clean = [];

        foreach ($links as $link) {
            $platform = $link['platform'] ?? null;
            $url = trim((string) ($link['url'] ?? ''));

            if (! $platform || $url === '' || ! config()->has("social_links.{$platform}")) {
                continue;
            }

            $key = $platform.'|'.strtolower($url);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $clean[] = [
                'platform' => $platform,
                // A custom label is only kept for the free-form entries. For a
                // known platform the label comes from config at render time, so
                // a link cannot present itself as "Twitter" while pointing
                // elsewhere.
                'label' => $this->isFreeform($platform)
                    ? (($link['label'] ?? null) ? mb_substr(trim($link['label']), 0, 40) : null)
                    : null,
                'url' => $url,
            ];

            if (count($clean) >= self::MAX_LINKS) {
                break;
            }
        }

        return $clean;
    }

    /**
     * The links that should actually be rendered for a channel.
     *
     * @return array<int, array{platform: string, url: string, label: string|null}>
     */
    public function forDisplay(User $user): array
    {
        if (! $this->outboundLinksEnabled()) {
            return [];
        }

        // Unverified accounts are the spam vector: a throwaway signup that
        // exists only to host links. Creators get their links back the moment
        // they confirm their email.
        if (! $user->email_verified_at) {
            return [];
        }

        $links = $user->channel?->social_links;

        if (! is_array($links)) {
            return [];
        }

        return array_values(array_filter(
            $this->normalize($links),
            fn (array $link) => $this->hostAllowed($link['platform'], $link['url']),
        ));
    }

    /**
     * Site-wide kill switch, so outbound links can be disabled during a spam
     * wave without a deploy.
     */
    public function outboundLinksEnabled(): bool
    {
        return (bool) filter_var(
            Setting::get('channel_social_links_enabled', true),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    public function isFreeform(string $platform): bool
    {
        return config("social_links.{$platform}.hosts") === null;
    }

    /**
     * Re-check a stored URL against the current allowlist.
     */
    private function hostAllowed(string $platform, string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $allowed = config("social_links.{$platform}.hosts");

        if ($allowed === null) {
            return true;
        }

        return in_array(strtolower(rtrim($parts['host'], '.')), $allowed, true);
    }
}
