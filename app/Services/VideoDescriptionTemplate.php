<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Video;

/**
 * Renders default-description templates and applies them in bulk to videos
 * that have an empty description.
 *
 * Supported variables (case-insensitive):
 *   {title} {video_title}        — video title
 *   {category} {category_name}   — category name (or empty string)
 *   {site_name} {sitename}       — site name (Setting `site_name`)
 *   {uploader} {channel}         — uploader username
 *   {tags}                       — comma-separated tags
 *   {duration}                   — formatted duration (mm:ss)
 *   {views}                      — number_format views_count
 *   {year}                       — current year (4-digit)
 */
class VideoDescriptionTemplate
{
    public const SETTING_KEY = 'seo_video_default_description_template';

    public const DEFAULT_TEMPLATE = 'Watch {title} along with thousands of other {category} videos on {site_name}!';

    /**
     * Get the configured template (falls back to DEFAULT_TEMPLATE).
     */
    public static function template(): string
    {
        $tpl = Setting::get(self::SETTING_KEY, self::DEFAULT_TEMPLATE);
        return is_string($tpl) && trim($tpl) !== '' ? $tpl : self::DEFAULT_TEMPLATE;
    }

    /**
     * Render a template against a single video.
     */
    public static function render(string $template, Video $video): string
    {
        $siteName = (string) Setting::get('site_name', config('app.name', 'HubTube'));
        $title = (string) ($video->title ?? '');
        $categoryName = $video->category?->name ?? '';
        $uploader = $video->user?->username ?? '';
        $tags = is_array($video->tags) ? implode(', ', $video->tags) : '';
        $duration = $video->formatted_duration ?? '';
        $views = number_format((int) ($video->views_count ?? 0));
        $year = (string) date('Y');

        $vars = [
            'title' => $title,
            'video_title' => $title,
            'category' => $categoryName,
            'category_name' => $categoryName,
            'site_name' => $siteName,
            'sitename' => $siteName,
            'uploader' => $uploader,
            'channel' => $uploader,
            'tags' => $tags,
            'duration' => $duration,
            'views' => $views,
            'year' => $year,
        ];

        // Case-insensitive replacement: replace {Title} as well as {title}.
        $rendered = preg_replace_callback(
            '/\{([a-z_][a-z0-9_]*)\}/i',
            function ($m) use ($vars) {
                $key = strtolower($m[1]);
                return $vars[$key] ?? $m[0];
            },
            $template,
        ) ?? $template;

        // Collapse whitespace and trim
        $rendered = preg_replace('/[ \t]+/', ' ', $rendered) ?? $rendered;
        return trim($rendered);
    }

    /**
     * Render the configured template against a sample video. Useful for the
     * admin "preview" widget.
     */
    public static function previewForLatest(): ?string
    {
        $sample = Video::query()
            ->with(['user:id,username', 'category:id,name'])
            ->public()
            ->approved()
            ->processed()
            ->latest('published_at')
            ->first();

        if (!$sample) {
            return null;
        }

        return self::render(self::template(), $sample);
    }

    /**
     * Apply the given template to every video whose description is NULL or
     * empty (after trim). Returns the number of videos updated.
     *
     * @param  array{
     *     only_public?:bool,
     *     only_approved?:bool,
     *     only_processed?:bool,
     *     limit?:int|null,
     *     dry_run?:bool,
     * }  $opts
     */
    public static function applyToMissing(string $template, array $opts = []): int
    {
        $onlyPublic    = $opts['only_public']    ?? true;
        $onlyApproved  = $opts['only_approved']  ?? true;
        $onlyProcessed = $opts['only_processed'] ?? true;
        $limit         = $opts['limit']          ?? null;
        $dryRun        = $opts['dry_run']        ?? false;

        $query = Video::query()
            ->with(['user:id,username', 'category:id,name'])
            ->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            });

        if ($onlyPublic)    { $query->public(); }
        if ($onlyApproved)  { $query->approved(); }
        if ($onlyProcessed) { $query->processed(); }
        if ($limit !== null) { $query->limit($limit); }

        $updated = 0;
        $query->orderBy('id')->chunkById(200, function ($videos) use (&$updated, $template, $dryRun) {
            foreach ($videos as $video) {
                $rendered = self::render($template, $video);
                if ($rendered === '') {
                    continue;
                }
                if (!$dryRun) {
                    // Use updateQuietly so we don't trigger the description-translation
                    // invalidation hook for an auto-fill (no human change to translate).
                    $video->description = $rendered;
                    $video->saveQuietly();
                }
                $updated++;
            }
        });

        return $updated;
    }

    /**
     * Count videos that currently have no description and would be eligible
     * for the bulk apply.
     */
    public static function missingCount(array $opts = []): int
    {
        $onlyPublic    = $opts['only_public']    ?? true;
        $onlyApproved  = $opts['only_approved']  ?? true;
        $onlyProcessed = $opts['only_processed'] ?? true;

        $query = Video::query()
            ->where(function ($q) {
                $q->whereNull('description')->orWhere('description', '');
            });

        if ($onlyPublic)    { $query->public(); }
        if ($onlyApproved)  { $query->approved(); }
        if ($onlyProcessed) { $query->processed(); }

        return $query->count();
    }
}
