<?php

namespace App\Services;

use App\Models\Setting;

/**
 * The spam gate a comment passes before it is published.
 *
 * A comment that trips a rule is held for moderation rather than rejected.
 * Telling an author which word they used only teaches them how to word it
 * differently, and a false positive is then recoverable by a moderator rather
 * than lost at the form.
 */
class CommentFilter
{
    /** Why this comment should be held, or null when it is clean. */
    public function holdReason(string $content): ?string
    {
        if ($word = $this->blockedWord($content)) {
            return "blocked word: {$word}";
        }

        $maxLinks = (int) Setting::get('comment_max_links', 2);

        if ($maxLinks > 0 && $this->linkCount($content) > $maxLinks) {
            return 'too many links';
        }

        return null;
    }

    /** The first blocked word or phrase found in $content. */
    public function blockedWord(string $content): ?string
    {
        $haystack = mb_strtolower($content);

        foreach ($this->blockedWords() as $needle) {
            if ($this->matches($haystack, $needle)) {
                return $needle;
            }
        }

        return null;
    }

    /**
     * Whether $needle appears in $haystack as its own word.
     *
     * A bare substring match would hold every comment containing "class" for a
     * blocklist entry of "ass", so single words are matched on word boundaries.
     * Anything containing a space or punctuation is treated as a phrase and
     * matched as a substring, since boundaries there are ambiguous.
     */
    protected function matches(string $haystack, string $needle): bool
    {
        if (preg_match('/^[\p{L}\p{N}]+$/u', $needle) !== 1) {
            return str_contains($haystack, $needle);
        }

        return preg_match('/(?<![\p{L}\p{N}])'.preg_quote($needle, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1;
    }

    /**
     * The configured blocklist, lower-cased.
     *
     * Stored by the settings page as an array, but accepts a newline or
     * comma-separated string so the value can also be set from the console.
     */
    protected function blockedWords(): array
    {
        $configured = Setting::get('comment_blocked_words', []);

        if (is_string($configured)) {
            $configured = preg_split('/[\r\n,]+/', $configured) ?: [];
        }

        $words = array_map(
            fn ($word) => mb_strtolower(trim((string) $word)),
            (array) $configured
        );

        return array_values(array_filter($words, fn ($word) => $word !== ''));
    }

    protected function linkCount(string $content): int
    {
        return (int) preg_match_all('#https?://|www\.#i', $content);
    }
}
