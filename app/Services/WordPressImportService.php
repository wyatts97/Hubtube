<?php

namespace App\Services;

use App\Models\Category;
use App\Models\EmbeddedVideo;
use App\Models\Hashtag;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WordPressImportService
{
    // WP table prefix from the SQL dump
    private string $tablePrefix = 'MKdOzH8c_';

    // Bunny Stream config from the WP options
    private string $bunnyLibraryId = '250371';
    private string $bunnyCdnHost = 'vz-1530c1f0-3aa.b-cdn.net';

    // Meta keys used by VidMov/Bunny Stream
    private const META_VIDEO_URL = 'beeteam368_video_url';
    private const META_DURATION = 'beeteam368_video_duration';
    private const META_WEBP_PREVIEW = 'beeteam368_video_webp_url_preview';
    private const META_WEBP_PREVIEW_ALT = 'beeteam368_video_webp_preview_url';
    private const META_VIEWS_TOTAL = 'beeteam368_views_counter_totals';
    private const META_LIKES = 'beeteam368_reactions_like';
    private const META_THUMBNAIL_ID = '_thumbnail_id';

    private array $posts = [];
    private array $postmeta = [];
    private array $terms = [];
    private array $termTaxonomy = [];
    private array $termRelationships = [];

    // In-memory caches to avoid repeated DB queries during import
    private array $categoryCache = [];
    private array $hashtagCache = [];

    /**
     * Parse the SQL file and extract all relevant data.
     * Returns stats about what was found.
     */
    public function parseSqlFile(string $filePath): array
    {
        $this->posts = [];
        $this->postmeta = [];
        $this->terms = [];
        $this->termTaxonomy = [];
        $this->termRelationships = [];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open SQL file: {$filePath}");
        }

        $targetTables = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships'];
        $currentTable = null;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            // Skip empty lines, comments, and non-data statements
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            // Detect INSERT INTO statements — set current table context
            if (preg_match('/^INSERT INTO `' . preg_quote($this->tablePrefix, '/') . '(\w+)`/', $trimmed, $m)) {
                $tableName = $m[1];
                $currentTable = in_array($tableName, $targetTables) ? $tableName : null;

                // The INSERT line itself contains data tuples after VALUES
                if ($currentTable) {
                    $this->processLine($currentTable, $trimmed);
                }
                continue;
            }

            // If we're inside a relevant table's INSERT and line starts with '(' it's a continuation row
            if ($currentTable && str_starts_with($trimmed, '(')) {
                $this->processLine($currentTable, $trimmed);
                continue;
            }

            // Any other statement (CREATE, ALTER, etc.) resets context
            if (preg_match('/^(CREATE|ALTER|DROP|LOCK|UNLOCK|SET|START|COMMIT)/', $trimmed)) {
                $currentTable = null;
            }
        }

        fclose($handle);

        // Filter posts to only vidmov_video type
        $videoPosts = array_filter($this->posts, fn($p) => ($p['post_type'] ?? '') === 'vidmov_video' && ($p['post_status'] ?? '') === 'publish');

        return [
            'total_posts' => count($this->posts),
            'video_posts' => count($videoPosts),
            'postmeta_entries' => count($this->postmeta),
            'terms' => count($this->terms),
            'term_taxonomy' => count($this->termTaxonomy),
            'term_relationships' => count($this->termRelationships),
        ];
    }

    /**
     * Process a single line that contains one or more row tuples for a table.
     */
    private function processLine(string $table, string $line): void
    {
        $columns = $this->getColumnsForTable($table);
        if (empty($columns)) return;

        $colCount = count($columns);
        $offset = 0;
        $len = strlen($line);

        while ($offset < $len) {
            $start = strpos($line, '(', $offset);
            if ($start === false) break;

            $end = 0;
            $values = $this->parseTuple($line, $start, $end);
            if ($values === null) break;

            $offset = $end + 1;

            if (count($values) !== $colCount) continue;

            $row = array_combine($columns, $values);
            $this->storeRow($table, $row);
        }
    }

    /**
     * Store a parsed row into the appropriate in-memory collection.
     */
    private function storeRow(string $table, array $row): void
    {
        switch ($table) {
            case 'posts':
                // Only keep vidmov_video posts to save memory
                if (($row['post_type'] ?? '') !== 'vidmov_video') return;
                if (isset($row['ID'])) {
                    $this->posts[(int) $row['ID']] = $row;
                }
                break;

            case 'postmeta':
                $postId = (int) ($row['post_id'] ?? 0);
                $metaKey = $row['meta_key'] ?? null;
                // Only store meta for posts we care about (vidmov_video) or all meta
                // since we don't know post types yet during meta parsing.
                // Filter by relevant meta keys to save memory.
                $relevantKeys = [
                    self::META_VIDEO_URL, self::META_DURATION, self::META_WEBP_PREVIEW,
                    self::META_WEBP_PREVIEW_ALT, self::META_VIEWS_TOTAL, self::META_LIKES,
                    self::META_THUMBNAIL_ID,
                ];
                if ($postId && $metaKey && in_array($metaKey, $relevantKeys)) {
                    $this->postmeta[$postId][$metaKey] = $row['meta_value'] ?? null;
                }
                break;

            case 'terms':
                if (isset($row['term_id'])) {
                    $this->terms[(int) $row['term_id']] = $row;
                }
                break;

            case 'term_taxonomy':
                if (isset($row['term_taxonomy_id'])) {
                    $this->termTaxonomy[(int) $row['term_taxonomy_id']] = $row;
                }
                break;

            case 'term_relationships':
                $objId = (int) ($row['object_id'] ?? 0);
                $ttId = (int) ($row['term_taxonomy_id'] ?? 0);
                if ($objId && $ttId) {
                    $this->termRelationships[$objId][] = $ttId;
                }
                break;
        }
    }

    /**
     * Parse a single SQL value tuple starting at $start.
     */
    private function parseTuple(string $buffer, int $start, int &$end): ?array
    {
        $len = strlen($buffer);
        $i = $start + 1; // skip opening '('
        $values = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $depth = 0;

        while ($i < $len) {
            $char = $buffer[$i];

            if ($inString) {
                if ($char === '\\' && $i + 1 < $len) {
                    $current .= $buffer[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($char === $stringChar) {
                    // Check for doubled quote ('' escape)
                    if ($i + 1 < $len && $buffer[$i + 1] === $stringChar) {
                        $current .= $stringChar;
                        $i += 2;
                        continue;
                    }
                    $inString = false;
                    $i++;
                    continue;
                }
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === '\'' || $char === '"') {
                $inString = true;
                $stringChar = $char;
                $i++;
                continue;
            }

            if ($char === '(') {
                $depth++;
                $current .= $char;
                $i++;
                continue;
            }

            if ($char === ')') {
                if ($depth > 0) {
                    $depth--;
                    $current .= $char;
                    $i++;
                    continue;
                }
                // End of tuple
                $values[] = trim($current) === 'NULL' ? null : trim($current);
                $end = $i;
                return $values;
            }

            if ($char === ',' && $depth === 0) {
                $values[] = trim($current) === 'NULL' ? null : trim($current);
                $current = '';
                $i++;
                continue;
            }

            $current .= $char;
            $i++;
        }

        $end = $len;
        return null;
    }

    /**
     * Column definitions for each WP table we care about.
     */
    private function getColumnsForTable(string $table): array
    {
        return match ($table) {
            'posts' => ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type', 'comment_count'],
            'postmeta' => ['meta_id', 'post_id', 'meta_key', 'meta_value'],
            'terms' => ['term_id', 'name', 'slug', 'term_group'],
            'term_taxonomy' => ['term_taxonomy_id', 'term_id', 'taxonomy', 'description', 'parent', 'count'],
            'term_relationships' => ['object_id', 'term_taxonomy_id', 'term_order'],
            default => [],
        };
    }

    /**
     * Get the list of parsed video posts ready for import.
     */
    public function getVideoPosts(): array
    {
        $videoPosts = array_filter($this->posts, fn($p) => ($p['post_type'] ?? '') === 'vidmov_video' && ($p['post_status'] ?? '') === 'publish');

        $results = [];
        foreach ($videoPosts as $postId => $post) {
            $meta = $this->postmeta[$postId] ?? [];
            $rawEmbedCode = $meta[self::META_VIDEO_URL] ?? null;
            $bunnyVideoId = $this->extractBunnyVideoId($rawEmbedCode);

            // Use post_excerpt for description (post_content is usually empty for vidmov_video)
            $description = trim($post['post_excerpt'] ?? '');
            if (empty($description)) {
                $content = $post['post_content'] ?? '';
                if (!empty($content)) {
                    $description = trim(strip_tags($content));
                }
            }

            // Get webp preview from either meta key
            $webpPreview = $meta[self::META_WEBP_PREVIEW] ?? $meta[self::META_WEBP_PREVIEW_ALT] ?? null;

            $results[] = [
                'wp_id' => $postId,
                'title' => $post['post_title'] ?? 'Untitled',
                'description' => $description,
                'slug' => $post['post_name'] ?? '',
                'post_date' => $post['post_date'] ?? null,
                'raw_embed_code' => $rawEmbedCode,
                'bunny_video_id' => $bunnyVideoId,
                'duration_formatted' => $meta[self::META_DURATION] ?? null,
                'webp_preview' => $webpPreview,
                'views_total' => (int) ($meta[self::META_VIEWS_TOTAL] ?? 0),
                'likes' => (int) ($meta[self::META_LIKES] ?? 0),
                'tags' => $this->getPostTags($postId),
                'actors' => $this->getPostActors($postId),
                'category' => $this->getPostCategory($postId),
            ];
        }

        return $results;
    }

    /**
     * Extract Bunny Stream video ID from embed code.
     */
    private function extractBunnyVideoId(?string $embedCode): ?string
    {
        if (!$embedCode) return null;

        // Match iframe src with Bunny embed URL
        if (preg_match('/embed\/\d+\/([a-f0-9\-]{36})/', $embedCode, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Get tags for a post from term_relationships.
     */
    private function getPostTags(int $postId): array
    {
        $ttIds = $this->termRelationships[$postId] ?? [];
        $tags = [];

        foreach ($ttIds as $ttId) {
            $tt = $this->termTaxonomy[$ttId] ?? null;
            if ($tt && ($tt['taxonomy'] ?? '') === 'post_tag') {
                $termId = (int) ($tt['term_id'] ?? 0);
                $term = $this->terms[$termId] ?? null;
                if ($term) {
                    $tags[] = $term['name'];
                }
            }
        }

        return $tags;
    }

    /**
     * Get actors for a post from term_relationships.
     */
    private function getPostActors(int $postId): array
    {
        $ttIds = $this->termRelationships[$postId] ?? [];
        $actors = [];

        foreach ($ttIds as $ttId) {
            $tt = $this->termTaxonomy[$ttId] ?? null;
            if ($tt && ($tt['taxonomy'] ?? '') === 'actors') {
                $termId = (int) ($tt['term_id'] ?? 0);
                $term = $this->terms[$termId] ?? null;
                if ($term) {
                    $actors[] = $term['name'];
                }
            }
        }

        return $actors;
    }

    /**
     * Get the primary vidmov_video_category for a post.
     */
    private function getPostCategory(int $postId): ?string
    {
        $ttIds = $this->termRelationships[$postId] ?? [];

        foreach ($ttIds as $ttId) {
            $tt = $this->termTaxonomy[$ttId] ?? null;
            if ($tt && ($tt['taxonomy'] ?? '') === 'vidmov_video_category') {
                $termId = (int) ($tt['term_id'] ?? 0);
                $term = $this->terms[$termId] ?? null;
                if ($term) {
                    return $term['name'];
                }
            }
        }

        return null;
    }

    /**
     * Parse duration string like "2:01" or "1:23:45" to seconds.
     */
    private function parseDuration(?string $formatted): int
    {
        if (!$formatted) return 0;

        $parts = explode(':', $formatted);
        $parts = array_map('intval', $parts);

        if (count($parts) === 3) {
            return $parts[0] * 3600 + $parts[1] * 60 + $parts[2];
        } elseif (count($parts) === 2) {
            return $parts[0] * 60 + $parts[1];
        }

        return 0;
    }

    /**
     * Extract the iframe src URL from raw embed HTML.
     * Works with bare <iframe>, <div>-wrapped iframes, and raw .mp4 URLs.
     */
    private function extractEmbedUrl(?string $rawEmbed): ?string
    {
        if (!$rawEmbed) return null;

        // If it contains an iframe, extract the src attribute
        if (preg_match('/src=["\']([^"\']+)["\']/i', $rawEmbed, $m)) {
            return html_entity_decode($m[1]);
        }

        // If it's a plain URL (e.g. .mp4), return as-is
        if (filter_var($rawEmbed, FILTER_VALIDATE_URL)) {
            return $rawEmbed;
        }

        return null;
    }

    /**
     * Purge all previously imported WP videos so user can re-import cleanly.
     */
    public function purgeImported(): int
    {
        return EmbeddedVideo::whereIn('source_site', ['bunnystream', 'wordpress'])->delete();
    }

    /**
     * Get or create a category, using in-memory cache.
     */
    private function resolveCategory(string $name): string
    {
        $slug = Str::slug($name);
        if (isset($this->categoryCache[$slug])) {
            return $this->categoryCache[$slug];
        }

        $category = Category::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'description' => '', 'is_active' => true]
        );
        $this->categoryCache[$slug] = (string) $category->id;
        return $this->categoryCache[$slug];
    }

    /**
     * Get or create a hashtag, using in-memory cache.
     */
    private function resolveHashtag(string $name): void
    {
        $slug = Str::slug($name);
        if (empty($slug)) return;

        if (isset($this->hashtagCache[$slug])) {
            // Already created in this import run, just increment
            Hashtag::where('slug', $slug)->increment('usage_count');
            return;
        }

        Hashtag::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'usage_count' => 0]
        )->increment('usage_count');
        $this->hashtagCache[$slug] = true;
    }

    /**
     * Import a batch of video posts into the embedded_videos table.
     * Returns import results.
     */
    public function importBatch(array $videoPosts): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Pre-load existing imported IDs for this batch to avoid N+1 queries
        $batchIds = [];
        foreach ($videoPosts as $video) {
            $id = $video['bunny_video_id'] ?? $video['wp_id'];
            $site = $video['bunny_video_id'] ? 'bunnystream' : 'wordpress';
            $batchIds[$site][] = (string) $id;
        }
        $existingIds = [];
        foreach ($batchIds as $site => $ids) {
            foreach (EmbeddedVideo::getImportedIds($site, $ids) as $existingId) {
                $existingIds[$site . ':' . $existingId] = true;
            }
        }

        foreach ($videoPosts as $video) {
            try {
                $sourceVideoId = $video['bunny_video_id'] ?? $video['wp_id'];
                $sourceSite = $video['bunny_video_id'] ? 'bunnystream' : 'wordpress';

                // Skip if already imported (using pre-loaded set)
                if (isset($existingIds[$sourceSite . ':' . $sourceVideoId])) {
                    $skipped++;
                    continue;
                }

                // Use the raw embed code exactly as it was in WordPress
                $rawEmbedCode = $video['raw_embed_code'] ?? '';

                // Extract the iframe src for the embed_url field (used by EmbeddedVideoPlayer)
                $embedUrl = $this->extractEmbedUrl($rawEmbedCode);

                // For the embed_code field, store the raw HTML as-is
                $embedCode = $rawEmbedCode;

                // Thumbnail: use webp preview from WP, fallback to Bunny CDN thumbnail
                $thumbnailUrl = $video['webp_preview'] ?? null;
                if (!$thumbnailUrl && $video['bunny_video_id']) {
                    $thumbnailUrl = "https://{$this->bunnyCdnHost}/{$video['bunny_video_id']}/thumbnail.jpg";
                }

                // Preview URL (animated webp) — same as thumbnail source
                $previewUrl = $video['webp_preview'] ?? null;

                // Source URL on original site
                $sourceUrl = $video['slug'] ? "https://wedgietube.com/video/{$video['slug']}/" : '';

                // Duration
                $durationFormatted = $video['duration_formatted'] ?? null;
                $durationSeconds = $this->parseDuration($durationFormatted);

                // Resolve category (cached)
                $categoryId = null;
                if (!empty($video['category'])) {
                    $categoryId = $this->resolveCategory($video['category']);
                }

                // Resolve hashtags (cached)
                if (!empty($video['tags'])) {
                    foreach ($video['tags'] as $tagName) {
                        $this->resolveHashtag($tagName);
                    }
                }

                EmbeddedVideo::create([
                    'source_site' => $sourceSite,
                    'source_video_id' => (string) $sourceVideoId,
                    'title' => $video['title'],
                    'description' => !empty($video['description']) ? $video['description'] : null,
                    'duration' => $durationSeconds,
                    'duration_formatted' => $durationFormatted,
                    'thumbnail_url' => $thumbnailUrl,
                    'thumbnail_preview_url' => $previewUrl,
                    'source_url' => $sourceUrl,
                    'embed_url' => $embedUrl,
                    'embed_code' => $embedCode,
                    'views_count' => $video['views_total'],
                    'rating' => $video['likes'],
                    'tags' => !empty($video['tags']) ? $video['tags'] : null,
                    'actors' => !empty($video['actors']) ? $video['actors'] : null,
                    'category_id' => $categoryId,
                    'is_published' => true,
                    'is_featured' => false,
                    'source_upload_date' => $video['post_date'] ? \Carbon\Carbon::parse($video['post_date']) : null,
                    'imported_at' => now(),
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'wp_id' => $video['wp_id'],
                    'title' => $video['title'],
                    'error' => $e->getMessage(),
                ];
                Log::warning("WP Import error for post {$video['wp_id']}: {$e->getMessage()}");
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
