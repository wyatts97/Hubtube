<?php

namespace App\Http\Controllers;

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

        // Block private / internal IP ranges (SSRF protection)
        if ($this->isInternalHost($host)) {
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

        $imageData = $store->remember($cacheKey, 3600, function () use ($url) {
            try {
                $response = Http::timeout(10)
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
            } catch (\Exception $e) {
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
     * Check if a hostname resolves to a private/internal IP range.
     */
    protected function isInternalHost(string $host): bool
    {
        // Quick check for obvious internal hostnames
        if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0', '::1', ''], true)) {
            return true;
        }

        // Resolve hostname and check IP ranges
        $ip = gethostbyname($host);
        if ($ip === $host) {
            return true; // DNS resolution failed — block
        }

        return !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
