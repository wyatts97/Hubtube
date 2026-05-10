<?php

namespace App\Services;

use App\Models\SearchIndexSubmission;
use App\Models\Setting;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * IndexNow client. Submits URLs to IndexNow-compatible search engines
 * (Bing, Yandex, Seznam, Naver, etc) so they discover new/changed pages
 * without waiting for a sitemap recrawl.
 *
 * @see https://www.indexnow.org/documentation
 */
class IndexNowService
{
    public const DEFAULT_ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function isEnabled(): bool
    {
        return (bool) Setting::get('indexnow_enabled', false)
            && !empty($this->getKey());
    }

    public function getKey(): string
    {
        return (string) Setting::get('indexnow_key', '');
    }

    public function getEndpoint(): string
    {
        $endpoint = trim((string) Setting::get('indexnow_endpoint', self::DEFAULT_ENDPOINT));
        return $endpoint !== '' ? $endpoint : self::DEFAULT_ENDPOINT;
    }

    public function getKeyLocation(): string
    {
        $custom = trim((string) Setting::get('indexnow_key_location', ''));
        if ($custom !== '') {
            return $custom;
        }
        return url('/' . $this->getKey() . '.txt');
    }

    /**
     * Generate a fresh 32-character hex key.
     */
    public static function generateKey(): string
    {
        return Str::random(32);
    }

    /**
     * Submit a single URL. Returns true on accepted (HTTP 200/202).
     */
    public function submitUrl(string $url, ?string $subjectType = null, ?int $subjectId = null): bool
    {
        return $this->submitUrls([$url], $subjectType, $subjectId);
    }

    /**
     * Submit multiple URLs in one POST. IndexNow allows up to 10,000 URLs per request.
     * Returns true if accepted by the endpoint.
     */
    public function submitUrls(array $urls, ?string $subjectType = null, ?int $subjectId = null): bool
    {
        $urls = $this->sanitizeUrls($urls);

        if (empty($urls)) {
            return false;
        }

        if (!$this->isEnabled()) {
            Log::info('IndexNow disabled or missing key; skipping submission', [
                'count' => count($urls),
            ]);
            return false;
        }

        $host = parse_url($urls[0], PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        $payload = [
            'host' => $host,
            'key' => $this->getKey(),
            'keyLocation' => $this->getKeyLocation(),
            'urlList' => array_values($urls),
        ];

        $records = [];
        foreach ($urls as $u) {
            $records[] = SearchIndexSubmission::create([
                'engine' => 'indexnow',
                'url' => $u,
                'url_hash' => SearchIndexSubmission::hashUrl($u),
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'status' => 'pending',
                'attempts' => 1,
            ]);
        }

        try {
            $client = new Client(['timeout' => 10, 'connect_timeout' => 5]);
            $response = $client->post($this->getEndpoint(), [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'http_errors' => false,
            ]);

            $code = $response->getStatusCode();
            $body = (string) $response->getBody();
            $accepted = in_array($code, [200, 202], true);

            foreach ($records as $record) {
                $record->update([
                    'status' => $accepted ? 'success' : 'failed',
                    'response_code' => $code,
                    'response_message' => $accepted ? null : Str::limit($body, 500),
                    'submitted_at' => now(),
                ]);
            }

            if (!$accepted) {
                Log::warning('IndexNow submission rejected', [
                    'code' => $code,
                    'body' => Str::limit($body, 500),
                    'urls' => array_slice($urls, 0, 5),
                ]);
            }

            return $accepted;
        } catch (GuzzleException $e) {
            foreach ($records as $record) {
                $record->update([
                    'status' => 'failed',
                    'response_message' => Str::limit($e->getMessage(), 500),
                    'submitted_at' => now(),
                ]);
            }
            Log::warning('IndexNow submission failed', [
                'error' => $e->getMessage(),
                'urls' => array_slice($urls, 0, 5),
            ]);
            return false;
        }
    }

    /**
     * Filter out malformed/non-http(s) and duplicate URLs.
     */
    protected function sanitizeUrls(array $urls): array
    {
        $clean = [];
        foreach ($urls as $url) {
            if (!is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                continue;
            }
            if (filter_var($url, FILTER_VALIDATE_URL) === false) {
                continue;
            }
            $clean[$url] = true;
        }
        return array_keys($clean);
    }
}
