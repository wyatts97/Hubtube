<?php

namespace App\Services\Translation\Sections;

use App\Models\Video;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Cache;

/**
 * Video tags.
 *
 * Tags live in a JSON column, not their own table, so translations are cached
 * by content hash rather than stored in `translations`.
 *
 * Cardinality is the risk here. On-view dispatch only ever translated tags
 * someone actually looked at; an unbounded sweep would attempt every tag in the
 * database times every locale on the first run. This section is therefore
 * ordered by popularity and hard-capped by the run limit.
 */
class TagSection implements TranslationSection
{
    public function __construct(protected TranslationService $translations) {}

    public function key(): string
    {
        return 'tags';
    }

    public function pending(string $locale, int $limit): array
    {
        $counts = [];

        Video::query()
            ->where('privacy', 'public')
            ->where('is_approved', true)
            ->whereNotNull('tags')
            ->select(['tags'])
            ->chunk(500, function ($videos) use (&$counts) {
                foreach ($videos as $video) {
                    foreach ((array) $video->tags as $tag) {
                        $tag = trim((string) $tag);

                        if ($tag !== '') {
                            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
                        }
                    }
                }
            });

        arsort($counts);

        $rows = [];

        foreach (array_keys($counts) as $tag) {
            if (count($rows) >= $limit) {
                break;
            }

            if (Cache::has(TranslationService::textCacheKey($tag, $locale))) {
                continue;
            }

            $rows[] = ['ref' => $tag, 'field' => 'tag', 'text' => $tag, 'format' => 'text'];
        }

        return $rows;
    }

    public function store(array $rows, string $locale): void
    {
        foreach ($rows as $row) {
            $this->translations->rememberText($row['text'], $locale, $row['translated']);
        }
    }
}
