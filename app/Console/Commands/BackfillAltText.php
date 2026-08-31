<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Gallery;
use App\Models\Image;
use App\Models\Setting;
use App\Models\User;
use App\Models\Video;
use App\Services\AltTextService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Generate the persisted *_alt_text columns for existing media.
 *
 * New rows get their alt text from the observers in AppServiceProvider, so
 * this is for the back catalogue — a live site whose library predates the
 * alt text columns entirely.
 */
class BackfillAltText extends Command
{
    protected $signature = 'seo:backfill-alt-text
        {--type=all : videos|images|galleries|users|channels|all}
        {--force : Regenerate even where alt text already exists}
        {--dry-run : Show what would be changed without writing}
        {--chunk=500 : Rows per chunk}';

    protected $description = 'Generate missing image alt text for videos, images, galleries, avatars and channel banners';

    /**
     * type => [model, alt column, eager loads, the column that must be present
     * for alt text to be worth generating]
     */
    private const TYPES = [
        'videos' => [Video::class, 'thumbnail_alt_text', ['user.channel', 'category'], 'title'],
        'images' => [Image::class, 'alt_text', ['user', 'category'], 'title'],
        'galleries' => [Gallery::class, 'cover_alt_text', ['user'], 'title'],
        'users' => [User::class, 'avatar_alt_text', [], 'username'],
        'channels' => [Channel::class, 'banner_alt_text', ['user'], 'name'],
    ];

    public function handle(AltTextService $altText): int
    {
        $type = (string) $this->option('type');
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));

        $types = $type === 'all' ? array_keys(self::TYPES) : [$type];

        foreach ($types as $candidate) {
            if (!isset(self::TYPES[$candidate])) {
                $this->error("Unsupported --type={$candidate}. Use one of: " . implode('|', array_keys(self::TYPES)) . '|all');

                return self::FAILURE;
            }
        }

        // One settings read for the whole run. AltTextService would otherwise
        // hit the settings cache once per template per row, which on a library
        // of any size dwarfs the actual string work.
        $altText->withSettings(Setting::getAll());

        $totalUpdated = 0;
        $totalSkipped = 0;

        foreach ($types as $name) {
            [$updated, $skipped] = $this->backfill($name, $altText, $force, $dry, $chunk);
            $totalUpdated += $updated;
            $totalSkipped += $skipped;
        }

        $this->newLine();
        $this->info("Total updated: {$totalUpdated}");
        $this->info("Total skipped: {$totalSkipped}");

        if ($dry) {
            $this->warn('Dry run — no rows were modified.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [updated, skipped]
     */
    private function backfill(string $name, AltTextService $altText, bool $force, bool $dry, int $chunk): array
    {
        [$model, $column, $with, $required] = self::TYPES[$name];

        $query = $model::query()->with($with);

        if (!$force) {
            $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''));
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->line("{$name}: nothing to do.");

            return [0, 0];
        }

        $this->info("Found {$total} {$name} row(s) needing alt text" . ($dry ? ' [dry run]' : ''));

        $method = 'for' . match ($name) {
            'videos' => 'Video',
            'images' => 'Image',
            'galleries' => 'Gallery',
            'users' => 'UserAvatar',
            'channels' => 'ChannelBanner',
        };

        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        $skipped = 0;
        $sample = null;

        $query->orderBy('id')->chunkById($chunk, function ($rows) use (
            &$updated, &$skipped, &$sample, $altText, $method, $column, $required, $dry, $bar
        ) {
            foreach ($rows as $row) {
                // Nothing to build a sentence out of — leave it null so the
                // model accessor keeps returning the generic fallback rather
                // than persisting a meaningless string.
                if (blank($row->{$required})) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $value = $altText->{$method}($row);

                if ($value === $row->{$column}) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $sample ??= $value;

                if (!$dry) {
                    // A direct UPDATE rather than save(): this must not fire the
                    // alt text observer back at itself, and on a large library
                    // it must not emit an activity-log entry or a Scout reindex
                    // per row. Nothing else on the row is being changed.
                    DB::table($row->getTable())
                        ->where('id', $row->id)
                        ->update([$column => $value]);
                }

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        if ($sample !== null) {
            $this->line("  example: \"{$sample}\"");
        }

        $this->line("  {$name}: {$updated} updated, {$skipped} skipped");
        $this->newLine();

        return [$updated, $skipped];
    }
}
