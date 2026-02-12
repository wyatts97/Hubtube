<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Hashtag;
use App\Models\Video;
use App\Services\FfmpegService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArchiveImportService
{
    // WP table prefix from the SQL dump
    private string $tablePrefix = 'MKdOzH8c_';

    // Meta keys used by VidMov theme
    private const META_VIDEO_URL = 'beeteam368_video_url';
    private const META_DURATION = 'beeteam368_video_duration';
    private const META_WEBP_PREVIEW = 'beeteam368_video_webp_url_preview';
    private const META_WEBP_PREVIEW_ALT = 'beeteam368_video_webp_preview_url';
    private const META_VIEWS_TOTAL = 'beeteam368_views_counter_totals';
    private const META_LIKES = 'beeteam368_reactions_like';
    private const META_THUMBNAIL_ID = '_thumbnail_id';
    private const META_ATTACHED_FILE = '_wp_attached_file';

    private array $posts = [];
    private array $postmeta = [];
    private array $terms = [];
    private array $termTaxonomy = [];
    private array $termRelationships = [];

    // Attachment post ID => relative file path (from _wp_attached_file)
    private array $attachments = [];

    // In-memory caches
    private array $categoryCache = [];
    private array $hashtagCache = [];

    private ?int $importUserId = null;
    private string $archivePath = '';
    private string $storageDisk = 'public';

    public function setImportUserId(int $userId): void
    {
        $this->importUserId = $userId;
    }

    public function setArchivePath(string $path): void
    {
        $this->archivePath = rtrim($path, '/\\');
    }

    public function setStorageDisk(string $disk): void
    {
        $this->storageDisk = $disk;
    }

    /**
     * Parse the SQL file and extract all relevant data.
     * This version also extracts attachment file paths for local file mapping.
     */
    public function parseSqlFile(string $filePath): array
    {
        $this->posts = [];
        $this->postmeta = [];
        $this->terms = [];
        $this->termTaxonomy = [];
        $this->termRelationships = [];
        $this->attachments = [];

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException("Cannot open SQL file: {$filePath}");
        }

        $targetTables = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships'];
        $currentTable = null;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            if (preg_match('/^INSERT INTO `' . preg_quote($this->tablePrefix, '/') . '(\w+)`/', $trimmed, $m)) {
                $tableName = $m[1];
                $currentTable = in_array($tableName, $targetTables) ? $tableName : null;

                if ($currentTable) {
                    $this->processLine($currentTable, $trimmed);
                }
                continue;
            }

            if ($currentTable && str_starts_with($trimmed, '(')) {
                $this->processLine($currentTable, $trimmed);
                continue;
            }

            if (preg_match('/^(CREATE|ALTER|DROP|LOCK|UNLOCK|SET|START|COMMIT)/', $trimmed)) {
                $currentTable = null;
            }
        }

        fclose($handle);

        // Filter posts to only vidmov_video type with publish status
        $videoPosts = array_filter($this->posts, fn($p) => ($p['post_type'] ?? '') === 'vidmov_video' && ($p['post_status'] ?? '') === 'publish');

        // Count how many have local files vs bunny embeds
        $localCount = 0;
        $bunnyCount = 0;
        $noVideoCount = 0;
        foreach ($videoPosts as $postId => $post) {
            $meta = $this->postmeta[$postId] ?? [];
            $videoUrl = $meta[self::META_VIDEO_URL] ?? null;
            if ($videoUrl && str_contains($videoUrl, 'wp-content/uploads/')) {
                $localCount++;
            } elseif ($videoUrl && (str_contains($videoUrl, 'iframe') || str_contains($videoUrl, 'bunny'))) {
                $bunnyCount++;
            } else {
                $noVideoCount++;
            }
        }

        return [
            'total_posts' => count($this->posts),
            'video_posts' => count($videoPosts),
            'local_video_posts' => $localCount,
            'bunny_video_posts' => $bunnyCount,
            'no_video_posts' => $noVideoCount,
            'postmeta_entries' => count($this->postmeta),
            'terms' => count($this->terms),
            'term_taxonomy' => count($this->termTaxonomy),
            'term_relationships' => count($this->termRelationships),
            'attachments' => count($this->attachments),
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
                $postType = $row['post_type'] ?? '';
                // Keep vidmov_video posts AND attachment posts (for file path mapping)
                if ($postType === 'vidmov_video') {
                    if (isset($row['ID'])) {
                        $this->posts[(int) $row['ID']] = $row;
                    }
                } elseif ($postType === 'attachment') {
                    // Store attachment guid for file path resolution
                    if (isset($row['ID'])) {
                        $attachId = (int) $row['ID'];
                        $guid = $row['guid'] ?? '';
                        // Extract relative path from guid URL
                        if (preg_match('#wp-content/uploads/(.+)$#', $guid, $m)) {
                            $this->attachments[$attachId] = $m[1];
                        }
                    }
                }
                break;

            case 'postmeta':
                $postId = (int) ($row['post_id'] ?? 0);
                $metaKey = $row['meta_key'] ?? null;
                $relevantKeys = [
                    self::META_VIDEO_URL, self::META_DURATION, self::META_WEBP_PREVIEW,
                    self::META_WEBP_PREVIEW_ALT, self::META_VIEWS_TOTAL, self::META_LIKES,
                    self::META_THUMBNAIL_ID, self::META_ATTACHED_FILE,
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
        $i = $start + 1;
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
     * Enriched with local file path information.
     */
    public function getVideoPosts(): array
    {
        $videoPosts = array_filter($this->posts, fn($p) => ($p['post_type'] ?? '') === 'vidmov_video' && ($p['post_status'] ?? '') === 'publish');

        $results = [];
        foreach ($videoPosts as $postId => $post) {
            $meta = $this->postmeta[$postId] ?? [];
            $rawVideoUrl = $meta[self::META_VIDEO_URL] ?? null;

            // Extract the relative file path from the video URL
            $videoRelPath = null;
            if ($rawVideoUrl && preg_match('#wp-content/uploads/(.+\.mp4)#i', $rawVideoUrl, $m)) {
                $videoRelPath = $m[1];
            }

            // Get thumbnail relative path via _thumbnail_id -> attachment
            $thumbnailRelPath = null;
            $thumbnailId = isset($meta[self::META_THUMBNAIL_ID]) ? (int) $meta[self::META_THUMBNAIL_ID] : null;
            if ($thumbnailId) {
                // First check attachments from posts table (guid-based)
                if (isset($this->attachments[$thumbnailId])) {
                    $thumbnailRelPath = $this->attachments[$thumbnailId];
                }
                // Also check _wp_attached_file meta for this attachment
                $attachMeta = $this->postmeta[$thumbnailId] ?? [];
                if (isset($attachMeta[self::META_ATTACHED_FILE])) {
                    $thumbnailRelPath = $attachMeta[self::META_ATTACHED_FILE];
                }
            }

            // Get webp preview path
            $webpPreview = $meta[self::META_WEBP_PREVIEW] ?? $meta[self::META_WEBP_PREVIEW_ALT] ?? null;
            $previewRelPath = null;
            if ($webpPreview && preg_match('#wp-content/uploads/(.+\.webp)#i', $webpPreview, $m)) {
                $previewRelPath = $m[1];
            }

            // Description
            $description = trim($post['post_excerpt'] ?? '');
            if (empty($description)) {
                $content = $post['post_content'] ?? '';
                if (!empty($content)) {
                    $description = trim(strip_tags($content));
                }
            }

            $results[] = [
                'wp_id' => $postId,
                'title' => $post['post_title'] ?? 'Untitled',
                'description' => $description,
                'slug' => $post['post_name'] ?? '',
                'post_date' => $post['post_date'] ?? null,
                'video_rel_path' => $videoRelPath,
                'thumbnail_rel_path' => $thumbnailRelPath,
                'preview_rel_path' => $previewRelPath,
                'raw_video_url' => $rawVideoUrl,
                'duration_formatted' => $meta[self::META_DURATION] ?? null,
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
     * Scan the archive directory and return stats about what files exist.
     */
    public function scanArchive(): array
    {
        if (!$this->archivePath || !is_dir($this->archivePath)) {
            return ['error' => 'Archive directory not found: ' . $this->archivePath];
        }

        $mp4Count = 0;
        $jpgCount = 0;
        $webpCount = 0;
        $gifCount = 0;
        $otherCount = 0;
        $totalSize = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->archivePath, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $ext = strtolower($file->getExtension());
            $totalSize += $file->getSize();

            match ($ext) {
                'mp4', 'mov' => $mp4Count++,
                'jpg', 'jpeg', 'png' => $jpgCount++,
                'webp' => $webpCount++,
                'gif' => $gifCount++,
                default => $otherCount++,
            };
        }

        return [
            'mp4_files' => $mp4Count,
            'image_files' => $jpgCount,
            'webp_files' => $webpCount,
            'gif_files' => $gifCount,
            'other_files' => $otherCount,
            'total_size_bytes' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
        ];
    }

    /**
     * Validate which video posts have matching files in the archive.
     */
    public function validateFiles(array $videoPosts): array
    {
        $matched = 0;
        $missingVideo = 0;
        $missingThumb = 0;
        $details = [];

        foreach ($videoPosts as &$video) {
            $videoExists = false;
            $thumbExists = false;

            if ($video['video_rel_path']) {
                $fullPath = $this->archivePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $video['video_rel_path']);
                $videoExists = file_exists($fullPath);
                if ($videoExists) {
                    $video['video_size'] = filesize($fullPath);
                }
            }

            if ($video['thumbnail_rel_path']) {
                $fullPath = $this->archivePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $video['thumbnail_rel_path']);
                $thumbExists = file_exists($fullPath);
            }

            $video['video_found'] = $videoExists;
            $video['thumb_found'] = $thumbExists;

            if ($videoExists) {
                $matched++;
            } else {
                $missingVideo++;
            }

            if (!$thumbExists && $video['thumbnail_rel_path']) {
                $missingThumb++;
            }
        }

        return [
            'matched' => $matched,
            'missing_video' => $missingVideo,
            'missing_thumb' => $missingThumb,
            'videos' => $videoPosts,
        ];
    }

    /**
     * Import a single video: create DB record + copy files to HubTube storage.
     * Returns ['status' => 'imported'|'skipped'|'error', 'message' => '...']
     */
    public function importVideo(array $video): array
    {
        if (!$this->importUserId) {
            return ['status' => 'error', 'message' => 'No import user selected'];
        }

        // Skip if no local video file
        if (!$video['video_rel_path'] || empty($video['video_found'])) {
            return ['status' => 'skipped', 'message' => 'No local video file found'];
        }

        try {
            // Check for duplicate by source_video_id
            $sourceVideoId = 'wp_archive_' . $video['wp_id'];
            if (Video::where('source_video_id', $sourceVideoId)->exists()) {
                return ['status' => 'skipped', 'message' => 'Already imported'];
            }

            // Generate slug
            $baseSlug = Str::slug($video['title']) ?: 'imported-video';
            $slug = $baseSlug;
            $suffix = 2;
            while (Video::withTrashed()->where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $suffix;
                $suffix++;
            }

            $uuid = (string) Str::uuid();
            $videoDir = "videos/{$slug}";

            // Ensure the video directory exists
            Storage::disk($this->storageDisk)->makeDirectory($videoDir);

            // Copy video file
            $videoSourcePath = $this->archivePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $video['video_rel_path']);
            $videoFileName = Str::slug(pathinfo($video['video_rel_path'], PATHINFO_FILENAME), '_') . '.mp4';
            $videoStoragePath = "{$videoDir}/{$videoFileName}";

            $destPath = Storage::disk($this->storageDisk)->path($videoStoragePath);
            File::ensureDirectoryExists(dirname($destPath));
            File::copy($videoSourcePath, $destPath);

            // Move moov atom to the beginning of the MP4 for browser seekability
            $this->applyFaststart($destPath);

            $videoSize = filesize($destPath);

            // Copy thumbnail if available
            $thumbnailStoragePath = null;
            if (!empty($video['thumbnail_rel_path']) && !empty($video['thumb_found'])) {
                $thumbSourcePath = $this->archivePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $video['thumbnail_rel_path']);
                $thumbExt = pathinfo($video['thumbnail_rel_path'], PATHINFO_EXTENSION) ?: 'jpg';
                $thumbnailStoragePath = "{$videoDir}/thumbnail.{$thumbExt}";

                $thumbDestPath = Storage::disk($this->storageDisk)->path($thumbnailStoragePath);
                File::copy($thumbSourcePath, $thumbDestPath);
            }

            // Copy preview webp if available
            $previewStoragePath = null;
            if (!empty($video['preview_rel_path'])) {
                $previewSourcePath = $this->archivePath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $video['preview_rel_path']);
                if (file_exists($previewSourcePath)) {
                    $previewStoragePath = "{$videoDir}/preview.webp";
                    $previewDestPath = Storage::disk($this->storageDisk)->path($previewStoragePath);
                    File::copy($previewSourcePath, $previewDestPath);
                }
            }

            // Parse duration
            $durationSeconds = $this->parseDuration($video['duration_formatted'] ?? null);

            // Resolve category
            $categoryId = null;
            if (!empty($video['category'])) {
                $categoryId = $this->resolveCategory($video['category']);
            }

            // Resolve hashtags
            if (!empty($video['tags'])) {
                foreach ($video['tags'] as $tagName) {
                    $this->resolveHashtag($tagName);
                }
            }

            $publishedAt = $video['post_date'] ? \Carbon\Carbon::parse($video['post_date']) : now();

            // Create the video record as a NATIVE video (not embedded)
            Video::create([
                'user_id' => $this->importUserId,
                'uuid' => $uuid,
                'title' => $video['title'],
                'slug' => $slug,
                'description' => !empty($video['description']) ? $video['description'] : null,
                'video_path' => $videoStoragePath,
                'thumbnail' => $thumbnailStoragePath,
                'preview_path' => $previewStoragePath,
                'storage_disk' => $this->storageDisk,
                'duration' => $durationSeconds,
                'size' => $videoSize,
                'privacy' => 'public',
                'status' => 'processed',
                'is_short' => false,
                'is_embedded' => false,
                'is_featured' => false,
                'is_approved' => true,
                'age_restricted' => true,
                'views_count' => (int) ($video['views_total'] ?? 0),
                'likes_count' => (int) ($video['likes'] ?? 0),
                'tags' => !empty($video['tags']) ? $video['tags'] : null,
                'category_id' => $categoryId,
                'source_site' => 'wedgietube_archive',
                'source_video_id' => $sourceVideoId,
                'source_url' => $video['slug'] ? "https://wedgietube.com/video/{$video['slug']}/" : '',
                'published_at' => $publishedAt,
                'processing_completed_at' => $publishedAt,
            ]);

            return ['status' => 'imported', 'message' => "Imported: {$video['title']}"];

        } catch (\Throwable $e) {
            Log::warning("Archive Import error for WP post {$video['wp_id']}: {$e->getMessage()}");
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Purge all previously archive-imported videos and their files.
     */
    public function purgeImported(): int
    {
        return Video::where('source_site', 'wedgietube_archive')->forceDelete();
    }

    /**
     * Get count of already imported archive videos.
     */
    public function getImportedCount(): int
    {
        return Video::where('source_site', 'wedgietube_archive')->count();
    }

    // --- Helper methods ---

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

    private function resolveHashtag(string $name): void
    {
        $slug = Str::slug($name);
        if (empty($slug)) return;
        if (isset($this->hashtagCache[$slug])) {
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
     * Move the moov atom to the beginning of an MP4 file so browsers can seek without
     * downloading the entire file. Uses ffmpeg -movflags +faststart (copy, no re-encode).
     */
    private function applyFaststart(string $filePath): void
    {
        $ffmpegPath = FfmpegService::ffmpegPath();

        $tmpPath = $filePath . '.faststart.mp4';
        $cmd = escapeshellarg($ffmpegPath)
            . ' -i ' . escapeshellarg($filePath)
            . ' -c copy -movflags +faststart'
            . ' -y ' . escapeshellarg($tmpPath)
            . ' 2>&1';

        $output = shell_exec($cmd);

        if (file_exists($tmpPath) && filesize($tmpPath) > 0) {
            // Replace original with faststart version
            unlink($filePath);
            rename($tmpPath, $filePath);
        } else {
            // Faststart failed — keep original (still playable, just not seekable)
            if (file_exists($tmpPath)) {
                unlink($tmpPath);
            }
            Log::warning('Faststart failed for archive import', [
                'file' => $filePath,
                'output' => $output,
            ]);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}
