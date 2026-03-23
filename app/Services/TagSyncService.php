<?php

namespace App\Services;

use App\Models\Hashtag;
use App\Models\Video;
use Illuminate\Support\Str;

class TagSyncService
{
    public function syncVideo(Video $video): void
    {
        $rawTags = $video->getRawOriginal('tags');
        $normalizedTags = Video::normalizeTagsInput($rawTags ?? $video->tags);

        $previousHashtagIds = $video->hashtags()->pluck('hashtags.id')->all();

        $nextHashtagIds = [];

        foreach ($normalizedTags as $tag) {
            $normalizedName = $this->normalizeTagName($tag);
            $slug = Str::slug($normalizedName);

            if ($normalizedName === '' || $slug === '') {
                continue;
            }

            $hashtag = Hashtag::firstOrCreate(
                ['slug' => $slug],
                ['name' => $normalizedName, 'usage_count' => 0],
            );

            if ($hashtag->name !== $normalizedName) {
                $hashtag->update(['name' => $normalizedName]);
            }

            $nextHashtagIds[$hashtag->id] = true;
        }

        $video->hashtags()->sync(array_keys($nextHashtagIds));

        $this->refreshUsageCounts(array_unique([
            ...$previousHashtagIds,
            ...array_keys($nextHashtagIds),
        ]));
    }

    public function syncAllFromVideoJson(): void
    {
        Video::query()
            ->select(['id', 'tags'])
            ->orderBy('id')
            ->chunkById(200, function ($videos): void {
                foreach ($videos as $video) {
                    $this->syncVideo($video);
                }
            });

        $this->removeUnusedHashtags();
    }

    public function renameTag(Hashtag $hashtag, string $newName, ?string $newSlug = null): void
    {
        $this->renameTagBySlug($hashtag->slug, $newName, $newSlug, $hashtag);
    }

    public function renameTagBySlug(string $oldSlug, string $newName, ?string $newSlug = null, ?Hashtag $tag = null): void
    {
        $normalizedName = $this->normalizeTagName($newName);
        $normalizedSlug = Str::slug($newSlug ?: $normalizedName);

        if ($normalizedName === '' || $normalizedSlug === '') {
            return;
        }

        $hashtag = $tag ?: Hashtag::query()->where('slug', $oldSlug)->first();
        if (!$hashtag) {
            return;
        }

        if ($hashtag->name === $normalizedName && $oldSlug === $normalizedSlug) {
            return;
        }

        $hashtag->update([
            'name' => $normalizedName,
            'slug' => $normalizedSlug,
        ]);

        Video::query()
            ->whereNotNull('tags')
            ->select(['id', 'tags'])
            ->orderBy('id')
            ->chunkById(200, function ($videos) use ($oldSlug, $normalizedName): void {
                foreach ($videos as $video) {
                    $tags = Video::normalizeTagsInput($video->getRawOriginal('tags') ?? $video->tags);
                    if (empty($tags)) {
                        continue;
                    }

                    $changed = false;
                    $updatedTags = [];

                    foreach ($tags as $tag) {
                        if (Str::slug($tag) === $oldSlug) {
                            $updatedTags[] = $normalizedName;
                            $changed = true;
                        } else {
                            $updatedTags[] = $tag;
                        }
                    }

                    if (!$changed) {
                        continue;
                    }

                    $updatedTags = Video::normalizeTagsInput($updatedTags);
                    $video->update(['tags' => empty($updatedTags) ? null : $updatedTags]);
                    $this->syncVideo($video);
                }
            });

        $this->removeUnusedHashtags();
    }

    public function deleteTag(Hashtag $hashtag): void
    {
        $slug = $hashtag->slug;

        Video::query()
            ->whereNotNull('tags')
            ->select(['id', 'tags'])
            ->orderBy('id')
            ->chunkById(200, function ($videos) use ($slug): void {
                foreach ($videos as $video) {
                    $tags = Video::normalizeTagsInput($video->getRawOriginal('tags') ?? $video->tags);
                    if (empty($tags)) {
                        continue;
                    }

                    $updatedTags = array_values(array_filter(
                        $tags,
                        fn (string $tag): bool => Str::slug($tag) !== $slug,
                    ));

                    if (count($updatedTags) === count($tags)) {
                        continue;
                    }

                    $video->update(['tags' => empty($updatedTags) ? null : $updatedTags]);
                    $this->syncVideo($video);
                }
            });
    }

    public function refreshUsageCounts(array $hashtagIds): void
    {
        if (empty($hashtagIds)) {
            return;
        }

        Hashtag::query()
            ->whereIn('id', $hashtagIds)
            ->withCount('videos')
            ->get()
            ->each(function (Hashtag $hashtag): void {
                $hashtag->update(['usage_count' => $hashtag->videos_count]);
            });
    }

    public function removeUnusedHashtags(): void
    {
        Hashtag::query()
            ->doesntHave('videos')
            ->delete();
    }

    private function normalizeTagName(string $value): string
    {
        return trim(ltrim($value, '#'));
    }
}
