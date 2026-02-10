<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\Scrapers\EpornerApiAdapter;
use App\Services\Scrapers\RedtubeApiAdapter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class EmbedScraperService
{
    protected string $scraperUrl;

    /**
     * Sites that have PHP-native API adapters (no Node scraper needed).
     */
    protected array $nativeAdapters = [];

    /**
     * Sites that require the Node.js scraper (HTML scraping).
     */
    public const SCRAPER_SITES = ['xvideos', 'pornhub', 'xhamster', 'xnxx', 'youporn'];

    /**
     * Mapping of source site keys to display names and logo filenames.
     */
    protected const SITE_META = [
        'eporner'    => ['name' => 'Eporner',  'logo' => 'eporner.png'],
        'redtube'    => ['name' => 'Redtube',  'logo' => 'redtube.png'],
        'redtube_api'=> ['name' => 'Redtube',  'logo' => 'redtube.png'],
        'xvideos'    => ['name' => 'Xvideos',  'logo' => 'xvideos.png'],
        'pornhub'    => ['name' => 'Pornhub',  'logo' => 'pornhub.png'],
        'xhamster'   => ['name' => 'xHamster', 'logo' => 'xhamster.png'],
        'xnxx'       => ['name' => 'XNXX',     'logo' => 'xnxx.png'],
        'youporn'    => ['name' => 'YouPorn',  'logo' => 'youporn.png'],
    ];

    public function __construct()
    {
        $this->scraperUrl = Setting::get('scraper_url', 'http://localhost:3001');
        $this->nativeAdapters = [
            'eporner' => new EpornerApiAdapter(),
            'redtube_api' => new RedtubeApiAdapter(),
        ];
    }

    /**
     * Check if a site uses a native PHP adapter (no Node scraper needed).
     */
    public function isNativeSite(string $site): bool
    {
        return isset($this->nativeAdapters[$site]);
    }

    /**
     * Get all available sites (native + scraper).
     */
    public function getAvailableSites(): array
    {
        $sites = [
            ['id' => 'eporner', 'name' => 'Eporner (API - Recommended)', 'enabled' => true, 'native' => true],
            ['id' => 'redtube_api', 'name' => 'RedTube (API)', 'enabled' => true, 'native' => true],
        ];

        // Try to get scraper sites too
        try {
            $response = Http::timeout(5)->get("{$this->scraperUrl}/api/sites");
            if ($response->successful()) {
                foreach ($response->json('sites', []) as $s) {
                    $sites[] = array_merge($s, ['native' => false]);
                }
            }
        } catch (\Exception $e) {
            // Scraper offline — add scraper sites as disabled
            foreach (self::SCRAPER_SITES as $id) {
                $sites[] = ['id' => $id, 'name' => ucfirst($id) . ' (Scraper Offline)', 'enabled' => false, 'native' => false];
            }
        }

        return $sites;
    }

    /**
     * Search for videos. Uses native PHP adapter if available, otherwise falls back to Node scraper.
     */
    public function search(string $site, string $query, int $page = 1): array
    {
        // Use native adapter if available
        if ($this->isNativeSite($site)) {
            return $this->searchNative($site, $query, $page);
        }

        // Fall back to Node.js scraper
        return $this->searchViaScraper($site, $query, $page);
    }

    /**
     * Search using a native PHP API adapter (no Node scraper needed).
     */
    protected function searchNative(string $site, string $query, int $page): array
    {
        $adapter = $this->nativeAdapters[$site];
        $data = $adapter->search($query, $page);

        // Mark already-imported videos
        if (!empty($data['videos'])) {
            $sourceIds = array_column($data['videos'], 'sourceId');
            $importedIds = $this->getImportedIds($site, $sourceIds);
            foreach ($data['videos'] as &$video) {
                $video['isImported'] = in_array($video['sourceId'], $importedIds);
            }
        }

        return $data;
    }

    /**
     * Search using the Node.js scraper service.
     */
    protected function searchViaScraper(string $site, string $query, int $page): array
    {
        try {
            $response = Http::timeout(30)->get("{$this->scraperUrl}/api/search/{$site}", [
                'q' => $query,
                'page' => $page,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Mark videos that are already imported
                if (!empty($data['videos'])) {
                    $sourceIds = array_column($data['videos'], 'sourceId');
                    $importedIds = $this->getImportedIds($site, $sourceIds);
                    
                    foreach ($data['videos'] as &$video) {
                        $video['isImported'] = in_array($video['sourceId'], $importedIds);
                    }
                }
                
                return $data;
            }

            // Check for blocked/geo-restricted response
            if ($response->status() === 403) {
                $data = $response->json();
                return [
                    'error' => 'Site access blocked',
                    'message' => $data['message'] ?? 'This site appears to be geo-restricted in your region.',
                    'blocked' => true,
                    'suggestion' => $data['suggestion'] ?? 'Try Eporner or RedTube (API) — they work without the scraper and are not geo-blocked.',
                    'videos' => [],
                ];
            }

            return [
                'error' => 'Failed to fetch videos',
                'message' => $response->body(),
                'videos' => [],
            ];
        } catch (\Exception $e) {
            Log::error("Scraper search error for {$site}: " . $e->getMessage());
            
            return [
                'error' => 'Scraper service unavailable',
                'message' => "Could not connect to scraper at {$this->scraperUrl}. " .
                    'Try using Eporner or RedTube (API) instead — they work without the Node.js scraper.',
                'videos' => [],
            ];
        }
    }

    /**
     * Search multiple pages at once and return combined results.
     */
    public function searchMultiPage(string $site, string $query, int $fromPage = 1, int $toPage = 3): array
    {
        $allVideos = [];
        $lastPage = $fromPage;
        $hasMore = false;

        for ($page = $fromPage; $page <= $toPage; $page++) {
            $result = $this->search($site, $query, $page);

            if (isset($result['error'])) {
                if ($page === $fromPage) return $result; // First page failed
                break; // Subsequent page failed, return what we have
            }

            $allVideos = array_merge($allVideos, $result['videos'] ?? []);
            $lastPage = $page;
            $hasMore = $result['hasNextPage'] ?? false;

            if (!$hasMore) break;

            // Small delay between pages to be polite
            if ($page < $toPage) usleep(300000); // 0.3s
        }

        return [
            'site' => $site,
            'query' => $query,
            'page' => $fromPage,
            'lastPage' => $lastPage,
            'hasNextPage' => $hasMore,
            'hasPrevPage' => $fromPage > 1,
            'totalResults' => count($allVideos),
            'videos' => $allVideos,
        ];
    }

    public function getVideoDetails(string $site, string $videoId): ?array
    {
        // Use native adapter if available
        if ($this->isNativeSite($site)) {
            return $this->nativeAdapters[$site]->getVideoDetails($videoId);
        }

        try {
            $response = Http::timeout(30)->get("{$this->scraperUrl}/api/search/{$site}/video/{$videoId}");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch video details for {$site}/{$videoId}: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Check which source video IDs are already imported in the unified videos table.
     */
    protected function getImportedIds(string $sourceSite, array $sourceVideoIds): array
    {
        // Normalize site name to match how it's stored
        $siteName = strtolower(str_replace(' ', '', $sourceSite));

        return Video::where('source_site', $siteName)
            ->whereIn('source_video_id', $sourceVideoIds)
            ->pluck('source_video_id')
            ->toArray();
    }

    /**
     * Check if a specific video is already imported.
     */
    protected function isAlreadyImported(string $sourceSite, string $sourceVideoId): bool
    {
        $siteName = strtolower(str_replace(' ', '', $sourceSite));

        return Video::where('source_site', $siteName)
            ->where('source_video_id', $sourceVideoId)
            ->exists();
    }

    /**
     * Get or create a dedicated user account for a source site.
     * Creates a pro + verified user with the site's logo as avatar.
     */
    protected function getOrCreateSiteUser(string $sourceSite): User
    {
        $siteKey = strtolower(str_replace(' ', '', $sourceSite));
        $meta = self::SITE_META[$siteKey] ?? null;
        $displayName = $meta['name'] ?? ucfirst($siteKey);
        $logo = $meta['logo'] ?? null;

        // Username: lowercase, no spaces (e.g. "eporner", "redtube", "xvideos")
        $username = $siteKey;
        // Normalize redtube_api to redtube
        if ($username === 'redtube_api') {
            $username = 'redtube';
        }

        $user = User::where('username', $username)->first();

        if ($user) {
            // Update avatar if it changed (e.g. svg -> png migration)
            $expectedAvatar = $logo ? "/images/site-logos/{$logo}" : null;
            if ($expectedAvatar && $user->avatar !== $expectedAvatar) {
                $user->update(['avatar' => $expectedAvatar]);
            }
            return $user;
        }

        // Create the user
        $user = User::create([
            'username' => $username,
            'email' => "{$username}@import.local",
            'password' => Hash::make(Str::random(32)),
            'avatar' => $logo ? "/images/site-logos/{$logo}" : null,
            'bio' => "Official {$displayName} video channel. Videos imported from {$displayName}.",
            'email_verified_at' => now(),
        ]);

        // Set privileged fields via forceFill (not mass-assignable)
        $user->forceFill([
            'is_pro' => true,
            'is_verified' => true,
        ])->save();

        // Create a channel for the user
        Channel::firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => $displayName,
                'slug' => $username,
                'description' => "Official {$displayName} video channel.",
            ]
        );

        Log::info("Created site user '{$username}' (ID: {$user->id}) for source site '{$sourceSite}'");

        return $user;
    }

    public function importVideo(array $videoData): ?Video
    {
        // Check if already imported in the unified videos table
        if ($this->isAlreadyImported($videoData['sourceSite'], $videoData['sourceId'])) {
            Log::info("Video already imported: {$videoData['sourceSite']}/{$videoData['sourceId']}");
            return null;
        }

        Log::info("Importing video: {$videoData['sourceSite']}/{$videoData['sourceId']} - {$videoData['title']}");

        $title = $videoData['title'] ?? 'Untitled';
        $slug = Str::slug($title);
        // Ensure unique slug
        $baseSlug = $slug;
        $counter = 1;
        while (Video::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $siteUser = $this->getOrCreateSiteUser($videoData['sourceSite']);

        $video = Video::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $siteUser->id,
            'title' => $title,
            'slug' => $slug,
            'description' => $videoData['description'] ?? null,
            'duration' => $videoData['duration'] ?? 0,
            'is_embedded' => true,
            'embed_url' => $videoData['embedUrl'] ?? '',
            'embed_code' => $videoData['embedCode'] ?? '',
            'external_thumbnail_url' => $videoData['thumbnail'] ?? null,
            'external_preview_url' => $videoData['thumbnailPreview'] ?? null,
            'source_site' => strtolower(str_replace(' ', '', $videoData['sourceSite'])),
            'source_video_id' => $videoData['sourceId'],
            'source_url' => $videoData['url'] ?? '',
            'views_count' => $videoData['views'] ?? 0,
            'status' => 'processed',
            'is_approved' => true,
            'privacy' => 'public',
            'published_at' => now(),
            'tags' => $videoData['tags'] ?? [],
        ]);

        Log::info("Video imported to videos table with ID: {$video->id}");

        return $video;
    }

    public function bulkImport(array $videos): array
    {
        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($videos as $videoData) {
            try {
                $video = $this->importVideo($videoData);
                
                if ($video) {
                    $imported[] = $video->id;
                } else {
                    $skipped[] = $videoData['sourceId'];
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'sourceId' => $videoData['sourceId'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'imported' => count($imported),
            'skipped' => count($skipped),
            'errors' => $errors,
        ];
    }
}
