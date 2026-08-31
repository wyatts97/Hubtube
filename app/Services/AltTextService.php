<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;

/**
 * Builds SEO/accessibility alt text for images from the metadata already
 * attached to them.
 *
 * Generation is purely template-driven (no AI, no network) so it is cheap
 * enough to run inline during a model save and deterministic enough to
 * backfill a whole library in one pass.
 *
 * The methods here must never issue a query. Every relation is read through
 * loadedRelation(), which returns null for an unloaded relation rather than
 * lazy-loading it — Model::shouldBeStrict() is on outside production, so a
 * lazy load here would throw, and in production it would silently N+1 once
 * per card in every grid on the site.
 */
class AltTextService
{
    /**
     * Alt text longer than this is truncated. ~125 characters is the practical
     * ceiling: several screen readers cut the announcement around there, and
     * search engines stop weighting the tail.
     */
    public const MAX_LENGTH = 125;

    /**
     * Words that only make sense joined to a value. When the variable beside
     * one of these resolves to nothing, the connector has to go with it.
     */
    private const CONNECTORS = 'by|from|in|of|for|on|with|and';

    /**
     * Setting key => default template, per media type.
     */
    public const TEMPLATES = [
        // Pre-existing key — do not rename, it is already stored in settings
        // and read by SeoSettings. Only the default has been broadened.
        'video' => ['seo_video_thumbnail_alt_template', '{title} - video thumbnail from {channel}'],
        'image' => ['seo_image_alt_template', '{title} - photo by {username}'],
        'gallery' => ['seo_gallery_alt_template', 'Cover image for the gallery {title}'],
        'avatar' => ['seo_avatar_alt_template', 'Profile picture of {username}'],
        'banner' => ['seo_channel_banner_alt_template', 'Channel banner for {channel}'],
    ];

    /**
     * Last-resort labels. An image with no usable metadata at all still needs
     * a non-empty alt, otherwise a screen reader announces the file name.
     */
    private const FALLBACKS = [
        'video' => 'Video thumbnail',
        'image' => 'Photo',
        'gallery' => 'Gallery cover image',
        'avatar' => 'Profile picture',
        'banner' => 'Channel banner',
    ];

    /**
     * Pre-loaded settings, keyed by setting name.
     *
     * Populated by withSettings() so bulk callers (the backfill command) pay
     * for one Setting::getAll() instead of a cache read per row per template.
     * Null means "not pre-loaded" — fall through to Setting::get().
     */
    protected ?array $settings = null;

    /**
     * Use a pre-loaded settings array instead of hitting the settings cache
     * per lookup. Returns $this so it can be chained at a call site.
     */
    public function withSettings(array $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    public function forVideo(Video $video): string
    {
        // Video has no channel relation of its own; the channel hangs off the
        // uploader. Both hops are loaded-only, so an uploader queried without
        // ->with('user.channel') simply falls back to the username.
        $user = $this->loadedRelation($video, 'user');
        $channel = $user ? $this->loadedRelation($user, 'channel') : null;
        $category = $this->loadedRelation($video, 'category');

        return $this->build('video', [
            'title' => $video->title,
            'channel' => $channel?->name ?: $user?->username,
            'username' => $user?->username,
            'category' => $category?->name,
            'tags' => $this->formatTags($video->tags),
        ]);
    }

    public function forImage(Image $image): string
    {
        $user = $this->loadedRelation($image, 'user');
        $category = $this->loadedRelation($image, 'category');

        return $this->build('image', [
            // An image's own description is often the most descriptive thing
            // available, so it stands in when the title is blank.
            'title' => $image->title ?: $image->description,
            'username' => $user?->username,
            'category' => $category?->name,
            'tags' => $this->formatTags($image->tags),
        ]);
    }

    public function forGallery(Gallery $gallery): string
    {
        $user = $this->loadedRelation($gallery, 'user');

        return $this->build('gallery', [
            'title' => $gallery->title,
            'gallery' => $gallery->title,
            'username' => $user?->username,
        ]);
    }

    public function forUserAvatar(User $user): string
    {
        return $this->build('avatar', [
            'username' => $user->username,
            // getNameAttribute() falls back to username, so this is never the
            // empty string when a username exists.
            'title' => $user->name,
        ]);
    }

    public function forChannelBanner(Channel $channel): string
    {
        $user = $this->loadedRelation($channel, 'user');

        return $this->build('banner', [
            'channel' => $channel->name ?: $user?->username,
            'username' => $user?->username,
            'title' => $channel->name,
        ]);
    }

    /**
     * Resolve the template for a type, substitute, normalize, and guarantee a
     * non-empty result.
     */
    protected function build(string $type, array $vars): string
    {
        [$key, $default] = self::TEMPLATES[$type];

        $template = (string) $this->setting($key, $default);
        if (trim($template) === '') {
            $template = $default;
        }

        $vars['site_name'] = $this->setting('site_name', config('app.name', 'HubTube'));

        $result = $this->normalize($this->substitute($template, $vars));

        return $result !== '' ? $result : self::FALLBACKS[$type];
    }

    /**
     * Replace {placeholder} tokens, then strip any token the caller had no
     * value for. Substituting first and stripping second means a template can
     * reference a variable that only some rows have.
     */
    protected function substitute(string $template, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $template = str_replace('{' . $key . '}', trim((string) $value), $template);
        }

        return preg_replace('/\{[a-z_]+\}/i', '', $template) ?? '';
    }

