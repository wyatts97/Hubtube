<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ThumbnailProxyController extends Controller
{
    /**
     * Allowed domain suffixes for proxied thumbnails.
     * Matching uses strict suffix check: host must equal or end with "." + domain.
     */
    protected const ALLOWED_DOMAINS = [
        'xvideos-cdn.com',
        'phncdn.com',
        'xhamster.com',
        'xhcdn.com',
        'xnxx-cdn.com',
        'redtube.com',
        'ypncdn.com',
        'eporner.com',
        'rdtcdn.com',
    ];

    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        // Enforce HTTPS only
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            abort(403, 'Only HTTPS URLs are allowed');
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // Resolve the hostname ONCE here and reuse this exact IP for the actual fetch
        // below (via CURLOPT_RESOLVE). Checking the allowlist/private-IP rules against
        // one resolution and then letting the HTTP client re-resolve the hostname
        // independently at fetch time is a DNS-rebinding TOCTOU gap: an attacker-controlled
        // DNS record could resolve safely for this check and then to an internal IP by the
        // time the real request fires. Pinning the connection to the validated IP closes it.
        $resolvedIp = $this->resolveHostIp($host);

        if ($resolvedIp === null || $this->isInternalIp($resolvedIp)) {
            abort(403, 'Internal addresses are not allowed');
        }

        // Strict domain suffix matching
        $allowed = false;
        foreach (self::ALLOWED_DOMAINS as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            abort(403, 'Domain not allowed');
        }

        $cacheKey = 'thumb_proxy_' . md5($url);

        // Uses the configured cache driver — set CACHE_DRIVER=redis in .env for production
        $store = Cache::store(config('cache.default'));

        $imageData = $store->remember($cacheKey, 3600, function () use ($url, $host, $resolvedIp) {
            try {
                $response = Http::timeout(10)
                    ->withOptions([
                        // Pin the connection to the IP validated above instead of letting
                        // curl re-resolve $host independently.
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:443:{$resolvedIp}"]],
                    ])
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        'Accept' => 'image/*,*/*;q=0.8',
                        'Referer' => parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST) . '/',
                    ])
                    ->get($url);

                if ($response->successful()) {
                    return [
                        'body' => base64_encode($response->body()),
                        'content_type' => $response->header('Content-Type', 'image/jpeg'),
                    ];
                }
            } catch (Exception $e) {
                // Silent fail
            }

            return null;
        });

        if (!$imageData) {
            abort(404, 'Image not found');
        }

        return response(base64_decode($imageData['body']))
            ->header('Content-Type', $imageData['content_type'])
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Resolve a hostname to its IP once. Returns null if the hostname is an obviously
     * internal name or DNS resolution fails. The caller must reuse the returned IP for
     * both the allowlist/private-range check and the actual outbound request — resolving
     * twice reopens the DNS-rebinding gap this is meant to close.
     */
    protected function resolveHostIp(string $host): ?string
    {
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1', ''], true)) {
            return null;
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return null; // DNS resolution failed — block
        }

        return $ip;
    }

    /**
     * Check if an already-resolved IP is in a private/internal/reserved range.
     */
    protected function isInternalIp(string $ip): bool
    {
        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
