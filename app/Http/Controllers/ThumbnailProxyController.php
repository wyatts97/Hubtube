<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ThumbnailProxyController extends Controller
{
    public function proxy(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            abort(400, 'Invalid URL');
        }

        // Only allow image proxying from known domains
        $allowedDomains = [
            'img-cf.xvideos-cdn.com', 'img-hw.xvideos-cdn.com', 'img-l3.xvideos-cdn.com',
            'cdn77-pic.xvideos-cdn.com',
            'ci.phncdn.com', 'di.phncdn.com', 'ei.phncdn.com',
            'thumb-p', // xhamster CDN pattern
            'img-egc.xnxx-cdn.com', 'img-l3.xnxx-cdn.com', 'img-hw.xnxx-cdn.com',
            'cdn77-pic.xnxx-cdn.com',
            'thumbs-cdn.redtube.com',
            'fi1.ypncdn.com', 'fi2.ypncdn.com',
        ];

        $host = parse_url($url, PHP_URL_HOST);
        $allowed = false;
        foreach ($allowedDomains as $domain) {
            if (str_contains($host, $domain)) {
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
}