    /**
     * Turn a substituted template into something a human would read aloud.
     *
     * The interesting case is an empty variable: "{title} - photo by {username}"
     * with no username leaves "Foo - photo by", which is worse than no alt text
     * at all. Steps 3 and 4 below sweep up the connectors those empties strand.
     */
    protected function normalize(string $text): string
    {
        // 1. No markup or entities — alt text is rendered as a raw attribute.
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 2. Collapse whitespace (including the runs left by removed tokens).
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        // 3. Squash punctuation that lost the word it belonged to, e.g. the
        //    " - " in "Title -  - photo" when the middle variable was empty.
        $text = preg_replace('/\s*([,;:])\s*(?=[,;:])/u', '', $text) ?? $text;
        $text = preg_replace('/(?:\s[-–—]\s)+/u', ' - ', $text) ?? $text;

        // 4. Trim dangling connectors and punctuation from both ends. Looped
        //    because one strip can expose another: "Title - photo by" loses
        //    "by", then the now-trailing " - " goes on the next pass.
        do {
            $before = $text;
            $text = preg_replace('/[\s,;:._\-–—]+$/u', '', $text) ?? $text;
            $text = preg_replace('/\s+(?:' . self::CONNECTORS . ')$/iu', '', $text) ?? $text;
            $text = preg_replace('/^[\s,;:._\-–—]+/u', '', $text) ?? $text;
            $text = preg_replace('/^(?:' . self::CONNECTORS . ')\s+/iu', '', $text) ?? $text;
        } while ($text !== $before);

        // 5. Reject a result that carries no meaning of its own — no letters or
        //    digits at all ("- -", "()"), or nothing but connectives. The latter
        //    is what "{title} by {username}" collapses to when both variables
        //    are empty: step 4 cannot strip a trailing "by" that has no word in
        //    front of it, so the whole-string case is caught here instead.
        //    Returning '' makes build() substitute a real fallback label.
        if (!preg_match('/[\p{L}\p{N}]/u', $text)) {
            return '';
        }

        $words = preg_split('/[\s,;:._\-–—]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words !== [] && array_diff($words, explode('|', self::CONNECTORS)) === []) {
            return '';
        }

        return $this->truncate($text);
    }

    /**
     * Cut to MAX_LENGTH on a word boundary. No ellipsis: alt text is read
     * aloud, and "..." announces as "dot dot dot" in several screen readers.
     */
    protected function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::MAX_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');

        // A single word longer than the limit has no space to fall back to,
        // so it is cut hard rather than returned empty.
        if ($lastSpace !== false && $lastSpace > 0) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:!?-");
    }

    /**
     * Render a tag list as prose. Capped at three: alt text is a label, not an
     * index, and a 40-tag video would blow the length budget on keywords.
     */
    protected function formatTags(mixed $tags): string
    {
        if (!is_array($tags)) {
            return '';
        }

        $clean = array_values(array_filter(
            array_map(fn ($tag) => trim((string) $tag), $tags),
            fn ($tag) => $tag !== '',
        ));

        return implode(', ', array_slice($clean, 0, 3));
    }

    /**
     * Read a relation only if it is already loaded.
     *
     * Returning null instead of lazy-loading is deliberate — see the class
     * docblock. Callers degrade to a template without that variable.
     */
    protected function loadedRelation(object $model, string $relation): mixed
    {
        return $model->relationLoaded($relation) ? $model->getRelation($relation) : null;
    }

    protected function setting(string $key, mixed $default): mixed
    {
        if ($this->settings !== null) {
            return $this->settings[$key] ?? $default;
        }

        return Setting::get($key, $default);
    }
}
