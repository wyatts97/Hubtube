<?php

namespace App\Rules;

use App\Models\Hashtag;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * Restricts a tag list to hashtags that already exist.
 *
 * Uploaders pick from the existing vocabulary rather than extending it. Without
 * this, TagSyncService::syncVideo() calls Hashtag::firstOrCreate() for anything
 * it is handed, so every upload could mint new rows — which is how a tag table
 * accumulates typos, casing variants and near-duplicates that then have to be
 * merged by hand in the admin.
 *
 * Deliberately enforced here rather than in TagSyncService: that service is also
 * the path archive imports and admin tag management use, and both of those are
 * legitimately allowed to create tags. The restriction belongs to user-submitted
 * requests, not to the syncing mechanism.
 *
 * Matching is by slug, not by literal name, so "Tighty Whities", "tighty
 * whities" and "#Tighty  Whities" all resolve to the same existing row — the
 * same key TagSyncService uses when it looks a hashtag up.
 */
class KnownTags implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value) || $value === []) {
            return;
        }

        // slug => the name as submitted, so the failure message can quote what
        // the user actually typed rather than its slug.
        $submitted = [];

        foreach ($value as $tag) {
            if (! is_string($tag)) {
                continue;
            }

            $slug = Str::slug(trim(ltrim($tag, '#')));

            if ($slug !== '') {
                $submitted[$slug] = trim(ltrim($tag, '#'));
            }
        }

        if ($submitted === []) {
            return;
        }

        // One query for the whole list: a per-tag rule would issue up to 20.
        $known = Hashtag::query()
            ->whereIn('slug', array_keys($submitted))
            ->pluck('slug')
            ->all();

        $unknown = array_diff_key($submitted, array_flip($known));

        if ($unknown === []) {
            return;
        }

        $names = implode(', ', array_map(fn (string $name): string => '"'.$name.'"', $unknown));

        $fail(count($unknown) === 1
            ? "The tag {$names} does not exist. Please choose from the available tags."
            : "These tags do not exist: {$names}. Please choose from the available tags.");
    }
}
